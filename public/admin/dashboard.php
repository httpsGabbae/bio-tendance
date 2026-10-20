<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/layout.php';
require_login();
$today = date('Y-m-d'); $c = ['PRESENT'=>0,'LATE'=>0,'ABSENT'=>0,'EARLY_OUT'=>0]; $recent = []; $devs = []; $total = 0;
try { $st = db()->prepare("SELECT status, COUNT(*) c FROM attendance_daily WHERE work_date=? GROUP BY status"); $st->execute([$today]); foreach ($st as $r) $c[$r['status']] = (int)$r['c']; } catch (Throwable $e) {}
try { $total = (int)db()->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn(); } catch (Throwable $e) {}
try { $recent = db()->query("SELECT CONCAT(e.first_name,' ',e.last_name) nm, ev.employee_no, ev.device_code, ev.event_time, ev.direction FROM attendance_events ev JOIN employees e ON e.id=ev.employee_id ORDER BY ev.event_time DESC LIMIT 8")->fetchAll(); } catch (Throwable $e) {}
try { $devs = db()->query("SELECT device_code, device_name, location, status, last_seen_at FROM devices ORDER BY device_code")->fetchAll(); } catch (Throwable $e) {}
$in = $c['PRESENT'] + $c['LATE'] + $c['EARLY_OUT']; $rate = $total ? (int)round($in / $total * 100) : 0;
$h = (int)date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
layout_start('Dashboard', 'dashboard');
?>
<div class="card hero"><div><h2><?=e($greet)?>, <?=e($_SESSION['admin_user'] ?? 'admin')?> 👋</h2><p><?= $in ?> of <?= $total ?> active employees have checked in today.</p></div>
<div class="clock"><span id="clk"><?=date('h:i:s A')?></span><small><?=date('l, F j')?></small></div></div>
<div class="grid">
<a class="stat s-present" href="attendance.php?date=<?=$today?>&status=PRESENT"><span>Present</span><b><?= $c['PRESENT'] ?></b><small>On time today</small></a>
<a class="stat s-late" href="attendance.php?date=<?=$today?>&status=LATE"><span>Late</span><b><?= $c['LATE'] ?></b><small>Past grace period</small></a>
<a class="stat s-absent" href="attendance.php?date=<?=$today?>&status=ABSENT"><span>Absent</span><b><?= $c['ABSENT'] ?></b><small>Finalized absences</small></a>
<a class="stat s-early" href="attendance.php?date=<?=$today?>&status=EARLY_OUT"><span>Early out</span><b><?= $c['EARLY_OUT'] ?></b><small>Left before schedule</small></a></div>
<div class="card"><div class="card-h">Attendance rate <small><?= $rate ?>%</small></div><div class="bar"><i style="width:<?= $rate ?>%"></i></div></div>
<div class="two">
<div class="card"><div class="card-h">Recent activity <small>Latest scans</small></div>
<?php if (!$recent): ?><div class="empty">No scans yet. Events appear here as devices report in.</div><?php else: ?><ul class="list">
<?php foreach ($recent as $r): ?><li><?= avatar($r['nm']) ?><span class="sp"><small><?= e($r['employee_no']) ?> · <?= e($r['device_code']) ?></small></span><?= badge($r['direction']) ?><small><?= tfmt($r['event_time']) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
<p style="margin:12px 0 0"><a href="attendance.php">View all attendance →</a></p></div>
<div class="card"><div class="card-h">Devices <small><?= count($devs) ?> registered</small></div>
<?php if (!$devs): ?><div class="empty">No devices registered.</div><?php else: ?><ul class="list">
<?php foreach ($devs as $d): $on = $d['status'] === 'active' && $d['last_seen_at'] && time() - strtotime($d['last_seen_at']) < 300; ?>
<li><span class="dot <?= $on ? 'on' : '' ?>"></span><span><b><?= e($d['device_name']) ?></b><small><?= e($d['device_code']) ?> · <?= e($d['location']) ?></small></span><span class="sp"></span><?= badge($on ? 'online' : ($d['status'] === 'active' ? 'offline' : 'inactive')) ?></li><?php endforeach; ?></ul><?php endif; ?></div></div>
<script>setInterval(function(){document.getElementById('clk').textContent=new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit',second:'2-digit'})},1000)</script>
<?php layout_end(); ?>
