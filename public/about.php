<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/layout.php';
public_start('About', '/about.php');
?>
<h1>About this project</h1>
<p class="lead">The National Organ &amp; Blood Bank System is a student project built for the full-stack web development course at the University of Professional Studies, Accra (UPSA).</p>

<h2>What it is</h2>
<p>A working prototype that shows how blood donation, blood requests and organ matching could run on one shared, rule-based system in Ghana.</p>

<h2>What it is not</h2>
<ul>
  <li>It is not an official system of the National Blood Service, the Ministry of Health or the Ghana Health Service.</li>
  <li>All names, hospitals, patients and stock levels you see are sample data.</li>
  <li>It must not be used to make real medical decisions.</li>
</ul>

<h2>How it is built</h2>
<p>PHP 8.4 running on FrankenPHP in a Docker container, a PostgreSQL database hosted on Supabase, and plain HTML and CSS with no front-end framework. The matching rules are written in plain PHP and their settings can be changed by the administrator.</p>

<h2>Try it</h2>
<p>Register as a donor to see the donor portal, or ask the team for demonstration staff accounts.</p>
<p><a class="btn" href="/register.php">Register as a donor</a></p>
<?php public_end();
