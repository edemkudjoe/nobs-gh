<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
require __DIR__ . '/../../includes/blood.php';
$user = require_capability('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int)($_POST['request_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($action === 'verify' || $action === 'reject') {
        [$ok, $msg] = blood_review($user, $id, $action, $reason);
    } elseif ($action === 'offer') {
        [$ok, $msg] = blood_offer($user, $id, $reason);
    } else {
        [$ok, $msg] = [false, 'Unknown action.'];
    }
    flash_set($msg, $ok ? 'ok' : 'error');
    redirect('/admin/blood-requests.php');
}

$new      = blood_queue('submitted');
$queue    = blood_queue('verified');
$offered  = db_all(
    "SELECT r.id, r.patient_ref, r.urgency, bg.label, f.name AS hospital,
            MIN(a.respond_by) AS respond_by, COUNT(*) AS units
       FROM requests r JOIN blood_groups bg ON bg.id = r.blood_group_id JOIN facilities f ON f.id = r.hospital_id
       JOIN allocations a ON a.request_id = r.id AND a.response = 'pending'
      WHERE r.status = 'offered' GROUP BY r.id, bg.label, f.name ORDER BY MIN(a.respond_by)"
);

layout_start($user, 'Blood requests');
?>
<h2>New requests to review (<?= count($new) ?>)</h2>
<?php if (!$new): ?>
  <p>Nothing waiting for review.</p>
<?php else: ?>
<table>
  <thead><tr><th>Request</th><th>Hospital</th><th>Group</th><th>Units</th><th>Urgency</th><th>Sent</th><th>Decision</th></tr></thead>
  <tbody>
  <?php foreach ($new as $r): ?>
    <tr>
      <td><span class="code">BR-<?= (int)$r['id'] ?></span><br><span class="code"><?= e($r['patient_ref']) ?></span></td>
      <td><?= e($r['hospital']) ?></td>
      <td><span class="code"><?= e($r['label']) ?></span><?= $r['accept_compatible'] ? '<br>or compatible' : '' ?></td>
      <td><?= (int)$r['quantity'] ?></td>
      <td><strong><?= e(ucfirst($r['urgency'])) ?></strong></td>
      <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
      <td>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
          <input type="text" name="reason" maxlength="200" placeholder="Reason (needed to reject)" aria-label="Reason for rejecting BR-<?= (int)$r['id'] ?>">
          <button class="btn btn-small" name="action" value="verify" type="submit">Verify</button>
          <button class="btn btn-danger btn-small" name="action" value="reject" type="submit">Reject</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Ranked queue: verified requests (<?= count($queue) ?>)</h2>
<p class="hint">Ranked by urgency, then by how long the request has waited. Units are suggested with the exact group first, then the soonest expiry.</p>
<?php if (!$queue): ?>
  <p>No verified requests are waiting.</p>
<?php else:
  $seen = [];   // verified requests ahead, per blood group
  ?>
<table>
  <thead><tr><th>Rank</th><th>Request</th><th>Hospital</th><th>Needs</th><th>Urgency</th><th>Waiting since</th><th>Suggested units</th><th>Offer</th></tr></thead>
  <tbody>
  <?php foreach ($queue as $i => $r):
      $need  = (int)$r['quantity'] - (int)$r['quantity_fulfilled'];
      $units = blood_find_units((int)$r['blood_group_id'], (bool)$r['accept_compatible'], $r['urgency'], $need);
      $ahead = $seen[$r['blood_group_id']] ?? 0;
      $seen[$r['blood_group_id']] = $ahead + 1; ?>
    <tr>
      <td><strong>#<?= $i + 1 ?></strong></td>
      <td><span class="code">BR-<?= (int)$r['id'] ?></span><br><span class="code"><?= e($r['patient_ref']) ?></span></td>
      <td><?= e($r['hospital']) ?></td>
      <td><?= $need ?> × <span class="code"><?= e($r['label']) ?></span><?= $r['accept_compatible'] ? '<br>or compatible' : '' ?></td>
      <td><strong><?= e(ucfirst($r['urgency'])) ?></strong></td>
      <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
      <td>
        <?php if (!$units): ?>
          None available
        <?php else: foreach ($units as $u): ?>
          <span class="code">BU-<?= (int)$u['id'] ?></span> <?= e($u['label']) ?>, <?= (int)$u['days_left'] ?> days left, <?= e($u['facility']) ?><br>
        <?php endforeach; if (count($units) < $need): ?>Only <?= count($units) ?> of <?= $need ?> available<?php endif; endif; ?>
      </td>
      <td>
        <?php if ($units): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
          <?php if ($ahead > 0): ?>
            <input type="text" name="reason" maxlength="300" placeholder="Why offer out of order?" aria-label="Reason for offering BR-<?= (int)$r['id'] ?> out of order">
          <?php endif; ?>
          <button class="btn btn-small" name="action" value="offer" type="submit">Offer units</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Offers waiting for a hospital reply (<?= count($offered) ?>)</h2>
<?php if (!$offered): ?>
  <p>No open offers.</p>
<?php else: ?>
<table>
  <thead><tr><th>Request</th><th>Hospital</th><th>Group</th><th>Units offered</th><th>Reply by</th></tr></thead>
  <tbody>
  <?php foreach ($offered as $o): ?>
    <tr>
      <td><span class="code">BR-<?= (int)$o['id'] ?></span></td>
      <td><?= e($o['hospital']) ?></td>
      <td><span class="code"><?= e($o['label']) ?></span></td>
      <td><?= (int)$o['units'] ?></td>
      <td><?= e(substr($o['respond_by'], 0, 16)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
