<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/layout.php';
$user = require_capability('admin');

$perPage = 50;
$page    = max(1, (int)($_GET['page'] ?? 1));
$action  = trim((string)($_GET['action'] ?? ''));
$actions = array_column(db_all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action');
if ($action !== '' && !in_array($action, $actions, true)) {
    $action = '';
}
$total = (int)db_val('SELECT COUNT(*) FROM audit_log WHERE (?::text = \'\' OR action = ?)', [$action, $action]);
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$rows  = db_all(
    "SELECT l.id, l.created_at, l.action, l.entity, l.entity_id, l.detail, u.full_name, u.role
       FROM audit_log l LEFT JOIN users u ON u.id = l.user_id
      WHERE (?::text = '' OR l.action = ?)
      ORDER BY l.created_at DESC, l.id DESC LIMIT ?::int OFFSET ?::int",
    [$action, $action, $perPage, ($page - 1) * $perPage]
);
$q = fn(int $p) => '?' . http_build_query(['action' => $action, 'page' => $p]);

layout_start($user, 'Audit log');
?>
<form method="get" class="field">
  <label for="action">Show only</label>
  <select id="action" name="action" onchange="this.form.submit()">
    <option value="">All actions</option>
    <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= $a === $action ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
  </select>
  <noscript><button class="btn btn-small" type="submit">Filter</button></noscript>
</form>
<p class="hint"><?= $total ?> entries. Page <?= $page ?> of <?= $pages ?>.</p>
<table>
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>About</th><th>Detail</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(substr($r['created_at'], 0, 19)) ?></td>
      <td><?= $r['full_name'] ? e($r['full_name']) . '<br><span class="fine">' . e($r['role']) . '</span>' : 'Automatic' ?></td>
      <td><span class="code"><?= e($r['action']) ?></span></td>
      <td><?= $r['entity'] ? e($r['entity']) . ($r['entity_id'] ? ' #' . (int)$r['entity_id'] : '') : '' ?></td>
      <td><?= e((string)$r['detail']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p>
  <?php if ($page > 1): ?><a class="btn btn-quiet btn-small" href="<?= e($q($page - 1)) ?>">Newer</a><?php endif; ?>
  <?php if ($page < $pages): ?><a class="btn btn-quiet btn-small" href="<?= e($q($page + 1)) ?>">Older</a><?php endif; ?>
</p>
<?php layout_end();
