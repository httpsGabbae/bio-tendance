<?php
declare(strict_types=1);
function event_hash(string $device, string $emp, string $time, string $method, string $dir, string $vendor): string {
  return hash('sha256', $device.'|'.$emp.'|'.$time.'|'.$method.'|'.$dir.'|'.$vendor);
}
function calc_late_minutes(string $sched_in, int $grace, string $actual_datetime): int {
  $day = substr($actual_datetime,0,10);
  $deadline = strtotime($day.' '.$sched_in) + $grace*60;
  $actual = strtotime($actual_datetime);
  if ($actual <= $deadline) return 0;
  $scheduled = strtotime($day.' '.$sched_in);
  $diff = (int)floor(($actual-$scheduled)/60);
  return $diff > 0 ? $diff : 0;
}
function calc_early_minutes(string $sched_out, string $actual_datetime): int {
  $day = substr($actual_datetime,0,10);
  $sched = strtotime($day.' '.$sched_out);
  $actual = strtotime($actual_datetime);
  $diff = (int)floor(($sched-$actual)/60);
  return $diff > 0 ? $diff : 0;
}
function find_schedule_for_employee(PDO $pdo, int $employee_id, string $work_date): ?array {
  $st = $pdo->prepare("SELECT s.* FROM employee_schedules es JOIN schedules s ON s.id=es.schedule_id WHERE es.employee_id=? AND s.status='active' AND (es.effective_date IS NULL OR es.effective_date<=?) ORDER BY es.effective_date IS NULL, es.effective_date DESC LIMIT 1");
  $st->execute([$employee_id,$work_date]);
  $r = $st->fetch(); return $r ?: null;
}

function summarize_attendance_day(?string $first_in, ?string $last_out, ?array $schedule): array {
  $late = ($first_in !== null && $schedule)
    ? calc_late_minutes($schedule['time_in'], (int)$schedule['grace_minutes'], $first_in) : 0;
  $early = ($last_out !== null && $schedule)
    ? calc_early_minutes($schedule['time_out'], $last_out) : 0;
  $status = $early > 0 ? 'EARLY_OUT' : ($late > 0 ? 'LATE' : 'PRESENT');
  return ['status'=>$status, 'late_minutes'=>$late, 'early_out_minutes'=>$early];
}
