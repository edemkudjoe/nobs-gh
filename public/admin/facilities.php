<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Each button maps to one fixed change. Nothing from the form goes into the SQL text.
    $changes = [
        'approve'    => "verification_status = 'approved'",
        'reject'     => "verification_status = 'rejected'",
        'deactivate' => 'active = FALSE',
        'activate'   => 'active = TRUE',
    ];
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['facility_id'] ?? 0);
    if (isset($changes[$action]) && db_exec("UPDATE facilities SET {$changes[$action]} WHERE id = ?", [$id]) === 1) {
        if ($action === 'deactivate') {
            db_exec('DELETE FROM sessions WHERE user_id IN (SELECT id FROM users WHERE facility_id = ?)', [$id]);
        }
        db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id) VALUES (?, ?, 'facility', ?)", [$user['id'], 'facility_' . $action, $id]);
        flash_set('Facility updated.');
    } else {
        flash_set('Nothing was changed.', 'error');
    }
    redirect('/admin/facilities.php');
}

$facilities = db_all(
    "SELECT f.id, f.name, f.type, f.license_no, f.verification_status, f.active,
            f.can_request_blood, f.can_report_donors, f.can_transplant, r.name AS region,
            (SELECT COUNT(*) FROM users u WHERE u.facility_id = f.id) AS accounts
       FROM facilities f JOIN regions r ON r.id = f.region_id
   ORDER BY f.type, f.name"
);

layout_start($user, 'Facilities');
?>
<p><a class="btn" href="/admin/facility-new.php">Create facility account</a></p>
<table>
  <thead><tr><th>Facility</th><th>Type</th><th>Region</th><th>Rights</th><th>Accounts</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($facilities as $f):
      $rights = array_filter([$f['can_request_blood'] ? 'Request blood' : '', $f['can_report_donors'] ? 'Report donors' : '', $f['can_transplant'] ? 'Transplant' : '']); ?>
    <tr>
      <td><?= e($f['name']) ?><br><span class="code"><?= e($f['license_no']) ?></span></td>
      <td><?= $f['type'] === 'hospital' ? 'Hospital' : 'Blood bank' ?></td>
      <td><?= e($f['region']) ?></td>
      <td><?= $rights ? e(implode(', ', $rights)) : '—' ?></td>
      <td><?= (int)$f['accounts'] ?></td>
      <td><?= e(ucfirst($f['verification_status'])) ?><?= $f['active'] ? '' : ', deactivated' ?></td>
      <td class="row-actions">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="facility_id" value="<?= (int)$f['id'] ?>">
          <?php if ($f['verification_status'] === 'pending'): ?>
            <button class="btn btn-quiet btn-small" name="action" value="approve" type="submit">Approve</button>
            <button class="btn btn-danger btn-small" name="action" value="reject" type="submit">Reject</button>
          <?php elseif ($f['active']): ?>
            <button class="btn btn-danger btn-small" name="action" value="deactivate" type="submit">Deactivate</button>
          <?php else: ?>
            <button class="btn btn-quiet btn-small" name="action" value="activate" type="submit">Reactivate</button>
          <?php endif; ?>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php layout_end();
