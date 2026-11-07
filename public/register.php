<?php
declare(strict_types=1);
require __DIR__ . '/../includes/layout.php';

if (current_user()) {
    redirect(home_for(current_user()['role']));
}

$regions = db_all('SELECT id, name FROM regions ORDER BY name');
$groups  = db_all('SELECT id, label FROM blood_groups ORDER BY id');
$errors  = [];
$f = ['full_name' => '', 'email' => '', 'date_of_birth' => '', 'weight_kg' => '', 'region_id' => '', 'blood_group_id' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($f as $k => $_) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $f['email'] = mb_strtolower($f['email']);
    $password = (string)($_POST['password'] ?? '');

    if (mb_strlen($f['full_name']) < 2 || mb_strlen($f['full_name']) > 120) {
        $errors['full_name'] = 'Enter your full name.';
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 160) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (db_val('SELECT 1 FROM users WHERE lower(email) = ?', [$f['email']])) {
        $errors['email'] = 'An account with this email already exists. Try signing in.';
    }
    $dob = DateTime::createFromFormat('!Y-m-d', $f['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $f['date_of_birth'] || $dob > new DateTime('today')) {
        $errors['date_of_birth'] = 'Enter your date of birth.';
    } elseif ($dob->diff(new DateTime('today'))->y < setting_int('min_age')) {
        $errors['date_of_birth'] = 'You must be at least ' . setting_int('min_age') . ' years old to register as a donor.';
    }
    $weight = filter_var($f['weight_kg'], FILTER_VALIDATE_FLOAT);
    if ($weight === false || $weight < 30 || $weight > 250) {
        $errors['weight_kg'] = 'Enter your weight in kilograms (30 to 250).';
    }
    if (!in_array((int)$f['region_id'], array_column($regions, 'id'), true)) {
        $errors['region_id'] = 'Choose your region.';
    }
    if ($f['blood_group_id'] !== '' && !in_array((int)$f['blood_group_id'], array_column($groups, 'id'), true)) {
        $errors['blood_group_id'] = 'Choose a blood group from the list.';
    }
    if ($f['phone'] !== '' && !preg_match('/^[0-9+ ]{7,20}$/', $f['phone'])) {
        $errors['phone'] = 'Use only digits, spaces and +.';
    }
    if ($problem = password_problem($password)) {
        $errors['password'] = $problem;
    } elseif ($password !== (string)($_POST['confirm_password'] ?? '')) {
        $errors['password'] = 'The two passwords do not match.';
    }

    if (!$errors) {
        db_transaction(function () use ($f, $password, $weight) {
            $id = db_val(
                "INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'donor') RETURNING id",
                [$f['full_name'], $f['email'], password_hash($password, PASSWORD_DEFAULT)]
            );
            db_exec(
                'INSERT INTO donors (user_id, blood_group_id, date_of_birth, weight_kg, region_id, phone) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $f['blood_group_id'] === '' ? null : (int)$f['blood_group_id'], $f['date_of_birth'], $weight,
                 (int)$f['region_id'], $f['phone'] === '' ? null : $f['phone']]
            );
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'donor_registered', 'user', ?, 'self-registered')", [$id, $id]);
        });
        attempt_login($f['email'], $password);   // sign them in straight away
        redirect('/donor/dashboard.php');
    }
}

guest_start('Register as a donor');
?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="field"><label for="full_name">Full name</label>
    <input id="full_name" name="full_name" type="text" maxlength="120" required value="<?= e($f['full_name']) ?>"><?php field_error($errors, 'full_name'); ?></div>
  <div class="field"><label for="email">Email address</label>
    <input id="email" name="email" type="email" maxlength="160" required autocomplete="username" value="<?= e($f['email']) ?>"><?php field_error($errors, 'email'); ?></div>
  <div class="field"><label for="date_of_birth">Date of birth</label>
    <input id="date_of_birth" name="date_of_birth" type="date" required value="<?= e($f['date_of_birth']) ?>"><?php field_error($errors, 'date_of_birth'); ?></div>
  <div class="field"><label for="weight_kg">Weight (kg)</label>
    <input id="weight_kg" name="weight_kg" type="number" step="0.1" min="30" max="250" required value="<?= e($f['weight_kg']) ?>"><?php field_error($errors, 'weight_kg'); ?></div>
  <div class="field"><label for="region_id">Region</label>
    <select id="region_id" name="region_id" required><option value="">Choose…</option>
      <?php foreach ($regions as $r): ?><option value="<?= (int)$r['id'] ?>"<?= (string)$r['id'] === $f['region_id'] ? ' selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select><?php field_error($errors, 'region_id'); ?></div>
  <div class="field"><label for="blood_group_id">Blood group</label>
    <span class="hint">If you do not know, leave it. A centre will confirm it at your first donation.</span>
    <select id="blood_group_id" name="blood_group_id"><option value="">I do not know</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"<?= (string)$g['id'] === $f['blood_group_id'] ? ' selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?>
    </select><?php field_error($errors, 'blood_group_id'); ?></div>
  <div class="field"><label for="phone">Phone (optional)</label>
    <input id="phone" name="phone" type="text" maxlength="20" value="<?= e($f['phone']) ?>"><?php field_error($errors, 'phone'); ?></div>
  <div class="field"><label for="password">Password</label>
    <span class="hint">At least 8 characters, with a letter and a number.</span>
    <input id="password" name="password" type="password" required autocomplete="new-password"><?php field_error($errors, 'password'); ?></div>
  <div class="field"><label for="confirm_password">Confirm password</label>
    <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password"></div>
  <button class="btn" type="submit">Create account</button>
</form>
<p>Already registered? <a href="/login.php">Sign in</a></p>
<?php guest_end();
