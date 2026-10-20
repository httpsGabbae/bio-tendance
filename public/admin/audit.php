<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$rows=db()->query("SELECT l.created_at,l.action,l.details,l.ip_address,a.username FROM audit_logs l LEFT JOIN admins a ON a.id=l.admin_id ORDER BY l.id DESC LIMIT 300")->fetchAll();
layout_start('Audit log','audit'); ?>
<div class="card"><div class="card-h">Recent activity <small>Last 300 actions</small></div><div class="tw"><table><tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr>
<?php foreach($rows as $r): ?><tr><td><?=e($r['created_at'])?></td><td><?=e((string)($r['username']??'—'))?></td><td><span class="badge"><?=e($r['action'])?></span></td><td><?=e((string)$r['details'])?></td><td><?=e($r['ip_address'])?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="5" class="empty">No activity recorded yet.</td></tr><?php endif; ?></table></div></div>
<?php layout_end(); ?>
