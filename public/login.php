<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';

$error = '';
if (current_user() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(home_for(current_user()['role']));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = (string)($_POST['email'] ?? '');
    if (login_blocked($email)) {
        $error = 'Too many wrong attempts. Wait 15 minutes and try again.';
    } else {
        $user = attempt_login($email, (string)($_POST['password'] ?? ''));
        if ($user === null) {
            login_record_failure($email);
            $error = 'Email or password is not correct.';
        } elseif ($user['must_change_password']) {
            redirect('/set-password.php');
        } else {
            redirect(home_for($user['role']));
        }
    }
}
guest_start('Sign in');
?>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
  </div>
  <button class="btn" type="submit">Sign in</button>
</form>
<p>New donor? <a href="/register.php">Create an account</a></p>
<?php guest_end();
