<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
require __DIR__ . '/../../includes/donation.php';
$user  = require_capability('donor');
$check = donor_check((int)$user['id']);

layout_start($user, 'Check eligibility');
?>
<?php if (!$check['donor']): ?>
  <p class="alert">Your donor details are not complete yet.</p>
<?php else: ?>
  <?php if (donor_can_donate_today($check)): ?>
    <p class="notice">You meet the requirements today. <a href="/donor/book.php">Book a donation</a></p>
  <?php elseif ($check['blockers']): ?>
    <div class="alert"><?php foreach ($check['blockers'] as $b): ?><div><?= e($b) ?></div><?php endforeach; ?></div>
  <?php else: ?>
    <p class="alert">You can donate again from <?= e($check['from']->format('j F Y')) ?>. <a href="/donor/book.php">You can book for that day or later.</a></p>
  <?php endif; ?>
  <table>
    <thead><tr><th>Check</th><th>You</th><th>Rule</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach ($check['rows'] as [$label, $value, $ok, $rule]): ?>
      <tr><td><?= e($label) ?></td><td><?= e($value) ?></td><td><?= e($rule) ?></td><td><strong><?= $ok ? 'Met' : 'Not met' ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint">This is a quick check. A nurse at the centre makes the final decision on the day.</p>
<?php endif; ?>
<?php layout_end();
