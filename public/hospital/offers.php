<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/organ.php';
$user = require_capability('can_transplant');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $decision = (string)($_POST['decision'] ?? '');
    if (in_array($decision, ['accept', 'decline'], true)) {
        [$ok, $msg] = organ_respond($user, (int)($_POST['offer_id'] ?? 0), $decision, trim((string)($_POST['reason'] ?? '')));
    } else {
        [$ok, $msg] = [false, 'Unknown action.'];
    }
    flash_set($msg, $ok ? 'ok' : 'error');
    redirect('/hospital/offers.php');
}

// Only offers for this hospital's own patients. The donor's hospital is not shown.
$sql = "SELECT f.id, f.response, f.respond_by, f.offered_at, f.decline_reason, o.id AS organ_id, o.organ_type, o.viable_until,
               w.patient_ref, bg.label,
               EXTRACT(EPOCH FROM (f.respond_by - now())) / 60 AS minutes_left
          FROM organ_offers f JOIN organs o ON o.id = f.organ_id JOIN waiting_list w ON w.id = f.waiting_list_id
          JOIN donor_reports dr ON dr.id = o.donor_report_id JOIN blood_groups bg ON bg.id = dr.blood_group_id
         WHERE w.transplant_center_id = ? AND ";
$open    = db_all($sql . "f.response = 'pending' ORDER BY f.respond_by", [$user['facility_id']]);
$history = db_all($sql . "f.response <> 'pending' ORDER BY f.offered_at DESC LIMIT 15", [$user['facility_id']]);

layout_start($user, 'Organ offers');
?>
<h2>Waiting for your reply (<?= count($open) ?>)</h2>
<?php if (!$open): ?>
  <p>No open offers.</p>
<?php else: ?>
<table>
  <thead><tr><th>Organ</th><th>Patient</th><th>Donor group</th><th>Reply by</th><th>Answer</th></tr></thead>
  <tbody>
  <?php foreach ($open as $o):
      $m = (int)floor((float)$o['minutes_left']); ?>
    <tr>
      <td><span class="code">OG-<?= (int)$o['organ_id'] ?></span> <?= e(ucfirst($o['organ_type'])) ?></td>
      <td><span class="code"><?= e($o['patient_ref']) ?></span></td>
      <td><span class="code"><?= e($o['label']) ?></span></td>
      <td><strong><?= e(substr($o['respond_by'], 0, 16)) ?></strong><br>
          <?= $m > 0 ? 'about ' . intdiv($m, 60) . ' h ' . ($m % 60) . ' min left' : 'time is up' ?></td>
      <td>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
          <button class="btn btn-small" name="decision" value="accept" type="submit">Accept</button>
        </form>
        <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
          <input type="text" name="reason" maxlength="200" placeholder="Reason to decline" aria-label="Reason to decline OG-<?= (int)$o['organ_id'] ?>">
          <button class="btn btn-danger btn-small" name="decision" value="decline" type="submit">Decline</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Recent answers</h2>
<?php if (!$history): ?>
  <p>Nothing yet.</p>
<?php else: ?>
<table>
  <thead><tr><th>Organ</th><th>Patient</th><th>Offered</th><th>Result</th></tr></thead>
  <tbody>
  <?php foreach ($history as $o): ?>
    <tr><td><span class="code">OG-<?= (int)$o['organ_id'] ?></span> <?= e(ucfirst($o['organ_type'])) ?></td>
        <td><span class="code"><?= e($o['patient_ref']) ?></span></td>
        <td><?= e(substr($o['offered_at'], 0, 16)) ?></td>
        <td><?= e(ucfirst(str_replace('_', ' ', $o['response']))) ?><?= $o['decline_reason'] ? '<br><span class="hint">' . e($o['decline_reason']) . '</span>' : '' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php layout_end();
