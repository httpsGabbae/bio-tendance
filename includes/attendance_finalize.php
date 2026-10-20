<?php
// Correct absence finalization: honors rest days, holidays, leave, and schedules.
function finalize_day(string $d): string {
  if ($d >= date('Y-m-d')) return 'Error: only past dates can be finalized.';
  $pdo = db(); $dow = (string)date('N', strtotime($d));
  $h = $pdo->prepare('SELECT 1 FROM holidays WHERE holiday_date=?'); $h->execute([$d]); $isHol = (bool)$h->fetch();
  $emps = $pdo->prepare("SELECT id FROM employees WHERE status='active' AND id NOT IN (SELECT employee_id FROM attendance_daily WHERE work_date=?)"); $emps->execute([$d]);
  $sch = $pdo->prepare('SELECT s.id, s.rest_days FROM employee_schedules es JOIN schedules s ON s.id=es.schedule_id WHERE es.employee_id=? AND (es.effective_date IS NULL OR es.effective_date<=?) ORDER BY es.effective_date IS NULL, es.effective_date DESC LIMIT 1');
  $lv = $pdo->prepare('SELECT 1 FROM employee_leaves WHERE employee_id=? AND ? BETWEEN start_date AND end_date LIMIT 1');
  $ins = $pdo->prepare('INSERT INTO attendance_daily (employee_id,work_date,status,schedule_id) VALUES (?,?,?,?)');
  $n = ['ABSENT'=>0,'ON_LEAVE'=>0,'REST_DAY'=>0,'HOLIDAY'=>0,'skipped'=>0];
  foreach ($emps->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $sch->execute([$id, $d]); $s = $sch->fetch();
    if (!$s) { $n['skipped']++; continue; }
    $lv->execute([$id, $d]);
    if (in_array($dow, explode(',', (string)$s['rest_days']), true)) $st = 'REST_DAY';
    elseif ($isHol) $st = 'HOLIDAY';
    elseif ($lv->fetch()) $st = 'ON_LEAVE';
    else $st = 'ABSENT';
    $ins->execute([$id, $d, $st, $s['id']]); $n[$st]++;
  }
  audit('finalize_day', $d.' '.json_encode($n));
  return "Finalized $d: {$n['ABSENT']} absent, {$n['ON_LEAVE']} on leave, {$n['REST_DAY']} rest day, {$n['HOLIDAY']} holiday. {$n['skipped']} skipped (no schedule assigned).";
}
