<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

$counts = [
    'Hospitals and blood banks' => 'SELECT COUNT(*) FROM facilities',
    'Staff and donor accounts'  => "SELECT COUNT(*) FROM users WHERE role <> 'admin'",
    'Donors'                    => 'SELECT COUNT(*) FROM donors',
    'Appointments and slots'    => 'SELECT (SELECT COUNT(*) FROM appointments) + (SELECT COUNT(*) FROM center_slots)',
    'Blood units'               => 'SELECT COUNT(*) FROM blood_units',
    'Blood requests and offers' => 'SELECT (SELECT COUNT(*) FROM requests) + (SELECT COUNT(*) FROM allocations)',
    'Organ reports, waiting list and offers' => 'SELECT (SELECT COUNT(*) FROM donor_reports) + (SELECT COUNT(*) FROM organs) + (SELECT COUNT(*) FROM waiting_list) + (SELECT COUNT(*) FROM organ_offers)',
];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $hash = (string)db_val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if (trim((string)($_POST['confirm_word'] ?? '')) !== 'RESET') {
        $errors['confirm_word'] = 'Type RESET in capital letters to continue.';
    }
    if (!password_verify((string)($_POST['password'] ?? ''), $hash)) {
        $errors['password'] = 'That password is not correct.';
    }
    if (!$errors) {
        db_transaction(function () use ($user): void {
            // Child tables first, in one statement, so every foreign key points inside the list.
            db_exec('TRUNCATE organ_offers, organs, donor_reports, waiting_list, allocations, requests, blood_units, appointments, center_slots, donors RESTART IDENTITY');
            db_exec('DELETE FROM stock_thresholds WHERE facility_id IS NOT NULL');
            // Keep the audit trail, but detach it from accounts that are about to disappear.
            db_exec("UPDATE audit_log SET user_id = NULL WHERE user_id IN (SELECT id FROM users WHERE role <> 'admin')");
            db_exec("DELETE FROM users WHERE role <> 'admin'");
            db_exec('DELETE FROM facilities');
            foreach (['users', 'facilities'] as $table) {
                db_val("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)");
            }
            db_exec("INSERT INTO audit_log (user_id, action, entity, detail) VALUES (?, 'system_reset', 'system', ?)",
                    [$user['id'], 'All facilities, staff, donors and records were deleted. Administrator accounts were kept.']);
        });
        flash_set('All data has been cleared. Administrator accounts, settings, regions and travel times were kept.');
        redirect('/admin/dashboard.php');
    }
}

layout_start($user, 'Clear all data');
?>
<div class="alert wide">
  <strong>This cannot be undone.</strong> It deletes every hospital, blood bank, staff account, donor account and all the records
  below. Administrator accounts, settings, regions, travel times and the audit log are kept.
</div>

<h2>What will be deleted now</h2>
<table class="no-tools">
  <thead><tr><th>Item</th><th>Count</th></tr></thead>
  <tbody>
  <?php foreach ($counts as $label => $sql): ?>
    <tr><td><?= e($label) ?></td><td><?= (int)db_val($sql) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Confirm</h2>
<p class="hint">Download or print any records you need first. Your hosting provider may also keep a database backup.</p>
<form method="post" class="form-narrow" autocomplete="off">
  <?= csrf_field() ?>
  <div class="field"><label for="confirm_word">Type RESET to confirm</label>
    <input id="confirm_word" name="confirm_word" type="text" autocomplete="off" required><?php field_error($errors, 'confirm_word'); ?></div>
  <div class="field"><label for="password">Your administrator password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required><?php field_error($errors, 'password'); ?></div>
  <button class="btn btn-danger" type="submit">Delete all data</button>
</form>
<?php layout_end();
