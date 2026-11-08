<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

$show = ($_GET['show'] ?? 'pending') === 'all' ? 'all' : 'pending';
$rows = db_all(
    "SELECT a.id, a.request_id, a.blood_unit_id, a.offered_at, a.respond_by, a.response, a.responded_at, a.override_reason,
            bg.label, f.name AS hospital, r.urgency, r.patient_ref, bu.expiry_date
       FROM allocations a
       JOIN requests r ON r.id = a.request_id
       JOIN facilities f ON f.id = r.hospital_id
       JOIN blood_units bu ON bu.id = a.blood_unit_id
       JOIN blood_groups bg ON bg.id = bu.blood_group_id
      WHERE (?::text = 'all' OR a.response = 'pending')
      ORDER BY (a.response = 'pending') DESC, a.offered_at DESC
      LIMIT 100",
    [$show]
);

layout_start($user, 'Blood offers');
?>
<p>
  <a class="btn btn-quiet btn-small" href="?show=pending">Waiting for an answer</a>
  <a class="btn btn-quiet btn-small" href="?show=all">All offers (latest 100)</a>
</p>
<?php if (!$rows): ?>
  <p>No offers to show.</p>
<?php else: ?>
<table>
  <thead><tr><th>Offer</th><th>Request</th><th>Hospital</th><th>Unit</th><th>Offered</th><th>Answer by</th><th>Response</th><th>Override reason</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><span class="code">OF-<?= (int)$r['id'] ?></span></td>
      <td><span class="code">BR-<?= (int)$r['request_id'] ?></span><br><?= e(ucfirst($r['urgency'])) ?></td>
      <td><?= e($r['hospital']) ?></td>
      <td><span class="code">BU-<?= (int)$r['blood_unit_id'] ?></span> <?= e($r['label']) ?><br>expires <?= e($r['expiry_date']) ?></td>
      <td><?= e(substr($r['offered_at'], 0, 16)) ?></td>
      <td><?= e(substr($r['respond_by'], 0, 16)) ?></td>
      <td><strong><?= e(ucfirst(str_replace('_', ' ', $r['response']))) ?></strong><?= $r['responded_at'] ? '<br>' . e(substr($r['responded_at'], 0, 16)) : '' ?></td>
      <td><?= $r['override_reason'] ? e($r['override_reason']) : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif;
layout_end();
