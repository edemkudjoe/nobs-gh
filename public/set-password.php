<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

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
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Set your password</title><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body>
  <h1>Set your password</h1>
  <?php if ($user['must_change_password']): ?><p>You must choose a new password before continuing.</p><?php endif; ?>
  <?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Current password <input type="password" name="current_password" required autocomplete="current-password"></label><br>
    <label>New password <input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label><br>
    <label>Confirm new password <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label><br>
    <button type="submit">Save password</button>
  </form>
</body>
</html>
