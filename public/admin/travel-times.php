<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

$regions = db_all('SELECT id, name FROM regions ORDER BY name');
$saved = [];
foreach (db_all('SELECT from_region_id, to_region_id, hours FROM region_travel_times') as $t) {
    $saved[$t['from_region_id'] . '-' . $t['to_region_id']] = rtrim(rtrim((string)$t['hours'], '0'), '.');
}
// every pair once, lower id first (the table stores one row per pair)
$pairs = [];
foreach ($regions as $a) {
    foreach ($regions as $b) {
        if ($a['id'] < $b['id']) {
            $pairs[] = ['key' => $a['id'] . '-' . $b['id'], 'from' => $a, 'to' => $b];
        }
    }
}

$errors = [];
$input  = $saved;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($pairs as $p) {
        $v = trim((string)($_POST['h'][$p['key']] ?? ''));
        $input[$p['key']] = $v;
        if ($v !== '' && (!is_numeric($v) || (float)$v <= 0 || (float)$v >= 100)) {
            $errors[$p['key']] = 'Hours, more than 0 and under 100.';
        }
    }
    if (!$errors) {
        $count = db_transaction(function () use ($pairs, $input) {
            $n = 0;
            foreach ($pairs as $p) {
                $v = $input[$p['key']];
                [$from, $to] = [$p['from']['id'], $p['to']['id']];
                if ($v === '') {
                    db_exec('DELETE FROM region_travel_times WHERE from_region_id = ? AND to_region_id = ?', [$from, $to]);
                } else {
                    db_exec('INSERT INTO region_travel_times (from_region_id, to_region_id, hours) VALUES (?, ?, ?)
                             ON CONFLICT (from_region_id, to_region_id) DO UPDATE SET hours = EXCLUDED.hours', [$from, $to, $v]);
                    $n++;
                }
            }
            return $n;
        });
        db_exec("INSERT INTO audit_log (user_id, action, entity, detail) VALUES (?, 'travel_times_saved', 'region_travel_times', ?)",
                [$user['id'], "$count of " . count($pairs) . ' pairs filled']);
        flash_set("Travel times saved. $count of " . count($pairs) . ' pairs are filled in.');
        redirect('/admin/travel-times.php');
    }
}
$filled = count(array_filter($input, fn($v) => $v !== ''));

layout_start($user, 'Travel times between regions');
?>
<p>Helicopter flight hours between each pair of regions,used to rank hospitals for an organ offer. One number covers both directions. A blank pair ranks last when everything else ties.</p>
<div class="tiles">
  <div class="tile"><div class="label">Pairs filled in</div><div class="value"><?= $filled ?> of <?= count($pairs) ?></div></div>
</div>
<form method="post" novalidate>
  <?= csrf_field() ?>
    <table class="no-tools">
    <thead><tr><th>From</th><th>To</th><th>Hours by air</th></tr></thead>
    <tbody>
    <?php foreach ($pairs as $p): ?>
      <tr>
        <td><?= e($p['from']['name']) ?></td>
        <td><?= e($p['to']['name']) ?></td>
        <td>
          <input type="text" inputmode="decimal" name="h[<?= e($p['key']) ?>]" value="<?= e($input[$p['key']] ?? '') ?>"
                 aria-label="Hours from <?= e($p['from']['name']) ?> to <?= e($p['to']['name']) ?>" style="max-width:110px">
          <?php field_error($errors, $p['key']); ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <button class="btn" type="submit">Save travel times</button>
</form>
<?php layout_end();
