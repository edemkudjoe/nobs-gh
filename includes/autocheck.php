<?php
declare(strict_types=1);
require_once __DIR__ . '/settings.php';

/**
 * The "automatic check". There is no background job on Vercel, so it runs when a
 * signed-in page loads, at most once a minute for the whole system.
 * It is safe to run twice: every step only touches rows that are overdue.
 */
function autocheck(): void
{
    try {
        $now = time();
        db_exec("INSERT INTO settings (name, value) VALUES ('last_autocheck', '0') ON CONFLICT (name) DO NOTHING");
        // Claim this minute. Only one request wins, so the work is never done twice at once.
        $claimed = db_exec("UPDATE settings SET value = ? WHERE name = 'last_autocheck' AND CAST(value AS BIGINT) < ?",
                           [(string)$now, $now - 60]);
        if ($claimed === 1) {
            db_transaction('autocheck_run');
        }
    } catch (Throwable $err) {
        error_log('autocheck failed: ' . $err->getMessage());   // never break the page the user asked for
    }
}

function autocheck_log(string $action, string $entity, string $detail): void
{
    db_exec('INSERT INTO audit_log (user_id, action, entity, detail) VALUES (NULL, ?, ?, ?)', [$action, $entity, $detail]);
}

function autocheck_run(): void
{
    // 1. Blood offers nobody answered in time: the unit and the request go back to the queue.
    $late = db_all("UPDATE allocations SET response = 'timed_out'
                     WHERE response = 'pending' AND respond_by < now()
                 RETURNING request_id, blood_unit_id");
    foreach ($late as $a) {
        db_exec("UPDATE blood_units SET status = 'available' WHERE id = ? AND status = 'reserved'", [$a['blood_unit_id']]);
        db_exec("UPDATE requests SET status = 'verified'
                  WHERE id = ? AND status = 'offered'
                    AND NOT EXISTS (SELECT 1 FROM allocations WHERE request_id = requests.id AND response = 'pending')",
                [$a['request_id']]);
    }
    if ($late) {
        autocheck_log('auto_blood_timeout', 'allocation', count($late) . ' blood offer(s) timed out');
    }

    // 2. Blood units past their expiry date.
    $n = db_exec("UPDATE blood_units SET status = 'expired'
                   WHERE status IN ('testing','available') AND expiry_date < CURRENT_DATE");
    if ($n) {
        autocheck_log('auto_expire_units', 'blood_unit', "$n unit(s) expired");
    }

    // 3. Organ offers nobody answered in time: the organ goes back to be offered to the next patient.
    $late = db_all("UPDATE organ_offers SET response = 'timed_out'
                     WHERE response = 'pending' AND respond_by < now()
                 RETURNING organ_id");
    foreach ($late as $o) {
        db_exec("UPDATE organs SET status = 'available' WHERE id = ? AND status = 'offered'", [$o['organ_id']]);
    }
    if ($late) {
        autocheck_log('auto_organ_timeout', 'organ_offer', count($late) . ' organ offer(s) timed out');
    }

    // 4. Organs past their viability window.
    $n = db_exec("UPDATE organs SET status = 'expired'
                   WHERE status IN ('available','offered') AND viable_until < now()");
    if ($n) {
        autocheck_log('auto_expire_organs', 'organ', "$n organ(s) expired");
    }

    // 5. Donation bookings whose day has passed without a visit.
    $n = db_exec("UPDATE appointments SET status = 'missed'
                   WHERE status = 'scheduled'
                     AND slot_id IN (SELECT id FROM center_slots WHERE slot_date < CURRENT_DATE)");
    if ($n) {
        autocheck_log('auto_missed_bookings', 'appointment', "$n booking(s) marked missed");
    }

    // 6. Routine blood requests nobody has dealt with for too long.
    $days = setting_int('stale_request_days');
    $n = db_exec("UPDATE requests SET status = 'cancelled'
                   WHERE urgency = 'routine' AND status IN ('submitted','verified')
                     AND created_at < now() - (? * INTERVAL '1 day')", [$days]);
    if ($n) {
        autocheck_log('auto_stale_requests', 'request', "$n routine request(s) cancelled after $days days");
    }

    // 7. Housekeeping: sessions that have expired.
    db_exec('DELETE FROM sessions WHERE expires_at < now()');
}
