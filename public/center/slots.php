<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
require __DIR__ . '/../../includes/donation.php';
$user = require_capability('center');

$errors = [];
$f = ['slot_date' => '', 'capacity' => '20'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $f['slot_date'] = trim((string)($_POST['slot_date'] ?? ''));
        $f['capacity']  = trim((string)($_POST['capacity'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $f['slot_date']);
        $max  = (new DateTimeImmutable('today'))->modify('+' . setting_int('booking_window_days') . ' days');
        if (!$date || $date->format('Y-m-d') !== $f['slot_date']) {
            $errors['slot_date'] = 'Choose a date.';
        } elseif ($date < new DateTimeImmutable('today') || $date > $max) {
            $errors['slot_date'] = 'Choose a date from today to ' . $max->format('j M Y') . '.';
        } elseif (db_val('SELECT 1 FROM center_slots WHERE facility_id = ? AND slot_date = ?', [$user['facility_id'], $f['slot_date']])) {
            $errors['slot_date'] = 'You already have a slot on that day. Change its places instead.';
        }
        $cap = filter_var($f['capacity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
        if ($cap === false) {
            $errors['capacity'] = 'Enter a whole number from 1 to 200.';
        }
        if (!$errors) {
            db_exec('INSERT INTO center_slots (facility_id, slot_date, capacity) VALUES (?, ?, ?)', [$user['facility_id'], $f['slot_date'], $cap]);
            flash_set('Slot added for ' . $date->format('D j M Y') . '.');
            redirect('/center/slots.php');
        }
    } elseif ($action === 'capacity' || $action === 'remove') {
        $id   = (int)($_POST['slot_id'] ?? 0);
        $slot = db_one('SELECT id FROM center_slots WHERE id = ? AND facility_id = ? AND slot_date >= CURRENT_DATE FOR UPDATE', [$id, $user['facility_id']]);
        $booked = $slot ? (int)db_val("SELECT COUNT(*) FROM appointments WHERE slot_id = ? AND status IN ('scheduled','completed','deferred')", [$id]) : 0;
        if (!$slot) {
            flash_set('That slot cannot be changed.', 'error');
        } elseif ($action === 'remove') {
            if ($booked > 0) {
                flash_set('Donors are booked on that day, so it cannot be removed.', 'error');
            } else {
                db_exec("DELETE FROM appointments WHERE slot_id = ? AND status IN ('cancelled','missed')", [$id]);
                db_exec('DELETE FROM center_slots WHERE id = ?', [$id]);
                flash_set('Slot removed.');
            }
        } else {
            $cap = filter_var($_POST['capacity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
            if ($cap === false || $cap < $booked) {
                flash_set("Enter a number from 1 to 200 that is not below the $booked donor(s) already booked.", 'error');
            } else {
                db_exec('UPDATE center_slots SET capacity = ? WHERE id = ?', [$cap, $id]);
                flash_set('Places updated.');
            }
        }
        redirect('/center/slots.php');
    }
}

$slots = db_all(
    "SELECT s.id, s.slot_date, s.capacity,
            (SELECT COUNT(*) FROM appointments a WHERE a.slot_id = s.id AND a.status IN ('scheduled','completed','deferred')) AS booked
       FROM center_slots s WHERE s.facility_id = ? AND s.slot_date >= CURRENT_DATE ORDER BY s.slot_date LIMIT 90",
    [$user['facility_id']]);

layout_start($user, 'Slots');
?>
<h2>Add a donation day</h2>
<form method="post" novalidate>
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <div class="field"><label for="slot_date">Date</label>
    <input id="slot_date" name="slot_date" type="date" required value="<?= e($f['slot_date']) ?>"><?php field_error($errors, 'slot_date'); ?></div>
  <div class="field"><label for="capacity">Places for donors</label>
    <input id="capacity" name="capacity" type="number" min="1" max="200" required value="<?= e($f['capacity']) ?>"><?php field_error($errors, 'capacity'); ?></div>
  <button class="btn" type="submit">Add day</button>
</form>

<h2>Your upcoming days</h2>
<?php if (!$slots): ?>
  <p>No days added yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Date</th><th>Booked</th><th>Places</th><th>Change</th></tr></thead>
  <tbody>
  <?php foreach ($slots as $s): ?>
    <tr>
      <td><?= e((new DateTime($s['slot_date']))->format('D j M Y')) ?></td>
      <td><?= (int)$s['booked'] ?></td>
      <td><?= (int)$s['capacity'] ?></td>
      <td class="row-actions">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
          <input type="number" name="capacity" min="1" max="200" value="<?= (int)$s['capacity'] ?>" style="width:90px" aria-label="Places on <?= e($s['slot_date']) ?>">
          <button class="btn btn-quiet btn-small" name="action" value="capacity" type="submit">Save</button>
          <?php if ((int)$s['booked'] === 0): ?><button class="btn btn-danger btn-small" name="action" value="remove" type="submit">Remove</button><?php endif; ?>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
