<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
public_start('How it works', '/how-it-works.php');
?>
<section class="sec hero">
  <div class="wrap">
    <div class="eyebrow">How it works</div>
    <h1>Two journeys, one record.</h1>
    <p class="lede">Blood and organs follow different rules. Both end the same way, with a clear next step and a visible history.</p>
  </div>
</section>

<section class="sec grey">
  <div class="wrap split">
    <div class="l">
      <div class="eyebrow">Blood</div>
      <h2 class="big">From a donor to a bedside.</h2>
      <p class="txt">Blood is stocked by centers. Hospitals request it, and the administrator matches the request to stock.</p>
      <div class="illus" aria-hidden="true"><svg viewBox="0 0 400 240" xmlns="http://www.w3.org/2000/svg"><ellipse cx="200" cy="226" rx="190" ry="12" fill="#E3EEE7"/><rect x="60" y="20" width="8" height="206" rx="4" fill="#14212B"/><rect x="40" y="20" width="110" height="8" rx="4" fill="#14212B"/><path d="M120 28 v18" stroke="#14212B" stroke-width="4"/><rect x="92" y="46" width="64" height="90" rx="14" fill="#C4121B"/><rect x="92" y="86" width="64" height="50" rx="12" fill="#8E0D13"/><rect x="104" y="66" width="40" height="14" rx="3" fill="#FFFFFF" opacity=".9"/><path d="M124 136 q0 40 40 50 q60 14 100 -10" stroke="#C4121B" stroke-width="4" fill="none"/><rect x="250" y="150" width="110" height="56" rx="10" fill="#185C35"/><rect x="262" y="164" width="86" height="8" rx="4" fill="#FFFFFF" opacity=".85"/><rect x="262" y="180" width="54" height="8" rx="4" fill="#FFFFFF" opacity=".55"/><path d="M300 90 c-12 16 -16 24 -16 31 a16 16 0 0 0 32 0 c0 -7 -4 -15 -16 -31z" fill="#C4121B"/></svg></div>
    </div>
    <div class="r">
      <div class="steps">
        <div><div class="n">01</div><div><h3>A donor books a day</h3><p>The donor picks a center and a date that still has free places, within the booking window.</p></div></div>
        <div><div class="n">02</div><div><h3>The center collects and tests</h3><p>Each unit starts in Testing. A passed unit becomes Available, and a failed one is discarded with a reason.</p></div></div>
        <div><div class="n">03</div><div><h3>A hospital requests blood</h3><p>It names the blood group, the units and the urgency. The administrator verifies it.</p></div></div>
        <div><div class="n">04</div><div><h3>The administrator offers units</h3><p>The exact blood group goes first, then compatible groups, and the nearest expiry first. A partial offer keeps the request open.</p></div></div>
        <div><div class="n">05</div><div><h3>The hospital answers in time</h3><p>By default offers last 4 hours for urgent and critical requests and 24 hours for routine ones. A late offer returns the units to stock.</p></div></div>
        <div><div class="n">06</div><div><h3>Everything is recorded</h3><p>Overrides need a reason, and the audit log keeps who did what and when.</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap split">
    <div class="l">
      <div class="eyebrow">Organs</div>
      <h2 class="big">A waiting list, ranked fairly.</h2>
      <p class="txt">Organs come from deceased donors only. A person decides every offer, and the system suggests and ranks.</p>
      <div class="illus" aria-hidden="true"><svg viewBox="0 0 400 240" xmlns="http://www.w3.org/2000/svg"><ellipse cx="200" cy="226" rx="190" ry="12" fill="#DCEBF3"/><path d="M140 80 a30 30 0 0 1 120 0" stroke="#14212B" stroke-width="10" fill="none" stroke-linecap="round"/><rect x="80" y="80" width="240" height="130" rx="14" fill="#0B6A97"/><rect x="80" y="80" width="240" height="34" rx="14" fill="#08506f"/><rect x="150" y="128" width="100" height="62" rx="6" fill="#FFFFFF"/><path d="M215 142 c-18 -4 -34 8 -30 24 c3 12 14 14 22 10 c-2 -6 2 -9 8 -9 c8 0 14 -8 10 -17 c-2 -5 -6 -7 -10 -8z" fill="#C4121B"/><g transform="translate(300 30)"><circle r="22" fill="#FFFFFF" stroke="#ECCB22" stroke-width="4"/><path d="M0 -12v12l8 6" stroke="#14212B" stroke-width="4" fill="none" stroke-linecap="round"/></g></svg></div>
    </div>
    <div class="r">
      <div class="steps">
        <div><div class="n">01</div><div><h3>A hospital registers a patient</h3><p>The patient is added with a code, not a name. The administrator verifies the registration first.</p></div></div>
        <div><div class="n">02</div><div><h3>A hospital reports a deceased donor</h3><p>Staff enter the blood group, the consent details and the organs that can be offered.</p></div></div>
        <div><div class="n">03</div><div><h3>The system ranks the candidates</h3><p>It filters by organ and blood group, then orders by urgency, waiting time, travel time and region.</p></div></div>
        <div><div class="n">04</div><div><h3>The top candidate gets an offer</h3><p>The response deadline is one-eighth of the organ viability window. If it passes, the next candidate is offered.</p></div></div>
        <div><div class="n">05</div><div><h3>The administrator can override</h3><p>A different candidate can be chosen, but only with a written reason that goes in the audit log.</p></div></div>
      </div>
    </div>
  </div>
</section>

<section class="sec grey">
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
