<?php
// Connection test. Delete this file (or require an admin login) before the demo.
declare(strict_types=1);
require_once __DIR__ . '/../includes/session.php';

start_session();
$_SESSION['visits'] = ($_SESSION['visits'] ?? 0) + 1;

header('Content-Type: text/plain; charset=utf-8');
echo "PHP " . PHP_VERSION . " (" . PHP_SAPI . ")\n";
echo "Regions in database: " . db_val('SELECT count(*) FROM regions') . "\n";
echo "Your visits this session: " . $_SESSION['visits'] . "\n";
