<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
require __DIR__ . '/../../includes/blood.php';
$user = require_capability('can_request_blood');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $decision = (string)($_POST['decision'] ?? '');
    if (in_array($decision, ['accept', 'decline'], true)) {
        [$ok, $msg] = blood_respond($user, (int)($_POST['request_id'] ?? 0), $decision);
    } else {
        [$ok, $msg] = [false, 'Unknown action.'];
    }
    flash_set($msg, $ok ? 'ok' : 'error');
    redirect('/hospital/requests.php');
}

// Only this hospital's own requests.
$requests = db_all(
    "SELECT r.id, bg.label, r.quantity, r.quantity_fulfilled, r.urgency, r.status, r.reject_reason, r.created_at
       FROM requests r JOIN blood_groups bg ON bg.id = r.blood_group_id
      WHERE r.hospital_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 50",
    [$user['facility_id']]
);
$offers = [];   // pending units per request
foreach (db_all(
    "SELECT a.request_id, a.respond_by, bu.id AS unit_id, bu.expiry_date, bg.label, f.name AS facility
       FROM allocations a JOIN blood_units bu ON bu.id = a.blood_unit_id
       JOIN blood_groups bg ON bg.id = bu.blood_group_id JOIN facilities f ON f.id = bu.facility_id
      WHERE a.response = 'pending' AND a.request_id IN (SELECT id FROM requests WHERE hospital_id = ?)
   ORDER BY bu.id", [$user['facility_id']]) as $a) {
    $offers[$a['request_id']][] = $a;
}

layout_start($user, 'My requests');
?>
<p><a class="btn" href="/hospital/request.php">Request blood</a></p>
<?php if (!$requests): ?>
  <p>You have not sent any requests yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Request</th><th>Group</th><th>Units</th><th>Urgency</th><th>Status</th><th>Sent</th></tr></thead>
  <tbody>
  <?php foreach ($requests as $r): ?>
    <tr>
      <td><span class="code">BR-<?= (int)$r['id'] ?></span></td>
      <td><span class="code"><?= e($r['label']) ?></span></td>
      <td><?= (int)$r['quantity_fulfilled'] ?> of <?= (int)$r['quantity'] ?></td>
      <td><?= e(ucfirst($r['urgency'])) ?></td>
      <td><?= e(ucfirst($r['status'])) ?><?= $r['status'] === 'rejected' && $r['reject_reason'] ? ': ' . e($r['reject_reason']) : '' ?></td>
      <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
    </tr>
    <?php if (!empty($offers[$r['id']])): $o = $offers[$r['id']]; ?>
    <tr>
      <td colspan="6">
        <strong>Offer from the national office. Reply by <?= e(substr($o[0]['respond_by'], 0, 16)) ?></strong><br>
        <?php foreach ($o as $u): ?>
          <span class="code">BU-<?= (int)$u['unit_id'] ?></span> <?= e($u['label']) ?>, expires <?= e($u['expiry_date']) ?>, from <?= e($u['facility']) ?><br>
        <?php endforeach; ?>
        <form method="post" class="row-actions"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-small" name="decision" value="accept" type="submit">Accept units</button>
          <button class="btn btn-danger btn-small" name="decision" value="decline" type="submit">Decline</button>
        </form>
      </td>
    </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
