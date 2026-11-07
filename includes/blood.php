<?php
declare(strict_types=1);
require_once __DIR__ . '/settings.php';

/*
 * Blood request rules in one place (Person 4 owns this file).
 * Flow: submitted -> verified (or rejected) -> offered -> fulfilled.
 * An offer that is declined or times out sends the request back to verified.
 */

/** SQL that turns urgency into a sort number: critical first, then urgent, then routine. */
function blood_urgency_rank(string $alias): string
{
    return "CASE $alias.urgency WHEN 'critical' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END";
}

/** Requests that need the national office, in ranked order: urgency first, then oldest first. */
function blood_queue(string $status): array
{
    return db_all(
        "SELECT r.id, r.blood_group_id, r.quantity, r.quantity_fulfilled, r.urgency, r.accept_compatible, r.patient_ref,
                r.created_at, bg.label, f.name AS hospital
           FROM requests r
           JOIN blood_groups bg ON bg.id = r.blood_group_id
           JOIN facilities f ON f.id = r.hospital_id
          WHERE r.status = ?
       ORDER BY " . blood_urgency_rank('r') . ", r.created_at, r.id",
        [$status]
    );
}

/**
 * Units that could be given to a request, best first:
 * the exact group before a compatible one, then the unit that expires soonest.
 * Routine requests skip units with fewer than min_days_routine days left.
 */
function blood_find_units(int $group_id, bool $accept_compatible, string $urgency, int $limit, bool $lock = false): array
{
    $min_days = $urgency === 'routine' ? setting_int('min_days_routine') : 0;
    return db_all(
        "SELECT bu.id, bu.expiry_date, bg.label, f.name AS facility, (bu.expiry_date - CURRENT_DATE) AS days_left,
                (bu.blood_group_id = ?) AS exact
           FROM blood_units bu
           JOIN blood_groups bg ON bg.id = bu.blood_group_id
           JOIN facilities f ON f.id = bu.facility_id
          WHERE bu.status = 'available' AND bu.expiry_date >= CURRENT_DATE
            AND (bu.expiry_date - CURRENT_DATE) >= ?
            AND (bu.blood_group_id = ?
                 OR (?::boolean AND EXISTS (SELECT 1 FROM blood_compatibility c
                                             WHERE c.donor_group_id = bu.blood_group_id AND c.recipient_group_id = ?)))
       ORDER BY (bu.blood_group_id = ?) DESC, bu.expiry_date, bu.id
          LIMIT ?" . ($lock ? ' FOR UPDATE OF bu' : ''),
        [$group_id, $min_days, $group_id, $accept_compatible ? 't' : 'f', $group_id, $group_id, $limit]
    );
}

/** National office: accept or reject a newly submitted request. Returns [ok, message]. */
function blood_review(array $admin, int $request_id, string $decision, string $reason): array
{
    if ($decision === 'reject' && mb_strlen($reason) < 5) {
        return [false, 'Give a reason of at least 5 characters when rejecting a request.'];
    }
    $n = db_exec(
        "UPDATE requests SET status = ?, reviewed_by = ?, reject_reason = ? WHERE id = ? AND status = 'submitted'",
        [$decision === 'verify' ? 'verified' : 'rejected', $admin['id'], $decision === 'reject' ? $reason : null, $request_id]
    );
    if ($n !== 1) {
        return [false, 'That request is no longer waiting for review.'];
    }
    db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, ?, 'request', ?, ?)",
            [$admin['id'], 'request_' . ($decision === 'verify' ? 'verified' : 'rejected'), $request_id, $decision === 'reject' ? $reason : null]);
    return [true, $decision === 'verify' ? 'Request verified. It is now in the ranked queue.' : 'Request rejected.'];
}

/**
 * National office: offer the best units to a verified request.
 * Offering ahead of a higher-ranked request for the same group needs a written reason.
 */
function blood_offer(array $admin, int $request_id, string $override_reason): array
{
    return db_transaction(function () use ($admin, $request_id, $override_reason) {
        $r = db_one("SELECT * FROM requests WHERE id = ? AND status = 'verified' FOR UPDATE", [$request_id]);
        if (!$r) {
            return [false, 'That request is not waiting for an offer.'];
        }
        return blood_offer_checked($admin, $r, $override_reason);
    });
}

/** The offer itself. Runs inside the transaction opened by blood_offer(). */
function blood_offer_checked(array $admin, array $r, string $override_reason): array
{
    $ahead = (int)db_val(
        "SELECT COUNT(*) FROM requests o
          WHERE o.status = 'verified' AND o.blood_group_id = ? AND o.id <> ?
            AND (" . blood_urgency_rank('o') . " < ?
                 OR (" . blood_urgency_rank('o') . " = ? AND (o.created_at, o.id) < (?::timestamptz, ?)))",
        [$r['blood_group_id'], $r['id'], blood_rank_of($r['urgency']), blood_rank_of($r['urgency']), $r['created_at'], $r['id']]
    );
    $override = null;
    if ($ahead > 0) {
        if (mb_strlen($override_reason) < 10) {
            return [false, "A higher-ranked request for the same blood group is still waiting. Give a reason (at least 10 characters) to offer out of order."];
        }
        $override = mb_substr($override_reason, 0, 300);
    }

    $need  = (int)$r['quantity'] - (int)$r['quantity_fulfilled'];
    $units = blood_find_units((int)$r['blood_group_id'], (bool)$r['accept_compatible'], $r['urgency'], $need, true);
    if (!$units) {
        return [false, 'No suitable unit is available for this request right now.'];
    }
    $hours = $r['urgency'] === 'routine' ? setting_int('offer_hours_routine') : setting_int('offer_hours_urgent');
    foreach ($units as $u) {
        db_exec("INSERT INTO allocations (request_id, blood_unit_id, respond_by, override_reason)
                 VALUES (?, ?, now() + (? * INTERVAL '1 hour'), ?)", [$r['id'], $u['id'], $hours, $override]);
        db_exec("UPDATE blood_units SET status = 'reserved' WHERE id = ?", [$u['id']]);
    }
    db_exec("UPDATE requests SET status = 'offered' WHERE id = ?", [$r['id']]);
    db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'blood_offered', 'request', ?, ?)",
            [$admin['id'], $r['id'], count($units) . ' unit(s) offered' . ($override ? ', out of order: ' . $override : '')]);
    $short = count($units) < $need ? " Only " . count($units) . " of $need units were available." : '';
    return [true, count($units) . ' unit(s) offered. The hospital has ' . $hours . ' hours to reply.' . $short];
}

function blood_rank_of(string $urgency): int
{
    return ['critical' => 1, 'urgent' => 2][$urgency] ?? 3;
}

/** Hospital: accept or decline the units offered for one of its own requests. */
function blood_respond(array $user, int $request_id, string $decision): array
{
    return db_transaction(function () use ($user, $request_id, $decision) {
        $r = db_one("SELECT id, quantity, quantity_fulfilled FROM requests
                      WHERE id = ? AND hospital_id = ? AND status = 'offered' FOR UPDATE", [$request_id, $user['facility_id']]);
        if (!$r) {
            return [false, 'There is no open offer for that request.'];
        }
        $pending = db_all("SELECT id, blood_unit_id, (respond_by < now()) AS late FROM allocations
                            WHERE request_id = ? AND response = 'pending'", [$r['id']]);
        if (!$pending || $pending[0]['late']) {
            return [false, 'This offer has expired. The national office will offer again.'];
        }
        $accept = $decision === 'accept';
        foreach ($pending as $a) {
            db_exec('UPDATE allocations SET response = ?, responded_at = now() WHERE id = ?', [$accept ? 'accepted' : 'declined', $a['id']]);
            db_exec("UPDATE blood_units SET status = ? WHERE id = ? AND status = 'reserved'", [$accept ? 'allocated' : 'available', $a['blood_unit_id']]);
        }
        if ($accept) {
            $done = (int)$r['quantity_fulfilled'] + count($pending);
            db_exec('UPDATE requests SET quantity_fulfilled = ?, status = ? WHERE id = ?',
                    [$done, $done >= (int)$r['quantity'] ? 'fulfilled' : 'verified', $r['id']]);
        } else {
            db_exec("UPDATE requests SET status = 'verified' WHERE id = ?", [$r['id']]);
        }
        db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, ?, 'request', ?, ?)",
                [$user['id'], $accept ? 'blood_accepted' : 'blood_declined', $r['id'], count($pending) . ' unit(s)']);
        return [true, $accept ? 'Offer accepted. The units are reserved for you.' : 'Offer declined. The units go back to stock.'];
    });
}
