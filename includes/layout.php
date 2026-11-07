<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/autocheck.php';

/**
 * The menu for each role: [section => [[label, url, right-needed], ...]].
 * To add a page to the menu, add one line here. Nobody else needs to touch this.
 * right-needed is '' (everyone with the role) or a capability name from require_capability().
 */
function nav_items(array $user): array
{
    $nav = match ($user['role']) {
        'admin' => [
            'Overview' => [['Dashboard', '/admin/dashboard.php', '']],
            'Blood'    => [['Blood requests', '/admin/blood-requests.php', ''], ['Blood offers', '/admin/blood-offers.php', ''], ['Inventory', '/admin/inventory.php', '']],
            'Organs'   => [['Waiting list', '/admin/waiting-list.php', ''], ['Organ offers', '/admin/organ-offers.php', '']],
            'Admin'    => [['Facilities', '/admin/facilities.php', ''], ['Settings', '/admin/settings.php', ''], ['Audit log', '/admin/audit.php', '']],
        ],
        'center' => [
            'Overview'  => [['Dashboard', '/center/dashboard.php', '']],
            'Donations' => [['Appointments', '/center/appointments.php', ''], ['Slots', '/center/slots.php', '']],
            'Blood'     => [['Blood units', '/center/units.php', '']],
        ],
        'hospital' => [
            'Overview' => [['Dashboard', '/hospital/dashboard.php', '']],
            'Blood'    => [['Blood availability', '/hospital/availability.php', ''], ['Request blood', '/hospital/request.php', 'can_request_blood'], ['My requests', '/hospital/requests.php', 'can_request_blood']],
            'Organs'   => [['Waiting list', '/hospital/waiting-list.php', 'can_transplant'], ['Report a donor', '/hospital/report-donor.php', 'can_report_donors'], ['Organ offers', '/hospital/offers.php', 'can_transplant']],
        ],
        default => [
            'Overview' => [['Dashboard', '/donor/dashboard.php', '']],
            'Donating' => [['Check eligibility', '/donor/eligibility.php', ''], ['Book a donation', '/donor/book.php', ''], ['My bookings', '/donor/bookings.php', '']],
        ],
    };
    if ($user['is_facility_admin'] && in_array($user['role'], ['center', 'hospital'], true)) {
        $nav['Facility'] = [['Staff accounts', '/staff-accounts.php', '']];
    }
    // Hide menu items the user's facility has no right to use.
    foreach ($nav as $section => $items) {
        $nav[$section] = array_values(array_filter($items, fn($i) => $i[2] === '' || !empty($user[$i[2]])));
        if (!$nav[$section]) {
            unset($nav[$section]);
        }
    }
    return $nav;
}

/** Print the top of every signed-in page. Pair it with layout_end(). */
function layout_start(array $user, string $title): void
{
        autocheck();   // overdue offers, expired blood and organs, missed bookings
    $here  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $who   = match ($user['role']) {
        'admin'  => 'Admin, head office',
        'donor'  => 'Donor account',
        default  => ucfirst($user['role']) . ' staff, ' . $user['facility_name'],
    };
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | National Organ &amp; Blood Bank</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <div class="flag"><i></i><i></i><i></i></div>
  <header class="top">
    <div class="brand">National Organ &amp; Blood Bank System</div>
    <div class="who">
      <span><?= e($user['full_name']) ?> · <?= e($who) ?></span>
      <a class="btn btn-quiet" href="/logout.php">Sign out</a>
    </div>
  </header>
  <div class="shell">
    <nav class="side" aria-label="Main menu">
      <?php foreach (nav_items($user) as $section => $items): ?>
        <div class="side-title"><?= e($section) ?></div>
        <?php foreach ($items as [$label, $url]): ?>
          <a href="<?= e($url) ?>"<?= $url === $here ? ' class="on" aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <main class="main">
      <h1><?= e($title) ?></h1>
<?php
}

/** Print the bottom of every signed-in page. */
function layout_end(): void
{
    ?>
      <p class="fine">Student project prototype with sample data. Not an official NBS, MoH or GHS system.</p>
    </main>
  </div>
</body>
</html>
<?php
}
