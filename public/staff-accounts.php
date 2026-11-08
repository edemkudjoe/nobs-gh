<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
$user = require_capability('facility_admin');   // a centre or hospital administrator

$errors = [];
$f = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $f['full_name'] = trim((string)($_POST['full_name'] ?? ''));
        $f['email']     = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        if (mb_strlen($f['full_name']) < 2 || mb_strlen($f['full_name']) > 120) {
            $errors['full_name'] = 'Enter the staff member\'s full name.';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 160) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (db_val('SELECT 1 FROM users WHERE lower(email) = ?', [$f['email']])) {
            $errors['email'] = 'An account with this email already exists.';
        }
        if (!$errors) {
            $pw = temp_password();
            db_transaction(function () use ($user, $f, $pw) {
                // The new account always belongs to the administrator's own facility and role.
                $id = db_val(
                    "INSERT INTO users (full_name, email, password_hash, role, facility_id, is_facility_admin, must_change_password, created_by)
                     VALUES (?, ?, ?, ?, ?, FALSE, TRUE, ?) RETURNING id",
                    [$f['full_name'], $f['email'], password_hash($pw, PASSWORD_DEFAULT), $user['role'], $user['facility_id'], $user['id']]
                );
                db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'staff_created', 'user', ?, ?)",
                        [$user['id'], $id, $f['email']]);
            });
            flash_set("Account created for {$f['full_name']}. Temporary password: $pw (shown only once. They must change it at first sign-in).");
            redirect('/staff-accounts.php');
        }
    } elseif (in_array($action, ['deactivate', 'activate', 'reset'], true)) {
        $target = (int)($_POST['user_id'] ?? 0);
        // Only non-admin staff of this same facility can be touched.
        $row = db_one('SELECT id, full_name FROM users WHERE id = ? AND facility_id = ? AND is_facility_admin = FALSE',
                      [$target, $user['facility_id']]);
        if (!$row) {
            flash_set('That account is not one of your staff.', 'error');
            redirect('/staff-accounts.php');
        }
        if ($action === 'reset') {
            $pw = temp_password();
            db_exec('UPDATE users SET password_hash = ?, must_change_password = TRUE WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['id']]);
            db_exec('DELETE FROM sessions WHERE user_id = ?', [$row['id']]);
            $msg = "New temporary password for {$row['full_name']}: $pw (shown only once).";
        } else {
            db_exec('UPDATE users SET status = ? WHERE id = ?', [$action === 'deactivate' ? 'deactivated' : 'active', $row['id']]);
            if ($action === 'deactivate') {
                db_exec('DELETE FROM sessions WHERE user_id = ?', [$row['id']]);   // signs them out at once
            }
            $msg = $row['full_name'] . ($action === 'deactivate' ? ' can no longer sign in.' : ' can sign in again.');
        }
        db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, ?, 'user', ?, NULL)",
                [$user['id'], 'staff_' . $action, $row['id']]);
        flash_set($msg);
        redirect('/staff-accounts.php');
    }
}

$staff = db_all(
    "SELECT id, full_name, email, status, last_login_at, must_change_password
       FROM users WHERE facility_id = ? AND is_facility_admin = FALSE ORDER BY full_name",
    [$user['facility_id']]
);

layout_start($user, 'Staff accounts');
?>
<p><?= e($user['facility_name']) ?></p>

<h2>Add a staff member</h2>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="field"><label for="full_name">Full name</label>
    <input id="full_name" name="full_name" type="text" maxlength="120" required value="<?= e($f['full_name']) ?>">
    <?php field_error($errors, 'full_name'); ?></div>
  <div class="field"><label for="email">Email address</label>
    <input id="email" name="email" type="email" maxlength="160" required value="<?= e($f['email']) ?>">
    <?php field_error($errors, 'email'); ?></div>
  <button class="btn" type="submit">Create account</button>
</form>

<h2>Your staff</h2>
<?php if (!$staff): ?>
  <p>No staff accounts yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Last sign-in</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($staff as $s): ?>
    <tr>
      <td><?= e($s['full_name']) ?></td>
      <td><?= e($s['email']) ?></td>
      <td><?= $s['status'] === 'active' ? 'Active' : 'Deactivated' ?><?= $s['must_change_password'] ? ' (new password pending)' : '' ?></td>
      <td><?= $s['last_login_at'] ? e(substr($s['last_login_at'], 0, 16)) : 'Never' ?></td>
      <td class="row-actions">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-quiet btn-small" name="action" value="reset" type="submit">Reset password</button>
          <?php if ($s['status'] === 'active'): ?>
            <button class="btn btn-danger btn-small" name="action" value="deactivate" type="submit">Deactivate</button>
          <?php else: ?>
            <button class="btn btn-quiet btn-small" name="action" value="activate" type="submit">Reactivate</button>
          <?php endif; ?>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
