<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

$near = setting_int('near_expiry_days');
$groups = db_all(
    "SELECT bg.id, bg.label,
            COUNT(*) FILTER (WHERE bu.status = 'available') AS available,
            COUNT(*) FILTER (WHERE bu.status = 'testing') AS testing,
            COUNT(*) FILTER (WHERE bu.status = 'reserved') AS reserved,
            COUNT(*) FILTER (WHERE bu.status = 'available' AND bu.expiry_date <= CURRENT_DATE + ?::int) AS near,
            COALESCE((SELECT t.min_units FROM stock_thresholds t WHERE t.blood_group_id = bg.id AND t.facility_id IS NULL), 0) AS minimum
       FROM blood_groups bg LEFT JOIN blood_units bu ON bu.blood_group_id = bg.id
      GROUP BY bg.id, bg.label ORDER BY bg.label",
    [$near]
);
$byCentre = db_all(
    "SELECT f.name, r.name AS region,
            COUNT(*) FILTER (WHERE bu.status = 'available') AS available,
            COUNT(*) FILTER (WHERE bu.status = 'testing') AS testing,
            COUNT(*) FILTER (WHERE bu.status = 'reserved') AS reserved
       FROM facilities f JOIN regions r ON r.id = f.region_id
       LEFT JOIN blood_units bu ON bu.facility_id = f.id
      WHERE f.type = 'blood_bank' AND f.verification_status = 'approved'
      GROUP BY f.id, f.name, r.name ORDER BY f.name"
);
$low = count(array_filter($groups, fn($g) => (int)$g['available'] < (int)$g['minimum']));

layout_start($user, 'National blood inventory');
?>
<div class="tiles">
  <div class="tile"><div class="label">Units available</div><div class="value"><?= array_sum(array_column($groups, 'available')) ?></div></div>
  <div class="tile"><div class="label">In testing</div><div class="value"><?= array_sum(array_column($groups, 'testing')) ?></div></div>
  <div class="tile"><div class="label">Groups below minimum</div><div class="value"><?= $low ?></div></div>
</div>

<h2>By blood group</h2>
<table>
  <thead><tr><th>Group</th><th>Available</th><th>Minimum</th><th>Reserved</th><th>In testing</th><th>Expiring within <?= $near ?> days</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($groups as $g): $isLow = (int)$g['available'] < (int)$g['minimum']; ?>
    <tr>
      <td><span class="code"><?= e($g['label']) ?></span></td>
      <td><?= (int)$g['available'] ?></td>
      <td><?= (int)$g['minimum'] ?></td>
      <td><?= (int)$g['reserved'] ?></td>
      <td><?= (int)$g['testing'] ?></td>
      <td><?= (int)$g['near'] ?></td>
      <td><?= $isLow ? '<span class="badge badge-red">Low stock</span>' : '<span class="badge badge-green">OK</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="hint">Change the minimums on the Settings page.</p>

<h2>By blood centre</h2>
<table>
  <thead><tr><th>Centre</th><th>Region</th><th>Available</th><th>Reserved</th><th>In testing</th></tr></thead>
  <tbody>
  <?php foreach ($byCentre as $c): ?>
    <tr><td><?= e($c['name']) ?></td><td><?= e($c['region']) ?></td><td><?= (int)$c['available'] ?></td><td><?= (int)$c['reserved'] ?></td><td><?= (int)$c['testing'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php layout_end();
