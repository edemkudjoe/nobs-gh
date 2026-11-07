<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
$user = require_capability('center');

// A filter from the address bar, checked against a fixed list before it is used.
$allowed = ['testing', 'available', 'reserved', 'allocated', 'expired', 'discarded'];
$filter  = in_array($_GET['status'] ?? '', $allowed, true) ? $_GET['status'] : '';

// A centre sees only its own units: the facility id always comes from the signed-in user.
$sql = "SELECT bu.id, bg.label, bu.collected_date, bu.expiry_date, bu.status,
               (bu.expiry_date - CURRENT_DATE) AS days_left
          FROM blood_units bu JOIN blood_groups bg ON bg.id = bu.blood_group_id
         WHERE bu.facility_id = ?";
$params = [$user['facility_id']];
if ($filter !== '') {
    $sql .= ' AND bu.status = ?';
    $params[] = $filter;
}
$units = db_all($sql . ' ORDER BY bu.expiry_date, bu.id LIMIT 200', $params);

layout_start($user, 'Blood units');
?>
<form method="get" class="field" style="max-width:260px">
  <label for="status">Show</label>
  <select id="status" name="status" onchange="this.form.submit()">
    <option value="">All units</option>
    <?php foreach ($allowed as $s): ?>
      <option value="<?= e($s) ?>"<?= $s === $filter ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn" type="submit">Apply</button></noscript>
</form>

<?php if (!$units): ?>
  <p>No units to show.</p>
<?php else: ?>
<table>
  <thead><tr><th>Unit</th><th>Group</th><th>Collected</th><th>Expires</th><th>Days left</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($units as $u): ?>
    <tr>
      <td><span class="code">BU-<?= (int)$u['id'] ?></span></td>
      <td><span class="code"><?= e($u['label']) ?></span></td>
      <td><?= e($u['collected_date']) ?></td>
      <td><?= e($u['expiry_date']) ?></td>
      <td><?= in_array($u['status'], ['testing', 'available', 'reserved'], true) ? (int)$u['days_left'] : '' ?></td>
      <td><?= e(ucfirst($u['status'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
