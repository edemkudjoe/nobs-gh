<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/auth.php';
$user = require_capability('admin');
echo 'Admin dashboard for ' . e($user['full_name']);