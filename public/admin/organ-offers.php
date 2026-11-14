<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/organ.php';
$user = require_capability('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $chosen = (int)($_POST['waiting_id'] ?? 0);
    [$ok, $msg] = organ_offer($user, (int)($_POST['organ_id'] ?? 0), $chosen > 0 ? $chosen : null, trim((string)($_POST['reason'] ?? '')));
    flash_set($msg, $ok ? 'ok' : 'error');
    redirect('/admin/organ-offers.php');
}

$organs = db_all(
    "SELECT o.id, o.organ_type, o.retrieved_at, o.viable_until, bg.label, dh.name AS donor_hospital,
            EXTRACT(EPOCH FROM (o.viable_until - now())) / 60 AS minutes_left
       FROM organs o JOIN donor_reports dr ON dr.id = o.donor_report_id JOIN blood_groups bg ON bg.id = dr.blood_group_id
       JOIN facilities dh ON dh.id = dr.hospital_id
      WHERE o.status = 'available' AND o.viable_until > now() ORDER BY o.viable_until");
$open = db_all(
    "SELECT f.id, f.rank_position, f.respond_by, f.override_reason, f.offered_by, o.id AS organ_id, o.organ_type, w.patient_ref, c.name AS center,
            (c.id = (SELECT dr.hospital_id FROM donor_reports dr WHERE dr.id = o.donor_report_id)) AS same_hospital
       FROM organ_offers f JOIN organs o ON o.id = f.organ_id JOIN waiting_list w ON w.id = f.waiting_list_id JOIN facilities c ON c.id = w.transplant_center_id
      WHERE f.response = 'pending' ORDER BY f.respond_by");
$recent = db_all(
    "SELECT f.id, f.response, f.rank_position, f.offered_at, f.decline_reason, f.override_reason, f.offered_by, o.id AS organ_id, o.organ_type, w.patient_ref, c.name AS center,
            (c.id = (SELECT dr.hospital_id FROM donor_reports dr WHERE dr.id = o.donor_report_id)) AS same_hospital
       FROM organ_offers f JOIN organs o ON o.id = f.organ_id JOIN waiting_list w ON w.id = f.waiting_list_id JOIN facilities c ON c.id = w.transplant_center_id
      WHERE f.response <> 'pending' ORDER BY f.offered_at DESC LIMIT 15");

function hours_left(float $minutes): string
{
    $m = (int)floor($minutes);
    return intdiv($m, 60) . ' h ' . ($m % 60) . ' min';
}

layout_start($user, 'Organ offers');
?>
<h2>Organs waiting to be offered (<?= count($organs) ?>)</h2>
<p class="hint">Patients are ranked by urgency, then waiting time, then travel hours, then region. Choose someone other than the first only with a written reason. A patient at the hospital that reported the donor is marked <strong>Same hospital as donor</strong>. Choosing such a patient out of order needs a reason of at least 30 characters.</p>
<?php if (!$organs): ?>
  <p>No organs are waiting.</p>
<?php endif; ?>
<?php foreach ($organs as $o): $cands = organ_candidates((int)$o['id']); ?>
  <section style="border:1px solid var(--line);border-left:6px solid var(--green);border-radius:4px;padding:12px 16px;margin-bottom:16px">
    <strong><span class="code">OG-<?= (int)$o['id'] ?></span> <?= e(ucfirst($o['organ_type'])) ?></strong>
    · donor group <span class="code"><?= e($o['label']) ?></span>
    · reported by <?= e($o['donor_hospital']) ?>
    · <strong><?= hours_left((float)$o['minutes_left']) ?> left</strong> (usable until <?= e(substr($o['viable_until'], 0, 16)) ?>)
    <?php if (!$cands): ?>
      <p>No eligible patient is waiting for this organ.</p>
    <?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="organ_id" value="<?= (int)$o['id'] ?>">
      <table>
        <thead><tr><th></th><th>Rank</th><th>Patient</th><th>Hospital</th><th>Urgency</th><th>Waiting since</th><th>Travel</th><th>Region</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($cands, 0, 6) as $i => $c): ?>
          <tr>
            <td><input type="radio" name="waiting_id" value="<?= (int)$c['id'] ?>" id="w<?= (int)$o['id'] ?>_<?= (int)$c['id'] ?>"<?= $i === 0 ? ' checked' : '' ?> aria-label="Offer to <?= e($c['patient_ref']) ?>"></td>
            <td><strong>#<?= $i + 1 ?></strong></td>
            <td><span class="code"><?= e($c['patient_ref']) ?></span></td>
            <td><?= e($c['center']) ?><?= $c['same_hospital'] ? '<br><span class="badge badge-amber">Same hospital as donor</span>' : '' ?></td>
            <td><strong><?= e(ucfirst($c['urgency'])) ?></strong></td>
            <td><?= e(substr($c['registered_at'], 0, 10)) ?></td>
            <td><?= $c['travel_hours'] === null ? 'not recorded' : e(rtrim(rtrim(number_format((float)$c['travel_hours'], 1), '0'), '.')) . ' h' ?></td>
            <td><?= e($c['region']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="field" style="max-width:none;margin-top:8px"><label for="reason<?= (int)$o['id'] ?>">Reason (required if you choose someone other than #1; at least 30 characters for a same-hospital patient)</label>
        <input id="reason<?= (int)$o['id'] ?>" type="text" name="reason" maxlength="300"></div>
      <button class="btn" type="submit">Offer this organ</button>
    </form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<h2>Offers waiting for a reply (<?= count($open) ?>)</h2>
<?php if (!$open): ?><p>No open offers.</p><?php else: ?>
<table>
  <thead><tr><th>Organ</th><th>Patient</th><th>Hospital</th><th>Rank</th><th>Reply by</th><th>Offered by</th></tr></thead>
  <tbody>
  <?php foreach ($open as $f): ?>
    <tr><td><span class="code">OG-<?= (int)$f['organ_id'] ?></span> <?= e(ucfirst($f['organ_type'])) ?></td><td><span class="code"><?= e($f['patient_ref']) ?></span></td>
        <td><?= e($f['center']) ?><?= $f['same_hospital'] ? '<br><span class="badge badge-amber">Same hospital as donor</span>' : '' ?></td><td>#<?= (int)$f['rank_position'] ?></td><td><?= e(substr($f['respond_by'], 0, 16)) ?></td>
        <td><?= $f['offered_by'] ? 'National office' : 'System (next in line)' ?><?= $f['override_reason'] ? '<br><span class="hint">Out of order: ' . e($f['override_reason']) . '</span>' : '' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Recent results</h2>
<?php if (!$recent): ?><p>Nothing yet.</p><?php else: ?>
<table>
  <thead><tr><th>Organ</th><th>Patient</th><th>Hospital</th><th>Rank</th><th>Result</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $f): ?>
    <tr><td><span class="code">OG-<?= (int)$f['organ_id'] ?></span> <?= e(ucfirst($f['organ_type'])) ?></td><td><span class="code"><?= e($f['patient_ref']) ?></span></td>
        <td><?= e($f['center']) ?><?= $f['same_hospital'] ? '<br><span class="badge badge-amber">Same hospital as donor</span>' : '' ?></td><td>#<?= (int)$f['rank_position'] ?></td>
        <td><?= e(ucfirst(str_replace('_', ' ', $f['response']))) ?><?= $f['decline_reason'] ? '<br><span class="hint">' . e($f['decline_reason']) . '</span>' : '' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();