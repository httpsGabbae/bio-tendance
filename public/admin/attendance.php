<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/attendance_finalize.php';
require_login();
$date=$_GET['date']??date('Y-m-d'); $q=trim($_GET['q']??''); $status=$_GET['status']??'';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  if (($_POST['op']??'')==='absent') {
    $d=$_POST['date']??date('Y-m-d');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
      $msg=finalize_day($d);
    }
  }
}
$sql="SELECT a.*, e.employee_no, CONCAT(e.first_name,' ',e.last_name) nm, d.department_code FROM attendance_daily a JOIN employees e ON e.id=a.employee_id LEFT JOIN departments d ON d.id=e.department_id WHERE 1=1";
$p=[];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { $sql.=" AND a.work_date=?"; $p[]=$date; }
if ($status!=='') { $sql.=" AND a.status=?"; $p[]=$status; }
if ($q!=='') { $sql.=" AND (e.employee_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)"; $p[]="%$q%"; $p[]="%$q%"; $p[]="%$q%"; }
$sql.=" ORDER BY a.work_date DESC, e.employee_no LIMIT 200";
$st=db()->prepare($sql); $st->execute($p); $rows=$st->fetchAll();
?>
<?php layout_start('Attendance','attendance'); ?>
<div class="page-intro"><div><p class="eyebrow">Daily records</p><h2>Check-ins and departures</h2><p>Review check-ins, departures, and exceptions for a selected day.</p></div><span class="count-pill"><?=count($rows)?> records</span></div>
<?=flash($msg??'')?>
<div class="card table-card"><div class="table-toolbar"><div><h3>Attendance records</h3><p>Showing up to 200 results.</p></div><form method="get" class="filter-form noprint"><label class="sr-only" for="att-date">Date</label><input id="att-date" type="date" name="date" value="<?=e($date)?>"><label class="sr-only" for="att-search">Employee number or name</label><input id="att-search" name="q" value="<?=e($q)?>" placeholder="Employee number or name"><label class="sr-only" for="att-status">Status</label><select id="att-status" name="status"><option value="">All statuses</option><?php foreach(['PRESENT','LATE','ABSENT','EARLY_OUT','ON_LEAVE','REST_DAY','HOLIDAY'] as $s): ?><option value="<?=$s?>" <?= $status===$s?'selected':'' ?>><?=ucwords(strtolower(str_replace('_',' ',$s)))?></option><?php endforeach; ?></select><button>Apply</button><button type="button" onclick="window.print()">Print</button></form></div><div class="tw"><table><thead><tr><th>Date</th><th>No</th><th>Name</th><th>Dept</th><th>In</th><th>Out</th><th>Status</th><th>Late</th><th>Early</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=e($r['work_date'])?></td><td><?=e($r['employee_no'])?></td><td><?=avatar($r['nm'])?></td><td><?=e((string)($r['department_code'] ?? ''))?></td><td><?=tfmt($r['time_in'])?></td><td><?=tfmt($r['time_out'])?></td><td><?=badge($r['status'])?></td><td><?=(int)$r['late_minutes']?></td><td><?=(int)$r['early_out_minutes']?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="9" class="empty">No records match these filters.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card finalize-panel noprint"><div><h3>Finalize a past day</h3><p>Mark employees without scans as absent, on leave, on a rest day, or on a holiday based on their schedule.</p></div><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="op" value="absent"><label class="sr-only" for="finalize-date">Day to finalize</label><input id="finalize-date" type="date" name="date" value="<?=e($date)?>" max="<?=date('Y-m-d',strtotime('-1 day'))?>"><button class="btn">Finalize day</button></form></div>
<?php layout_end(); ?>
