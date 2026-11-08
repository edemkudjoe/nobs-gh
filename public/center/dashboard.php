<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('center');
layout_start($user, 'Dashboard');
?>
<p>Welcome, <?= e($user['full_name']) ?> (<?= e($user['facility_name']) ?>).</p>
<?php layout_end();
