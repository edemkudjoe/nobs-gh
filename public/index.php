<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';

// Signed-in people go straight to their own dashboard.
if (current_user()) {
    redirect(home_for(current_user()['role']));
}
public_start('Blood and organs, matched fairly', '/');
?>
<h1>Blood and organs, matched fairly</h1>
<p class="lead">One national system where blood banks, hospitals and donors work from the same records. Every request is ranked by need, every offer has a deadline, and every decision leaves a trace.</p>
<div class="actions">
  <a class="btn" href="/register.php">Register as a donor</a>
  <a class="btn btn-quiet" href="/login.php">Sign in</a>
</div>

<h2>Who it is for</h2>
<ul>
  <li><strong>Donors</strong> check if they can give blood, book a time at a centre and follow their donation.</li>
  <li><strong>Blood centres</strong> record every unit from collection to testing and stock.</li>
  <li><strong>Hospitals</strong> ask for blood, report organ donors and manage their transplant waiting list.</li>
  <li><strong>The national administrator</strong> verifies requests and patients and sees the whole picture.</li>
</ul>

<h2>Why it matters</h2>
<p>When blood or an organ is scarce, the order in which people are served should follow medical need, not who phoned first. The system applies the same written rules every time and shows its reasons.</p>
<p><a href="/how-it-works.php">See how it works</a></p>
<?php public_end();
