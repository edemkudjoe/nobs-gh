<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
public_start('How it works', '/how-it-works.php');
?>
<h1>How it works</h1>
<p class="lead">Three flows share one set of records: donating blood, getting blood, and matching organs.</p>

<h2>1. Donating blood</h2>
<ol>
  <li>A donor registers and answers a short eligibility check (age, weight, time since the last donation).</li>
  <li>If eligible, the donor books a free day at a blood centre. One active booking at a time.</li>
  <li>At the centre, staff mark the visit as completed or deferred and record the unit.</li>
  <li>The unit is tested. Only tested, in-date units can be offered to hospitals.</li>
</ol>

<h2>2. Getting blood</h2>
<ol>
  <li>A hospital sends a request: blood group, number of units, urgency and a patient code. Never a patient name.</li>
  <li>The national administrator checks it. Requests are then ranked: critical first, then urgent, then routine, and within each level the longest waiting first.</li>
  <li>The system suggests units: the exact blood group before compatible ones, and the soonest expiry first so that nothing is wasted.</li>
  <li>The hospital has a deadline to accept or decline. If it does not answer, the units return to stock automatically.</li>
  <li>Skipping a higher-ranked request needs a written reason, which is kept in the audit log.</li>
</ol>

<h2>3. Matching organs</h2>
<ol>
  <li>A transplant hospital reports a consented donor and the organs retrieved. The donor's name is never stored.</li>
  <li>Verified patients on the waiting list are ranked by urgency, then time waiting, then travel time to the organ, then region.</li>
  <li>The organ is offered to the first patient. Because organs are viable for only hours, the answer window is a small fraction of that time.</li>
  <li>If the hospital declines or does not answer in time, the offer moves to the next patient at once. The organ stops being offered when it is no longer viable.</li>
</ol>

<h2>Always on</h2>
<ul>
  <li>Late offers, expired units and missed bookings are handled automatically.</li>
  <li>Hospitals only see what they need. Blood availability is shown as counts, not as who holds which unit.</li>
  <li>Every important action is written to an audit log.</li>
</ul>
<p><a class="btn" href="/register.php">Register as a donor</a></p>
<?php public_end();
