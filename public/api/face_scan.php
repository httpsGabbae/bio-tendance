<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/face.php';
require_once __DIR__ . '/../../includes/attendance_process.php';
header('Content-Type: application/json');
function out(int $c, array $a): void { http_response_code($c); echo json_encode($a); exit; }
if (isset($_GET['ping'])) out(empty($_SESSION['admin_id']) ? 401 : 200, ['success'=>!empty($_SESSION['admin_id'])]);
$j = json_decode(file_get_contents('php://input'), true) ?: [];
if (empty($_SESSION['admin_id']) || !hash_equals(csrf_token(), (string)($j['csrf'] ?? ''))) out(401, ['success'=>false,'message'=>'Kiosk session expired. Sign in again.']);
$pdo = db(); $st = $pdo->prepare("SELECT * FROM devices WHERE device_code=? AND status='active' LIMIT 1"); $st->execute([(string)($j['device'] ?? '')]); $dev = $st->fetch();
if (!$dev) out(422, ['success'=>false,'message'=>'Kiosk device is not active.']);
if (!face_valid($j['descriptor'] ?? null)) out(422, ['success'=>false,'message'=>'Invalid face data.']);
$m = face_match($j['descriptor']);
if (!$m || $m['distance'] > FACE_MATCH_MAX || ($m['second'] !== null && $m['second'] - $m['distance'] < 0.03)) { if (time() - (int)($_SESSION['fu'] ?? 0) > 30) { $_SESSION['fu'] = time(); audit('face_unrecognized', $dev['device_code']); } out(404, ['success'=>false,'message'=>'Face not recognized.']); }
$e = $pdo->prepare('SELECT employee_no, first_name, last_name FROM employees WHERE id=?'); $e->execute([$m['employee_id']]); $emp = $e->fetch();
$c = $pdo->prepare('SELECT MAX(event_time) FROM attendance_events WHERE employee_no=?'); $c->execute([$emp['employee_no']]); $last = $c->fetchColumn();
$name = $emp['first_name'].' '.$emp['last_name'];
if ($last && time() - strtotime((string)$last) < 60) out(200, ['success'=>true,'repeat'=>true,'name'=>$name,'id'=>(int)$m['employee_id'],'message'=>'Already recorded a moment ago.']);
$d = $pdo->prepare('SELECT COUNT(*) FROM attendance_events WHERE employee_no=? AND event_time>=? AND event_time<?'); $d->execute([$emp['employee_no'], date('Y-m-d 00:00:00'), date('Y-m-d 00:00:00', strtotime('+1 day'))]);
$dir = (int)$d->fetchColumn() === 0 ? 'IN' : 'OUT'; $now = date('Y-m-d H:i:s');
try { $r = process_attendance($pdo, $dev, $emp['employee_no'], $now, 'FACE', $dir, 'KIOSK-'.bin2hex(random_bytes(4))); }
catch (RuntimeException $x) { out(in_array($x->getCode(), [404,409,422], true) ? $x->getCode() : 500, ['success'=>false,'message'=>$x->getMessage()]); }
out(200, ['success'=>true,'name'=>$name,'id'=>(int)$m['employee_id'],'direction'=>$dir,'time'=>date('h:i A'),'status'=>$r['status'],'late'=>$r['late_minutes'],'message'=>'Recorded.']);
