<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

/** name => [label, hint, min, max, whole number?] */
$defs = [
    'Blood' => [
        'shelf_life_days'      => ['Shelf life of a blood unit (days)', 'Used to set the expiry date when a unit is collected.', 1, 60, true],
        'min_days_routine'     => ['Routine requests skip units with fewer days left than', '', 0, 30, true],
        'near_expiry_days'     => ['"Expiring soon" warning (days)', '', 1, 30, true],
        'offer_hours_urgent'   => ['Hours to answer an urgent or critical offer', '', 1, 72, true],
        'offer_hours_routine'  => ['Hours to answer a routine offer', '', 1, 168, true],
        'stale_request_days'   => ['Cancel unanswered requests after (days)', '', 1, 60, true],
    ],
    'Organs' => [
        'same_region_hours'       => ['Travel time inside one region (hours)', '', 0.1, 10, false],
        'organ_viable_kidney'     => ['Kidney viable for (hours)', '', 1, 72, true],
        'organ_viable_liver'      => ['Liver viable for (hours)', '', 1, 72, true],
        'organ_viable_pancreas'   => ['Pancreas viable for (hours)', '', 1, 72, true],
        'organ_viable_heart'      => ['Heart viable for (hours)', '', 1, 72, true],
        'organ_viable_lungs'      => ['Lungs viable for (hours)', '', 1, 72, true],
        'organ_response_fraction' => ['Share of the viable window a hospital has to answer', 'For example 0.125 means one eighth.', 0.01, 0.5, false],
    ],
    'Donors' => [
        'min_age'             => ['Minimum donor age', '', 16, 30, true],
        'max_age'             => ['Maximum donor age', '', 40, 80, true],
        'min_weight_kg'       => ['Minimum weight (kg)', '', 30, 100, true],
        'donation_gap_months' => ['Months between donations', '', 1, 12, true],
        'booking_window_days' => ['How far ahead donors can book (days)', '', 1, 180, true],
    ],
];

$groups = db_all(
    "SELECT bg.id, bg.label, COALESCE(t.min_units, 0) AS min_units
       FROM blood_groups bg LEFT JOIN stock_thresholds t ON t.blood_group_id = bg.id AND t.facility_id IS NULL
      ORDER BY bg.label"
);

$errors = [];
$input  = [];
foreach ($defs as $list) {
    foreach ($list as $name => $d) {
        $input[$name] = setting($name, '');
    }
}
$minimums = [];
foreach ($groups as $g) {
    $minimums[$g['id']] = (string)$g['min_units'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($defs as $list) {
        foreach ($list as $name => [$label, $hint, $min, $max, $whole]) {
            $v = trim((string)($_POST['s'][$name] ?? ''));
            $input[$name] = $v;
            if (!is_numeric($v) || (float)$v < $min || (float)$v > $max || ($whole && (string)(int)$v !== $v)) {
                $errors[$name] = 'Enter ' . ($whole ? 'a whole number' : 'a number') . " from $min to $max.";
            }
        }
    }
    foreach ($groups as $g) {
        $v = trim((string)($_POST['m'][$g['id']] ?? ''));
        $minimums[$g['id']] = $v;
        if ($v === '' || !ctype_digit($v) || (int)$v > 100000) {
            $errors['m' . $g['id']] = 'Enter a whole number, 0 or more.';
        }
    }
    if ((int)$input['min_age'] >= (int)$input['max_age'] && !isset($errors['min_age'], $errors['max_age'])) {
        $errors['max_age'] = 'The maximum age must be higher than the minimum age.';
    }
    if (!$errors) {
        db_transaction(function () use ($input, $minimums, $groups, $user) {
            $changed = [];
            foreach ($input as $name => $v) {
                if (setting($name, '') !== $v) {
                    $changed[] = "$name: " . setting($name, '') . ' to ' . $v;
                    setting_set($name, $v);
                }
            }
            foreach ($groups as $g) {
                if ((string)$g['min_units'] !== $minimums[$g['id']]) {
                    $changed[] = 'minimum ' . $g['label'] . ': ' . $g['min_units'] . ' to ' . $minimums[$g['id']];
                    db_exec('INSERT INTO stock_thresholds (facility_id, blood_group_id, min_units) VALUES (NULL, ?, ?)
                             ON CONFLICT (facility_id, blood_group_id) DO UPDATE SET min_units = EXCLUDED.min_units',
                            [$g['id'], (int)$minimums[$g['id']]]);
                }
            }
            if ($changed) {
                db_exec("INSERT INTO audit_log (user_id, action, entity, detail) VALUES (?, 'settings_changed', 'settings', ?)",
                        [$user['id'], implode('; ', $changed)]);
            }
        });
        flash_set('Settings saved.');
        redirect('/admin/settings.php');
    }
}

layout_start($user, 'Settings');
?>
<p class="hint">These numbers drive the rules across the whole system. Changes take effect straight away and are recorded in the audit log.</p>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <?php foreach ($defs as $section => $list): ?>
    <h2><?= e($section) ?></h2>
    <?php foreach ($list as $name => [$label, $hint, $min, $max, $whole]): ?>
      <div class="field"><label for="s_<?= e($name) ?>"><?= e($label) ?></label>
        <?php if ($hint): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
        <input id="s_<?= e($name) ?>" name="s[<?= e($name) ?>]" type="text" inputmode="decimal" value="<?= e($input[$name]) ?>"><?php field_error($errors, $name); ?></div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <h2>Minimum stock per blood group</h2>
  <p class="hint">A group is flagged "Low stock" on the inventory page when available units fall below its minimum.</p>
  <?php foreach ($groups as $g): ?>
    <div class="field"><label for="m_<?= (int)$g['id'] ?>"><?= e($g['label']) ?></label>
      <input id="m_<?= (int)$g['id'] ?>" name="m[<?= (int)$g['id'] ?>]" type="text" inputmode="numeric" value="<?= e($minimums[$g['id']]) ?>"><?php field_error($errors, 'm' . $g['id']); ?></div>
  <?php endforeach; ?>
  <button class="btn" type="submit">Save settings</button>
</form>
<?php layout_end();
