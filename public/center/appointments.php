<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/donation.php';
$user = require_capability('center');

/** Handle one visit outcome. Returns [ok, message]. */
function visit_outcome(array $user, int $id, string $action, array $in): array
{
    return db_transaction(function () use ($user, $id, $action, $in) {
        // Only this centre's own scheduled bookings, and only on or after their day.
        $a = db_one(
            "SELECT a.id, a.donor_id, d.blood_group_id FROM appointments a
               JOIN center_slots s ON s.id = a.slot_id JOIN donors d ON d.user_id = a.donor_id
              WHERE a.id = ? AND a.facility_id = ? AND a.status = 'scheduled' AND s.slot_date <= CURRENT_DATE
                FOR UPDATE OF a", [$id, $user['facility_id']]);
        if (!$a) {
            return [false, 'That booking cannot be updated.'];
        }
        if ($action === 'missed') {
            db_exec("UPDATE appointments SET status = 'missed' WHERE id = ?", [$a['id']]);
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id) VALUES (?, 'visit_missed', 'appointment', ?)", [$user['id'], $a['id']]);
            return [true, 'Marked as did not attend.'];
        }
        if ($action === 'defer') {
            $reason = trim((string)($in['reason'] ?? ''));
            $until  = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($in['until'] ?? ''));
            $today  = new DateTimeImmutable('today');
            if (mb_strlen($reason) < 5) {
                return [false, 'Give a reason of at least 5 characters.'];
            }
            if (!$until || $until <= $today || $until > $today->modify('+1 year')) {
                return [false, 'Choose a date after today, within a year, for when they may try again.'];
            }
            db_exec("UPDATE appointments SET status = 'deferred', deferral_reason = ?, deferred_until = ? WHERE id = ?",
                    [mb_substr($reason, 0, 200), $until->format('Y-m-d'), $a['id']]);
            db_exec("UPDATE donors SET eligibility_status = 'deferred', deferred_until = ?, eligibility_checked_at = now() WHERE user_id = ?",
                    [$until->format('Y-m-d'), $a['donor_id']]);
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'visit_deferred', 'appointment', ?, ?)",
                    [$user['id'], $a['id'], $reason]);
            return [true, 'Donor deferred until ' . $until->format('j M Y') . '.'];
        }
        // complete: a blood bag is collected and waits for lab testing
        $group = $a['blood_group_id'];
        if ($group === null) {
            $group = (int)($in['blood_group_id'] ?? 0);
            if (!db_val('SELECT 1 FROM blood_groups WHERE id = ?', [$group])) {
                return [false, 'This donor\'s blood group is not known yet. Choose the confirmed group.'];
            }
            db_exec('UPDATE donors SET blood_group_id = ? WHERE user_id = ?', [$group, $a['donor_id']]);
        }
        $unit = db_val(
            "INSERT INTO blood_units (facility_id, blood_group_id, appointment_id, collected_date, expiry_date, status)
             VALUES (?, ?, ?, CURRENT_DATE, CURRENT_DATE + (? * INTERVAL '1 day'), 'testing') RETURNING id",
            [$user['facility_id'], $group, $a['id'], setting_int('shelf_life_days')]);
        db_exec("UPDATE appointments SET status = 'completed' WHERE id = ?", [$a['id']]);
        db_exec("UPDATE donors SET last_donation_date = CURRENT_DATE, eligibility_status = 'passed', deferred_until = NULL, eligibility_checked_at = now() WHERE user_id = ?", [$a['donor_id']]);
        db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'donation_completed', 'appointment', ?, ?)",
                [$user['id'], $a['id'], "BU-$unit collected"]);
        return [true, "Donation recorded. Blood unit BU-$unit is waiting for testing."];
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, ['complete', 'defer', 'missed'], true)) {
        [$ok, $msg] = visit_outcome($user, (int)($_POST['appointment_id'] ?? 0), $action, $_POST);
    } else {
        [$ok, $msg] = [false, 'Unknown action.'];
    }
    flash_set($msg, $ok ? 'ok' : 'error');
    redirect('/center/appointments.php');
}

$sql = "SELECT a.id, a.status, s.slot_date, u.full_name, u.id AS donor_user, d.blood_group_id, bg.label AS blood_group
          FROM appointments a JOIN center_slots s ON s.id = a.slot_id
          JOIN users u ON u.id = a.donor_id JOIN donors d ON d.user_id = a.donor_id
          LEFT JOIN blood_groups bg ON bg.id = d.blood_group_id
         WHERE a.facility_id = ? AND a.status = 'scheduled' AND ";
$due      = db_all($sql . 's.slot_date <= CURRENT_DATE ORDER BY s.slot_date, a.id', [$user['facility_id']]);
$upcoming = db_all($sql . "s.slot_date > CURRENT_DATE AND s.slot_date <= CURRENT_DATE + 14 ORDER BY s.slot_date, a.id", [$user['facility_id']]);
$groups   = db_all('SELECT id, label FROM blood_groups ORDER BY id');

layout_start($user, 'Appointments');
?>
<h2>Today's donors (<?= count($due) ?>)</h2>
<?php if (!$due): ?>
  <p>No donors are waiting to be seen.</p>
<?php else: ?>
<table>
  <thead><tr><th>Donor</th><th>Date</th><th>Blood group</th><th>Outcome</th></tr></thead>
  <tbody>
  <?php foreach ($due as $a): ?>
    <tr>
      <td><?= e($a['full_name']) ?><br><span class="code">D-<?= (int)$a['donor_user'] ?></span></td>
      <td><?= e($a['slot_date']) ?></td>
      <td><?= $a['blood_group'] ? '<span class="code">' . e($a['blood_group']) . '</span>' : 'Not confirmed' ?></td>
      <td>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
          <?php if ($a['blood_group'] === null): ?>
            <select name="blood_group_id" aria-label="Confirmed blood group"><option value="">Confirm group…</option>
              <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['label']) ?></option><?php endforeach; ?></select>
          <?php endif; ?>
          <button class="btn btn-small" name="action" value="complete" type="submit">Donated</button>
          <button class="btn btn-quiet btn-small" name="action" value="missed" type="submit">Did not attend</button>
        </form>
        <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
          <input type="text" name="reason" maxlength="200" placeholder="Reason to defer" aria-label="Reason to defer">
          <input type="date" name="until" aria-label="Try again from" style="width:160px">
          <button class="btn btn-danger btn-small" name="action" value="defer" type="submit">Defer</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Coming up in the next 14 days (<?= count($upcoming) ?>)</h2>
<?php if (!$upcoming): ?>
  <p>No bookings yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Date</th><th>Donor</th><th>Blood group</th></tr></thead>
  <tbody>
  <?php foreach ($upcoming as $a): ?>
    <tr><td><?= e((new DateTime($a['slot_date']))->format('D j M')) ?></td>
        <td><?= e($a['full_name']) ?> <span class="code">D-<?= (int)$a['donor_user'] ?></span></td>
        <td><?= $a['blood_group'] ? '<span class="code">' . e($a['blood_group']) . '</span>' : 'Not confirmed' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
