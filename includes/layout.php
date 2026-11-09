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
            'Admin'    => [['Facilities', '/admin/facilities.php', ''], ['Travel times', '/admin/travel-times.php', ''], ['Settings', '/admin/settings.php', ''], ['Audit log', '/admin/audit.php', '']],
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

/** Print the error under a form field, if that field has one. */
function field_error(array $errors, string $key): void
{
    if (isset($errors[$key])) {
        echo '<div class="alert">' . e($errors[$key]) . '</div>';
    }
}

/** Remember a one-time message for the next page the user sees (after a form is saved). */
function flash_set(string $message, string $type = 'ok'): void
{
    start_session();
    $_SESSION['flash'] = [$type, $message];
}

/** Print and clear the one-time message, if there is one. */
function flash_show(): void
{
    start_session();
    if (!empty($_SESSION['flash'])) {
        [$type, $message] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<p class="' . ($type === 'error' ? 'alert' : 'notice') . '" role="status">' . e($message) . '</p>';
    }
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
    flash_show();
}

/** Print the bottom of every signed-in page. */
function layout_end(): void
{
    ?>
      <p class="fine">&copy; 2026, Developed by Group 2, IT8</p>
    </main>
  </div>
</body>
</html>
<?php
}


/** Small inline icons used on the public pages. Names: shield calendar bell flask box clipboard people drop list lock history arrow eye. */
function icon(string $name, int $size = 26): string
{
    static $paths = [
        'shield'    => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/>',
        'calendar'  => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'bell'      => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4l2-2zM10 21h4"/>',
        'flask'     => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3M7.5 15h9"/>',
        'box'       => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3zM4 7.5l8 4.5 8-4.5M12 12v9"/>',
        'clipboard' => '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h4"/>',
        'people'    => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.5 3-6 6-6s6 2.5 6 6M16 5a3 3 0 0 1 0 6M18 14c2 .8 3 3 3 6"/>',
        'drop'      => '<path d="M12 3c3 4 6 7 6 11a6 6 0 0 1-12 0c0-4 3-7 6-11z"/>',
        'list'      => '<path d="M5 6h14M5 12h11M5 18h7"/>',
        'lock'      => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'history'   => '<path d="M4 12a8 8 0 1 0 3-6.2M4 4v4h4M12 8v4l3 2"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'eye'       => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** The three partner logos. Set the environment variable SHOW_LOGOS=0 to hide them (for example until they are approved). */
function site_logos(): string
{
    if (getenv('SHOW_LOGOS') === '0') {
        return '';
    }
    return '<span class="logos">'
         . '<img src="/assets/img/logo-nbs.png" alt="National Blood Service Ghana logo" width="44" height="44">'
         . '<img src="/assets/img/logo-moh.png" alt="Ministry of Health, Republic of Ghana seal" width="44" height="44">'
         . '<img src="/assets/img/logo-ghs.png" alt="Ghana Health Service logo" width="36" height="48">'
         . '</span><span class="logos-sep" aria-hidden="true"></span>';
}

/** Everything up to and including the coloured flag stripe. Shared by every page that is not inside a portal. */
function site_head(string $title): void
{
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
<body class="site">
  <div class="flag"><i></i><i></i><i></i></div>
<?php
}

/** The footer text shared by the public pages. */
function site_footer(): void
{
    ?>
  <footer class="site-foot">
    <div class="wrap">
      <div class="foot-cols">
        <div class="foot-about">
          <?php if (getenv('SHOW_LOGOS') !== '0'): ?><span class="logos"><img src="/assets/img/logo-nbs.png" alt="National Blood Service Ghana logo" width="44" height="44"><img src="/assets/img/logo-moh.png" alt="Ministry of Health, Republic of Ghana seal" width="44" height="44"><img src="/assets/img/logo-ghs.png" alt="Ghana Health Service logo" width="36" height="48"></span><?php endif; ?>
          <p>A clearer connection between donors, blood centers, hospitals and the next request.</p>
        </div>
        <div><h2>Product</h2><a href="/how-it-works.php">How it works</a><a href="/features.php">Features</a></div>
        <div><h2>Company</h2><a href="/about.php">About</a></div>
        <div><h2>Access</h2><a href="/login.php">Sign in</a><a href="/register.php">Register as a donor</a></div>
      </div>
      <div class="foot-base">
        <span>&copy; 2026, Developed and powered by Group 2, IT8</span>
      </div>
    </div>
  </footer>
</body>
</html>
<?php
}

/** Top of the public information pages and the login page. Pair it with public_end(). */
function public_start(string $title, string $current = ''): void
{
    $links = ['/' => 'Home', '/about.php' => 'About', '/how-it-works.php' => 'How it works', '/features.php' => 'Features'];
    site_head($title);
    ?>
  <header class="site-top">
    <div class="site-id"><?= site_logos() ?><span class="site-name">National Organ &amp; Blood Bank System</span></div>
    <nav class="site-nav" aria-label="Main">
      <?php foreach ($links as $url => $label): ?>
        <a href="<?= e($url) ?>"<?= $url === $current ? ' class="on" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn-line" href="/login.php"<?= $current === '/login.php' ? ' aria-current="page"' : '' ?>>Log in</a>
      <a class="btn" href="/register.php">Register as a donor</a>
    </nav>
  </header>
  <main class="site-main">
<?php
}

function public_end(): void
{
    ?>
  </main>
<?php
    site_footer();
}

/**
 * Top of a simple form page for people who are not signed in (register, set password).
 * Pair it with guest_end(). The optional intro is the sentence under the heading.
 */
function guest_start(string $title, string $intro = ''): void
{
    site_head($title);
    $signedIn = current_user() !== null;
    ?>
  <header class="site-top slim">
    <div class="site-id"><?= site_logos() ?><span class="site-name">National Organ &amp; Blood Bank System</span></div>
    <?php if ($signedIn): ?>
      <a class="plain" href="/logout.php">Sign out</a>
    <?php else: ?>
      <a class="plain" href="/login.php">Sign in</a>
    <?php endif; ?>
  </header>
  <main class="form-page">
    <div class="form-wrap">
      <h1><?= e($title) ?></h1>
      <?php if ($intro !== ''): ?><p class="intro"><?= e($intro) ?></p><?php endif; ?>
<?php
}

function guest_end(): void
{
    ?>
      <p class="fine">&copy; 2026, Developed by Group 2, IT8</p>
    </div>
  </main>
</body>
</html>
<?php
}
