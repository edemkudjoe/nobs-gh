<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/donation.php';
$user = require_capability('donor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$ok, $msg] = donor_book($user, (int)($_POST['slot_id'] ?? 0));
    flash_set($msg, $ok ? 'ok' : 'error');
    redirect($ok ? '/donor/bookings.php' : '/donor/book.php');
}

$check   = donor_check((int)$user['id']);
$active  = donor_active_booking((int)$user['id']);
$first   = max($check['from'], new DateTimeImmutable('today'));
$last    = (new DateTimeImmutable('today'))->modify('+' . setting_int('booking_window_days') . ' days');

// Optional filters from the calendar form: ?date=2026-11-20&centre=3
$picked = null;
$pickError = '';
$rawDate = trim((string)($_GET['date'] ?? ''));
if ($rawDate !== '') {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
    if (!$d || $d->format('Y-m-d') !== $rawDate) {
        $pickError = 'Choose a valid date.';
    } elseif ($d < $first || $d > $last) {
        $pickError = 'Choose a date from ' . $first->format('j M Y') . ' to ' . $last->format('j M Y') . '.';
    } else {
        $picked = $rawDate;
    }
}
$centre = (int)($_GET['centre'] ?? 0);

/** Open days with places left, optionally for one date and/or one centre. */
$fetch = function (?string $date, int $centre, int $limit) use ($first, $last): array {
    $sql = "SELECT * FROM (
              SELECT s.id, s.slot_date, f.name AS facility, r.name AS region,
                     s.capacity - (SELECT COUNT(*) FROM appointments a
                                    WHERE a.slot_id = s.id AND a.status IN ('scheduled','completed','deferred')) AS spots_left
                FROM center_slots s
                JOIN facilities f ON f.id = s.facility_id AND f.active AND f.verification_status = 'approved'
                JOIN regions r ON r.id = f.region_id
               WHERE s.slot_date BETWEEN ?::date AND ?::date";
    $params = [$first->format('Y-m-d'), $last->format('Y-m-d')];
    if ($date !== null) {
        $sql .= ' AND s.slot_date = ?::date';
        $params[] = $date;
    }
    if ($centre > 0) {
        $sql .= ' AND s.facility_id = ?::int';
        $params[] = $centre;
    }
    $sql .= ') x WHERE spots_left > 0 ORDER BY slot_date, region, facility LIMIT ' . $limit;
    return db_all($sql, $params);
};

$slots = $nearest = $centres = $openDays = [];
$canBook = $check['donor'] && !$check['blockers'] && !$active;
if ($canBook) {
    $slots = $fetch($picked, $centre, 60);
    if ($picked !== null && !$slots) {
        $nearest = $fetch(null, $centre, 10);     // nothing that day: show the closest open days
    }
    $centres  = db_all(
        "SELECT DISTINCT f.id, f.name FROM center_slots s
           JOIN facilities f ON f.id = s.facility_id AND f.active AND f.verification_status = 'approved'
          WHERE s.slot_date BETWEEN ?::date AND ?::date ORDER BY f.name",
        [$first->format('Y-m-d'), $last->format('Y-m-d')]);
    $openDays = array_values(array_unique(array_column($fetch(null, 0, 60), 'slot_date')));
}

/** Print a table of days with a Book button on each row. */
$table = function (array $rows): void { ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Centre</th><th>Region</th><th>Places left</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr>
        <td><?= e((new DateTime($s['slot_date']))->format('D j M Y')) ?></td>
        <td><?= e($s['facility']) ?></td>
        <td><?= e($s['region']) ?></td>
        <td><?= (int)$s['spots_left'] ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-small" type="submit">Book</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php };

layout_start($user, 'Book a donation');
?>
<?php if ($check['blockers']): ?>
  <div class="alert"><?php foreach ($check['blockers'] as $b): ?><div><?= e($b) ?></div><?php endforeach; ?></div>
<?php elseif ($active): ?>
  <p>You already have a booking on <strong><?= e((new DateTime($active['slot_date']))->format('l j F Y')) ?></strong> at <?= e($active['facility']) ?>.
     <a href="/donor/bookings.php">Manage it</a> (you can cancel it to choose another day).</p>
<?php else: ?>
  <?php if ($check['from'] > new DateTimeImmutable('today')): ?>
    <p class="hint">You can donate again from <?= e($check['from']->format('j F Y')) ?>, so earlier days are not shown.</p>
  <?php endif; ?>

  <form method="get" class="pickbar" novalidate>
    <div class="field"><label for="date">Preferred date</label>
      <input id="date" name="date" type="date" value="<?= e((string)$picked) ?>"
             min="<?= e($first->format('Y-m-d')) ?>" max="<?= e($last->format('Y-m-d')) ?>">
      <?php if ($pickError): ?><span class="err" role="alert"><?= e($pickError) ?></span><?php endif; ?></div>
    <?php if ($centres): ?>
    <div class="field"><label for="centre">Centre</label>
      <select id="centre" name="centre">
        <option value="0">Any centre</option>
        <?php foreach ($centres as $c): ?>
          <option value="<?= (int)$c['id'] ?>"<?= $centre === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="pickbtns"><button class="btn" type="submit">Show places</button>
      <a class="btn btn-quiet" href="/donor/book.php">Show all days</a></div>
  </form>

  <?php if ($openDays): ?>
    <p class="hint">Days with places open (tap to pick one):</p>
    <p class="daychips">
      <?php foreach (array_slice($openDays, 0, 10) as $day): ?>
        <a class="chip" href="/donor/book.php?date=<?= e($day) ?><?= $centre > 0 ? '&amp;centre=' . $centre : '' ?>"><?= e((new DateTime($day))->format('D j M')) ?></a>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if ($picked !== null && $slots): ?>
    <h2>Places on <?= e((new DateTime($picked))->format('l j F Y')) ?></h2>
    <?php $table($slots); ?>
  <?php elseif ($picked !== null): ?>
    <p>No places are open on <strong><?= e((new DateTime($picked))->format('l j F Y')) ?></strong>.<?= $nearest ? ' These are the nearest open days:' : '' ?></p>
    <?php if ($nearest) { $table($nearest); } ?>
  <?php elseif (!$slots): ?>
    <p>No places are open in the next <?= setting_int('booking_window_days') ?> days. Please check again later.</p>
  <?php else: ?>
    <?php $table($slots); ?>
  <?php endif; ?>
<?php endif; ?>
<?php layout_end();