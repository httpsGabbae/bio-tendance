<?php
// AttendX shared UI helpers + layout shell
function icon(string $n): string {
  static $i = [
    'home'=>'<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    'users'=>'<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M16 4a4 4 0 0 1 0 8M22 21c0-3-2-5-4-5.5"/>',
    'building'=>'<path d="M4 21V4l8-2v19M12 8h8v13M4 21h16M8 8h1M8 12h1M8 16h1M16 12h1M16 16h1"/>',
    'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'device'=>'<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M12 18h.01M9.5 9a3 3 0 0 1 5 0M10.5 12.5a1.5 1.5 0 0 1 3 0"/>',
    'cal'=>'<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18M9 15l2 2 4-4"/>',
    'chart'=>'<path d="M4 20V4M4 20h16M8 16v-5M13 16V8M18 16v-3"/>',
    'out'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    'flag'=>'<path d="M5 22V4M5 4h13l-2 4 2 4H5"/>',
    'shield'=>'<path d="M12 2l8 3v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5z"/>',
    'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
  ];
  return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($i[$n] ?? '').'</svg>';
}
function badge(?string $s): string {
  $s = (string)$s; if ($s === '') return '<span class="badge">—</span>';
  return '<span class="badge b-'.e(strtolower($s)).'">'.e(ucfirst(strtolower(str_replace('_',' ',$s)))).'</span>';
}
function avatar(string $name): string {
  $p = preg_split('/\s+/', trim($name)); $ini = strtoupper(substr($p[0] ?? '',0,1).substr(end($p) ?: '',0,1));
  return '<span class="who"><span class="av" style="--h:'.(crc32($name)%360).'">'.e($ini).'</span>'.e($name).'</span>';
}
function tfmt($dt): string { return $dt ? e(date('h:i A', strtotime((string)$dt))) : '<span style="color:var(--mute)">—</span>'; }
function flash(string $m): string {
  if ($m === '') return '';
  $err = stripos($m,'error') === 0;
  if (preg_match('/^(.*SECRET[^:]*:\s*)(\S+)$/', $m, $x)) return '<div class="alert">'.e($x[1]).'<code>'.e($x[2]).'</code></div>';
  return '<div class="alert'.($err?' err':'').'">'.e($m).'</div>';
}
function layout_start(string $title, string $active): void {
  $nav = ['Overview'=>[['dashboard','Dashboard','home']],
    'People'=>[['employees','Employees','users'],['departments','Departments','building'],['schedules','Schedules','clock']],
    'Operations'=>[['attendance','Attendance','cal'],['../kiosk','Face kiosk','device'],['holidays','Calendar','flag'],['devices','Devices','device'],['reports','Reports','chart']],
    'System'=>[['audit','Audit log','shield']]];
  $u = (string)($_SESSION['admin_user'] ?? 'admin');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' · AttendX</title>'
   .'<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml"><link rel="alternate icon" href="../assets/favicon.ico"><link rel="apple-touch-icon" href="../assets/apple-touch-icon.png"><meta name="theme-color" content="#4F46E5">'
   .'<link rel="stylesheet" href="../assets/style.css"></head><body><a class="skip-link" href="#main-content">Skip to content</a><div class="app"><button class="nav-scrim" type="button" aria-label="Close menu" onclick="document.body.classList.remove(\'nav-open\')"></button><aside class="side" id="side-nav"><div class="logo"><img src="../assets/logo-light.svg" alt="AttendX"><button class="side-close" type="button" aria-label="Close menu" onclick="document.body.classList.remove(\'nav-open\')">×</button></div><nav aria-label="Primary navigation">';
  foreach ($nav as $g=>$items) { echo '<h6>'.e($g).'</h6>';
    foreach ($items as [$k,$l,$ic]) echo '<a href="'.$k.'.php" class="'.($k===$active?'on':'').'"'.($k===$active?' aria-current="page"':'').'>'.icon($ic).e($l).'</a>'; }
  echo '</nav><div class="me"><span class="av" style="--h:240;margin:0">'.e(strtoupper(substr($u,0,2))).'</span><div><b>'.e($u).'</b><small>Administrator</small></div><form method="post" action="../logout.php" style="margin-left:auto"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><button title="Log out" aria-label="Log out" style="border:0;background:none;color:#CBD5E1;padding:7px">'.icon('out').'</button></form></div></aside>'
   .'<div class="main"><header class="top"><button class="burger noprint" type="button" onclick="document.body.classList.toggle(\'nav-open\')" aria-label="Open menu" aria-controls="side-nav"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">'.substr(icon('menu'),strpos(icon('menu'),'>')+1,-6).'</svg></button><div class="page-title"><span>AttendX / Workspace</span><h1>'.e($title).'</h1></div><span class="sp"></span><span class="sub">'.date('l, M j, Y').'</span></header><main class="content" id="main-content">';
}
function layout_end(): void { echo '</main></div></div><script>document.addEventListener("keydown",function(e){if(e.key==="Escape")document.body.classList.remove("nav-open")});</script></body></html>'; }

function days_label(string $csv): string {
  $m = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun']; $o = [];
  foreach (array_filter(explode(',', $csv)) as $d) if (isset($m[(int)$d])) $o[] = $m[(int)$d];
  return $o ? implode(', ', $o) : 'None';
}
function handle_row_ops(): void {
  $t = basename($_SERVER['SCRIPT_NAME'], '.php'); $id = (int)($_POST['id'] ?? 0);
  if (($_POST['op'] ?? '') !== 'toggle' || !$id || !in_array($t, ['employees','departments','schedules','devices'], true)) return;
  db()->prepare("UPDATE `$t` SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
  audit('toggle_'.$t, "id=$id"); header('Location: '.$t.'.php'); exit;
}
function row_actions(string $t, array $r): string {
  $f = fn($op, $label, $msg) => '<form method="post" style="margin:0;display:inline" onsubmit="return confirm(\''.$msg.'\')"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="op" value="'.$op.'"><input type="hidden" name="id" value="'.(int)$r['id'].'"><button style="padding:4px 10px;font-size:12px">'.$label.'</button></form> ';
  $on = ($r['status'] ?? '') === 'active';
  return $f('toggle', $on ? 'Deactivate' : 'Activate', ($on ? 'Deactivate' : 'Activate').' this record?').($t === 'devices' ? $f('resetkey', 'Reset key', 'Generate a new secret? The device stops working until updated.') : '');
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
