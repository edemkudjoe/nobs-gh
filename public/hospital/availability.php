<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('hospital');

$near = setting_int('near_expiry_days');
// Counts only. A hospital sees how much blood exists, never who holds which unit.
$groups = db_all(
    "SELECT bg.label,
            COUNT(bu.id) FILTER (WHERE bu.status = 'available' AND bu.expiry_date >= CURRENT_DATE) AS total,
            COUNT(bu.id) FILTER (WHERE bu.status = 'available' AND bu.expiry_date >= CURRENT_DATE AND f.region_id = h.region_id) AS in_region,
            COUNT(bu.id) FILTER (WHERE bu.status = 'available' AND bu.expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + ?::int) AS soon
       FROM blood_groups bg
       CROSS JOIN (SELECT region_id FROM facilities WHERE id = ?) h
       LEFT JOIN blood_units bu ON bu.blood_group_id = bg.id
       LEFT JOIN facilities f ON f.id = bu.facility_id
      GROUP BY bg.id, bg.label ORDER BY bg.label",
    [$near, $user['facility_id']]
);
$region = (string)db_val('SELECT r.name FROM facilities f JOIN regions r ON r.id = f.region_id WHERE f.id = ?', [$user['facility_id']]);

layout_start($user, 'Blood availability');
?>
<p>Units that are tested, in date and not already promised to another hospital. Counts change as offers are made and answered.</p>
<table>
  <thead><tr><th>Group</th><th>Available nationally</th><th>In <?= e($region) ?></th><th>Expiring within <?= $near ?> days</th><th>Level</th></tr></thead>
  <tbody>
  <?php foreach ($groups as $g): $n = (int)$g['total']; ?>
    <tr>
      <td><span class="code"><?= e($g['label']) ?></span></td>
      <td><?= $n ?></td>
      <td><?= (int)$g['in_region'] ?></td>
      <td><?= (int)$g['soon'] ?></td>
      <td><?= $n === 0 ? '<span class="badge badge-red">None</span>' : ($n < 5 ? '<span class="badge badge-amber">Low</span>' : '<span class="badge badge-green">Good</span>') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if (!empty($user['can_request_blood'])): ?>
  <p><a class="btn" href="/hospital/request.php">Request blood</a></p>
<?php endif;
layout_end();
