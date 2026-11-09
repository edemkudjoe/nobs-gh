<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';

$user = require_login(true);   // true = this page is allowed while a password change is pending
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $old     = (string)($_POST['current_password'] ?? '');

    $hash = db_val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if (!password_verify($old, (string)$hash)) {
        $error = 'Your current password is not correct.';
    } elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $error = 'The new password must be at least 8 characters and include a letter and a number.';
    } elseif ($new !== $confirm) {
        $error = 'The two new passwords do not match.';
    } elseif ($new === $old) {
        $error = 'Choose a password different from the current one.';
    } else {
        db_exec('UPDATE users SET password_hash = ?, must_change_password = FALSE WHERE id = ?',
                [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        redirect(home_for($user['role']));
    }
}
guest_start('Set your own password', $user['must_change_password']
    ? 'Your temporary password was created for you. Choose one that only you know before you continue.'
    : 'Choose a new password for your account.');
?>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" style="max-width:480px">
  <?= csrf_field() ?>
  <div class="field"><label for="current_password">Temporary or current password</label>
    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
  <div class="field"><label for="new_password">New password</label>
    <span class="hint">At least 8 characters, with a letter and a number.</span>
    <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password"></div>
  <div class="field"><label for="confirm_password">Repeat new password</label>
    <input id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password"></div>
  <div class="form-actions"><button class="btn" type="submit">Save and continue</button></div>
</form>
<?php guest_end();
