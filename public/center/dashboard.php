<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('center');
$fid  = (int)$user['facility_id'];
$near = setting_int('near_expiry_days');

$today     = (int)db_val("SELECT COUNT(*) FROM appointments a JOIN center_slots s ON s.id = a.slot_id
                           WHERE a.facility_id = ? AND a.status = 'scheduled' AND s.slot_date = CURRENT_DATE", [$fid]);
$upcoming  = (int)db_val("SELECT COUNT(*) FROM appointments a JOIN center_slots s ON s.id = a.slot_id
                           WHERE a.facility_id = ? AND a.status = 'scheduled' AND s.slot_date >= CURRENT_DATE", [$fid]);
$testing   = (int)db_val("SELECT COUNT(*) FROM blood_units WHERE facility_id = ? AND status = 'testing'", [$fid]);
$available = (int)db_val("SELECT COUNT(*) FROM blood_units WHERE facility_id = ? AND status = 'available' AND expiry_date >= CURRENT_DATE", [$fid]);

$stock = db_all(
    "SELECT bg.label,
            COUNT(bu.id) FILTER (WHERE bu.status = 'available' AND bu.expiry_date >= CURRENT_DATE) AS units,
            COUNT(bu.id) FILTER (WHERE bu.status = 'available' AND bu.expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + ?::int) AS soon,
            COUNT(bu.id) FILTER (WHERE bu.status = 'testing') AS testing
       FROM blood_groups bg LEFT JOIN blood_units bu ON bu.blood_group_id = bg.id AND bu.facility_id = ?
      GROUP BY bg.id, bg.label ORDER BY bg.label",
    [$near, $fid]
);
$slots = db_all(
    "SELECT s.slot_date, s.capacity,
            (SELECT COUNT(*) FROM appointments a WHERE a.slot_id = s.id AND a.status IN ('scheduled','completed','deferred')) AS booked
       FROM center_slots s WHERE s.facility_id = ? AND s.slot_date >= CURRENT_DATE
      ORDER BY s.slot_date LIMIT 7", [$fid]
);

layout_start($user, 'Dashboard');
?>
<p>Welcome, <?= e(person_name($user['title'], $user['full_name'])) ?> (<?= e($user['facility_name']) ?>).</p>
<div class="tiles">
  <div class="tile"><div class="label">Donors booked today</div><div class="value"><?= $today ?></div><div class="label"><a href="/center/appointments.php">Open appointments</a></div></div>
  <div class="tile"><div class="label">Booked from today on</div><div class="value"><?= $upcoming ?></div></div>
  <div class="tile"><div class="label">Units awaiting tests</div><div class="value"><?= $testing ?></div><div class="label"><a href="/center/units.php">Open blood units</a></div></div>
  <div class="tile"><div class="label">Units ready to give</div><div class="value"><?= $available ?></div></div>
</div>

<h2>Your stock</h2>
<table>
  <thead><tr><th>Group</th><th>Ready</th><th>Expiring within <?= $near ?> days</th><th>In testing</th></tr></thead>
  <tbody>
  <?php foreach ($stock as $s): ?>
    <tr><td><span class="code"><?= e($s['label']) ?></span></td><td><?= (int)$s['units'] ?></td><td><?= (int)$s['soon'] ?></td><td><?= (int)$s['testing'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Next days on your calendar</h2>
<?php if (!$slots): ?>
  <p>You have no open days. <a href="/center/slots.php">Add donation days</a> so donors can book.</p>
<?php else: ?>
<table>
  <thead><tr><th>Date</th><th>Booked</th><th>Capacity</th></tr></thead>
  <tbody>
  <?php foreach ($slots as $s): ?>
    <tr><td><?= e($s['slot_date']) ?></td><td><?= (int)$s['booked'] ?></td><td><?= (int)$s['capacity'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif;
layout_end();
