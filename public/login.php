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
public_start('Sign in', '/login.php');
?>
<div class="auth">
  <div class="auth-brand">
    <div class="ab-name"><?= icon('drop', 28) ?>National Organ &amp; Blood Bank System</div>
    <div>
      <div class="ab-big">Every offer and decision, on the record.</div>
      <p>Sign in to book a donation, manage stock, or respond to an offer.</p>
    </div>
    <div class="ab-fine">Student project prototype</div>
  </div>
  <div class="auth-side">
    <form class="auth-card" method="post">
      <?= csrf_field() ?>
      <?= site_logos() !== '' ? str_replace('<span class="logos-sep" aria-hidden="true"></span>', '', site_logos()) : '' ?>
      <div>
        <h1>Sign in</h1>
        <div class="sub">Access your account.</div>
      </div>
      <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="pwbox">
          <input id="password" name="password" type="password" required autocomplete="current-password">
          <button type="button" id="showpw" aria-label="Show password" aria-pressed="false"><?= icon('eye', 22) ?></button>
        </div>
      </div>
      <button class="btn" type="submit">Sign in</button>
      <div class="small">Need help accessing your account? Contact your facility administrator.</div>
      <div class="secure"><?= icon('shield', 16) ?>Secure access</div>
      <div class="links"><a href="/register.php">Create a donor account</a><a href="/">Return to home</a></div>
    </form>
  </div>
</div>
<script>
// Show or hide the password. The form works the same without this script.
(function () {
  var b = document.getElementById('showpw'), f = document.getElementById('password');
  if (!b || !f) { return; }
  b.addEventListener('click', function () {
    var show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
})();
</script>
<?php public_end();
