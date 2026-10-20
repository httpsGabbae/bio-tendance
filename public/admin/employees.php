<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/face.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check(); handle_row_ops();
  $no=trim($_POST['employee_no']??''); $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??''); $dept=$_POST['department_id']??null; $sched=$_POST['schedule_id']??null;
  if ($no!==''&&$fn!==''&&$ln!=='') { try {
    $pdo=db(); $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO employees (employee_no,first_name,last_name,department_id) VALUES (?,?,?,?)")->execute([$no,$fn,$ln,$dept?:null]);
    $eid=(int)$pdo->lastInsertId();
    if ($sched) $pdo->prepare("INSERT INTO employee_schedules (employee_id,schedule_id) VALUES (?,?)")->execute([$eid,$sched]);
    $pdo->commit(); audit('employee_added',$no); $msg='Saved.';
  } catch (Throwable $e){ if(db()->inTransaction()) db()->rollBack(); $msg='Error: could not save.'; } }
}
$q=trim($_GET['q']??'');
if ($q!=='') { $st=db()->prepare("SELECT e.*, d.department_code FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.employee_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? ORDER BY e.employee_no LIMIT 100"); $like="%$q%"; $st->execute([$like,$like,$like]); $rows=$st->fetchAll(); }
else $rows=db()->query("SELECT e.*, d.department_code FROM employees e LEFT JOIN departments d ON d.id=e.department_id ORDER BY e.employee_no LIMIT 100")->fetchAll();
$depts=db()->query("SELECT * FROM departments WHERE status='active'")->fetchAll();
$scheds=db()->query("SELECT * FROM schedules WHERE status='active'")->fetchAll();
?>
<?php layout_start('Employees','employees'); ?>
<div class="page-intro"><div><p class="eyebrow">People directory</p><h2>Your team</h2><p>Manage employee records, schedules, badges, and face enrollment.</p></div><span class="count-pill"><?=count($rows)?> shown</span></div>
<?=flash($msg)?>
<details class="card create-panel" <?=!$rows?'open':''?>><summary><span><strong>Add employee</strong><small>Create a record and assign an optional schedule.</small></span><span class="summary-action">Add employee <span aria-hidden="true">＋</span></span></summary>
<form method="post" id="addForm" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Employee number<input name="employee_no" placeholder="e.g. 25-0001" required></label><label>First name<input name="first_name" required></label><label>Last name<input name="last_name" required></label><label>Department<select name="department_id"><option value="">No department</option><?php foreach($depts as $d): ?><option value="<?=(int)$d['id']?>"><?=e($d['department_code'])?></option><?php endforeach; ?></select></label><label>Schedule<select name="schedule_id"><option value="">No schedule yet</option><?php foreach($scheds as $s): ?><option value="<?=(int)$s['id']?>"><?=e($s['schedule_name'])?></option><?php endforeach; ?></select></label><div class="form-actions"><button class="btn">Create employee</button></div></form></details>
<div class="card table-card"><div class="table-toolbar"><div><h3>Employee directory</h3><p>Search by number or name. Showing up to 100 results.</p></div><form method="get" class="search-form"><label class="sr-only" for="employee-search">Search employees</label><input id="employee-search" name="q" value="<?=e($q)?>" placeholder="Search employees"><button>Search</button><?php if($q!==''): ?><a class="text-link" href="employees.php">Clear</a><?php endif; ?></form></div><div class="tw"><table><thead><tr><th>No</th><th>Name</th><th>Dept</th><th>Face</th><th>Status</th><th>Badge</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=e($r['employee_no'])?></td><td><?=avatar($r['first_name'].' '.$r['last_name'])?></td><td><?=e((string)($r['department_code'] ?? ''))?></td><td><?=face_cell((int)$r['id'])?></td><td><?=badge($r['status'])?></td><td><a href="badge.php?no=<?=urlencode($r['employee_no'])?>">Print</a></td><td><?=row_actions('employees',$r)?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="7" class="empty">No employees found. Add one above or clear the search.</td></tr><?php endif; ?></tbody></table></div></div>
<?php layout_end(); ?>
