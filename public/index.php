<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/art.php';

// Signed-in people go straight to their own dashboard.
if (current_user()) {
    redirect(home_for(current_user()['role']));
}
public_start('Every donation, every request', '/');
?>
<section class="sec hero2">
  <div class="wrap hero-split">
    <div class="t">
      <div class="eyebrow">Connected centers. Clearer decisions.</div>
      <h1 class="xl">Every donation, every request, <em>in one national system.</em></h1>
      <p class="lede wide">Donors book a day. Hospitals request blood and register patients for organs. A national administrator checks every request and offer.</p>
      <div class="cta-row" style="margin-top:34px;gap:14px">
        <a class="btn btn-lg" href="/register.php">Register as a donor <?= icon('arrow', 18) ?></a>
        <a class="btn-line btn-lg" href="/login.php">Facility sign in</a>
      </div>
      <div class="note-line" style="margin-top:22px;gap:10px"><?= icon('shield', 18) ?>Facility accounts are approved by the national administrator.</div>
    </div>
    <div class="v">
      <div class="panel" role="group" aria-label="Examples with sample data">
        <div class="pc">
          <div class="tag">Example booking</div>
          <div class="big-line">Saturday 14 November</div>
          <div class="kvs">
            <div class="kv"><span>Kumasi Blood Center</span><span class="mono">Ashanti</span></div>
            <div class="kv"><span>Free slots</span><span class="mono">3 of 20</span></div>
          </div>
        </div>
        <div class="pc blue">
          <div class="tag">Organ offer</div>
          <div class="big-line">Kidney, O+</div>
          <div class="kvs">
            <div class="kv"><span>Respond within</span><span class="mono">02:41:10</span></div>
            <div class="kv"><span>Organ viable for</span><span class="mono">05:42:10</span></div>
          </div>
        </div>
        <div class="pc-note"><?= icon('shield', 28) ?><div>Tested before it is used.<br><b>Verified before it is offered.</b></div></div>
      </div>
    </div>
  </div>
</section>

<section class="sec pad-who">
  <div class="wrap">
    <div class="eyebrow">Who it is for</div>
    <h2 class="big xl">One system, three ways in.</h2>
    <div class="cards3 roomy">
      <div><?= icon('drop') ?><h3>Donors</h3><p>Register, check that you are eligible, and book a date at a center with free places.</p></div>
      <div><?= icon('clipboard') ?><h3>Hospitals</h3><p>Request blood, register patients waiting for an organ, and report a deceased donor.</p></div>
      <div><?= icon('flask') ?><h3>Blood centers</h3><p>Keep stock current, test every unit, set daily donor slots and run appointments.</p></div>
    </div>
  </div>
</section>

<section class="sec grey pad-work">
  <div class="wrap">
    <div class="work-head">
      <div>
        <div class="eyebrow">A working view of the system</div>
        <h2 class="big xl">Your day, brought into focus.</h2>
      </div>
      <p>Requests, offers and stock in one place, so the administrator can decide where to start.</p>
    </div>
    <div class="window big" role="img" aria-label="Example of the administrator dashboard with sample data">
      <div class="bar"><i></i><i></i><i></i><span class="t">National system / Administrator workspace</span></div>
      <div class="body">
        <div class="menu"><div class="on">Dashboard</div><div>Blood requests</div><div>Blood offers</div><div>Waiting list</div><div>Organ offers</div><div>Inventory</div></div>
        <div class="main2">
          <div class="t">Dashboard</div><div class="s">Here is the system at a glance.</div>
          <div class="tiles">
            <div class="tile"><div class="label">Pending verification</div><div class="value">12</div></div>
            <div class="tile"><div class="label">Open organ offers</div><div class="value">3</div></div>
            <div class="tile"><div class="label">Groups low or out</div><div class="value">2</div></div>
            <div class="tile"><div class="label">Expiring in 7 days</div><div class="value">9</div></div>
          </div>
          <div class="q">Queue, most urgent first</div>
          <div class="qrow"><b><span class="dot c"></span>Critical</b><span class="d">4 units O-</span><span>1 h 12 min</span></div>
          <div class="qrow"><b><span class="dot u"></span>Urgent</b><span class="d">Heart, A+, new waiting-list entry</span><span>3 d 4 h</span></div>
          <div class="qrow"><b><span class="dot r"></span>Routine</b><span class="d">2 units B+</span><span>5 h 40 min</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap split">
    <div class="l">
      <div class="eyebrow">One connected workflow</div>
      <h2 class="big">From donation to handover.</h2>
      <a class="linkarrow" href="/how-it-works.php">See how it works <?= icon('arrow', 18) ?></a>
      <div class="art" role="img" aria-label="Illustration of the people who use the system" style="margin-top:32px"><?= art_people() ?></div>
    </div>
    <div class="r">
      <div class="steps">
        <div><div class="n">01</div><div><h3>Donate and test</h3><p>A donor gives blood at a center. The center tests every unit before it is available.</p></div></div>
        <div><div class="n">02</div><div><h3>Request and verify</h3><p>A hospital asks for blood. The administrator verifies the request and offers units.</p></div></div>
        <div><div class="n">03</div><div><h3>Confirm the handover</h3><p>The hospital accepts before the deadline. Every step is kept in the record.</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="sec grey">
  <div class="wrap split">
    <div class="l">
      <div class="eyebrow">Tools that work together</div>
      <h2 class="big">The details behind better coordination.</h2>
      <a class="linkarrow" href="/features.php">Explore all features <?= icon('arrow', 18) ?></a>
    </div>
    <div class="r">
      <div class="tlist">
        <div><?= icon('calendar') ?>Donor booking</div>
        <div><?= icon('flask') ?>Stock and testing</div>
        <div><?= icon('clipboard') ?>Requests and offers</div>
        <div><?= icon('people') ?>Organ waiting list</div>
        <div><?= icon('list') ?>Ranked organ offers</div>
        <div><?= icon('history') ?>Audit log</div>
      </div>
    </div>
  </div>
</section>

<section class="sec pad-cta">
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
