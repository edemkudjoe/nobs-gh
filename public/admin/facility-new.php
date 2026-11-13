<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

$regions = db_all('SELECT id, name FROM regions ORDER BY name');
$errors  = [];
$f = ['name' => '', 'type' => 'hospital', 'ownership' => 'public', 'region_id' => '', 'license_no' => '', 'admin_name' => '', 'admin_email' => '',
      'can_request_blood' => '', 'can_report_donors' => '', 'can_transplant' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'type', 'ownership', 'region_id', 'license_no', 'admin_name', 'admin_email'] as $k) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $f['admin_email'] = mb_strtolower($f['admin_email']);
    foreach (['can_request_blood', 'can_report_donors', 'can_transplant'] as $k) {
        // Only hospitals can hold these rights. A blood bank never gets them.
        $f[$k] = ($f['type'] === 'hospital' && isset($_POST[$k])) ? '1' : '';
    }

    if (mb_strlen($f['name']) < 3 || mb_strlen($f['name']) > 120) { $errors['name'] = 'Enter the facility name.'; }
    if (!in_array($f['type'], ['hospital', 'blood_bank'], true)) { $errors['type'] = 'Choose the facility type.'; }
      if (!in_array($f['ownership'], ['public', 'private'], true)) { $errors['ownership'] = 'Choose public or private.'; }
    if (!in_array((int)$f['region_id'], array_column($regions, 'id'), true)) { $errors['region_id'] = 'Choose a region.'; }
    if (strlen($f['license_no']) > 40) { $errors['license_no'] = 'The licence number is too long.'; }
    if (mb_strlen($f['admin_name']) < 2 || mb_strlen($f['admin_name']) > 120) { $errors['admin_name'] = 'Enter the administrator\'s name.'; }
    if (!filter_var($f['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($f['admin_email']) > 160) {
        $errors['admin_email'] = 'Enter a valid email address.';
    } elseif (db_val('SELECT 1 FROM users WHERE lower(email) = ?', [$f['admin_email']])) {
        $errors['admin_email'] = 'An account with this email already exists.';
    }

    if (!$errors) {
        $pw = temp_password();
        db_transaction(function () use ($user, $f, $pw) {
            $fid = db_val(
                "INSERT INTO facilities (name, type, ownership, region_id, license_no, verification_status, can_request_blood, can_report_donors, can_transplant)
                 VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?) RETURNING id,
                [$f['name'], $f['type'], $f['ownership'], (int)$f['region_id'], $f['license_no'] === '' ? null : $f['license_no'],
                 $f['can_request_blood'] ? 't' : 'f', $f['can_report_donors'] ? 't' : 'f', $f['can_transplant'] ? 't' : 'f']
            );
            db_exec(
                "INSERT INTO users (full_name, email, password_hash, role, facility_id, is_facility_admin, must_change_password, created_by)
                 VALUES (?, ?, ?, ?, ?, TRUE, TRUE, ?)",
                [$f['admin_name'], $f['admin_email'], password_hash($pw, PASSWORD_DEFAULT),
                 $f['type'] === 'hospital' ? 'hospital' : 'center', $fid, $user['id']]
            );
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'facility_created', 'facility', ?, ?)",
                    [$user['id'], $fid, $f['name']]);
        });
        flash_set("{$f['name']} was created. Administrator {$f['admin_email']}, temporary password: $pw (shown only once).");
        redirect('/admin/facilities.php');
    }
}

layout_start($user, 'Create facility account');
?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <h2>Facility</h2>
  <div class="field"><label for="name">Name</label>
    <input id="name" name="name" type="text" maxlength="120" required value="<?= e($f['name']) ?>"><?php field_error($errors, 'name'); ?></div>
  <div class="field"><label for="type">Type</label>
    <select id="type" name="type">
      <option value="hospital"<?= $f['type'] === 'hospital' ? ' selected' : '' ?>>Hospital</option>
      <option value="blood_bank"<?= $f['type'] === 'blood_bank' ? ' selected' : '' ?>>Blood bank (centre)</option>
    </select><?php field_error($errors, 'type'); ?></div>
        <div class="field"><label for="ownership">Public or private</label>
    <select id="ownership" name="ownership">
      <option value="public"<?= $f['ownership'] === 'public' ? ' selected' : '' ?>>Public (government or agency)</option>
      <option value="private"<?= $f['ownership'] === 'private' ? ' selected' : '' ?>>Private</option>
    </select><?php field_error($errors, 'ownership'); ?></div>
  <div class="field"><label for="region_id">Region</label>
    <select id="region_id" name="region_id" required><option value="">Choose…</option>
      <?php foreach ($regions as $r): ?><option value="<?= (int)$r['id'] ?>"<?= (string)$r['id'] === $f['region_id'] ? ' selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select><?php field_error($errors, 'region_id'); ?></div>
  <div class="field"><label for="license_no">Licence number (optional)</label>
    <input id="license_no" name="license_no" type="text" maxlength="40" value="<?= e($f['license_no']) ?>"><?php field_error($errors, 'license_no'); ?></div>

  <h2>Hospital rights</h2>
  <p class="hint">Only for hospitals. Ignored for blood banks.</p>
  <label class="check"><input type="checkbox" name="can_request_blood" value="1"<?= $f['can_request_blood'] ? ' checked' : '' ?>><span>Can request blood</span></label>
  <label class="check"><input type="checkbox" name="can_report_donors" value="1"<?= $f['can_report_donors'] ? ' checked' : '' ?>><span>Can report organ donors</span></label>
  <label class="check"><input type="checkbox" name="can_transplant" value="1"<?= $f['can_transplant'] ? ' checked' : '' ?>><span>Can register patients and receive organs (transplant centre)</span></label>

  <h2>First administrator</h2>
  <div class="field"><label for="admin_name">Full name</label>
    <input id="admin_name" name="admin_name" type="text" maxlength="120" required value="<?= e($f['admin_name']) ?>"><?php field_error($errors, 'admin_name'); ?></div>
  <div class="field"><label for="admin_email">Email address</label>
    <input id="admin_email" name="admin_email" type="email" maxlength="160" required value="<?= e($f['admin_email']) ?>"><?php field_error($errors, 'admin_email'); ?></div>
  <p class="hint">A temporary password is shown once after you save. They must change it at first sign-in.</p>
  <button class="btn" type="submit">Create facility and administrator</button>
</form>
<?php layout_end();
