<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/blood.php';
$user = require_capability('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['patient_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    if (!in_array($action, ['verify', 'reject'], true)) {
        flash_set('Unknown action.', 'error');
    } elseif ($action === 'reject' && mb_strlen($reason) < 5) {
        flash_set('Give a reason of at least 5 characters when rejecting a registration.', 'error');
    } else {
        $n = db_exec(
            "UPDATE waiting_list SET verification_status = ?, verified_by = ?, status = ?, removal_reason = ?
              WHERE id = ? AND verification_status = 'pending' AND status = 'active'",
            [$action === 'verify' ? 'verified' : 'rejected', $user['id'], $action === 'verify' ? 'active' : 'removed',
             $action === 'reject' ? mb_substr($reason, 0, 200) : null, $id]);
        if ($n === 1) {
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, ?, 'waiting_list', ?, ?)",
                    [$user['id'], 'patient_' . ($action === 'verify' ? 'verified' : 'rejected'), $id, $action === 'reject' ? $reason : null]);
        }
        flash_set($n === 1 ? ($action === 'verify' ? 'Patient verified. They can now be matched.' : 'Registration rejected.') : 'That registration is no longer waiting for verification.', $n === 1 ? 'ok' : 'error');
    }
    redirect('/admin/waiting-list.php');
}

$sql = "SELECT w.id, w.patient_ref, w.organ_type, w.urgency, w.registered_at, bg.label, f.name AS center
          FROM waiting_list w JOIN blood_groups bg ON bg.id = w.blood_group_id JOIN facilities f ON f.id = w.transplant_center_id
         WHERE w.status = 'active' AND ";
$pending  = db_all($sql . "w.verification_status = 'pending' ORDER BY w.registered_at", []);
$verified = db_all($sql . "w.verification_status = 'verified' ORDER BY w.organ_type, " . blood_urgency_rank('w') . ", w.registered_at", []);

layout_start($user, 'Waiting list');
?>
<h2>Registrations to verify (<?= count($pending) ?>)</h2>
<?php if (!$pending): ?>
  <p>Nothing waiting for verification.</p>
<?php else: ?>
<table>
  <thead><tr><th>Patient</th><th>Hospital</th><th>Organ</th><th>Group</th><th>Urgency</th><th>Registered</th><th>Decision</th></tr></thead>
  <tbody>
  <?php foreach ($pending as $p): ?>
    <tr>
      <td><span class="code"><?= e($p['patient_ref']) ?></span></td><td><?= e($p['center']) ?></td><td><?= e(ucfirst($p['organ_type'])) ?></td>
      <td><span class="code"><?= e($p['label']) ?></span></td><td><?= e(ucfirst($p['urgency'])) ?></td><td><?= e(substr($p['registered_at'], 0, 10)) ?></td>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= (int)$p['id'] ?>">
        <input type="text" name="reason" maxlength="200" placeholder="Reason (needed to reject)" aria-label="Reason to reject <?= e($p['patient_ref']) ?>">
        <button class="btn btn-small" name="action" value="verify" type="submit">Verify</button>
        <button class="btn btn-danger btn-small" name="action" value="reject" type="submit">Reject</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Verified patients on the list (<?= count($verified) ?>)</h2>
<?php if (!$verified): ?>
  <p>No verified patients yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Organ</th><th>Patient</th><th>Hospital</th><th>Group</th><th>Urgency</th><th>Waiting since</th></tr></thead>
  <tbody>
  <?php foreach ($verified as $p): ?>
    <tr><td><?= e(ucfirst($p['organ_type'])) ?></td><td><span class="code"><?= e($p['patient_ref']) ?></span></td><td><?= e($p['center']) ?></td>
        <td><span class="code"><?= e($p['label']) ?></span></td><td><?= e(ucfirst($p['urgency'])) ?></td><td><?= e(substr($p['registered_at'], 0, 10)) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
