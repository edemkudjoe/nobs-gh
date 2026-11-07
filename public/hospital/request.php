<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
$user = require_capability('can_request_blood');

$errors = [];
$form   = ['blood_group_id' => '', 'quantity' => '1', 'urgency' => 'routine', 'accept_compatible' => '', 'patient_ref' => ''];
$groups = db_all('SELECT id, label FROM blood_groups ORDER BY id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form['blood_group_id']    = (string)($_POST['blood_group_id'] ?? '');
    $form['quantity']          = (string)($_POST['quantity'] ?? '');
    $form['urgency']           = (string)($_POST['urgency'] ?? '');
    $form['accept_compatible'] = isset($_POST['accept_compatible']) ? '1' : '';
    $form['patient_ref']       = strtoupper(trim((string)($_POST['patient_ref'] ?? '')));

    // Check every field on the server. Never trust the browser.
    if (!in_array((int)$form['blood_group_id'], array_column($groups, 'id'), true)) {
        $errors['blood_group_id'] = 'Choose a blood group.';
    }
    $qty = filter_var($form['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if ($qty === false) {
        $errors['quantity'] = 'Enter a whole number from 1 to 20.';
    }
    if (!in_array($form['urgency'], ['routine', 'urgent', 'critical'], true)) {
        $errors['urgency'] = 'Choose how urgent the request is.';
    }
    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $form['patient_ref'])) {
        $errors['patient_ref'] = 'Use a patient code of 3 to 20 letters, numbers or dashes. Never a name.';
    }

    if (!$errors) {
        $id = db_transaction(function () use ($user, $form, $qty) {
            $id = db_val(
                "INSERT INTO requests (hospital_id, blood_group_id, quantity, urgency, accept_compatible, patient_ref, submitted_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [$user['facility_id'], (int)$form['blood_group_id'], $qty, $form['urgency'],
                 $form['accept_compatible'] === '1' ? 't' : 'f', $form['patient_ref'], $user['id']]
            );
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'request_submitted', 'request', ?, ?)",
                    [$user['id'], $id, "$qty unit(s), {$form['urgency']}"]);
            return $id;
        });
        flash_set('Request BR-' . $id . ' was sent to the national blood bank.');
        redirect('/hospital/request.php');          // stops a refresh from sending it twice
    }
}

// This hospital's most recent requests (its own id comes from the signed-in user).
$recent = db_all(
    "SELECT r.id, bg.label, r.quantity, r.quantity_fulfilled, r.urgency, r.status, r.created_at
       FROM requests r JOIN blood_groups bg ON bg.id = r.blood_group_id
      WHERE r.hospital_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 10",
    [$user['facility_id']]
);

layout_start($user, 'Request blood');
?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="blood_group_id">Blood group needed</label>
    <select id="blood_group_id" name="blood_group_id" required>
      <option value="">Choose…</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?= (int)$g['id'] ?>"<?= (string)$g['id'] === $form['blood_group_id'] ? ' selected' : '' ?>><?= e($g['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (isset($errors['blood_group_id'])): ?><div class="alert"><?= e($errors['blood_group_id']) ?></div><?php endif; ?>
  </div>
  <div class="field">
    <label for="quantity">Units</label>
    <input id="quantity" name="quantity" type="number" min="1" max="20" value="<?= e($form['quantity']) ?>" required>
    <?php if (isset($errors['quantity'])): ?><div class="alert"><?= e($errors['quantity']) ?></div><?php endif; ?>
  </div>
  <div class="field">
    <label for="urgency">Urgency</label>
    <select id="urgency" name="urgency">
      <?php foreach (['routine' => 'Routine', 'urgent' => 'Urgent', 'critical' => 'Critical'] as $v => $l): ?>
        <option value="<?= $v ?>"<?= $v === $form['urgency'] ? ' selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (isset($errors['urgency'])): ?><div class="alert"><?= e($errors['urgency']) ?></div><?php endif; ?>
  </div>
  <div class="field">
    <label for="patient_ref">Patient code</label>
    <span class="hint">A code from your own records. Never enter a patient's name.</span>
    <input id="patient_ref" name="patient_ref" type="text" maxlength="20" value="<?= e($form['patient_ref']) ?>" required>
    <?php if (isset($errors['patient_ref'])): ?><div class="alert"><?= e($errors['patient_ref']) ?></div><?php endif; ?>
  </div>
  <div class="field">
    <label><input type="checkbox" name="accept_compatible" value="1"<?= $form['accept_compatible'] ? ' checked' : '' ?>> Accept a compatible group if the exact one is not available</label>
  </div>
  <button class="btn" type="submit">Send request</button>
</form>

<h2>Your recent requests</h2>
<?php if (!$recent): ?>
  <p>You have not sent any requests yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Request</th><th>Group</th><th>Units</th><th>Urgency</th><th>Status</th><th>Sent</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $r): ?>
    <tr>
      <td><span class="code">BR-<?= (int)$r['id'] ?></span></td>
      <td><span class="code"><?= e($r['label']) ?></span></td>
      <td><?= (int)$r['quantity_fulfilled'] ?> of <?= (int)$r['quantity'] ?></td>
      <td><?= e(ucfirst($r['urgency'])) ?></td>
      <td><?= e(ucfirst($r['status'])) ?></td>
      <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
