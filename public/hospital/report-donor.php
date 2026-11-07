<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
$user = require_capability('can_request_blood');
echo 'Request blood at ' . e($user['facility_name']);