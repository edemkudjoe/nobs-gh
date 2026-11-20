<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/slip.php';
$user = require_capability('admin');

/** Three-letter region code used in registration IDs. */
function region_code(string $regionName): string
{
    $codes = ['Greater Accra' => 'GAR', 'Ashanti' => 'ASH', 'Eastern' => 'EAS', 'Northern' => 'NOR', 'Volta' => 'VOL',
              'Western' => 'WES', 'Western North' => 'WNR', 'Central' => 'CEN', 'Oti' => 'OTI', 'Savannah' => 'SAV',
              'North East' => 'NEA', 'Upper East' => 'UEA', 'Upper West' => 'UWE', 'Bono' => 'BON', 'Bono East' => 'BOE', 'Ahafo' => 'AHA'];
    return $codes[$regionName] ?? strtoupper(substr((string)preg_replace('/[^A-Za-z]/', '', $regionName), 0, 3));
}

/**
 * Registration ID such as H-GAR-0001: H hospital or B blood bank, a region code, then a running number
 * for that type and region. Call it inside a transaction.
 */
function next_registration_id(string $type, string $regionName): string
{
    $prefix = ($type === 'hospital' ? 'H' : 'B') . '-' . region_code($regionName) . '-';
    db_val('SELECT pg_advisory_xact_lock(7301)');   // two admins saving at once must not get the same number
    $last = (int)db_val('SELECT MAX(CAST(right(license_no, 4) AS INT)) FROM facilities WHERE license_no ~ ?::text', ['^' . $prefix . '[0-9]{4}$']);
    return sprintf('%s%04d', $prefix, $last + 1);
}

$regions = db_all('SELECT id, name FROM regions ORDER BY name');
$errors  = [];
$slip    = null;   // set after a facility is created, to print the credentials
$f = ['name' => '', 'type' => 'hospital', 'ownership' => 'public', 'region_id' => '', 'admin_title' => '', 'admin_title_other' => '', 'admin_name' => '', 'admin_email' => '',
      'can_request_blood' => '', 'can_report_donors' => '', 'can_transplant' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'type', 'ownership', 'region_id', 'admin_title', 'admin_title_other', 'admin_name', 'admin_email'] as $k) {
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
        [$adminTitle, $titleError] = read_title('admin_title');
    if ($titleError) { $errors['admin_title'] = $titleError; }
    if (mb_strlen($f['admin_name']) < 2 || mb_strlen($f['admin_name']) > 120) { $errors['admin_name'] = 'Enter the administrator\'s name.'; }
    if (!filter_var($f['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($f['admin_email']) > 160) {
        $errors['admin_email'] = 'Enter a valid email address.';
    } elseif (db_val('SELECT 1 FROM users WHERE lower(email) = ?', [$f['admin_email']])) {
        $errors['admin_email'] = 'An account with this email already exists.';
    }

    if (!$errors) {
        $pw = temp_password();
        $regionName = '';
        foreach ($regions as $r) {
            if ((int)$r['id'] === (int)$f['region_id']) { $regionName = (string)$r['name']; }
        }
        $license = db_transaction(function () use ($user, $f, $pw, $regionName, $adminTitle) {
            $license = next_registration_id($f['type'], $regionName);   // always made here, never taken from the form
            $fid = db_val(
                "INSERT INTO facilities (name, type, ownership, region_id, license_no, verification_status, can_request_blood, can_report_donors, can_transplant)
                 VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?) RETURNING id",
                [$f['name'], $f['type'], $f['ownership'], (int)$f['region_id'], $license,
                 $f['can_request_blood'] ? 't' : 'f', $f['can_report_donors'] ? 't' : 'f', $f['can_transplant'] ? 't' : 'f']
            );
            db_exec(
                "INSERT INTO users (title, full_name, email, password_hash, role, facility_id, is_facility_admin, must_change_password, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, TRUE, TRUE, ?)",
                [$adminTitle, $f['admin_name'], $f['admin_email'], password_hash($pw, PASSWORD_DEFAULT),
                 $f['type'] === 'hospital' ? 'hospital' : 'center', $fid, $user['id']]
            );
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'facility_created', 'facility', ?, ?)",
                    [$user['id'], $fid, $f['name']]);
            return $license;
        });
        // The password is shown on this page only. It is not saved anywhere readable, so it cannot be shown again.
        $slip = ['facility' => $f['name'], 'license' => $license, 'type' => $f['type'], 'ownership' => $f['ownership'],
                 'region' => $regionName, 'admin_name' => person_name($adminTitle, $f['admin_name']), 'admin_email' => $f['admin_email'], 'password' => $pw];
    }
}

if ($slip !== null) {
    render_slip($user, [
        'title'   => 'Facility created',
        'heading' => 'Facility account credentials',
        'rows'    => [
            ['Facility', $slip['facility']],
            ['Type', ($slip['type'] === 'hospital' ? 'Hospital' : 'Blood bank') . ' · ' . ($slip['ownership'] === 'private' ? 'Private' : 'Public')],
            ['Region', $slip['region']],
            ['Registration ID', $slip['license'], 'mono'],
            ['Administrator', $slip['admin_name']],
            ['Sign-in email', $slip['admin_email'], 'mono'],
            ['Temporary password', $slip['password'], 'mono big'],
            ['Sign in at', signin_url(), 'mono'],
        ],
        'steps'   => [
            'Open the sign-in address above and sign in with the email and temporary password.',
            'You will be asked to choose your own password. The temporary one stops working once you do.',
            'Then create accounts for your own staff from the menu.',
        ],
        'actions' => [['Create another facility', '/admin/facility-new.php'], ['Back to facilities', '/admin/facilities.php']],
    ]);
    exit;
}

// Next free number for each type and region, so the form can show the ID before saving.
$nextNo = [];
foreach (db_all("SELECT license_no FROM facilities WHERE license_no ~ '^[HB]-[A-Z]{3}-[0-9]{4}$'") as $row) {
    $prefix = substr($row['license_no'], 0, 6);
    $nextNo[$prefix] = max($nextNo[$prefix] ?? 1, (int)substr($row['license_no'], -4) + 1);
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
      <?php foreach ($regions as $r): ?><option value="<?= (int)$r['id'] ?>" data-code="<?= e(region_code($r['name'])) ?>"<?= (string)$r['id'] === $f['region_id'] ? ' selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select><?php field_error($errors, 'region_id'); ?></div>
  <div class="field"><label for="license_no">Registration ID</label>
    <span class="hint">Made by the system from the type and region. It cannot be edited.</span>
    <input id="license_no" type="text" value="" readonly aria-readonly="true" placeholder="Choose a type and region"></div>

  <h2>Hospital rights</h2>
  <p class="hint">Only for hospitals. Ignored for blood banks.</p>
  <label class="check"><input type="checkbox" name="can_request_blood" value="1"<?= $f['can_request_blood'] ? ' checked' : '' ?>><span>Can request blood</span></label>
  <label class="check"><input type="checkbox" name="can_report_donors" value="1"<?= $f['can_report_donors'] ? ' checked' : '' ?>><span>Can report organ donors</span></label>
  <label class="check"><input type="checkbox" name="can_transplant" value="1"<?= $f['can_transplant'] ? ' checked' : '' ?>><span>Can register patients and receive organs (transplant centre)</span></label>

  <h2>First administrator</h2>
    <?php title_field('admin_title', $f['admin_title'], $f['admin_title_other'], $errors); ?>
  <div class="field"><label for="admin_name">Full name</label>
    <input id="admin_name" name="admin_name" type="text" maxlength="120" required value="<?= e($f['admin_name']) ?>"><?php field_error($errors, 'admin_name'); ?></div>
  <div class="field"><label for="admin_email">Email address</label>
    <input id="admin_email" name="admin_email" type="email" maxlength="160" required value="<?= e($f['admin_email']) ?>"><?php field_error($errors, 'admin_email'); ?></div>
  <p class="hint">A temporary password is shown once after you save. They must change it at first sign-in.</p>
  <button class="btn" type="submit">Create facility and administrator</button>
</form>
<script>
(function () {
  var next = <?= json_encode($nextNo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_FORCE_OBJECT) ?>;
  var type = document.getElementById('type'), region = document.getElementById('region_id'), out = document.getElementById('license_no');
  function update() {
    var opt = region.options[region.selectedIndex];
    var code = opt ? opt.getAttribute('data-code') : '';
    if (!code) { out.value = ''; return; }
    var prefix = (type.value === 'hospital' ? 'H' : 'B') + '-' + code + '-';
    out.value = prefix + String(next[prefix] || 1).padStart(4, '0');
  }
  type.addEventListener('change', update);
  region.addEventListener('change', update);
  update();
})();
</script>
<?php layout_end();
