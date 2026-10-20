<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
date_default_timezone_set(APP_TIMEZONE);
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/attendance_functions.php';
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid JSON.']); exit; }
$device_code = trim((string)($data['device_code']??''));
$employee_no = trim((string)($data['employee_no']??''));
$event_time_s = trim((string)($data['event_time']??''));
$method = strtoupper(trim((string)($data['verification_method']??'')));
$direction = strtoupper(trim((string)($data['direction']??'')));
$vendor = (string)($data['vendor_reference']??'');
$key = $_SERVER['HTTP_X_DEVICE_KEY'] ?? '';
if ($device_code===''||$employee_no===''||$event_time_s===''||$method===''||$direction==='') { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Missing required fields.']); exit; }
if (!in_array($method,['FINGERPRINT','FACE','CARD','PIN'],true)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid verification method.']); exit; }
if (!in_array($direction,['IN','OUT'],true)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid direction.']); exit; }
$dt = DateTime::createFromFormat('Y-m-d H:i:s', $event_time_s, new DateTimeZone(APP_TIMEZONE));
if (!$dt || $dt->format('Y-m-d H:i:s')!==$event_time_s) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid event_time format. Use Y-m-d H:i:s.']); exit; }
$now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
if ($dt->getTimestamp() > $now->getTimestamp()+300) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'event_time is in the future.']); exit; }
try {
  $pdo = db();
  $st = $pdo->prepare("SELECT * FROM devices WHERE device_code=? LIMIT 1"); $st->execute([$device_code]); $dev = $st->fetch();
  if (!$dev || !hash_equals((string)$dev['api_key_hash'], hash('sha256', (string)$key))) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized device.']); exit; }
  if ($dev['status'] !== 'active') { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Device inactive.']); exit; }
  require_once __DIR__ . '/../../includes/attendance_process.php';
  $r = process_attendance($pdo, $dev, $employee_no, $event_time_s, $method, $direction, $vendor);
  echo json_encode(['success'=>true,'message'=>'Recorded.','status'=>$r['status'],'late_minutes'=>$r['late_minutes'],'early_out_minutes'=>$r['early_out_minutes']]);
} catch (RuntimeException $e) {
  $code = in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 500;
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code($code); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500); echo json_encode(['success'=>false,'message'=>'Server error.']);
}
