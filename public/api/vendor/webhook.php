<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/config.php';
date_default_timezone_set(APP_TIMEZONE);
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/attendance_process.php';
require_once __DIR__ . '/../../../device_adapter/AdapterFactory.php';
header('Content-Type: application/json');
$vendor = trim((string)($_GET['vendor'] ?? ''));
$device_code = trim((string)($_GET['device'] ?? ''));
if ($vendor === '' || $device_code === '') { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Missing vendor or device query param.']); exit; }
$body = file_get_contents('php://input');
$raw = json_decode($body, true);
if (!is_array($raw)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid JSON.']); exit; }
try {
  $pdo = db();
  $st = $pdo->prepare("SELECT * FROM devices WHERE device_code=? LIMIT 1"); $st->execute([$device_code]); $dev = $st->fetch();
  if (!$dev) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Device not found.']); exit; }
  if ($dev['status'] !== 'active') { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Device inactive.']); exit; }
  $adapter = AdapterFactory::get((string)($dev['vendor_type'] ?? 'Generic'));
  $headers = [];
  foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_')) $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string)$v;
  }
  $raw['_headers'] = $headers; $raw['_body'] = $body; $raw['_device_code'] = $device_code;
  $st2 = $pdo->prepare("SELECT api_key_hash FROM devices WHERE id=?"); $st2->execute([(int)$dev['id']]);
  $rowHash = (string)($st2->fetch()['api_key_hash'] ?? '');
  $secret = (string)($headers['x-device-secret'] ?? '');
  if ($secret === '' || !hash_equals($rowHash, hash('sha256', $secret))) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized device.']); exit; }
  $std = $adapter->normalize($raw);
  if (($std['device_code'] ?? '') === '' || ($std['employee_no'] ?? '') === '' || ($std['event_time'] ?? '') === '' || ($std['verification_method'] ?? '') === '' || ($std['direction'] ?? '') === '') { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Missing required fields after normalize.']); exit; }
  if (!in_array($std['verification_method'], ['FINGERPRINT','FACE','CARD','PIN'], true)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid verification method.']); exit; }
  if (!in_array($std['direction'], ['IN','OUT'], true)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid direction.']); exit; }
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $std['event_time'], new DateTimeZone(APP_TIMEZONE));
  if (!$dt || $dt->format('Y-m-d H:i:s') !== $std['event_time']) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid event_time format. Use Y-m-d H:i:s.']); exit; }
  $now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
  if ($dt->getTimestamp() > $now->getTimestamp() + 300) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'event_time is in the future.']); exit; }
  $r = process_attendance($pdo, $dev, $std['employee_no'], $std['event_time'], $std['verification_method'], $std['direction'], (string)($std['vendor_reference'] ?? ''));
  echo json_encode(['success'=>true,'message'=>'Recorded.','status'=>$r['status'],'late_minutes'=>$r['late_minutes'],'early_out_minutes'=>$r['early_out_minutes']]);
} catch (RuntimeException $e) {
  $code = in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 500;
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code($code); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500); echo json_encode(['success'=>false,'message'=>'Server error.']);
}
