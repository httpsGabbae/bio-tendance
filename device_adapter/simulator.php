<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
if ($argc < 3) { fwrite(STDERR, "Usage: php simulator.php <EMP_NO> <IN|OUT> [--time=\"Y-m-d H:i:s\"] [--device=DEVICE-001] [--key=test-device-secret-001]\n"); exit(1); }
$emp = $argv[1]; $dir = strtoupper($argv[2]);
if (!in_array($dir, ['IN','OUT'], true)) { fwrite(STDERR, "Direction must be IN or OUT\n"); exit(1); }
$opt = ['time'=>date('Y-m-d H:i:s'),'device'=>'DEVICE-001','key'=>'test-device-secret-001','vendor'=>'Generic'];
foreach (array_slice($argv,3) as $a) {
  if (str_starts_with($a,'--time=')) $opt['time']=substr($a,7);
  elseif (str_starts_with($a,'--device=')) $opt['device']=substr($a,9);
  elseif (str_starts_with($a,'--key=')) $opt['key']=substr($a,6);
  elseif (str_starts_with($a,'--vendor=')) $opt['vendor']=substr($a,9);
}
if ($opt['vendor'] !== 'Generic') {
  if ($opt['vendor'] === 'ZKTeco') $payload=['SN'=>$opt['device'],'UserID'=>$emp,'VerifyTime'=>$opt['time'],'VerifyMode'=>1,'InOut'=>($dir==='OUT'?1:0),'RecordID'=>'SIM-'.time()];
  elseif ($opt['vendor'] === 'Suprema') $payload=['DeviceID'=>$opt['device'],'UserID'=>$emp,'DateTime'=>date('c'),'AuthMode'=>'FP','EventType'=>($dir==='OUT'?'Exit':'Entry'),'LogID'=>'SIM-'.time()];
  elseif ($opt['vendor'] === 'Anviz') $payload=['DeviceID'=>$opt['device'],'PIN'=>$emp,'Time'=>$opt['time'],'VerifyMode'=>'FP','Status'=>($dir==='OUT'?'Exit':'Entry'),'RecordID'=>'SIM-'.time()];
  elseif ($opt['vendor'] === 'Hikvision') $payload=['deviceID'=>$opt['device'],'EmployeeNo'=>$emp,'AttendanceTime'=>date('c'),'VerifyMode'=>'Fingerprint','Direction'=>($dir==='OUT'?'Exit':'Entry'),'EventID'=>'SIM-'.time()];
  else { fwrite(STDERR, "Unknown vendor\n"); exit(1); }
  $ch=curl_init('http://localhost/bio-tendance/public/api/vendor/webhook.php?vendor='.$opt['vendor'].'&device='.$opt['device']);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Device-Secret: '.$opt['key']],CURLOPT_POSTFIELDS=>json_encode($payload)]);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  if ($res === false) { fwrite(STDERR, "cURL error: ".curl_error($ch)."\n"); exit(1); }
  curl_close($ch);
  echo "HTTP $code\n$res\n";
  exit(0);
}
$payload=['device_code'=>$opt['device'],'employee_no'=>$emp,'event_time'=>$opt['time'],'verification_method'=>'FINGERPRINT','direction'=>$dir,'vendor_reference'=>'SIM-'.time()];
$ch=curl_init('http://localhost/bio-tendance/public/api/attendance.php');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Device-Key: '.$opt['key']],CURLOPT_POSTFIELDS=>json_encode($payload)]);
$res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
if ($res === false) { fwrite(STDERR, "cURL error: ".curl_error($ch)."\n"); exit(1); }
curl_close($ch);
echo "HTTP $code\n$res\n";
