<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/organ.php';
$user = require_capability('can_report_donors');

$types  = ['kidney' => 'Kidney', 'liver' => 'Liver', 'pancreas' => 'Pancreas', 'heart' => 'Heart', 'lungs' => 'Lungs'];
$groups = db_all('SELECT id, label FROM blood_groups ORDER BY id');
$errors = [];
$f = ['blood_group_id' => '', 'consent_reference' => '', 'organs' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f['blood_group_id']    = (string)($_POST['blood_group_id'] ?? '');
    $f['consent_reference'] = trim((string)($_POST['consent_reference'] ?? ''));
    $f['organs']            = array_values(array_intersect(array_keys($types), (array)($_POST['organs'] ?? [])));

    if (!in_array((int)$f['blood_group_id'], array_column($groups, 'id'), true)) {
        $errors['blood_group_id'] = 'Choose the donor\'s blood group.';
    }
    if (empty($_POST['consent'])) {
        $errors['consent'] = 'You must confirm that consent was given before an organ can be reported.';
    }
    if (mb_strlen($f['consent_reference']) < 3 || mb_strlen($f['consent_reference']) > 80) {
        $errors['consent_reference'] = 'Enter the consent record reference (3 to 80 characters). Never a name.';
    }
    if (!$f['organs']) {
        $errors['organs'] = 'Choose at least one organ that was retrieved.';
    }

    if (!$errors) {
        $report = db_transaction(function () use ($user, $f) {
            // No donor name is stored, only the blood group and the consent reference.
            $rid = db_val(
                "INSERT INTO donor_reports (hospital_id, blood_group_id, declared_at, consent_confirmed, consent_reference, reported_by)
                 VALUES (?, ?, now(), TRUE, ?, ?) RETURNING id",
                [$user['facility_id'], (int)$f['blood_group_id'], $f['consent_reference'], $user['id']]);
            foreach ($f['organs'] as $type) {
                db_exec("INSERT INTO organs (donor_report_id, organ_type, retrieved_at, viable_until, status)
                         VALUES (?, ?, now(), now() + (? * INTERVAL '1 hour'), 'available')", [$rid, $type, organ_viable_hours($type)]);
            }
            db_exec("INSERT INTO audit_log (user_id, action, entity, entity_id, detail) VALUES (?, 'donor_reported', 'donor_report', ?, ?)",
                    [$user['id'], $rid, implode(', ', $f['organs'])]);
            return $rid;
        });
        flash_set('Donor report OD-' . $report . ' sent. The national office has been alerted and will match the organs.');
        redirect('/hospital/report-donor.php');
    }
}

$reports = db_all(
    "SELECT dr.id, dr.declared_at, bg.label,
            (SELECT string_agg(o.organ_type || ' (' || o.status || ')', ', ' ORDER BY o.id) FROM organs o WHERE o.donor_report_id = dr.id) AS organs
       FROM donor_reports dr JOIN blood_groups bg ON bg.id = dr.blood_group_id
      WHERE dr.hospital_id = ? ORDER BY dr.declared_at DESC LIMIT 10", [$user['facility_id']]);

layout_start($user, 'Report a donor');
?>
<p class="hint">Use this only after consent has been confirmed. Organs lose their usefulness quickly, so send the report as soon as the organs are retrieved.</p>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="field"><label for="blood_group_id">Donor blood group</label>
    <select id="blood_group_id" name="blood_group_id" required><option value="">Choose…</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"<?= (string)$g['id'] === $f['blood_group_id'] ? ' selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?>
    </select><?php field_error($errors, 'blood_group_id'); ?></div>

  <fieldset class="field" style="border:0;padding:0;margin:0 0 16px">
    <legend style="font-size:15px;font-weight:700;padding:0;margin-bottom:6px">Organs retrieved</legend>
    <?php foreach ($types as $v => $label): ?>
      <label class="check"><input type="checkbox" name="organs[]" value="<?= $v ?>"<?= in_array($v, $f['organs'], true) ? ' checked' : '' ?>>
        <span><?= e($label) ?> <span class="hint">(usable for about <?= rtrim(rtrim(number_format(organ_viable_hours($v), 1), '0'), '.') ?> hours)</span></span></label>
    <?php endforeach; ?>
    <?php field_error($errors, 'organs'); ?>
  </fieldset>

  <div class="field"><label for="consent_reference">Consent record reference</label>
    <span class="hint">The reference of the consent form in your records. Do not enter the donor's name.</span>
    <input id="consent_reference" name="consent_reference" type="text" maxlength="80" required value="<?= e($f['consent_reference']) ?>"><?php field_error($errors, 'consent_reference'); ?></div>
  <div class="field">
    <label class="check"><input type="checkbox" name="consent" value="1"<?= !empty($_POST['consent']) ? ' checked' : '' ?>><span>I confirm that consent for organ donation has been given and recorded.</span></label>
    <?php field_error($errors, 'consent'); ?></div>
  <button class="btn" type="submit">Send donor report</button>
</form>

<h2>Your recent reports</h2>
<?php if (!$reports): ?>
  <p>You have not reported any donors yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Report</th><th>Reported</th><th>Blood group</th><th>Organs</th></tr></thead>
  <tbody>
  <?php foreach ($reports as $r): ?>
    <tr><td><span class="code">OD-<?= (int)$r['id'] ?></span></td><td><?= e(substr($r['declared_at'], 0, 16)) ?></td>
        <td><span class="code"><?= e($r['label']) ?></span></td><td><?= e($r['organs']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
