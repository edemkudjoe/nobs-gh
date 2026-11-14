<?php
declare(strict_types=1);
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/blood.php';   // for blood_urgency_rank()

/*
 * Organ rules in one place (Person 5 owns this file).
 * Ranking is strict, in this order: urgency, then waiting time (longest first),
 * then travel hours (shortest first), then region name.
 * Flow: organ available -> offered -> allocated. A declined or late offer moves
 * the organ on to the next ranked patient automatically.
 */

/** Patients who could receive this organ, best first. Each row has travel_hours (null = not recorded). */
function organ_candidates(int $organ_id): array
{
    $o = db_one(
        "SELECT o.id, o.organ_type, dr.blood_group_id AS donor_group, dr.hospital_id AS donor_hospital, h.region_id AS from_region
           FROM organs o JOIN donor_reports dr ON dr.id = o.donor_report_id JOIN facilities h ON h.id = dr.hospital_id
          WHERE o.id = ?", [$organ_id]);
    if (!$o) {
        return [];
    }
    return db_all(
        "SELECT * FROM (
            SELECT w.id, w.patient_ref, w.urgency, w.registered_at, f.name AS center, r.name AS region,
                   CASE WHEN f.region_id = ?::int THEN ?::numeric ELSE rt.hours END AS travel_hours,
                   (f.id = ?::int) AS same_hospital
              FROM waiting_list w
              JOIN facilities f ON f.id = w.transplant_center_id AND f.active AND f.verification_status = 'approved' AND f.can_transplant
              JOIN regions r ON r.id = f.region_id
              LEFT JOIN region_travel_times rt ON rt.from_region_id = LEAST(f.region_id, ?::int) AND rt.to_region_id = GREATEST(f.region_id, ?::int)
             WHERE w.organ_type = ? AND w.status = 'active' AND w.verification_status = 'verified'
               AND EXISTS (SELECT 1 FROM blood_compatibility c WHERE c.donor_group_id = ? AND c.recipient_group_id = w.blood_group_id)
               AND NOT EXISTS (SELECT 1 FROM organ_offers x WHERE x.organ_id = ? AND x.waiting_list_id = w.id AND x.response IN ('declined','timed_out'))
         ) c
         ORDER BY " . blood_urgency_rank('c') . ", c.registered_at, c.travel_hours NULLS LAST, c.region, c.id",
        [$o['from_region'], setting_float('same_region_hours'), $o['donor_hospital'], $o['from_region'], $o['from_region'], $o['organ_type'], $o['donor_group'], $organ_id]
    );
}

/**
 * Offer an organ. Call it inside a transaction.
 * $admin = null means the system is moving the organ on to the next patient.
 * $waiting_id = null means "the top ranked patient". Any other patient needs a written reason.
 */
function organ_offer_to(?array $admin, int $organ_id, ?int $waiting_id, string $override_reason = ''): array
{
    $o = db_one("SELECT id, organ_type, retrieved_at, viable_until FROM organs
                  WHERE id = ? AND status = 'available' AND viable_until > now() FOR UPDATE", [$organ_id]);
    if (!$o) {
        return [false, 'That organ is not available to offer.'];
    }
    $list = organ_candidates($organ_id);
    if (!$list) {
        return [false, 'No eligible patient is waiting for this organ.'];
    }
    $pos = 0;
    if ($waiting_id !== null) {
        $pos = array_search($waiting_id, array_map('intval', array_column($list, 'id')), true);
        if ($pos === false) {
            return [false, 'That patient is not an eligible match for this organ.'];
        }
    }
    $override = null;
    if ($pos > 0) {
        if (mb_strlen($override_reason) < 10) {
            return [false, 'You chose a patient who is not ranked first. Give a reason of at least 10 characters.'];
        }
        $override = mb_substr($override_reason, 0, 300);
    }
    $patient = $list[$pos];
    // The hospital that reported the donor also holds this patient: choosing them out of order needs a fuller reason.
    $same = !empty($patient['same_hospital']);
    if ($pos > 0 && $same && mb_strlen($override_reason) < 30) {
        return [false, 'This patient is at the hospital that reported the donor. Give a fuller reason (at least 30 characters) for choosing them out of order.'];
    }
    db_exec(
        "INSERT INTO organ_offers (organ_id, waiting_list_id, rank_position, respond_by, override_reason, offered_by)
         VALUES (?, ?, ?, LEAST(?::timestamptz, now() + (EXTRACT(EPOCH FROM (?::timestamptz - ?::timestamptz)) * ?::numeric) * INTERVAL '1 second'), ?, ?)",
        [$organ_id, $patient['id'], $pos + 1, $o['viable_until'], $o['viable_until'], $o['retrieved_at'], setting_float('organ_response_fraction'),
         $override, $admin['id'] ?? null]);
    db_exec("UPDATE organs SET status = 'offered' WHERE id = ?", [$organ_id]);
    db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, ?, 'organ', ?, ?)",
            [$admin['id'] ?? null, $admin ? 'organ_offered' : 'organ_auto_offered', $organ_id,
             'OG-' . $organ_id . ' to ' . $patient['patient_ref'] . ' (rank ' . ($pos + 1) . ')' . ($same ? ', same hospital as the donor report' : '') . ($override ? ', out of order: ' . $override : '')]);
    return [true, 'OG-' . $organ_id . ' offered to ' . $patient['patient_ref'] . ' at ' . $patient['center'] . '.'];
}

/** The national office offers an organ. Returns [ok, message]. */
function organ_offer(array $admin, int $organ_id, ?int $waiting_id, string $override_reason): array
{
    return db_transaction(fn() => organ_offer_to($admin, $organ_id, $waiting_id, $override_reason));
}

/** Move an organ to the next ranked patient (system action). Call it inside a transaction. */
function organ_advance(int $organ_id): array
{
    return organ_offer_to(null, $organ_id, null);
}

/** A transplant hospital answers an offer for one of its own patients. Returns [ok, message]. */
function organ_respond(array $user, int $offer_id, string $decision, string $reason): array
{
    if ($decision === 'decline' && mb_strlen($reason) < 5) {
        return [false, 'Give a reason of at least 5 characters when declining an organ.'];
    }
    return db_transaction(function () use ($user, $offer_id, $decision, $reason) {
        $f = db_one(
            "SELECT f.id, f.organ_id, f.waiting_list_id, (f.respond_by < now()) AS late
               FROM organ_offers f JOIN waiting_list w ON w.id = f.waiting_list_id
              WHERE f.id = ? AND f.response = 'pending' AND w.transplant_center_id = ? FOR UPDATE OF f",
            [$offer_id, $user['facility_id']]);
        if (!$f) {
            return [false, 'There is no open offer to answer.'];
        }
        if ($f['late']) {
            return [false, 'This offer has expired. The organ is being offered to the next patient.'];
        }
        if ($decision === 'accept') {
            db_exec("UPDATE organ_offers SET response = 'accepted' WHERE id = ?", [$f['id']]);
            db_exec("UPDATE organs SET status = 'allocated' WHERE id = ?", [$f['organ_id']]);
            db_exec("UPDATE waiting_list SET status = 'transplanted' WHERE id = ?", [$f['waiting_list_id']]);
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'organ_accepted', 'organ_offer', ?, ?)",
                    [$user['id'], $f['id'], 'OG-' . $f['organ_id']]);
            return [true, 'Offer accepted. The organ is allocated to your patient.'];
        }
        db_exec("UPDATE organ_offers SET response = 'declined', decline_reason = ? WHERE id = ?", [mb_substr($reason, 0, 200), $f['id']]);
        db_exec("UPDATE organs SET status = 'available' WHERE id = ?", [$f['organ_id']]);
        db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'organ_declined', 'organ_offer', ?, ?)",
                [$user['id'], $f['id'], $reason]);
        [$ok, $msg] = organ_advance((int)$f['organ_id']);
        return [true, 'Offer declined. ' . ($ok ? 'The organ was offered to the next patient.' : $msg)];
    });
}

/** Viability window in hours for an organ type, from the settings table. */
function organ_viable_hours(string $type): float
{
    return setting_float('organ_viable_' . $type);
}