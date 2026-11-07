<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
$user = require_capability('donor');

$donor = db_one(
    "SELECT d.date_of_birth, d.weight_kg, d.last_donation_date, bg.label AS blood_group
       FROM donors d LEFT JOIN blood_groups bg ON bg.id = d.blood_group_id
      WHERE d.user_id = ?", [$user['id']]);

// Can this donor give blood today? The limits come from the settings table.
$reasons = [];
if ($donor) {
    $age = (new DateTime($donor['date_of_birth']))->diff(new DateTime('today'))->y;
    if ($age < setting_int('min_age') || $age > setting_int('max_age')) {
        $reasons[] = 'Donors must be between ' . setting_int('min_age') . ' and ' . setting_int('max_age') . ' years old.';
    }
    if ($donor['weight_kg'] === null || (float)$donor['weight_kg'] < setting_float('min_weight_kg')) {
        $reasons[] = 'Donors must weigh at least ' . setting_int('min_weight_kg') . ' kg.';
    }
    if ($donor['last_donation_date']) {
        $next = (new DateTime($donor['last_donation_date']))->modify('+' . setting_int('donation_gap_months') . ' months');
        if ($next > new DateTime('today')) {
            $reasons[] = 'Your last donation was recent. You can donate again from ' . $next->format('j F Y') . '.';
        }
    }
}

$booking = db_one(
    "SELECT s.slot_date, f.name AS facility
       FROM appointments a JOIN center_slots s ON s.id = a.slot_id JOIN facilities f ON f.id = a.facility_id
      WHERE a.donor_id = ? AND a.status = 'scheduled' ORDER BY s.slot_date LIMIT 1", [$user['id']]);

layout_start($user, 'Dashboard');
?>
<p>Welcome, <?= e($user['full_name']) ?>.</p>

<h2>Can I donate today?</h2>
<?php if (!$donor): ?>
  <p>Your donor details are not complete yet.</p>
<?php elseif (!$reasons): ?>
  <p class="notice">Yes. You meet the basic requirements. A centre will confirm on the day.</p>
<?php else: ?>
  <div class="alert"><?php foreach ($reasons as $r): ?><div><?= e($r) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($donor): ?>
  <p>Blood group: <strong><?= e($donor['blood_group'] ?? 'Not confirmed yet') ?></strong></p>
<?php endif; ?>

<h2>Your next booking</h2>
<?php if ($booking): ?>
  <p><strong><?= e((new DateTime($booking['slot_date']))->format('l j F Y')) ?></strong> at <?= e($booking['facility']) ?>.</p>
<?php else: ?>
  <p>You have no booking. <a href="/donor/book.php">Book a donation</a></p>
<?php endif; ?>
<?php layout_end();
