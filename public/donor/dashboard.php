<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/layout.php';
$user = require_capability('donor');
layout_start($user, 'Dashboard');
?>
<p>Welcome, <?= e($user['full_name']) ?>.</p>
<?php layout_end();
