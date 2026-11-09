<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
public_start('Features', '/features.php');

/** Each group: [eyebrow, heading, [[icon, title, text], ...]] */
$groups = [
    ['For donors', 'Book a day without the phone calls.', [
        ['shield',   'Quick eligibility check', 'A few questions on age, weight and your last donation. Only the result and the date are kept.'],
        ['calendar', 'Pick a center with free places', 'Choose a date within the booking window. Cancel a booking if your plans change.'],
        ['history',  'Your donation history', 'See your bookings and the date you can donate again.'],
    ]],
    ['For blood centers', 'Keep stock honest.', [
        ['flask',    'Testing before use', 'Every unit starts in Testing and moves on only with a recorded result.'],
        ['box',      'Stock and expiry', 'Expiry dates follow the shelf life, with warnings before units expire.'],
        ['calendar', 'Slots and appointments', 'Set capacity per day and mark each appointment.'],
    ]],
    ['For hospitals', 'Ask once, track everything.', [
        ['clipboard', 'Blood requests', 'Choose group, units and urgency. See offers and answer before the deadline.'],
        ['people',    'Organ waiting list', 'Register patients with a code. Only verified patients are matched.'],
        ['drop',      'Report a deceased donor', 'Enter the organs, and the system ranks the waiting patients.'],
    ]],
    ['For the national administrator', 'Decide with the facts in view.', [
        ['list',    'Ranked suggestions', 'Urgency, waiting time, travel time and region are shown, never hidden.'],
        ['lock',    'Role-based access', 'Each page checks the role and the facility rights on the server.'],
        ['history', 'Audit log', 'Overrides, verifications and setting changes are recorded with reasons.'],
    ]],
];
?>
<section class="sec hero short">
  <div class="wrap">
    <div class="eyebrow">Features</div>
    <h1>The details behind better coordination.</h1>
    <p class="lede">What each kind of user can do, and the checks that keep it safe.</p>
  </div>
</section>

<?php foreach ($groups as $i => [$eyebrow, $heading, $cards]): ?>
<section class="sec<?= $i % 2 === 1 ? ' grey' : '' ?>">
  <div class="wrap">
    <div class="eyebrow"><?= e($eyebrow) ?></div>
    <h2 class="mid"><?= e($heading) ?></h2>
    <div class="cards3">
      <?php foreach ($cards as [$ic, $title, $text]): ?>
        <div><?= icon($ic) ?><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<section class="sec<?= count($groups) % 2 === 1 ? ' grey' : '' ?>">
  <div class="wrap center">
    <div class="eyebrow">Get started</div>
    <h2 class="big">Start with a donation, or sign in to your facility.</h2>
    <p class="txt">Donors register in about two minutes. Hospitals and blood centers use the account the national administrator created for them.</p>
    <div class="cta-row">
      <a class="btn btn-lg" href="/register.php">Register as a donor <?= icon('arrow', 18) ?></a>
      <a class="btn-line btn-lg" href="/login.php">Sign in</a>
    </div>
  </div>
</section>
<?php public_end();
