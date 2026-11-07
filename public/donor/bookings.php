<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
$user = require_capability('donor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Only the donor's own scheduled booking, and only until its day.
    $n = db_exec(
        "UPDATE appointments SET status = 'cancelled'
          WHERE id = ? AND donor_id = ? AND status = 'scheduled'
            AND slot_id IN (SELECT id FROM center_slots WHERE slot_date >= CURRENT_DATE)",
        [(int)($_POST['appointment_id'] ?? 0), $user['id']]);
    if ($n === 1) {
        db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id) VALUES (?, 'booking_cancelled', 'appointment', ?)", [$user['id'], (int)$_POST['appointment_id']]);
    }
    flash_set($n === 1 ? 'Your booking was cancelled.' : 'That booking cannot be cancelled.', $n === 1 ? 'ok' : 'error');
    redirect('/donor/bookings.php');
}

$bookings = db_all(
    "SELECT a.id, a.status, a.deferral_reason, a.deferred_until, s.slot_date, f.name AS facility
       FROM appointments a JOIN center_slots s ON s.id = a.slot_id JOIN facilities f ON f.id = a.facility_id
      WHERE a.donor_id = ? ORDER BY s.slot_date DESC, a.id DESC LIMIT 30", [$user['id']]);

layout_start($user, 'My bookings');
?>
<?php if (!$bookings): ?>
  <p>You have no bookings. <a href="/donor/book.php">Book a donation</a></p>
<?php else: ?>
<table>
  <thead><tr><th>Date</th><th>Centre</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($bookings as $b): ?>
    <tr>
      <td><?= e((new DateTime($b['slot_date']))->format('D j M Y')) ?></td>
      <td><?= e($b['facility']) ?></td>
      <td><?= e(ucfirst($b['status'])) ?>
          <?= $b['status'] === 'deferred' ? '<br><span class="hint">' . e($b['deferral_reason']) . ($b['deferred_until'] ? ', try again from ' . e($b['deferred_until']) : '') . '</span>' : '' ?></td>
      <td><?php if ($b['status'] === 'scheduled' && $b['slot_date'] >= date('Y-m-d')): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int)$b['id'] ?>">
          <button class="btn btn-danger btn-small" type="submit">Cancel</button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
