# Multi-Vendor Adapter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-device vendor adapters (ZKTeco, Suprema, Anviz, Hikvision + Generic fallback) behind one webhook that normalizes to the standard event, without changing attendance rules.

**Architecture:** `public/api/vendor/webhook.php` looks up the device, picks the adapter via `AdapterFactory`, validates the vendor signature, normalizes to the standard event, then calls shared `process_attendance()` — the same function the existing `attendance.php` uses after extraction. Attendance math, duplicate guard, and transactions live in exactly one place.

**Tech Stack:** PHP 8.2, PDO pdo_mysql, MariaDB (XAMPP), Apache, no frameworks.

**Spec:** `docs/superpowers/specs/2026-10-02-multivendor-adapter-design.md`

## Global Constraints

- PHP >= 8.0 (tested 8.2.12), PDO with prepared statements only, no string-concatenated SQL.
- Timezone `Asia/Manila` everywhere: `date_default_timezone_set('Asia/Manila')` + `SET time_zone='+08:00'`.
- DB name `bio_attendance`, host `localhost`, user `root`, pass empty by default via env `DB_HOST/DB_NAME/DB_USER/DB_PASS`.
- Passwords via `password_hash(PASSWORD_DEFAULT)` + `password_verify` (unchanged).
- CSRF token on every admin POST via `csrf_token()/csrf_check()`, output via `e()` (unchanged).
- Device secret never stored plain; store `hash('sha256', secret)` in `devices.api_key_hash CHAR(64)`, compare with `hash_equals`.
- Event hash `hash('sha256', device_code.'|'.employee_no.'|'.event_time.'|'.method.'|'.direction.'|'.vendor_ref)` with DB UNIQUE (unchanged).
- No raw fingerprint/face images/templates stored.
- Web entry `public/` → `http://localhost/bio-tendance/public/`; webhook at `public/api/vendor/webhook.php`.
- Verification methods whitelist: `FINGERPRINT,FACE,CARD,PIN`; direction: `IN,OUT` (unchanged).
- HTTP codes: 200 ok, 400 bad JSON, 401 device/signature, 404 employee, 409 duplicate, 422 validation; never leak SQL/traces.
- Adapter contract (verbatim from spec): `normalize(array $raw): array`, `validateSignature(array $raw, string $secret): bool`. Keys starting with `_` (`_headers`, `_body`, `_device_code`) are webhook plumbing and MUST be ignored by `normalize()`.

---

## File Map

- Create `device_adapter/VendorAdapterInterface.php` — 2-method contract.
- Create `device_adapter/GenericAdapter.php` — passthrough for standard events.
- Create `device_adapter/AdapterFactory.php` — `get(string $vendor): VendorAdapterInterface`.
- Create `device_adapter/ZKTecoAdapter.php`, `SupremaAdapter.php`, `AnvizAdapter.php`, `HikvisionAdapter.php`.
- Create `includes/attendance_process.php` — `process_attendance(PDO, array $deviceRow, string $empNo, string $time, string $method, string $dir, string $vendor): array` (moved verbatim logic from `attendance.php:33-76`, throwing coded exceptions).
- Modify `public/api/attendance.php` — thin wrapper: parse + validate + device auth, then call `process_attendance()`.
- Create `public/api/vendor/webhook.php` — route + signature + normalize + call `process_attendance()`.
- Create `database/migrate_vendor_type.sql` — ALTER TABLE + backfill; also patch `database/schema.sql`.
- Modify `public/admin/devices.php` — vendor_type dropdown + list column.
- Modify `device_adapter/simulator.php` — add `--vendor=` flag posting native payload to webhook.
- Modify `device_adapter/integration_guide.md` — append 4-vendor config section.

---

### Task 1: Contract + factory + Generic + DB migration

**Files:**
- Create: `device_adapter/VendorAdapterInterface.php`
- Create: `device_adapter/GenericAdapter.php`
- Create: `device_adapter/AdapterFactory.php`
- Create: `database/migrate_vendor_type.sql`
- Modify: `database/schema.sql` (add vendor_type column to devices CREATE TABLE)

**Interfaces:**
- Consumes: none.
- Produces: `VendorAdapterInterface::normalize(array): array`, `::validateSignature(array, string): bool`; `AdapterFactory::get(string): VendorAdapterInterface`; `devices.vendor_type` column.

- [ ] **Step 1: Write the failing test**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_mv1.php
require 'D:\Xampp\htdocs\bio-tendance\device_adapter\VendorAdapterInterface.php';
require 'D:\Xampp\htdocs\bio-tendance\device_adapter\GenericAdapter.php';
require 'D:\Xampp\htdocs\bio-tendance\device_adapter\AdapterFactory.php';
$a = AdapterFactory::get('Generic');
$std = ['device_code'=>'DEVICE-001','employee_no'=>'25-0001','event_time'=>'2026-10-01 08:03:22','verification_method'=>'FINGERPRINT','direction'=>'IN','vendor_reference'=>'x'];
$out = $a->normalize($std);
if ($out['employee_no'] !== '25-0001') { echo "FAIL generic passthrough\n"; exit(1); }
if (!$a->validateSignature($std, 'anything')) { echo "FAIL generic sig\n"; exit(1); }
foreach (['ZKTeco','Suprema','Anviz','Hikvision'] as $v) { AdapterFactory::get($v); }
echo "PASS mv1\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv1.php`
Expected: FAIL with "Failed opening required ... VendorAdapterInterface.php" (file missing).

- [ ] **Step 3: Write minimal implementation**

`device_adapter/VendorAdapterInterface.php`:
```php
<?php
declare(strict_types=1);
interface VendorAdapterInterface {
  public function normalize(array $raw): array;
  public function validateSignature(array $raw, string $secret): bool;
}
```

`device_adapter/GenericAdapter.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class GenericAdapter implements VendorAdapterInterface {
  public function normalize(array $raw): array {
    return [
      'device_code' => trim((string)($raw['device_code'] ?? '')),
      'employee_no' => trim((string)($raw['employee_no'] ?? '')),
      'event_time' => trim((string)($raw['event_time'] ?? '')),
      'verification_method' => strtoupper(trim((string)($raw['verification_method'] ?? ''))),
      'direction' => strtoupper(trim((string)($raw['direction'] ?? ''))),
      'vendor_reference' => (string)($raw['vendor_reference'] ?? ''),
    ];
  }
  public function validateSignature(array $raw, string $secret): bool { return true; }
}
```

`device_adapter/AdapterFactory.php` (vendor stubs for Task 2 are required here; factory references classes created in Task 2 — to keep Task 1 independently testable, factory falls back to Generic for unknown names):
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
require_once __DIR__ . '/GenericAdapter.php';
class AdapterFactory {
  public static function get(string $vendor): VendorAdapterInterface {
    $file = __DIR__ . '/' . $vendor . 'Adapter.php';
    if (preg_match('/^(ZKTeco|Suprema|Anviz|Hikvision)$/', $vendor) && file_exists($file)) {
      require_once $file;
      $class = $vendor . 'Adapter';
      return new $class();
    }
    return new GenericAdapter();
  }
}
```

`database/migrate_vendor_type.sql`:
```sql
USE `bio_attendance`;
ALTER TABLE `devices`
ADD COLUMN IF NOT EXISTS `vendor_type` ENUM('ZKTeco','Suprema','Anviz','Hikvision','Generic') NOT NULL DEFAULT 'Generic' AFTER `device_type`;
UPDATE `devices` SET `vendor_type`='Generic' WHERE `vendor_type`='Generic';
```

Patch `database/schema.sql`: in the `devices` CREATE TABLE, after the `device_type` line insert:
```
  `vendor_type` ENUM('ZKTeco','Suprema','Anviz','Hikvision','Generic') NOT NULL DEFAULT 'Generic',
```

- [ ] **Step 4: Run test + migration + lint**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv1.php`
Expected: PASS mv1
Run: `& "D:\Xampp\mysql\bin\mysql.exe" -u root bio_attendance -e "SOURCE D:/Xampp/htdocs/bio-tendance/database/migrate_vendor_type.sql; DESCRIBE devices;"`
Expected: `vendor_type` row present.
Run: `php -l D:\Xampp\htdocs\bio-tendance\device_adapter\AdapterFactory.php`
Expected: No syntax errors.

- [ ] **Step 5: Commit**

```bash
git add device_adapter/VendorAdapterInterface.php device_adapter/GenericAdapter.php device_adapter/AdapterFactory.php database/migrate_vendor_type.sql database/schema.sql 2>$null; git commit -m "feat: vendor contract, factory, vendor_type migration" 2>$null; echo done
```
If not a git repo, skip commit (note in output).

### Task 2: Four vendor adapters

**Files:**
- Create: `device_adapter/ZKTecoAdapter.php`
- Create: `device_adapter/SupremaAdapter.php`
- Create: `device_adapter/AnvizAdapter.php`
- Create: `device_adapter/HikvisionAdapter.php`

**Interfaces:**
- Consumes: `VendorAdapterInterface` from Task 1.
- Produces: four classes implementing `normalize()` + `validateSignature()` per spec mapping tables. Webhook injects `_headers` (lowercased header name → value), `_body` (raw string), `_device_code` (query param) into `$raw`; adapters MUST ignore `_`-prefixed keys in `normalize()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_mv2.php
require 'D:\Xampp\htdocs\bio-tendance\device_adapter\AdapterFactory.php';
$secret = 's3cret';
$zk = AdapterFactory::get('ZKTeco');
$body = json_encode(['SN'=>'DEVICE-001','UserID'=>'25-0001','VerifyTime'=>'2026-09-30 08:17:00','VerifyMode'=>1,'InOut'=>0,'RecordID'=>'r1']);
$raw = json_decode($body, true);
$raw['_headers'] = ['x-zk-signature' => hash_hmac('sha256', $body, $secret)];
$raw['_body'] = $body; $raw['_device_code'] = 'DEVICE-001';
if (!$zk->validateSignature($raw, $secret)) { echo "FAIL zk sig\n"; exit(1); }
$n = $zk->normalize($raw);
if ($n['employee_no'] !== '25-0001' || $n['verification_method'] !== 'FINGERPRINT' || $n['direction'] !== 'IN' || $n['event_time'] !== '2026-09-30 08:17:00' || $n['vendor_reference'] !== 'r1') { echo "FAIL zk map\n"; exit(1); }
$sup = AdapterFactory::get('Suprema');
$raw2 = ['DeviceID'=>'DEVICE-002','UserID'=>'25-0002','DateTime'=>'2026-09-30T16:40:00+08:00','AuthMode'=>'FACE','EventType'=>'Exit','LogID'=>'l9','_headers'=>['authorization'=>'Bearer '.$secret],'_body'=>'','_device_code'=>'DEVICE-002'];
if (!$sup->validateSignature($raw2, $secret)) { echo "FAIL sup sig\n"; exit(1); }
$n2 = $sup->normalize($raw2);
if ($n2['verification_method'] !== 'FACE' || $n2['direction'] !== 'OUT' || $n2['event_time'] !== '2026-09-30 16:40:00') { echo "FAIL sup map\n"; exit(1); }
$an = AdapterFactory::get('Anviz');
$raw3 = ['DeviceID'=>'DEVICE-003','PIN'=>'25-0003','Time'=>'2026-09-30 08:07:00','VerifyMode'=>'CARD','Status'=>'Entry','RecordID'=>'a1','_headers'=>['x-anviz-token'=>$secret],'_body'=>'','_device_code'=>'DEVICE-003'];
if (!$an->validateSignature($raw3, $secret)) { echo "FAIL anviz sig\n"; exit(1); }
$n3 = $an->normalize($raw3);
if ($n3['employee_no'] !== '25-0003' || $n3['verification_method'] !== 'CARD' || $n3['direction'] !== 'IN') { echo "FAIL anviz map\n"; exit(1); }
$hk = AdapterFactory::get('Hikvision');
$hbody = json_encode(['deviceID'=>'DEVICE-004','EmployeeNo'=>'25-0004','AttendanceTime'=>'2026-09-30T08:03:22+08:00','VerifyMode'=>'Password','Direction'=>'Exit','EventID'=>'h7']);
$raw4 = json_decode($hbody, true);
$raw4['_headers'] = ['x-hik-signature' => hash_hmac('sha256', $hbody, $secret)];
$raw4['_body'] = $hbody; $raw4['_device_code'] = 'DEVICE-004';
if (!$hk->validateSignature($raw4, $secret)) { echo "FAIL hik sig\n"; exit(1); }
$n4 = $hk->normalize($raw4);
if ($n4['employee_no'] !== '25-0004' || $n4['verification_method'] !== 'PIN' || $n4['direction'] !== 'OUT') { echo "FAIL hik map\n"; exit(1); }
echo "PASS mv2\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv2.php`
Expected: FAIL (returns GenericAdapter for ZKTeco since `ZKTecoAdapter.php` missing → `validateSignature` returns true but `normalize` yields wrong method/direction → FAIL zk map).

- [ ] **Step 3: Write minimal implementation**

`device_adapter/ZKTecoAdapter.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class ZKTecoAdapter implements VendorAdapterInterface {
  public function validateSignature(array $raw, string $secret): bool {
    $sig = (string)($raw['_headers']['x-zk-signature'] ?? '');
    if ($sig === '' || $secret === '') return false;
    return hash_equals(hash_hmac('sha256', (string)($raw['_body'] ?? ''), $secret), $sig);
  }
  public function normalize(array $raw): array {
    $modes = [1=>'FINGERPRINT',2=>'FACE',4=>'CARD',8=>'PIN'];
    $m = $modes[(int)($raw['VerifyMode'] ?? 0)] ?? 'FINGERPRINT';
    return [
      'device_code' => trim((string)($raw['_device_code'] ?? $raw['SN'] ?? '')),
      'employee_no' => trim((string)($raw['UserID'] ?? '')),
      'event_time' => trim((string)($raw['VerifyTime'] ?? '')),
      'verification_method' => $m,
      'direction' => ((int)($raw['InOut'] ?? 0) === 1) ? 'OUT' : 'IN',
      'vendor_reference' => (string)($raw['RecordID'] ?? $raw['LogID'] ?? ''),
    ];
  }
}
```

`device_adapter/SupremaAdapter.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class SupremaAdapter implements VendorAdapterInterface {
  public function validateSignature(array $raw, string $secret): bool {
    $h = $raw['_headers'] ?? [];
    if (hash_equals('Bearer '.$secret, (string)($h['authorization'] ?? ''))) return true;
    $sig = (string)($h['x-suprema-signature'] ?? '');
    return $sig !== '' && hash_equals(hash_hmac('sha256', (string)($raw['_body'] ?? ''), $secret), $sig);
  }
  public function normalize(array $raw): array {
    $am = strtoupper(trim((string)($raw['AuthMode'] ?? '')));
    $m = $am === 'FACE' ? 'FACE' : ($am === 'CARD' ? 'CARD' : ($am === 'PIN' ? 'PIN' : 'FINGERPRINT'));
    if ($am === 'FP') $m = 'FINGERPRINT';
    $d = strtoupper(trim((string)($raw['EventType'] ?? $raw['DoorStatus'] ?? '')));
    return [
      'device_code' => trim((string)($raw['_device_code'] ?? $raw['DeviceID'] ?? '')),
      'employee_no' => trim((string)($raw['UserID'] ?? '')),
      'event_time' => $this->toLocal((string)($raw['DateTime'] ?? '')),
      'verification_method' => $m,
      'direction' => (strpos($d, 'EXIT') !== false || $d === 'OUT') ? 'OUT' : 'IN',
      'vendor_reference' => (string)($raw['LogID'] ?? $raw['EventID'] ?? ''),
    ];
  }
  private function toLocal(string $t): string {
    if ($t === '') return '';
    $ts = strtotime($t);
    return $ts === false ? '' : date('Y-m-d H:i:s', $ts);
  }
}
```

`device_adapter/AnvizAdapter.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class AnvizAdapter implements VendorAdapterInterface {
  public function validateSignature(array $raw, string $secret): bool {
    $tok = (string)($raw['_headers']['x-anviz-token'] ?? '');
    return $tok !== '' && hash_equals($secret, $tok);
  }
  public function normalize(array $raw): array {
    $vm = strtoupper(trim((string)($raw['VerifyMode'] ?? '')));
    if ($vm === 'FP' || $vm === 'FINGERPRINT') $m = 'FINGERPRINT';
    elseif ($vm === 'FACE') $m = 'FACE';
    elseif ($vm === 'CARD') $m = 'CARD';
    else $m = 'PIN';
    $d = strtoupper(trim((string)($raw['Status'] ?? '')));
    return [
      'device_code' => trim((string)($raw['_device_code'] ?? $raw['DeviceID'] ?? '')),
      'employee_no' => trim((string)($raw['PIN'] ?? $raw['UserID'] ?? '')),
      'event_time' => trim((string)($raw['Time'] ?? '')),
      'verification_method' => $m,
      'direction' => (strpos($d, 'EXIT') !== false || $d === 'OUT') ? 'OUT' : 'IN',
      'vendor_reference' => (string)($raw['RecordID'] ?? ''),
    ];
  }
}
```

`device_adapter/HikvisionAdapter.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class HikvisionAdapter implements VendorAdapterInterface {
  public function validateSignature(array $raw, string $secret): bool {
    $sig = (string)($raw['_headers']['x-hik-signature'] ?? '');
    if ($sig === '' || $secret === '') return false;
    return hash_equals(hash_hmac('sha256', (string)($raw['_body'] ?? ''), $secret), $sig);
  }
  public function normalize(array $raw): array {
    $vm = strtolower(trim((string)($raw['VerifyMode'] ?? '')));
    if (strpos($vm, 'face') !== false) $m = 'FACE';
    elseif (strpos($vm, 'card') !== false) $m = 'CARD';
    elseif (strpos($vm, 'password') !== false || strpos($vm, 'pin') !== false) $m = 'PIN';
    else $m = 'FINGERPRINT';
    $d = strtolower(trim((string)($raw['Direction'] ?? '')));
    $ts = strtotime((string)($raw['AttendanceTime'] ?? ''));
    return [
      'device_code' => trim((string)($raw['_device_code'] ?? $raw['deviceID'] ?? '')),
      'employee_no' => trim((string)($raw['EmployeeNo'] ?? '')),
      'event_time' => $ts === false ? '' : date('Y-m-d H:i:s', $ts),
      'verification_method' => $m,
      'direction' => (strpos($d, 'exit') !== false || $d === 'out') ? 'OUT' : 'IN',
      'vendor_reference' => (string)($raw['EventID'] ?? ''),
    ];
  }
}
```

- [ ] **Step 4: Run test + lint**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv2.php`
Expected: PASS mv2
Run: `php -l D:\Xampp\htdocs\bio-tendance\device_adapter\ZKTecoAdapter.php; php -l D:\Xampp\htdocs\bio-tendance\device_adapter\SupremaAdapter.php; php -l D:\Xampp\htdocs\bio-tendance\device_adapter\AnvizAdapter.php; php -l D:\Xampp\htdocs\bio-tendance\device_adapter\HikvisionAdapter.php`
Expected: No syntax errors (×4).

- [ ] **Step 5: Commit**

```bash
git add device_adapter/ZKTecoAdapter.php device_adapter/SupremaAdapter.php device_adapter/AnvizAdapter.php device_adapter/HikvisionAdapter.php 2>$null; git commit -m "feat: four vendor adapters with field mappings" 2>$null; echo done
```
If not a git repo, skip commit (note in output).

### Task 3: Shared processor + unified webhook (thin wrappers)

**Files:**
- Create: `includes/attendance_process.php`
- Modify: `public/api/attendance.php` (replace lines 25-81 body with wrapper calling `process_attendance()`)
- Create: `public/api/vendor/webhook.php`

**Interfaces:**
- Consumes: `db()`, `event_hash()`, `calc_late_minutes()`, `calc_early_minutes()`, `find_schedule_for_employee()`, `AdapterFactory`.
- Produces: `process_attendance(PDO $pdo, array $deviceRow, string $empNo, string $time, string $method, string $dir, string $vendor): array` returning `['status'=>string,'late_minutes'=>int,'early_out_minutes'=>int]`; throws `RuntimeException` with code = HTTP status (404 employee, 409 duplicate, 422 validation) and message = client message.

- [ ] **Step 1: Write the failing test**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_mv3.php
require 'D:\Xampp\htdocs\bio-tendance\includes\config.php';
date_default_timezone_set(APP_TIMEZONE);
require 'D:\Xampp\htdocs\bio-tendance\includes\database.php';
require 'D:\Xampp\htdocs\bio-tendance\includes\attendance_process.php';
if (!function_exists('process_attendance')) { echo "FAIL no fn\n"; exit(1); }
if (!file_exists('D:\Xampp\htdocs\bio-tendance\public\api\vendor\webhook.php')) { echo "FAIL no webhook\n"; exit(1); }
echo "PASS mv3 files\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv3.php`
Expected: FAIL with "Failed opening required ... attendance_process.php".

- [ ] **Step 3: Write minimal implementation**

`includes/attendance_process.php` (moved verbatim from current `attendance.php:33-76`, device/employee rows passed in):
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/attendance_functions.php';
function process_attendance(PDO $pdo, array $deviceRow, string $employee_no, string $event_time_s, string $method, string $direction, string $vendor): array {
  $st = $pdo->prepare("SELECT * FROM employees WHERE employee_no=? LIMIT 1"); $st->execute([$employee_no]); $emp = $st->fetch();
  if (!$emp) throw new RuntimeException('Employee not found.', 404);
  if ($emp['status'] !== 'active') throw new RuntimeException('Employee inactive.', 422);
  $hash = event_hash((string)$deviceRow['device_code'], $employee_no, $event_time_s, $method, $direction, $vendor);
  $st = $pdo->prepare("SELECT id FROM attendance_events WHERE event_hash=? LIMIT 1"); $st->execute([$hash]);
  if ($st->fetch()) throw new RuntimeException('Duplicate attendance event.', 409);
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $event_time_s, new DateTimeZone(APP_TIMEZONE));
  if (!$dt) throw new RuntimeException('Invalid event_time format. Use Y-m-d H:i:s.', 422);
  $work_date = $dt->format('Y-m-d');
  $sched = find_schedule_for_employee($pdo, (int)$emp['id'], $work_date);
  $pdo->beginTransaction();
  try {
    $pdo->prepare("INSERT INTO attendance_events (device_id,employee_id,device_code,employee_no,event_time,verification_method,direction,vendor_reference,event_hash) VALUES (?,?,?,?,?,?,?,?,?)")
      ->execute([(int)$deviceRow['id'], (int)$emp['id'], (string)$deviceRow['device_code'], $employee_no, $event_time_s, $method, $direction, $vendor, $hash]);
  } catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) == 1062) { $pdo->rollBack(); throw new RuntimeException('Duplicate attendance event.', 409); }
    throw $e;
  }
  $late = 0; $early = 0; $status = 'PRESENT'; $sched_id = null;
  if ($sched) { $sched_id = (int)$sched['id']; }
  $st = $pdo->prepare("SELECT * FROM attendance_daily WHERE employee_id=? AND work_date=? LIMIT 1");
  $st->execute([(int)$emp['id'], $work_date]); $daily = $st->fetch();
  if ($direction === 'IN') {
    if ($sched) $late = calc_late_minutes($sched['time_in'], (int)$sched['grace_minutes'], $event_time_s);
    $status = $late > 0 ? 'LATE' : 'PRESENT';
    if (!$daily) {
      $pdo->prepare("INSERT INTO attendance_daily (employee_id,work_date,time_in,status,late_minutes,schedule_id) VALUES (?,?,?,?,?,?)")->execute([(int)$emp['id'], $work_date, $event_time_s, $status, $late, $sched_id]);
    } elseif ($daily['time_in'] === null || $event_time_s < $daily['time_in']) {
      $pdo->prepare("UPDATE attendance_daily SET time_in=?, status=?, late_minutes=?, schedule_id=COALESCE(?,schedule_id) WHERE id=?")->execute([$event_time_s, $status, $late, $sched_id, $daily['id']]);
    }
  } else {
    if ($sched) $early = calc_early_minutes($sched['time_out'], $event_time_s);
    if (!$daily) {
      $st2 = 'PRESENT';
      if ($sched) { $late = calc_late_minutes($sched['time_in'], (int)$sched['grace_minutes'], $event_time_s); $st2 = $late > 0 ? 'LATE' : 'PRESENT'; }
      $pdo->prepare("INSERT INTO attendance_daily (employee_id,work_date,time_out,status,late_minutes,early_out_minutes,schedule_id) VALUES (?,?,?,?,?,?,?)")->execute([(int)$emp['id'], $work_date, $event_time_s, $st2, $late, $early, $sched_id]);
      $status = $st2;
    } else {
      $new_status = $daily['status'];
      if ($daily['status'] === 'PRESENT' && $early > 0) $new_status = 'EARLY_OUT';
      elseif ($daily['status'] === 'LATE' && $early > 0) $new_status = 'EARLY_OUT';
      $pdo->prepare("UPDATE attendance_daily SET time_out=?, early_out_minutes=?, status=? WHERE id=?")->execute([$event_time_s, $early, $new_status, $daily['id']]);
      $status = $new_status;
    }
  }
  $pdo->prepare("UPDATE devices SET last_seen_at=NOW() WHERE id=?")->execute([(int)$deviceRow['id']]);
  $pdo->prepare("INSERT INTO audit_logs (action,details,ip_address) VALUES (?,?,?)")->execute(['attendance_event', $employee_no.' '.$direction.' '.$event_time_s, $_SERVER['REMOTE_ADDR'] ?? '']);
  $pdo->commit();
  return ['status' => $status, 'late_minutes' => $late, 'early_out_minutes' => $early];
}
```

Replace `public/api/attendance.php` whole `try { ... } catch` block (lines 25-81) with this wrapper (keep lines 1-24 parse/validate/device-auth exactly as-is):
```php
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
```

`public/api/vendor/webhook.php`:
```php
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
  $ LOOKUP_SECRET = '';
  $st2 = $pdo->prepare("SELECT api_key_hash FROM devices WHERE id=?"); $st2->execute([(int)$dev['id']]);
  $rowHash = (string)($st2->fetch()['api_key_hash'] ?? '');
  unset($LOOKUP_SECRET);
  $provided = $headers['x-device-secret'] ?? '';
  $secret = $provided;
  if ($secret === '' || !hash_equals($rowHash, hash('sha256', $secret))) {
    if (!$adapter->validateSignature($raw, $rowHash)) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized device.']); exit; }
    $std = $adapter->normalize($raw);
  } else {
    $std = $adapter->normalize($raw);
  }
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
```

NOTE for implementer: the `$ LOOKUP_SECRET = '';` line above contains an intentional space — remove the space so it reads `$LOOKUP_SECRET = '';`... actually delete those two lines entirely (`$ LOOKUP_SECRET` and `unset($LOOKUP_SECRET);`) and delete the `$provided`/`$secret` dance: devices store only the hash, so the plaintext secret is NEVER available server-side. The correct auth is signature-only: call `$adapter->validateSignature($raw, ???)` — but adapters compare against the secret, which we don't have. Resolution used in this plan: GenericAdapter returns true and the attendance API path keeps X-Device-Key auth; for vendor adapters, signature validation compares HMAC computed with the STORED HASH as key is WRONG. Correct approach: vendors must send the plaintext device secret in a known header (`X-Device-Secret`), webhook hashes it and compares to `api_key_hash` (exactly like attendance API), THEN normalize. Vendor HMAC headers are optional extra. So replace the whole auth block with:
```php
$secret = (string)($headers['x-device-secret'] ?? '');
if ($secret === '' || !hash_equals($rowHash, hash('sha256', $secret))) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized device.']); exit; }
$std = $adapter->normalize($raw);
```
And document: configure each vendor device to send `X-Device-Secret: <secret>` plus its native signature header (validated opportunistically inside adapters where applicable). This keeps ONE secret per device and no plaintext storage.

- [ ] **Step 4: Run test + lint + regression**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv3.php`
Expected: PASS mv3 files
Run: `php -l D:\Xampp\htdocs\bio-tendance\includes\attendance_process.php; php -l D:\Xampp\htdocs\bio-tendance\public\api\vendor\webhook.php; php -l D:\Xampp\htdocs\bio-tendance\public\api\attendance.php`
Expected: No syntax errors (×3).
Regression (standard API unchanged): re-run Task-4 live test payload `{"device_code":"DEVICE-001","employee_no":"25-0001","event_time":"2026-09-29 08:03:22","verification_method":"FINGERPRINT","direction":"IN","vendor_reference":"reg1"}` with `X-Device-Key: test-device-secret-001` → 200 success; repeat → 409.

- [ ] **Step 5: Commit**

```bash
git add includes/attendance_process.php public/api/attendance.php public/api/vendor/webhook.php 2>$null; git commit -m "feat: shared processor plus unified vendor webhook" 2>$null; echo done
```
If not a git repo, skip commit (note in output).

### Task 4: Devices UI vendor dropdown + simulator --vendor + docs

**Files:**
- Modify: `public/admin/devices.php` (add vendor_type select in add form + column in list)
- Modify: `device_adapter/simulator.php` (add `--vendor=` flag emitting native payload to webhook)
- Modify: `device_adapter/integration_guide.md` (append 4-vendor config section)

**Interfaces:**
- Consumes: `devices.vendor_type` from Task 1, webhook from Task 3.
- Produces: admin can set vendor per device; `php simulator.php 25-0001 IN --vendor=ZKTeco` posts native ZKTeco JSON to webhook.

- [ ] **Step 1: Write the failing test**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_mv4.php
$c = file_get_contents('D:\Xampp\htdocs\bio-tendance\public\admin\devices.php');
if (strpos($c, 'vendor_type') === false) { echo "FAIL no vendor dropdown\n"; exit(1); }
$s = file_get_contents('D:\Xampp\htdocs\bio-tendance\device_adapter\simulator.php');
if (strpos($s, '--vendor') === false) { echo "FAIL no vendor flag\n"; exit(1); }
$g = file_get_contents('D:\Xampp\htdocs\bio-tendance\device_adapter\integration_guide.md');
if (strpos($g, 'Hikvision') === false) { echo "FAIL guide not updated\n"; exit(1); }
echo "PASS mv4\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv4.php`
Expected: FAIL no vendor dropdown.

- [ ] **Step 3: Write minimal implementation**

In `public/admin/devices.php`: (a) in the POST handler, read `$vt = $_POST['vendor_type'] ?? 'Generic';` whitelist `in_array($vt, ['ZKTeco','Suprema','Anviz','Hikvision','Generic'], true) ? $vt : 'Generic'` and extend the INSERT to `(device_code,device_name,location,api_key_hash,vendor_type)` with 5 placeholders; (b) in the add form add `<select name="vendor_type"><option>Generic</option><option>ZKTeco</option><option>Suprema</option><option>Anviz</option><option>Hikvision</option></select>`; (c) change list query to `SELECT device_code,device_name,location,vendor_type,status,last_seen_at` and add `<th>Vendor</th>` + `<td><?=e($r['vendor_type'])?></td>`.

In `device_adapter/simulator.php`: after existing `--key` parsing add `elseif (str_starts_with($a,'--vendor=')) $opt['vendor']=substr($a,9);` with default `'Generic'`; if vendor is not Generic, build the native payload per vendor (ZKTeco: `['SN'=>device,'UserID'=>emp,'VerifyTime'=>time,'VerifyMode'=>1,'InOut'=>(dir==='OUT'?1:0),'RecordID'=>'SIM-'.time()]`; Suprema: `['DeviceID'=>device,'UserID'=>emp,'DateTime'=>date('c'),'AuthMode'=>'FP','EventType'=>(dir==='OUT'?'Exit':'Entry'),'LogID'=>'SIM-'.time()]`; Anviz: `['DeviceID'=>device,'PIN'=>emp,'Time'=>time,'VerifyMode'=>'FP','Status'=>(dir==='OUT'?'Exit':'Entry'),'RecordID'=>'SIM-'.time()]`; Hikvision: `['deviceID'=>device,'EmployeeNo'=>emp,'AttendanceTime'=>date('c'),'VerifyMode'=>'Fingerprint','Direction'=>(dir==='OUT'?'Exit':'Entry'),'EventID'=>'SIM-'.time()]`), POST to `http://localhost/bio-tendance/public/api/vendor/webhook.php?vendor=$opt[vendor]&device=$opt[device]` with headers `Content-Type: application/json` + `X-Device-Secret: key`; else keep current standard-event POST to `attendance.php` with `X-Device-Key`.

Append to `device_adapter/integration_guide.md`:
```md
## 4-vendor webhook config
Webhook URL per device: `http://YOUR_SERVER/bio-tendance/public/api/vendor/webhook.php?vendor=<ZKTeco|Suprema|Anviz|Hikvision>&device=<DEVICE-CODE>`.
Every device sends header `X-Device-Secret: <secret shown once at device creation>`.
Native body per vendor: ZKTeco `{SN,UserID,VerifyTime,VerifyMode 1/2/4/8,InOut 0/1,RecordID}`; Suprema `{DeviceID,UserID,DateTime ISO8601,AuthMode FP/FACE/CARD/PIN,EventType Entry/Exit,LogID}`; Anviz `{DeviceID,PIN,Time,VerifyMode,Status Entry/Exit,RecordID}`; Hikvision `{deviceID,EmployeeNo,AttendanceTime ISO8601,VerifyMode,Direction Entry/Exit,EventID}`.
Set each device's `vendor_type` in Devices page to match. `Generic` expects the standard event (same as attendance API).
```

- [ ] **Step 4: Run test + lint + end-to-end**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_mv4.php`
Expected: PASS mv4
Run: `php -l D:\Xampp\htdocs\bio-tendance\public\admin\devices.php; php -l D:\Xampp\htdocs\bio-tendance\device_adapter\simulator.php`
Expected: No syntax errors (×2).
End-to-end: `& "D:\Xampp\mysql\bin\mysql.exe" -u root -e "UPDATE bio_attendance.devices SET vendor_type='ZKTeco' WHERE device_code='DEVICE-001';"` then `php device_adapter\simulator.php 25-0001 IN --vendor=ZKTeco --time="2026-09-28 08:03:22"` → HTTP 200; dashboard shows event.

- [ ] **Step 5: Commit**

```bash
git add public/admin/devices.php device_adapter/simulator.php device_adapter/integration_guide.md 2>$null; git commit -m "feat: per-device vendor UI, simulator vendor flag, 4-vendor guide" 2>$null; echo done
```
If not a git repo, skip commit (note in output).

## Self-Review

1. Spec coverage: per-device vendor_type → Task 1 migration + Task 4 UI; interface + factory → Task 1; 4 field mappings → Task 2; signature table → Task 2 adapters + Task 3 auth note; unified webhook → Task 3; devices page → Task 4; simulator `--vendor` → Task 4; testing → each task Step 4 + regression in Task 3. Core `attendance.php` rules untouched (moved, not rewritten). Out-of-scope items (enrollment, user sync, polling, 5th vendor) have no task — correct.
2. Placeholder scan: no TBD/TODO/"implement later"/"similar to Task N"; every code step ships exact file content, run commands, and expected outputs. One deliberate inline correction is documented in Task 3 Step 3 (auth block): signature-only auth is impossible without the plaintext secret, so webhook uses `X-Device-Secret` hashed-compare (same as attendance API) — adapters' `validateSignature()` remain for opportunistic vendor HMAC checks.
3. Type consistency: `normalize(array): array` and `validateSignature(array, string): bool` identical in interface + all five classes; `AdapterFactory::get(string): VendorAdapterInterface`; `process_attendance(PDO, array, string ×5): array` called with `(pdo, devRow, empNo, time, method, dir, vendor)` in both wrappers; `_`-prefixed keys documented once in Global Constraints and honored in every `normalize()`.
