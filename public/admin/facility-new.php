<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

/**
 * Registration ID such as H-GAR-0001: H hospital or B blood bank, a region code, then a running number
 * for that type and region. Call it inside a transaction.
 */
function next_registration_id(string $type, string $regionName): string
{
    $codes = ['Greater Accra' => 'GAR', 'Ashanti' => 'ASH', 'Eastern' => 'EAS', 'Northern' => 'NOR', 'Volta' => 'VOL',
              'Western' => 'WES', 'Western North' => 'WNR', 'Central' => 'CEN', 'Oti' => 'OTI', 'Savannah' => 'SAV',
              'North East' => 'NEA', 'Upper East' => 'UEA', 'Upper West' => 'UWE', 'Bono' => 'BON', 'Bono East' => 'BOE', 'Ahafo' => 'AHA'];
    $code   = $codes[$regionName] ?? strtoupper(substr((string)preg_replace('/[^A-Za-z]/', '', $regionName), 0, 3));
    $prefix = ($type === 'hospital' ? 'H' : 'B') . '-' . $code . '-';
    db_val('SELECT pg_advisory_xact_lock(7301)');   // two admins saving at once must not get the same number
    $last = (int)db_val('SELECT MAX(CAST(right(license_no, 4) AS INT)) FROM facilities WHERE license_no ~ ?::text', ['^' . $prefix . '[0-9]{4}$']);
    return sprintf('%s%04d', $prefix, $last + 1);
}

$regions = db_all('SELECT id, name FROM regions ORDER BY name');
$errors  = [];
$f = ['name' => '', 'type' => 'hospital', 'ownership' => 'public', 'region_id' => '', 'license_no' => '', 'admin_name' => '', 'admin_email' => '',
      'can_request_blood' => '', 'can_report_donors' => '', 'can_transplant' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'type', 'ownership', 'region_id', 'license_no', 'admin_name', 'admin_email'] as $k) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $f['admin_email'] = mb_strtolower($f['admin_email']);
    foreach (['can_request_blood', 'can_report_donors', 'can_transplant'] as $k) {
        // Only hospitals can hold these rights. A blood bank never gets them.
        $f[$k] = ($f['type'] === 'hospital' && isset($_POST[$k])) ? '1' : '';
    }

    if (mb_strlen($f['name']) < 3 || mb_strlen($f['name']) > 120) { $errors['name'] = 'Enter the facility name.'; }
    if (!in_array($f['type'], ['hospital', 'blood_bank'], true)) { $errors['type'] = 'Choose the facility type.'; }
    if (!in_array($f['ownership'], ['public', 'private'], true)) { $errors['ownership'] = 'Choose public or private.'; }
    if (!in_array((int)$f['region_id'], array_column($regions, 'id'), true)) { $errors['region_id'] = 'Choose a region.'; }
    if (strlen($f['license_no']) > 40) {
        $errors['license_no'] = 'The licence number is too long.';
    }
