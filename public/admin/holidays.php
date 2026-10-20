<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check(); $op=$_POST['op']??'';
  try {
    if ($op==='holiday' && trim($_POST['name']??'')!=='') { db()->prepare('INSERT INTO holidays (holiday_date,name) VALUES (?,?)')->execute([$_POST['date'],trim($_POST['name'])]); audit('holiday_added',$_POST['date']); $msg='Holiday saved.'; }
    elseif ($op==='leave' && $_POST['end']>=$_POST['start']) { db()->prepare('INSERT INTO employee_leaves (employee_id,start_date,end_date,reason) VALUES (?,?,?,?)')->execute([(int)$_POST['emp'],$_POST['start'],$_POST['end'],trim($_POST['reason']??'')]); audit('leave_added','emp='.(int)$_POST['emp']); $msg='Leave saved.'; }
    elseif ($op==='del_holiday') { db()->prepare('DELETE FROM holidays WHERE id=?')->execute([(int)$_POST['id']]); audit('holiday_deleted','id='.(int)$_POST['id']); }
    elseif ($op==='del_leave') { db()->prepare('DELETE FROM employee_leaves WHERE id=?')->execute([(int)$_POST['id']]); audit('leave_deleted','id='.(int)$_POST['id']); }
    elseif ($op==='holiday'||$op==='leave') $msg='Error: check the values (end date must not be before start date).';
  } catch (Throwable $e) { $msg='Error: could not save (duplicate date?).'; }
}
$hol=db()->query('SELECT * FROM holidays ORDER BY holiday_date DESC LIMIT 100')->fetchAll();
$lv=db()->query("SELECT l.*, CONCAT(e.first_name,' ',e.last_name) nm FROM employee_leaves l JOIN employees e ON e.id=l.employee_id ORDER BY l.start_date DESC LIMIT 100")->fetchAll();
$emps=db()->query("SELECT id,employee_no,first_name,last_name FROM employees WHERE status='active' ORDER BY employee_no")->fetchAll();
layout_start('Calendar','holidays'); echo flash($msg); $del=fn($op,$id)=>'<form method="post" style="margin:0;display:inline" onsubmit="return confirm(\'Delete?\')">'.csrf_field().'<input type="hidden" name="op" value="'.$op.'"><input type="hidden" name="id" value="'.(int)$id.'"><button style="padding:4px 10px;font-size:12px">Delete</button></form>';
?>
<div class="two"><div class="card"><div class="card-h">Holidays <small>Finalizing a holiday marks employees HOLIDAY, not absent</small></div>
<form method="post"><?=csrf_field()?><input type="hidden" name="op" value="holiday"><input type="date" name="date" required><input name="name" placeholder="Holiday name" required><button class="btn">＋ Add</button></form>
<div class="tw"><table><tr><th>Date</th><th>Name</th><th></th></tr><?php foreach($hol as $r): ?><tr><td><?=e($r['holiday_date'])?></td><td><?=e($r['name'])?></td><td><?=$del('del_holiday',$r['id'])?></td></tr><?php endforeach; if(!$hol): ?><tr><td colspan="3" class="empty">No holidays yet.</td></tr><?php endif; ?></table></div></div>
<div class="card"><div class="card-h">Employee leave</div>
<form method="post"><?=csrf_field()?><input type="hidden" name="op" value="leave"><select name="emp" required><option value="">Employee…</option><?php foreach($emps as $e2): ?><option value="<?=(int)$e2['id']?>"><?=e($e2['employee_no'].' — '.$e2['first_name'].' '.$e2['last_name'])?></option><?php endforeach; ?></select><input type="date" name="start" required><input type="date" name="end" required><input name="reason" placeholder="Reason (optional)"><button class="btn">＋ Add</button></form>
<div class="tw"><table><tr><th>Employee</th><th>From</th><th>To</th><th></th></tr><?php foreach($lv as $r): ?><tr><td><?=avatar($r['nm'])?></td><td><?=e($r['start_date'])?></td><td><?=e($r['end_date'])?></td><td><?=$del('del_leave',$r['id'])?></td></tr><?php endforeach; if(!$lv): ?><tr><td colspan="4" class="empty">No leave recorded.</td></tr><?php endif; ?></table></div></div></div>
<?php layout_end(); ?>
