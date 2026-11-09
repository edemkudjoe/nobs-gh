<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
public_start('About', '/about.php');
?>
<section class="sec hero">
  <div class="wrap split">
    <div class="l">
      <div class="record" role="group" aria-label="Example record with sample data">
        <div class="tag eyebrow" style="color:var(--muted)">Example record</div>
        <div class="t">The records that explain what happened.</div>
        <div class="row"><span class="tm">08:15</span><span>Donor D-0412 reported by Komfo Anokye Teaching Hospital.</span></div>
        <div class="row"><span class="tm">08:16</span><span>Kidney O+ ranked. 3 candidates, 2 left out.</span></div>
        <div class="row"><span class="tm">08:17</span><span>Offered to P-0231 with a 3 hour deadline.</span></div>
        <div class="row"><span class="tm">11:40</span><span>Override: P-0302 offered, road to rank 1 closed.</span></div>
      </div>
    </div>
    <div class="r">
      <div class="eyebrow">A shared starting point</div>
      <h1 style="font-size:46px">Coordination starts with the people doing the work.</h1>
      <p class="body">Center staff keep stock and record tests. Hospital staff record needs. The national administrator reviews them and coordinates between facilities. Each decision needs clear information and a visible next step.</p>
      <p class="body">This system brings that work into one place, connecting donors, stock, requests and the records that explain what happened.</p>
      <p class="body">A student project for Full-Stack Web Development at UPSA, built by a team of six. It uses sample data and is not an official NBS, MoH or GHS system.</p>
    </div>
  </div>
</section>

<section class="sec grey">
  <div class="wrap">
    <div class="eyebrow">Who it is built for</div>
    <h2 class="big">Four kinds of people, one record.</h2>
    <p class="txt" style="max-width:640px">Donors, center staff, hospital staff and the national administrator each see only what their work needs, and each action leaves a trace.</p>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="eyebrow">Design principles</div>
    <h2 class="big">What we put first.</h2>
    <div class="principles">
      <div><div class="n">01</div><h3>Safety</h3><p>Blood is tested before it is used, and patients are verified before they are matched.</p></div>
      <div><div class="n">02</div><h3>Accountability</h3><p>Overrides need a reason, and every sensitive action is logged.</p></div>
      <div><div class="n">03</div><h3>Fairness</h3><p>Organ ranking uses a fixed order that everyone can see and check.</p></div>
      <div><div class="n">04</div><h3>Privacy</h3><p>Codes replace names for patients and organ donors, and each person sees only what their work needs.</p></div>
    </div>
  </div>
</section>

<section class="sec grey">
  <div class="wrap center">
    <h2 class="big">See the shared workflow in action.</h2>
    <div class="cta-row"><a class="btn btn-lg" href="/how-it-works.php">How it works <?= icon('arrow', 18) ?></a></div>
  </div>
</section>
<?php public_end();
