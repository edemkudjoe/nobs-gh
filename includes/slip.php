<?php
declare(strict_types=1);

/**
 * Printable credentials slip, used after creating a facility, creating a staff account
 * and resetting a password. The password is shown on this page only and is never stored readable.
 */
function render_slip(array $user, array $s): void
{
    layout_start($user, (string)$s['title']);
    ?>
    <div class="alert wide slip-warn"><strong>This is the only time the password is shown.</strong>
      Print this page or save it as a PDF now, before you leave it.</div>
    <div class="slip-actions">
      <button type="button" class="btn" id="slip-print">Print or save as PDF</button>
      <?php foreach ($s['actions'] as $a): ?><a class="btn btn-quiet" href="<?= e($a[1]) ?>"><?= e($a[0]) ?></a><?php endforeach; ?>
    </div>
    <article class="slip">
      <header class="slip-head"><?= site_logos() ?>
        <div><strong>National Organ &amp; Blood Bank System</strong><span><?= e((string)$s['heading']) ?></span></div></header>
      <dl class="slip-list">
        <?php foreach ($s['rows'] as $r): ?>
          <dt><?= e($r[0]) ?></dt><dd<?= !empty($r[2]) ? ' class="' . e($r[2]) . '"' : '' ?>><?= e($r[1]) ?></dd>
        <?php endforeach; ?>
      </dl>
      <ol class="slip-steps">
        <?php foreach ($s['steps'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?>
      </ol>
      <p class="slip-foot">Issued <?= e(date('j F Y')) ?> by <?= e($user['full_name']) ?>.
        Confidential: give this only to the person named above.</p>
    </article>
        <script>document.getElementById('slip-print').addEventListener('click', function () { if (window.nobsPrint) { window.nobsPrint(); } else { window.print(); } });</script>
    <?php
    layout_end();
}

function signin_url(): string
{
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/login.php';
}