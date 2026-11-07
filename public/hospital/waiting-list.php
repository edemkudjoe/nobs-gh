<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('can_transplant');

$types  = ['kidney' => 'Kidney', 'liver' => 'Liver', 'pancreas' => 'Pancreas', 'heart' => 'Heart', 'lungs' => 'Lungs'];
$groups = db_all('SELECT id, label FROM blood_groups ORDER BY id');
$errors = [];
$f = ['patient_ref' => '', 'organ_type' => '', 'blood_group_id' => '', 'urgency' => 'routine'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'register') {
        $f['patient_ref']    = strtoupper(trim((string)($_POST['patient_ref'] ?? '')));
        $f['organ_type']     = (string)($_POST['organ_type'] ?? '');
        $f['blood_group_id'] = (string)($_POST['blood_group_id'] ?? '');
        $f['urgency']        = (string)($_POST['urgency'] ?? '');
        if (!preg_match('/^[A-Z0-9-]{3,20}$/', $f['patient_ref'])) {
            $errors['patient_ref'] = 'Use a patient code of 3 to 20 letters, numbers or dashes. Never a name.';
        } elseif (db_val('SELECT 1 FROM waiting_list WHERE patient_ref = ?', [$f['patient_ref']])) {
            $errors['patient_ref'] = 'That patient code is already on the national waiting list.';
        }
        if (!isset($types[$f['organ_type']])) { $errors['organ_type'] = 'Choose the organ needed.'; }
        if (!in_array((int)$f['blood_group_id'], array_column($groups, 'id'), true)) { $errors['blood_group_id'] = 'Choose the patient\'s blood group.'; }
        if (!in_array($f['urgency'], ['routine', 'urgent', 'critical'], true)) { $errors['urgency'] = 'Choose the urgency.'; }
        if (!$errors) {
            $id = db_transaction(function () use ($user, $f) {
                $id = db_val("INSERT INTO waiting_list (patient_ref, transplant_center_id, organ_type, blood_group_id, urgency)
                              VALUES (?, ?, ?, ?, ?) RETURNING id", [$f['patient_ref'], $user['facility_id'], $f['organ_type'], (int)$f['blood_group_id'], $f['urgency']]);
                db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'patient_registered', 'waiting_list', ?, ?)",
                        [$user['id'], $id, $f['patient_ref'] . ', ' . $f['organ_type']]);
                return $id;
            });
            flash_set($f['patient_ref'] . ' was added. The national office will verify the registration before the patient can be matched.');
            redirect('/hospital/waiting-list.php');
        }
    } elseif ($action === 'remove') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            flash_set('Give a reason of at least 5 characters to remove a patient.', 'error');
        } else {
            // Only this hospital's own active patients, and never one with an offer in progress.
            $n = db_exec(
                "UPDATE waiting_list SET status = 'removed', removal_reason = ?
                  WHERE id = ? AND transplant_center_id = ? AND status = 'active'
                    AND NOT EXISTS (SELECT 1 FROM organ_offers o WHERE o.waiting_list_id = waiting_list.id AND o.response = 'pending')",
                [mb_substr($reason, 0, 200), (int)($_POST['patient_id'] ?? 0), $user['facility_id']]);
            if ($n === 1) {
                db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'patient_removed', 'waiting_list', ?, ?)",
                        [$user['id'], (int)$_POST['patient_id'], $reason]);
            }
            flash_set($n === 1 ? 'Patient removed from the list.' : 'That patient cannot be removed right now (an offer may be waiting for your reply).', $n === 1 ? 'ok' : 'error');
        }
        redirect('/hospital/waiting-list.php');
    }
}

$patients = db_all(
    "SELECT w.id, w.patient_ref, w.organ_type, w.urgency, w.registered_at, w.verification_status, w.status, w.removal_reason, bg.label
       FROM waiting_list w JOIN blood_groups bg ON bg.id = w.blood_group_id
      WHERE w.transplant_center_id = ? ORDER BY (w.status = 'active') DESC, w.registered_at DESC LIMIT 100", [$user['facility_id']]);

layout_start($user, 'Waiting list');
?>
<h2>Register a patient</h2>
<form method="post" novalidate>
  <?= csrf_field() ?><input type="hidden" name="action" value="register">
  <div class="field"><label for="patient_ref">Patient code</label><span class="hint">A code from your records. Never enter a name.</span>
    <input id="patient_ref" name="patient_ref" type="text" maxlength="20" required value="<?= e($f['patient_ref']) ?>"><?php field_error($errors, 'patient_ref'); ?></div>
  <div class="field"><label for="organ_type">Organ needed</label>
    <select id="organ_type" name="organ_type" required><option value="">Choose…</option>
      <?php foreach ($types as $v => $l): ?><option value="<?= $v ?>"<?= $v === $f['organ_type'] ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><?php field_error($errors, 'organ_type'); ?></div>
  <div class="field"><label for="blood_group_id">Blood group</label>
    <select id="blood_group_id" name="blood_group_id" required><option value="">Choose…</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"<?= (string)$g['id'] === $f['blood_group_id'] ? ' selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?></select><?php field_error($errors, 'blood_group_id'); ?></div>
  <div class="field"><label for="urgency">Urgency</label>
    <select id="urgency" name="urgency"><?php foreach (['routine' => 'Routine', 'urgent' => 'Urgent', 'critical' => 'Critical'] as $v => $l): ?>
      <option value="<?= $v ?>"<?= $v === $f['urgency'] ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><?php field_error($errors, 'urgency'); ?></div>
  <button class="btn" type="submit">Add to waiting list</button>
</form>

<h2>Your patients</h2>
<?php if (!$patients): ?>
  <p>No patients registered yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Patient</th><th>Organ</th><th>Group</th><th>Urgency</th><th>Registered</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($patients as $p): ?>
    <tr>
      <td><span class="code"><?= e($p['patient_ref']) ?></span></td>
      <td><?= e(ucfirst($p['organ_type'])) ?></td>
      <td><span class="code"><?= e($p['label']) ?></span></td>
      <td><?= e(ucfirst($p['urgency'])) ?></td>
      <td><?= e(substr($p['registered_at'], 0, 10)) ?></td>
      <td><?= $p['status'] === 'active' ? ($p['verification_status'] === 'verified' ? 'On the list' : 'Waiting for verification') : e(ucfirst($p['status'])) . ($p['removal_reason'] ? '<br><span class="hint">' . e($p['removal_reason']) . '</span>' : '') ?></td>
      <td><?php if ($p['status'] === 'active'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="patient_id" value="<?= (int)$p['id'] ?>">
          <input type="text" name="reason" maxlength="200" placeholder="Reason to remove" aria-label="Reason to remove <?= e($p['patient_ref']) ?>">
          <button class="btn btn-danger btn-small" type="submit">Remove</button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
