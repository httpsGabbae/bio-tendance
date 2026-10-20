<?php
// Scan station for the R45 QR/barcode terminal.
// OPEN KIOSK: no login. Safe on single-PC localhost (physical access = trusted).
// The scanner "types" the badge number + Enter into the box below.
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/attendance_process.php';

const SCAN_DEVICE_CODE = 'DEVICE-R45'; // admin must add this code in Devices page
const CUTOFF_HOUR = 12;               // scans before 12:00 = IN, 12:00 onward = OUT
const SCAN_METHOD = 'CARD';           // badge scan until the real biometric scanner arrives

$result = null;
$error = null;
$empName = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $no = trim($_POST['employee_no'] ?? '');
  if ($no === '') {
    $error = 'Empty scan. Try again.';
  } else {
    try {
      $pdo = db();
      $st = $pdo->prepare("SELECT * FROM devices WHERE device_code=? LIMIT 1");
      $st->execute([SCAN_DEVICE_CODE]);
      $dev = $st->fetch();
      if (!$dev) {
        $error = 'Setup needed: add device ' . SCAN_DEVICE_CODE . ' in the Devices page first.';
      } elseif ($dev['status'] !== 'active') {
        $error = 'Device disabled. Ask the administrator.';
      } else {
        $now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
        $dir = ((int)$now->format('H') < CUTOFF_HOUR) ? 'IN' : 'OUT';
        $etime = $now->format('Y-m-d H:i:s');
        $r = process_attendance($pdo, $dev, $no, $etime, SCAN_METHOD, $dir, 'R45-' . $now->format('YmdHis'));
        $st2 = $pdo->prepare("SELECT first_name,last_name FROM employees WHERE employee_no=? LIMIT 1");
        $st2->execute([$no]);
        $er = $st2->fetch();
        $empName = $er ? $er['first_name'] . ' ' . $er['last_name'] : $no;
        $result = $r + ['direction' => $dir, 'time' => $etime];
      }
    } catch (RuntimeException $e) {
      if ($e->getCode() === 409) {
        $error = 'Already recorded — badge scanned twice.';
      } else {
        $error = $e->getMessage();
      }
    } catch (Throwable $e) {
      $error = 'Server error. Try again.';
    }
  }
}
$modeNow = ((int)date('H') < CUTOFF_HOUR) ? 'IN' : 'OUT';
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Scan Station</title><link rel="stylesheet" href="assets/style.css">
<style>
.scanwrap{max-width:640px;margin:4vh auto;text-align:center}
.scanmode{font-size:64px;font-weight:bold;margin:8px 0}
.scanbox{font-size:32px;padding:12px;width:90%;text-align:center}
.flash{font-size:28px;padding:20px;border-radius:8px;margin:12px 0}
.ok{background:#e6f4ea;color:#137333}.bad{background:#fce8e6;color:#a50e0e}
</style></head>
<body><div class="wrap scanwrap">
<div class="card">
<div>Now accepting: <span class="scanmode"><?=e($modeNow)?></span></div>
<p><small>Before 12:00 = IN &nbsp;·&nbsp; 12:00 onward = OUT &nbsp;·&nbsp; <?=e(date('Y-m-d H:i'))?></small></p>
<?php if ($result): ?>
<div class="flash ok"><?=e($empName)?><br><?=e($result['direction'])?> <?=e($result['time'])?> — <?=e($result['status'])?></div>
<?php elseif ($error): ?>
<div class="flash bad"><?=e($error)?></div>
<?php else: ?>
<div class="flash">Scan your ID badge</div>
<?php endif; ?>
<form method="post" autocomplete="off">
<input class="scanbox" id="scanbox" name="employee_no" autofocus placeholder="Tap here, then scan">
</form>
<p><small>R45 terminal · method CARD (interim badge mode)</small></p>
</div></div>
<script>
var box = document.getElementById('scanbox');
box.focus();
document.addEventListener('click', function(){ box.focus(); });
function beep(freq){
  try {
    var ctx = new (window.AudioContext || window.webkitAudioContext)();
    var o = ctx.createOscillator(), g = ctx.createGain();
    o.connect(g); g.connect(ctx.destination);
    o.frequency.value = freq; o.start();
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
    setTimeout(function(){ o.stop(); }, 260);
  } catch (e) {}
}
<?php if ($result): ?>beep(880);<?php elseif ($error): ?>beep(220);<?php endif; ?>
</script>
</body></html>
