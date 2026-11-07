<?php
declare(strict_types=1);
require_once __DIR__ . '/settings.php';

/*
 * Donor eligibility and booking rules (Person 2 owns this file).
 * All limits come from the settings table.
 */

/**
 * Can this donor give blood, and from when?
 * Returns: donor (row or null), blockers (reasons that stop donation entirely),
 * from (earliest date they may donate), rows (a table of checks for the eligibility page).
 */
function donor_check(int $user_id): array
{
    $d = db_one(
        "SELECT d.*, bg.label AS blood_group FROM donors d
           LEFT JOIN blood_groups bg ON bg.id = d.blood_group_id WHERE d.user_id = ?", [$user_id]);
    $today = new DateTimeImmutable('today');
    if (!$d) {
        return ['donor' => null, 'blockers' => ['Your donor details are not complete yet.'], 'from' => $today, 'rows' => []];
    }
    $blockers = [];
    $rows     = [];
    $from     = $today;

    $age = (new DateTimeImmutable($d['date_of_birth']))->diff($today)->y;
    $min = setting_int('min_age'); $max = setting_int('max_age');
    $ok  = $age >= $min && $age <= $max;
    $rows[] = ['Age', "$age years", $ok, "Between $min and $max years"];
    if (!$ok) { $blockers[] = "Donors must be between $min and $max years old."; }

    $minw = setting_int('min_weight_kg');
    $ok   = $d['weight_kg'] !== null && (float)$d['weight_kg'] >= $minw;
    $rows[] = ['Weight', $d['weight_kg'] === null ? 'Not given' : rtrim(rtrim((string)$d['weight_kg'], '0'), '.') . ' kg', $ok, "At least $minw kg"];
    if (!$ok) { $blockers[] = "Donors must weigh at least $minw kg."; }

    $months = setting_int('donation_gap_months');
    if ($d['last_donation_date']) {
        $next = (new DateTimeImmutable($d['last_donation_date']))->modify("+$months months");
        $ok   = $next <= $today;
        $rows[] = ['Last donation', date('j M Y', strtotime($d['last_donation_date'])), $ok, "At least $months months ago"];
        if (!$ok) { $from = max($from, $next); }
    } else {
        $rows[] = ['Last donation', 'None recorded', true, "At least $months months ago"];
    }

    if ($d['deferred_until']) {
        $until = new DateTimeImmutable($d['deferred_until']);
        $ok    = $until <= $today;
        $rows[] = ['Deferral', 'Until ' . $until->format('j M Y'), $ok, 'A centre asked you to wait'];
        if (!$ok) { $from = max($from, $until); }
    }
    return ['donor' => $d, 'blockers' => $blockers, 'from' => $from, 'rows' => $rows];
}

/** True when the donor may give blood today. */
function donor_can_donate_today(array $check): bool
{
    return $check['donor'] !== null && !$check['blockers'] && $check['from'] <= new DateTimeImmutable('today');
}

/** The donor's one active booking, if any. */
function donor_active_booking(int $user_id): ?array
{
    return db_one(
        "SELECT a.id, a.status, s.slot_date, f.name AS facility
           FROM appointments a JOIN center_slots s ON s.id = a.slot_id JOIN facilities f ON f.id = a.facility_id
          WHERE a.donor_id = ? AND a.status = 'scheduled' ORDER BY s.slot_date LIMIT 1", [$user_id]);
}

/** Places still free on a slot. */
function slot_spots_left(int $slot_id): int
{
    return (int)db_val(
        "SELECT s.capacity - (SELECT COUNT(*) FROM appointments a
                               WHERE a.slot_id = s.id AND a.status IN ('scheduled','completed','deferred'))
           FROM center_slots s WHERE s.id = ?", [$slot_id]);
}

/** Book a slot for a donor. Returns [ok, message]. */
function donor_book(array $user, int $slot_id): array
{
    $check = donor_check((int)$user['id']);
    if ($check['blockers']) {
        return [false, $check['blockers'][0]];
    }
    if (donor_active_booking((int)$user['id'])) {
        return [false, 'You already have a booking. Cancel it first if you want to change it.'];
    }
    return db_transaction(function () use ($user, $slot_id, $check) {
        // Lock the slot so two donors cannot take the last place at the same moment.
        $slot = db_one(
            "SELECT s.id, s.slot_date, s.facility_id FROM center_slots s
               JOIN facilities f ON f.id = s.facility_id AND f.active AND f.verification_status = 'approved'
              WHERE s.id = ? FOR UPDATE OF s", [$slot_id]);
        if (!$slot) {
            return [false, 'That slot is not available.'];
        }
        $date   = new DateTimeImmutable($slot['slot_date']);
        $latest = (new DateTimeImmutable('today'))->modify('+' . setting_int('booking_window_days') . ' days');
        if ($date < new DateTimeImmutable('today') || $date > $latest) {
            return [false, 'You can only book within the next ' . setting_int('booking_window_days') . ' days.'];
        }
        if ($date < $check['from']) {
            return [false, 'You can donate again from ' . $check['from']->format('j F Y') . '. Choose a later day.'];
        }
        if (slot_spots_left($slot_id) < 1) {
            return [false, 'That day is full. Choose another.'];
        }
        $id = db_val('INSERT INTO appointments (donor_id, facility_id, slot_id) VALUES (?, ?, ?) RETURNING id',
                     [$user['id'], $slot['facility_id'], $slot['id']]);
        db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id) VALUES (?, 'booking_created', 'appointment', ?)", [$user['id'], $id]);
        return [true, 'Your donation is booked for ' . $date->format('l j F Y') . '.'];
    });
}
