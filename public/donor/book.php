<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/donation.php';
$user = require_capability('donor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$ok, $msg] = donor_book($user, (int)($_POST['slot_id'] ?? 0));
    flash_set($msg, $ok ? 'ok' : 'error');
    redirect($ok ? '/donor/bookings.php' : '/donor/book.php');
}

$check   = donor_check((int)$user['id']);
$active  = donor_active_booking((int)$user['id']);
$first   = max($check['from'], new DateTimeImmutable('today'));
$last    = (new DateTimeImmutable('today'))->modify('+' . setting_int('booking_window_days') . ' days');
$slots   = [];
if ($check['donor'] && !$check['blockers'] && !$active) {
    $slots = db_all(
        "SELECT * FROM (
            SELECT s.id, s.slot_date, f.name AS facility, r.name AS region,
                   s.capacity - (SELECT COUNT(*) FROM appointments a
                                  WHERE a.slot_id = s.id AND a.status IN ('scheduled','completed','deferred')) AS spots_left
              FROM center_slots s
              JOIN facilities f ON f.id = s.facility_id AND f.active AND f.verification_status = 'approved'
              JOIN regions r ON r.id = f.region_id
             WHERE s.slot_date BETWEEN ? AND ?) x
          WHERE spots_left > 0 ORDER BY slot_date, region, facility LIMIT 60",
        [$first->format('Y-m-d'), $last->format('Y-m-d')]);
}

layout_start($user, 'Book a donation');
?>
<?php if ($check['blockers']): ?>
  <div class="alert"><?php foreach ($check['blockers'] as $b): ?><div><?= e($b) ?></div><?php endforeach; ?></div>
<?php elseif ($active): ?>
  <p>You already have a booking on <strong><?= e((new DateTime($active['slot_date']))->format('l j F Y')) ?></strong> at <?= e($active['facility']) ?>.
     <a href="/donor/bookings.php">Manage it</a> (you can cancel it to choose another day).</p>
<?php else: ?>
  <?php if ($check['from'] > new DateTimeImmutable('today')): ?>
    <p class="hint">You can donate again from <?= e($check['from']->format('j F Y')) ?>, so earlier days are not shown.</p>
  <?php endif; ?>
  <?php if (!$slots): ?>
    <p>No places are open in the next <?= setting_int('booking_window_days') ?> days. Please check again later.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Date</th><th>Centre</th><th>Region</th><th>Places left</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($slots as $s): ?>
      <tr>
        <td><?= e((new DateTime($s['slot_date']))->format('D j M Y')) ?></td>
        <td><?= e($s['facility']) ?></td>
        <td><?= e($s['region']) ?></td>
        <td><?= (int)$s['spots_left'] ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-small" type="submit">Book</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
<?php endif; ?>
<?php layout_end();
