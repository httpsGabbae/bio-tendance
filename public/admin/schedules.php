<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check(); handle_row_ops();
  $n=trim($_POST['name']??''); $in=$_POST['time_in']??''; $out=$_POST['time_out']??''; $g=(int)($_POST['grace']??10); $rd=implode(',',array_intersect((array)($_POST['rest']??[]),['1','2','3','4','5','6','7']));
  if ($n!==''&&$in!==''&&$out!=='') { try { db()->prepare("INSERT INTO schedules (schedule_name,time_in,time_out,grace_minutes,rest_days) VALUES (?,?,?,?,?)")->execute([$n,$in,$out,$g,$rd]); audit('schedule_added',$n); $msg='Saved.'; } catch (Throwable $e){ $msg='Error: could not save.'; } }
}
$rows=db()->query("SELECT * FROM schedules ORDER BY id")->fetchAll();
?>
<?php layout_start('Schedules','schedules'); ?><div class="card"><?=flash($msg)?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input name="name" placeholder="Day Shift" required><input type="time" name="time_in" required><input type="time" name="time_out" required><input type="number" name="grace" value="10" min="0" max="120"><?php foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $k=>$l): ?><label class="chk"><input type="checkbox" name="rest[]" value="<?=$k?>" <?=$k>5?'checked':''?>> <?=$l?></label><?php endforeach; ?><span style="color:var(--mute);font-size:12px">= rest days</span><button class="btn">＋ Add</button></form><div class="tw"><table><tr><th>Name</th><th>In</th><th>Out</th><th>Grace</th><th>Rest days</th><th>Status</th><th></th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['schedule_name'])?></td><td><?=e($r['time_in'])?></td><td><?=e($r['time_out'])?></td><td><?=e((string)$r['grace_minutes'])?> min</td><td><?=e(days_label((string)$r['rest_days']))?></td><td><?=badge($r['status'])?></td><td><?=row_actions('schedules',$r)?></td></tr><?php endforeach; ?></table></div></div><?php layout_end(); ?>
