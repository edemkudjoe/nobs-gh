<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
public_start('Features', '/features.php');
?>
<h1>Features</h1>

<h2>For donors</h2>
<ul>
  <li>Self-service eligibility check with clear reasons if you cannot give today.</li>
  <li>Booking of donation days up to 60 days ahead, with one active booking at a time.</li>
  <li>History of bookings and your next eligible date.</li>
</ul>

<h2>For blood centres</h2>
<ul>
  <li>Calendar of donation days with capacity limits.</li>
  <li>Appointment list to mark donors as completed, deferred or missed.</li>
  <li>Blood unit register from collection through testing to stock, with expiry tracking.</li>
</ul>

<h2>For hospitals</h2>
<ul>
  <li>Blood requests with urgency levels and a status you can follow.</li>
  <li>Accept or decline offered units before the deadline.</li>
  <li>Blood availability by group, nationally and in your region.</li>
  <li>For approved transplant centres: organ donor reporting, a patient waiting list and organ offers.</li>
  <li>Staff accounts managed by your own facility administrator.</li>
</ul>

<h2>For the national administrator</h2>
<ul>
  <li>Review and rank blood requests, with suggested units and override reasons.</li>
  <li>Verify patients on the waiting list and run organ offers with automatic next-in-line.</li>
  <li>National inventory with minimum stock levels and low-stock warnings.</li>
  <li>Approve facilities, edit system rules and travel times, and read the audit log.</li>
</ul>

<h2>Built-in safeguards</h2>
<ul>
  <li>Role-based access: every person sees only their own portal.</li>
  <li>Patients and donors are identified by codes, not names.</li>
  <li>Temporary passwords, forced password change and login attempt limits.</li>
  <li>Every request is protected against forged form submissions.</li>
</ul>
<?php public_end();
