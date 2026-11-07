<?php
declare(strict_types=1);
require __DIR__ . '/../includes/auth.php';

$error = '';
if (current_user() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(home_for(current_user()['role']));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($user === null) {
        $error = 'Email or password is not correct.';
    } elseif ($user['must_change_password']) {
        redirect('/set-password.php');
    } else {
        redirect(home_for($user['role']));
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Sign in</title><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body>
  <h1>Sign in</h1>
  <?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email address <input type="email" name="email" required autocomplete="username"></label><br>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label><br>
    <button type="submit">Sign in</button>
  </form>
</body>
</html>