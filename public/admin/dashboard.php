<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

// Numbers for the tiles. One small query each.
$waiting_requests = (int)db_val("SELECT COUNT(*) FROM requests WHERE status IN ('submitted','verified')");
$critical         = (int)db_val("SELECT COUNT(*) FROM requests WHERE status IN ('submitted','verified') AND urgency = 'critical'");
$organ_offers     = (int)db_val("SELECT COUNT(*) FROM organ_offers WHERE response = 'pending'");
$to_verify        = (int)db_val("SELECT COUNT(*) FROM waiting_list WHERE verification_status = 'pending'");
$new_facilities   = (int)db_val("SELECT COUNT(*) FROM facilities WHERE verification_status = 'pending'");

// Stock per blood group against the national minimum.
$stock = db_all(
    "SELECT bg.label,
            COUNT(bu.id) FILTER (WHERE bu.status = 'available' AND bu.expiry_date >= CURRENT_DATE) AS units,
            COALESCE(st.min_units, 0) AS min_units
       FROM blood_groups bg
       LEFT JOIN blood_units bu ON bu.blood_group_id = bg.id
       LEFT JOIN stock_thresholds st ON st.blood_group_id = bg.id AND st.facility_id IS NULL
   GROUP BY bg.id, bg.label, st.min_units
   ORDER BY bg.id"
);

layout_start($user, 'Dashboard');
?>
<div class="tiles">
  <div class="tile"><div class="label">Blood requests waiting</div><div class="value"><?= $waiting_requests ?></div><div class="label"><?= $critical ?> critical</div></div>
  <div class="tile"><div class="label">Organ offers awaiting reply</div><div class="value"><?= $organ_offers ?></div></div>
  <div class="tile"><div class="label">Patients to verify</div><div class="value"><?= $to_verify ?></div></div>
  <div class="tile"><div class="label">Facilities to approve</div><div class="value"><?= $new_facilities ?></div></div>
</div>

<h2>Blood stock by group</h2>
<table>
  <thead><tr><th>Group</th><th>Units available</th><th>National minimum</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($stock as $row):
      $units = (int)$row['units']; $min = (int)$row['min_units'];
      $status = $units === 0 ? 'Out' : ($units < $min ? 'Low' : 'OK'); ?>
    <tr>
      <td><span class="code"><?= e($row['label']) ?></span></td>
      <td><?= $units ?></td>
      <td><?= $min ?></td>
      <td><strong><?= $status ?></strong></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php layout_end();
