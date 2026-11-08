<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('hospital');
$fid  = (int)$user['facility_id'];

$open = $offers = $organ_offers = $waiting = null;
if (!empty($user['can_request_blood'])) {
    $open   = (int)db_val("SELECT COUNT(*) FROM requests WHERE hospital_id = ? AND status IN ('submitted','verified','offered')", [$fid]);
    $offers = (int)db_val("SELECT COUNT(*) FROM allocations a JOIN requests r ON r.id = a.request_id
                            WHERE r.hospital_id = ? AND a.response = 'pending'", [$fid]);
    $recent = db_all(
        "SELECT r.id, r.patient_ref, r.urgency, r.status, r.quantity, r.quantity_fulfilled, r.created_at, bg.label
           FROM requests r JOIN blood_groups bg ON bg.id = r.blood_group_id
          WHERE r.hospital_id = ? ORDER BY r.created_at DESC LIMIT 5", [$fid]
    );
}
if (!empty($user['can_transplant'])) {
    $organ_offers = (int)db_val("SELECT COUNT(*) FROM organ_offers o JOIN waiting_list w ON w.id = o.waiting_list_id
                                  WHERE w.transplant_center_id = ? AND o.response = 'pending'", [$fid]);
    $waiting = (int)db_val("SELECT COUNT(*) FROM waiting_list WHERE transplant_center_id = ? AND status = 'active' AND verification_status = 'verified'", [$fid]);
}

layout_start($user, 'Dashboard');
?>
<p>Welcome, <?= e($user['full_name']) ?> (<?= e($user['facility_name']) ?>).</p>
<div class="tiles">
  <?php if ($open !== null): ?>
    <div class="tile"><div class="label">Open blood requests</div><div class="value"><?= $open ?></div></div>
    <div class="tile"><div class="label">Blood offers to answer</div><div class="value"><?= $offers ?></div><div class="label"><a href="/hospital/requests.php">Go to my requests</a></div></div>
  <?php endif; ?>
  <?php if ($organ_offers !== null): ?>
    <div class="tile"><div class="label">Organ offers to answer</div><div class="value"><?= $organ_offers ?></div><div class="label"><a href="/hospital/offers.php">Go to organ offers</a></div></div>
    <div class="tile"><div class="label">Patients on the waiting list</div><div class="value"><?= $waiting ?></div></div>
  <?php endif; ?>
</div>

<?php if ($open !== null): ?>
  <h2>Latest blood requests</h2>
  <?php if (!$recent): ?>
    <p>You have not made any requests yet. <a href="/hospital/request.php">Request blood</a></p>
  <?php else: ?>
  <table>
    <thead><tr><th>Request</th><th>Patient</th><th>Group</th><th>Units</th><th>Urgency</th><th>Status</th><th>Sent</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><span class="code">BR-<?= (int)$r['id'] ?></span></td>
        <td><span class="code"><?= e($r['patient_ref']) ?></span></td>
        <td><span class="code"><?= e($r['label']) ?></span></td>
        <td><?= (int)$r['quantity_fulfilled'] ?> of <?= (int)$r['quantity'] ?></td>
        <td><?= e(ucfirst($r['urgency'])) ?></td>
        <td><strong><?= e(ucfirst($r['status'])) ?></strong></td>
        <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
<?php endif; ?>
<?php if ($open === null && $organ_offers === null): ?>
  <p>Your hospital can view blood availability. Ask the national administrator if you need other rights.</p>
<?php endif;
layout_end();
