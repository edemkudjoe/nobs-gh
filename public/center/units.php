<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('center');

// Lab result for a unit that is waiting in testing. Only this centre's own units.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['unit_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    if (!in_array($action, ['pass', 'fail'], true)) {
        flash_set('Unknown action.', 'error');
    } elseif ($action === 'fail' && mb_strlen($reason) < 5) {
        flash_set('Give a reason of at least 5 characters when a unit fails testing.', 'error');
    } else {
        $n = db_exec(
            "UPDATE blood_units SET status = ?, tested_at = now(), tested_by = ?, discard_reason = ?
              WHERE id = ? AND facility_id = ? AND status = 'testing'",
            [$action === 'pass' ? 'available' : 'discarded', $user['id'], $action === 'fail' ? mb_substr($reason, 0, 200) : null, $id, $user['facility_id']]);
        if ($n === 1) {
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, ?, 'blood_unit', ?, ?)",
                    [$user['id'], $action === 'pass' ? 'unit_passed' : 'unit_discarded', $id, $action === 'fail' ? $reason : null]);
        }
        flash_set($n === 1 ? ($action === 'pass' ? "BU-$id passed testing and is now available." : "BU-$id was discarded.") : 'That unit is not waiting for testing.', $n === 1 ? 'ok' : 'error');
    }
    redirect('/center/units.php');
}

// A filter from the address bar, checked against a fixed list before it is used.
$allowed = ['testing', 'available', 'reserved', 'allocated', 'expired', 'discarded'];
$filter  = in_array($_GET['status'] ?? '', $allowed, true) ? $_GET['status'] : '';

// A centre sees only its own units: the facility id always comes from the signed-in user.
$sql = "SELECT bu.id, bg.label, bu.collected_date, bu.expiry_date, bu.status, bu.discard_reason,
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
  <thead><tr><th>Unit</th><th>Group</th><th>Collected</th><th>Expires</th><th>Days left</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($units as $u): ?>
    <tr>
      <td><span class="code">BU-<?= (int)$u['id'] ?></span></td>
      <td><span class="code"><?= e($u['label']) ?></span></td>
      <td><?= e($u['collected_date']) ?></td>
      <td><?= e($u['expiry_date']) ?></td>
      <td><?= in_array($u['status'], ['testing', 'available', 'reserved'], true) ? (int)$u['days_left'] : '' ?></td>
      <td><?= e(ucfirst($u['status'])) ?><?= $u['status'] === 'discarded' && $u['discard_reason'] ? '<br><span class="hint">' . e($u['discard_reason']) . '</span>' : '' ?></td>
      <td>
        <?php if ($u['status'] === 'testing'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="unit_id" value="<?= (int)$u['id'] ?>">
          <input type="text" name="reason" maxlength="200" placeholder="Reason (if it fails)" aria-label="Reason if BU-<?= (int)$u['id'] ?> fails">
          <button class="btn btn-small" name="action" value="pass" type="submit">Passed</button>
          <button class="btn btn-danger btn-small" name="action" value="fail" type="submit">Failed</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
