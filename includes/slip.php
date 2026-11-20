<?php
declare(strict_types=1);

/**
 * Printable credentials slip, used after creating a facility, creating a staff account
 * and resetting a password. The password is shown on this page only and is never stored readable.
 *
 * $s = [
 *   'title'   => page title,
 *   'heading' => line under the system name on the slip,
 *   'rows'    => [ [label, value, optional class ('mono' or 'mono big')], ... ],
 *   'steps'   => [ 'text', ... ],
 *   'actions' => [ [label, url], ... ],   // extra buttons next to Print
 * ]
 */
function render_slip(array $user, array $s): void
{
    require_once __DIR__ . '/pdf.php';
    // The PDF is made here, from the same data as the page, and handed to the browser inside the page.
    // The password is never saved on the server, so there is no second request to fetch it.
    $pdf = base64_encode(slip_pdf($user, $s));
    $file = preg_replace('/[^A-Za-z0-9]+/', '-', 'Credentials-' . ($s['rows'][0][1] ?? 'slip') . '-' . date('Y-m-d'));
    layout_start($user, (string)$s['title']);
    ?>
    <div class="alert wide slip-warn"><strong>This is the only time the password is shown.</strong>
      Print this page or save it as a PDF now, before you leave it.</div>
    <div class="slip-actions">
      <button type="button" class="btn" id="slip-pdf" data-pdf="<?= e($pdf) ?>" data-name="<?= e(trim($file, '-')) ?>.pdf">Download PDF</button>
      <button type="button" class="btn btn-quiet" id="slip-print">Print</button>
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
      <p class="slip-foot">Issued <?= e(date('j F Y')) ?> by <?= e(person_name($user['title'], $user['full_name'])) ?>.
        Confidential: give this only to the person named above.</p>
    </article>
    <script>
    document.getElementById('slip-pdf').addEventListener('click', function () {
      var b = this, raw = atob(b.getAttribute('data-pdf')), bytes = new Uint8Array(raw.length);
      for (var i = 0; i < raw.length; i++) { bytes[i] = raw.charCodeAt(i); }
      var url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
      var a = document.createElement('a');
      a.href = url; a.download = b.getAttribute('data-name');
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
    });
    </script>
    <script>document.getElementById('slip-print').addEventListener('click', function () { if (window.nobsPrint) { window.nobsPrint(); } else { window.print(); } });</script>
    <?php
    layout_end();
}

function signin_url(): string
{
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/login.php';
}
