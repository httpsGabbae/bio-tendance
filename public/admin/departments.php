<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check(); handle_row_ops();
  $code=trim($_POST['code']??''); $name=trim($_POST['name']??'');
  if ($code!=='' && $name!=='') { try { db()->prepare("INSERT INTO departments (department_code,department_name) VALUES (?,?)")->execute([$code,$name]); audit('department_added',$code); $msg='Saved.'; } catch (Throwable $e) { $msg='Error: could not save.'; } }
}
$rows = db()->query("SELECT * FROM departments ORDER BY department_code")->fetchAll();
?>
<?php layout_start('Departments','departments'); ?><div class="card"><?=flash($msg)?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input name="code" placeholder="CCTE" required><input name="name" placeholder="Full name" required><button class="btn">＋ Add</button></form><div class="tw"><table><tr><th>Code</th><th>Name</th><th>Status</th><th></th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['department_code'])?></td><td><?=e($r['department_name'])?></td><td><?=badge($r['status'])?></td><td><?=row_actions('departments',$r)?></td></tr><?php endforeach; ?></table></div></div><?php layout_end(); ?>
