<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
date_default_timezone_set(APP_TIMEZONE);
require_once __DIR__ . '/../includes/attendance_functions.php';

$schedule = ['time_in'=>'08:00:00', 'time_out'=>'17:00:00', 'grace_minutes'=>10];
$cases = [
  ['on time', '2026-10-01 08:07:00', '2026-10-01 17:01:00', 'PRESENT', 0, 0],
  ['late', '2026-10-01 08:17:00', null, 'LATE', 17, 0],
  ['early checkout', '2026-10-01 08:00:00', '2026-10-01 16:40:00', 'EARLY_OUT', 0, 20],
  ['late and early', '2026-10-01 08:17:00', '2026-10-01 16:40:00', 'EARLY_OUT', 17, 20],
  ['checkout without checkin', null, '2026-10-01 17:01:00', 'PRESENT', 0, 0],
];
foreach ($cases as [$name, $first_in, $last_out, $status, $late, $early]) {
  $actual = summarize_attendance_day($first_in, $last_out, $schedule);
  $expected = ['status'=>$status, 'late_minutes'=>$late, 'early_out_minutes'=>$early];
  if ($actual !== $expected) {
    fwrite(STDERR, $name.': '.json_encode($actual).' != '.json_encode($expected).PHP_EOL);
    exit(1);
  }
}
echo count($cases)." attendance summary cases passed.\n";
