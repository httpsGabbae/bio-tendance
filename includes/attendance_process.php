<?php
declare(strict_types=1);
require_once __DIR__ . '/attendance_functions.php';

function process_attendance(PDO $pdo, array $deviceRow, string $employee_no, string $event_time_s, string $method, string $direction, string $vendor): array {
  $dt = DateTime::createFromFormat('!Y-m-d H:i:s', $event_time_s, new DateTimeZone(APP_TIMEZONE));
  if (!$dt || $dt->format('Y-m-d H:i:s') !== $event_time_s) {
    throw new RuntimeException('Invalid event_time format. Use Y-m-d H:i:s.', 422);
  }
  if (!in_array($direction, ['IN', 'OUT'], true) || !in_array($method, ['FINGERPRINT', 'FACE', 'CARD', 'PIN'], true)) {
    throw new RuntimeException('Invalid attendance event.', 422);
  }
  $work_date = $dt->format('Y-m-d');
  $hash = event_hash((string)$deviceRow['device_code'], $employee_no, $event_time_s, $method, $direction, $vendor);

  $pdo->beginTransaction();
  try {
    // Serializing by employee keeps two devices from calculating the same daily row concurrently.
    $st = $pdo->prepare('SELECT id, status FROM employees WHERE employee_no=? LIMIT 1 FOR UPDATE');
    $st->execute([$employee_no]);
    $emp = $st->fetch();
    if (!$emp) throw new RuntimeException('Employee not found.', 404);
    if ($emp['status'] !== 'active') throw new RuntimeException('Employee inactive.', 422);

    $st = $pdo->prepare('SELECT id FROM attendance_events WHERE event_hash=? LIMIT 1');
    $st->execute([$hash]);
    if ($st->fetch()) throw new RuntimeException('Duplicate attendance event.', 409);

    $pdo->prepare('INSERT INTO attendance_events (device_id,employee_id,device_code,employee_no,event_time,verification_method,direction,vendor_reference,event_hash) VALUES (?,?,?,?,?,?,?,?,?)')
      ->execute([(int)$deviceRow['id'], (int)$emp['id'], (string)$deviceRow['device_code'], $employee_no, $event_time_s, $method, $direction, $vendor, $hash]);

    $sched = find_schedule_for_employee($pdo, (int)$emp['id'], $work_date);
    $st = $pdo->prepare("SELECT MIN(CASE WHEN direction='IN' THEN event_time END) AS first_in, MAX(CASE WHEN direction='OUT' THEN event_time END) AS last_out FROM attendance_events WHERE employee_id=? AND event_time>=? AND event_time<?");
    $st->execute([(int)$emp['id'], $work_date.' 00:00:00', $dt->modify('+1 day')->format('Y-m-d').' 00:00:00']);
    $times = $st->fetch();
    $first_in = $times['first_in'] ?? null;
    $last_out = $times['last_out'] ?? null;
    $summary = summarize_attendance_day($first_in, $last_out, $sched);

    $pdo->prepare('INSERT INTO attendance_daily (employee_id,work_date,time_in,time_out,status,late_minutes,early_out_minutes,schedule_id) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE time_in=VALUES(time_in),time_out=VALUES(time_out),status=VALUES(status),late_minutes=VALUES(late_minutes),early_out_minutes=VALUES(early_out_minutes),schedule_id=VALUES(schedule_id)')
      ->execute([(int)$emp['id'], $work_date, $first_in, $last_out, $summary['status'], $summary['late_minutes'], $summary['early_out_minutes'], $sched['id'] ?? null]);
    $pdo->prepare('UPDATE devices SET last_seen_at=NOW() WHERE id=?')->execute([(int)$deviceRow['id']]);
    $pdo->prepare('INSERT INTO audit_logs (action,details,ip_address) VALUES (?,?,?)')
      ->execute(['attendance_event', $employee_no.' '.$direction.' '.$event_time_s, $_SERVER['REMOTE_ADDR'] ?? '']);
    $pdo->commit();
    return $summary;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
      throw new RuntimeException('Duplicate attendance event.', 409, $e);
    }
    throw $e;
  }
}