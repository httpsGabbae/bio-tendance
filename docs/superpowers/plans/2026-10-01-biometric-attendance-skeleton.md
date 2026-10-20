# Biometric Attendance Skeleton Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build runnable scanner-agnostic skeleton in `D:\Xampp\htdocs\bio-tendance\` with full DB, login, attendance API, UI stubs, and simulator.

**Architecture:** `device_adapter/simulator.php` posts standard JSON to `public/api/attendance.php`, which validates, auths device via SHA256 hash, stores raw event + upserts daily with late/early calc in a transaction; admin UI in `public/admin/*` guarded by session; shared code in `includes/`.

**Tech Stack:** PHP 8.2, PDO pdo_mysql, MariaDB via XAMPP, Apache, HTML/CSS/vanilla JS, no frameworks.

**Spec:** `docs/superpowers/specs/2026-10-01-biometric-attendance-design.md`

## Global Constraints

- PHP >= 8.0 (tested 8.2.12), PDO with prepared statements only, no string-concatenated SQL.
- Timezone `Asia/Manila` everywhere: `date_default_timezone_set('Asia/Manila')` + `SET time_zone='+08:00'`.
- DB name `bio_attendance`, host `localhost`, user `root`, pass empty by default via env `DB_HOST/DB_NAME/DB_USER/DB_PASS`.
- Passwords via `password_hash(PASSWORD_DEFAULT)` + `password_verify`.
- CSRF token on every admin POST via `csrf_token()/csrf_check()`, output via `e()`.
- Device secret never stored plain; store `hash('sha256', secret)` in `devices.api_key_hash CHAR(64)`, compare with `hash_equals`.
- Event hash `hash('sha256', device_code.'|'.employee_no.'|'.event_time.'|'.method.'|'.direction.'|'.vendor_ref)` with DB UNIQUE.
- No raw fingerprint/face images/templates stored.
- Web entry `public/` → `http://localhost/bio-tendance/public/`; deny direct `includes/`, `database/`.
- Verification methods whitelist: `FINGERPRINT,FACE,CARD,PIN`; direction: `IN,OUT`.
- HTTP codes: 200 ok, 400 bad JSON, 401 device, 404 employee, 409 duplicate, 422 validation; never leak SQL/traces.

---

## File Map (create/modify)

- Create `database/schema.sql` — all 9 tables + indexes + seed.
- Create `includes/config.php` — env + constants.
- Create `includes/database.php` — `db(): PDO`.
- Create `includes/bootstrap.php` — session, timezone, `e()`, `csrf_*`, `json_response()`, `require_login()`.
- Create `includes/auth.php` — `login/logout/current_admin`.
- Create `includes/attendance_functions.php` — `event_hash()`, `calc_late_minutes()`, `calc_early_minutes()`, `find_schedule_for_employee()`, `upsert_daily_from_event()`.
- Create `public/index.php`, `public/logout.php`, `public/assets/style.css`.
- Create `public/admin/dashboard.php`, `employees.php`, `departments.php`, `schedules.php`, `devices.php`, `attendance.php`, `reports.php`.
- Create `public/api/attendance.php` — full algorithm.
- Create `device_adapter/simulator.php`, `device_adapter/integration_guide.md`.
- Create `.htaccess`, `public/.htaccess`, `README.md`, `SETUP.md`, `ALGORITHM.md`.
- Test: `php -l` each PHP file; `http://localhost/bio-tendance/public/` manual; `php device_adapter/simulator.php` end-to-end.

---

### Task 1: Foundation — structure, config, DB, schema

**Files:**
- Create: `database/schema.sql`
- Create: `includes/config.php`
- Create: `includes/database.php`
- Create: `includes/bootstrap.php`
- Create: `.htaccess`
- Create: `public/.htaccess`

**Interfaces:**
- Consumes: none.
- Produces: `db(): PDO`, `e(string): string`, `csrf_token(): string`, `csrf_check(): void`, `json_response(int,array): void`, `require_login(): void`, constants `DB_HOST,DB_NAME,DB_USER,DB_PASS,APP_TIMEZONE`.

- [ ] **Step 1: Write failing check — schema file exists and imports**

```php
<?php
// save as C:\Users\doruc\AppData\Local\Temp\opencode\check_task1.php
$f = 'D:\Xampp\htdocs\bio-tendance\database\schema.sql';
if (!file_exists($f)) { echo "FAIL missing schema.sql\n"; exit(1); }
$c = file_get_contents($f);
foreach (['CREATE TABLE `admins`','CREATE TABLE `attendance_events`','UNIQUE KEY `uq_event_hash`','INSERT INTO `admins`'] as $n) {
  if (strpos($c,$n)===false) { echo "FAIL missing $n\n"; exit(1); }
}
echo "PASS schema present\n";
```

- [ ] **Step 2: Run check to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task1.php`
Expected: FAIL missing schema.sql

- [ ] **Step 3: Create directories + write minimal implementation**

Run dirs (PowerShell): `New-Item -ItemType Directory -Path "D:\Xampp\htdocs\bio-tendance\database","D:\Xampp\htdocs\bio-tendance\includes","D:\Xampp\htdocs\bio-tendance\public\admin","D:\Xampp\htdocs\bio-tendance\public\api","D:\Xampp\htdocs\bio-tendance\public\assets","D:\Xampp\htdocs\bio-tendance\device_adapter" -Force`

Write `includes/config.php`:
```php
<?php
declare(strict_types=1);
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'bio_attendance');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
define('APP_TIMEZONE', 'Asia/Manila');
define('BASE_PATH', dirname(__DIR__));
```

Write `includes/database.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $pdo->exec("SET time_zone='+08:00'");
  return $pdo;
}
```

Write `includes/bootstrap.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
date_default_timezone_set(APP_TIMEZONE);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  $t = $_POST['csrf'] ?? '';
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
    http_response_code(419); echo 'Invalid CSRF token.'; exit;
  }
}
function json_response(int $code, array $data): void {
  http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit;
}
function require_login(): void {
  if (empty($_SESSION['admin_id'])) { header('Location: ../index.php'); exit; }
}
```

Write `.htaccess` (root):
```
<IfModule mod_rewrite.c>
RewriteEngine Off
</IfModule>
<FilesMatch "^(config|database|bootstrap|auth|attendance_functions)\.php$">
Require all denied
</FilesMatch>
```
Simpler robust root `.htaccess`:
```
Require all granted
<Files "config.php">
Require all denied
</Files>
```
Use this exact content for root `.htaccess`:
```
# bio-tendance root — block direct includes/database access
<IfModule mod_authz_core.c>
  <FilesMatch "^(config|database|bootstrap|auth|attendance_functions)\.php$">
    Require all denied
  </FilesMatch>
</IfModule>
RedirectMatch 403 ^.*/includes/.*$
RedirectMatch 403 ^.*/database/.*$
```

Write `public/.htaccess`:
```
Require all granted
```

Write `database/schema.sql` (full, no placeholders):
```sql
CREATE DATABASE IF NOT EXISTS `bio_attendance` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bio_attendance`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL DEFAULT '',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_code` VARCHAR(20) NOT NULL UNIQUE,
  `department_name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `schedule_name` VARCHAR(100) NOT NULL,
  `time_in` TIME NOT NULL,
  `time_out` TIME NOT NULL,
  `grace_minutes` INT NOT NULL DEFAULT 10,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_no` VARCHAR(20) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `middle_name` VARCHAR(50) NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NULL UNIQUE,
  `department_id` INT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_emp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `employee_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `schedule_id` INT NOT NULL,
  `effective_date` DATE NULL,
  UNIQUE KEY `uq_emp_eff` (`employee_id`,`effective_date`),
  CONSTRAINT `fk_es_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_sched` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_code` VARCHAR(30) NOT NULL UNIQUE,
  `device_name` VARCHAR(100) NOT NULL,
  `device_type` VARCHAR(50) NOT NULL DEFAULT 'BIOMETRIC',
  `location` VARCHAR(100) NOT NULL DEFAULT '',
  `api_key_hash` CHAR(64) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_seen_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `attendance_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `device_code` VARCHAR(30) NOT NULL,
  `employee_no` VARCHAR(20) NOT NULL,
  `event_time` DATETIME NOT NULL,
  `verification_method` ENUM('FINGERPRINT','FACE','CARD','PIN') NOT NULL,
  `direction` ENUM('IN','OUT') NOT NULL,
  `vendor_reference` VARCHAR(100) NOT NULL DEFAULT '',
  `event_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_event_hash` (`event_hash`),
  KEY `idx_emp_time` (`employee_id`,`event_time`),
  CONSTRAINT `fk_ev_dev` FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ev_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `attendance_daily` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `work_date` DATE NOT NULL,
  `time_in` DATETIME NULL,
  `time_out` DATETIME NULL,
  `status` ENUM('PRESENT','LATE','ABSENT','EARLY_OUT','ON_LEAVE','REST_DAY','HOLIDAY') NOT NULL DEFAULT 'PRESENT',
  `late_minutes` INT NOT NULL DEFAULT 0,
  `early_out_minutes` INT NOT NULL DEFAULT 0,
  `schedule_id` INT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_emp_date` (`employee_id`,`work_date`),
  CONSTRAINT `fk_daily_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_daily_sched` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- Seed (password Admin123! hashed at install; device secret test-device-secret-001 sha256)
INSERT INTO `departments` (`department_code`,`department_name`) VALUES ('CCTE','College of Computer and Technology Education')
ON DUPLICATE KEY UPDATE department_name=VALUES(department_name);
INSERT INTO `schedules` (`schedule_name`,`time_in`,`time_out`,`grace_minutes`) VALUES ('Day Shift','08:00:00','17:00:00',10)
ON DUPLICATE KEY UPDATE schedule_name=VALUES(schedule_name);
INSERT INTO `devices` (`device_code`,`device_name`,`location`,`api_key_hash`) VALUES ('DEVICE-001','Main Entrance','Building A', SHA2('test-device-secret-001',256))
ON DUPLICATE KEY UPDATE device_name=VALUES(device_name);
```

Note: admin + employee seeds go in Task 2/3 via PHP (need `password_hash`), not raw SQL. Schema import must succeed without them.

- [ ] **Step 4: Run check + lint + import**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task1.php`
Expected: PASS
Run: `php -l D:\Xampp\htdocs\bio-tendance\includes\config.php; php -l D:\Xampp\htdocs\bio-tendance\includes\database.php; php -l D:\Xampp\htdocs\bio-tendance\includes\bootstrap.php`
Expected: No syntax errors
Run: `& "D:\Xampp\mysql\bin\mysql.exe" -u root -e "SOURCE D:/Xampp/htdocs/bio-tendance/database/schema.sql; SHOW TABLES IN bio_attendance;"`
Expected: 8 tables listed.

- [ ] **Step 5: Commit**

```bash
git add docs includes database .htaccess public/.htaccess 2>$null; git commit -m "feat: foundation config, DB, schema" 2>$null; echo done
```
If not a git repo, skip commit (note in output).

### Task 2: Auth + login shell + dashboard

**Files:**
- Create: `includes/auth.php`
- Create: `public/index.php`
- Create: `public/logout.php`
- Create: `public/assets/style.css`
- Create: `public/admin/dashboard.php`

**Interfaces:**
- Consumes: `db()` from Task 1.
- Produces: `login(string,string): bool`, `logout(): void`, `current_admin(): ?array`.

- [ ] **Step 1: Write failing test (login + guard)**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_task2.php
require 'D:\Xampp\htdocs\bio-tendance\includes\bootstrap.php';
require 'D:\Xampp\htdocs\bio-tendance\includes\database.php';
require 'D:\Xampp\htdocs\bio-tendance\includes\auth.php';
foreach (['public/index.php','public/admin/dashboard.php','includes/auth.php'] as $f) {
  if (!file_exists('D:\Xampp\htdocs\bio-tendance\\'.$f)) { echo "FAIL missing $f\n"; exit(1); }
}
echo "PASS files present\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task2.php`
Expected: FAIL missing includes/auth.php

- [ ] **Step 3: Write minimal implementation**

`includes/auth.php`:
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
function login(string $username, string $password): bool {
  $st = db()->prepare("SELECT * FROM admins WHERE username=? AND status='active' LIMIT 1");
  $st->execute([$username]);
  $a = $st->fetch();
  if (!$a || !password_verify($password, $a['password_hash'])) return false;
  $_SESSION['admin_id'] = (int)$a['id'];
  $_SESSION['admin_user'] = $a['username'];
  return true;
}
function logout(): void { $_SESSION = []; if (session_status()===PHP_SESSION_ACTIVE) session_destroy(); }
function current_admin(): ?array {
  if (empty($_SESSION['admin_id'])) return null;
  $st = db()->prepare("SELECT id, username, full_name FROM admins WHERE id=? LIMIT 1");
  $st->execute([$_SESSION['admin_id']]);
  $r = $st->fetch(); return $r ?: null;
}
```

`public/assets/style.css` (clean, responsive, print):
```css
*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;background:#f4f6f8;color:#222}
.wrap{max-width:1000px;margin:0 auto;padding:16px}
.card{background:#fff;border-radius:8px;padding:16px;margin:12px 0;box-shadow:0 1px 3px rgba(0,0,0,.1)}
table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}
.btn{background:#1a73e8;color:#fff;border:0;padding:8px 14px;border-radius:4px;cursor:pointer}
.nav a{margin-right:12px}
@media print{.nav,.noprint{display:none}body{background:#fff}}
```

`public/index.php`:
```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
if (!empty($_SESSION['admin_id'])) { header('Location: admin/dashboard.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $u = trim($_POST['username'] ?? ''); $p = (string)($_POST['password'] ?? '');
  // ensure default admin exists (first-run seed)
  $c = db()->query("SELECT COUNT(*) c FROM admins")->fetch()['c'];
  if ((int)$c === 0) {
    $h = password_hash('Admin123!', PASSWORD_DEFAULT);
    db()->prepare("INSERT INTO admins (username,password_hash,full_name) VALUES (?,?,?)")->execute(['admin',$h,'Administrator']);
  }
  if (login($u,$p)) { header('Location: admin/dashboard.php'); exit; }
  $err = 'Invalid username or password.';
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login — Bio Attendance</title><link rel="stylesheet" href="assets/style.css"></head>
<body><div class="wrap"><div class="card"><h2>Biometric Attendance — Admin Login</h2>
<?php if($err): ?><p style="color:red"><?=e($err)?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p>Username<br><input name="username" required></p>
<p>Password<br><input type="password" name="password" required></p>
<p><button class="btn">Login</button></p>
<p><small>Default: admin / Admin123! (created on first login attempt if empty)</small></p>
</form></div></div></body></html>
```

`public/logout.php`:
```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
logout(); header('Location: index.php'); exit;
```

`public/admin/dashboard.php`:
```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_login();
$today = date('Y-m-d');
$counts = ['PRESENT'=>0,'LATE'=>0,'ABSENT'=>0,'EARLY_OUT'=>0];
try { $rows = db()->prepare("SELECT status, COUNT(*) c FROM attendance_daily WHERE work_date=? GROUP BY status"); $rows->execute([$today]); foreach ($rows as $r) $counts[$r['status']] = (int)$r['c']; } catch (Throwable $e) {}
$recent = [];
try { $q = db()->prepare("SELECT employee_no, device_code, event_time, direction FROM attendance_events ORDER BY event_time DESC LIMIT 10"); $q->execute(); $recent = $q->fetchAll(); } catch (Throwable $e) {}
$devs = [];
try { $devs = db()->query("SELECT device_code, status, last_seen_at FROM devices ORDER BY device_code")->fetchAll(); } catch (Throwable $e) {}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard</title><link rel="stylesheet" href="../assets/style.css"></head>
<body><div class="wrap"><div class="nav card"><a href="dashboard.php">Dashboard</a><a href="employees.php">Employees</a><a href="departments.php">Departments</a><a href="schedules.php">Schedules</a><a href="devices.php">Devices</a><a href="attendance.php">Attendance</a><a href="reports.php">Reports</a><a href="../logout.php">Logout (<?=e($_SESSION['admin_user']??'')?>)</a></div>
<div class="card"><h2>Today <?=e($today)?> — Present <?= (int)$counts['PRESENT'] ?> · Late <?= (int)$counts['LATE'] ?> · Absent <?= (int)$counts['ABSENT'] ?> · Early-out <?= (int)$counts['EARLY_OUT'] ?></h2></div>
<div class="card"><h3>Recent events</h3><table><tr><th>Employee</th><th>Device</th><th>Time</th><th>Dir</th></tr><?php foreach($recent as $r): ?><tr><td><?=e($r['employee_no'])?></td><td><?=e($r['device_code'])?></td><td><?=e($r['event_time'])?></td><td><?=e($r['direction'])?></td></tr><?php endforeach; ?></table></div>
<div class="card"><h3>Devices</h3><table><tr><th>Code</th><th>Status</th><th>Last seen</th></tr><?php foreach($devs as $d): ?><tr><td><?=e($d['device_code'])?></td><td><?=e($d['status'])?></td><td><?=e((string)$d['last_seen_at'])?></td></tr><?php endforeach; ?></table></div>
</div></body></html>
```

- [ ] **Step 4: Run test + lint + browser**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task2.php`
Expected: PASS
Run: `php -l D:\Xampp\htdocs\bio-tendance\includes\auth.php; php -l D:\Xampp\htdocs\bio-tendance\public\index.php`
Expected: No syntax errors
Manual: open `http://localhost/bio-tendance/public/` → login `admin/Admin123!` → dashboard counts show.

- [ ] **Step 5: Commit**

```bash
git add includes/auth.php public/index.php public/logout.php public/assets public/admin/dashboard.php 2>$null; git commit -m "feat: admin login and dashboard" 2>$null; echo done
```

### Task 3: Master data — departments, employees, schedules, devices CRUD stubs

**Files:**
- Create: `public/admin/departments.php`
- Create: `public/admin/employees.php`
- Create: `public/admin/schedules.php`
- Create: `public/admin/devices.php`

**Interfaces:**
- Consumes: `db()`, `require_login()`, `csrf_*`, `e()`.
- Produces: working list+add+toggle-active for each entity; device creation shows secret once (hash stored).

- [ ] **Step 1: Write failing test**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_task3.php
foreach (['public/admin/employees.php','public/admin/departments.php','public/admin/schedules.php','public/admin/devices.php'] as $f) {
  if (!file_exists('D:\Xampp\htdocs\bio-tendance\\'.$f)) { echo "FAIL $f\n"; exit(1); }
}
echo "PASS crud present\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task3.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation (same pattern ×4, copy exact code)**

`public/admin/departments.php`:
```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $code=trim($_POST['code']??''); $name=trim($_POST['name']??'');
  if ($code!=='' && $name!=='') { try { db()->prepare("INSERT INTO departments (department_code,department_name) VALUES (?,?)")->execute([$code,$name]); $msg='Saved.'; } catch (Throwable $e) { $msg='Error: '.$e->getMessage(); } }
}
$rows = db()->query("SELECT * FROM departments ORDER BY department_code")->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="wrap"><div class="nav card"><a href="dashboard.php">Dashboard</a><a href="../logout.php">Logout</a></div><div class="card"><h2>Departments</h2><p><?=e($msg)?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input name="code" placeholder="CCTE" required><input name="name" placeholder="Full name" required><button class="btn">Add</button></form><table><tr><th>Code</th><th>Name</th><th>Status</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['department_code'])?></td><td><?=e($r['department_name'])?></td><td><?=e($r['status'])?></td></tr><?php endforeach; ?></table></div></div></body></html>
```

`public/admin/schedules.php`:
```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $n=trim($_POST['name']??''); $in=$_POST['time_in']??''; $out=$_POST['time_out']??''; $g=(int)($_POST['grace']??10);
  if ($n!==''&&$in!==''&&$out!=='') { try { db()->prepare("INSERT INTO schedules (schedule_name,time_in,time_out,grace_minutes) VALUES (?,?,?,?)")->execute([$n,$in,$out,$g]); $msg='Saved.'; } catch (Throwable $e){ $msg='Error: '.$e->getMessage(); } }
}
$rows=db()->query("SELECT * FROM schedules ORDER BY id")->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="wrap"><div class="nav card"><a href="dashboard.php">Dashboard</a><a href="../logout.php">Logout</a></div><div class="card"><h2>Schedules</h2><p><?=e($msg)?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input name="name" placeholder="Day Shift" required><input type="time" name="time_in" required><input type="time" name="time_out" required><input type="number" name="grace" value="10" min="0" max="120"><button class="btn">Add</button></form><table><tr><th>Name</th><th>In</th><th>Out</th><th>Grace</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['schedule_name'])?></td><td><?=e($r['time_in'])?></td><td><?=e($r['time_out'])?></td><td><?=e((string)$r['grace_minutes'])?></td></tr><?php endforeach; ?></table></div></div></body></html>
```

`public/admin/employees.php`:
```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $no=trim($_POST['employee_no']??''); $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??''); $dept=$_POST['department_id']??null; $sched=$_POST['schedule_id']??null;
  if ($no!==''&&$fn!==''&&$ln!=='') { try {
    $pdo=db(); $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO employees (employee_no,first_name,last_name,department_id) VALUES (?,?,?,?)")->execute([$no,$fn,$ln,$dept?:null]);
    $eid=(int)$pdo->lastInsertId();
    if ($sched) $pdo->prepare("INSERT INTO employee_schedules (employee_id,schedule_id) VALUES (?,?)")->execute([$eid,$sched]);
    $pdo->commit(); $msg='Saved.';
  } catch (Throwable $e){ if(db()->inTransaction()) db()->rollBack(); $msg='Error: '.$e->getMessage(); } }
}
$q=trim($_GET['q']??'');
if ($q!=='') { $st=db()->prepare("SELECT e.*, d.department_code FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.employee_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? ORDER BY e.employee_no LIMIT 100"); $like="%$q%"; $st->execute([$like,$like,$like]); $rows=$st->fetchAll(); }
else $rows=db()->query("SELECT e.*, d.department_code FROM employees e LEFT JOIN departments d ON d.id=e.department_id ORDER BY e.employee_no LIMIT 100")->fetchAll();
$depts=db()->query("SELECT * FROM departments WHERE status='active'")->fetchAll();
$scheds=db()->query("SELECT * FROM schedules WHERE status='active'")->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="wrap"><div class="nav card"><a href="dashboard.php">Dashboard</a><a href="../logout.php">Logout</a></div><div class="card"><h2>Employees</h2><p><?=e($msg)?></p><form method="get"><input name="q" value="<?=e($q)?>" placeholder="Search no/name"><button>Search</button></form><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input name="employee_no" placeholder="25-0001" required><input name="first_name" placeholder="First" required><input name="last_name" placeholder="Last" required><select name="department_id"><option value="">—dept—</option><?php foreach($depts as $d): ?><option value="<?= (int)$d['id'] ?>"><?=e($d['department_code'])?></option><?php endforeach; ?></select><select name="schedule_id"><option value="">—schedule—</option><?php foreach($scheds as $s): ?><option value="<?= (int)$s['id'] ?>"><?=e($s['schedule_name'])?></option><?php endforeach; ?></select><button class="btn">Add</button></form><table><tr><th>No</th><th>Name</th><th>Dept</th><th>Status</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['employee_no'])?></td><td><?=e($r['first_name'].' '.$r['last_name'])?></td><td><?=e((string)($r['department_code']??''))?></td><td><?=e($r['status'])?></td></tr><?php endforeach; ?></table></div></div></body></html>
```
Fix escaping typo before saving: use `<?=e((string)($r['department_code']??''))?>`.

`public/admin/devices.php`:
```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_login();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  $code=trim($_POST['device_code']??''); $name=trim($_POST['device_name']??''); $loc=trim($_POST['location']??'');
  if ($code!==''&&$name!=='') {
    $secret=bin2hex(random_bytes(16));
    try { db()->prepare("INSERT INTO devices (device_code,device_name,location,api_key_hash) VALUES (?,?,?,?)")->execute([$code,$name,$loc,hash('sha256',$secret)]); $msg='Saved. SECRET (copy once): '.$secret; }
    catch (Throwable $e){ $msg='Error: '.$e->getMessage(); }
  }
}
$rows=db()->query("SELECT device_code,device_name,location,status,last_seen_at FROM devices ORDER BY device_code")->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="wrap"><div class="nav card"><a href="dashboard.php">Dashboard</a><a href="../logout.php">Logout</a></div><div class="card"><h2>Devices</h2><p><?=e($msg)?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input name="device_code" placeholder="DEVICE-002" required><input name="device_name" placeholder="Name" required><input name="location" placeholder="Location"><button class="btn">Add (generates secret)</button></form><table><tr><th>Code</th><th>Name</th><th>Loc</th><th>Status</th><th>Last seen</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['device_code'])?></td><td><?=e($r['device_name'])?></td><td><?=e($r['location'])?></td><td><?=e($r['status'])?></td><td><?=e((string)$r['last_seen_at'])?></td></tr><?php endforeach; ?></table><p><small>Seed device DEVICE-001 secret: test-device-secret-001</small></p></div></div></body></html>
```

Also seed demo employee if none (run once via dashboard or SQL): after creating employee UI, insert via SQL:
```sql
INSERT IGNORE INTO employees (employee_no,first_name,last_name,department_id) SELECT '25-0001','Juan','Dela Cruz', id FROM departments WHERE department_code='CCTE' LIMIT 1;
INSERT INTO employee_schedules (employee_id,schedule_id) SELECT e.id,s.id FROM employees e, schedules s WHERE e.employee_no='25-0001' AND s.schedule_name='Day Shift' AND NOT EXISTS (SELECT 1 FROM employee_schedules WHERE employee_id=e.id) LIMIT 1;
```

- [ ] **Step 4: Run test + lint**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task3.php`
Expected: PASS
Run: `php -l D:\Xampp\htdocs\bio-tendance\public\admin\employees.php; php -l D:\Xampp\htdocs\bio-tendance\public\admin\devices.php`
Expected: No syntax errors

- [ ] **Step 5: Commit**

```bash
git add public/admin/employees.php public/admin/departments.php public/admin/schedules.php public/admin/devices.php 2>$null; git commit -m "feat: master data CRUD stubs" 2>$null; echo done
```

### Task 4: Attendance core — functions + API + duplicate guard

**Files:**
- Create: `includes/attendance_functions.php`
- Create: `public/api/attendance.php`

**Interfaces:**
- Consumes: `db()`.
- Produces: `event_hash(string,string,string,string,string,string): string`, `calc_late_minutes(string HH:MM:SS,int,string datetime): int`, `calc_early_minutes(string HH:MM:SS,string datetime): int`, `find_schedule_for_employee(PDO,int,string Y-m-d): ?array`.

- [ ] **Step 1: Write failing test (pure functions + API file)**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_task4.php
require 'D:\Xampp\htdocs\bio-tendance\includes\attendance_functions.php';
$h = event_hash('DEVICE-001','25-0001','2026-10-01 08:03:22','FINGERPRINT','IN','123456');
if (strlen($h)!==64) { echo "FAIL hash\n"; exit(1); }
if (calc_late_minutes('08:00:00',10,'2026-10-01 08:17:00')!==17) { echo "FAIL late 17\n"; exit(1); }
if (calc_late_minutes('08:00:00',10,'2026-10-01 08:07:00')!==0) { echo "FAIL late 0\n"; exit(1); }
if (calc_early_minutes('17:00:00','2026-10-01 16:40:00')!==20) { echo "FAIL early 20\n"; exit(1); }
if (calc_early_minutes('17:00:00','2026-10-01 17:02:00')!==0) { echo "FAIL early 0\n"; exit(1); }
if (!file_exists('D:\Xampp\htdocs\bio-tendance\public\api\attendance.php')) { echo "FAIL api\n"; exit(1); }
echo "PASS core\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task4.php`
Expected: FAIL (file missing / function undefined)

- [ ] **Step 3: Write minimal implementation**

`includes/attendance_functions.php`:
```php
<?php
declare(strict_types=1);
function event_hash(string $device, string $emp, string $time, string $method, string $dir, string $vendor): string {
  return hash('sha256', $device.'|'.$emp.'|'.$time.'|'.$method.'|'.$dir.'|'.$vendor);
}
function calc_late_minutes(string $sched_in, int $grace, string $actual_datetime): int {
  $day = substr($actual_datetime,0,10);
  $deadline = strtotime($day.' '.$sched_in) + $grace*60;
  $actual = strtotime($actual_datetime);
  $diff = (int)floor(($actual-$deadline)/60);
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
```

`public/api/attendance.php`:
```php
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
  if (!$dev || !hash_equals((string)$dev['api_key_hash'], hash('sha256',(string)$key))) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized device.']); exit; }
  if ($dev['status']!=='active') { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Device inactive.']); exit; }
  $st = $pdo->prepare("SELECT * FROM employees WHERE employee_no=? LIMIT 1"); $st->execute([$employee_no]); $emp = $st->fetch();
  if (!$emp) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'Employee not found.']); exit; }
  if ($emp['status']!=='active') { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Employee inactive.']); exit; }
  $hash = event_hash($device_code,$employee_no,$event_time_s,$method,$direction,$vendor);
  $st = $pdo->prepare("SELECT id FROM attendance_events WHERE event_hash=? LIMIT 1"); $st->execute([$hash]);
  if ($st->fetch()) { http_response_code(409); echo json_encode(['success'=>false,'message'=>'Duplicate attendance event.']); exit; }
  $work_date = $dt->format('Y-m-d');
  $sched = find_schedule_for_employee($pdo,(int)$emp['id'],$work_date);
  $pdo->beginTransaction();
  try {
    $pdo->prepare("INSERT INTO attendance_events (device_id,employee_id,device_code,employee_no,event_time,verification_method,direction,vendor_reference,event_hash) VALUES (?,?,?,?,?,?,?,?,?)")
      ->execute([(int)$dev['id'],(int)$emp['id'],$device_code,$employee_no,$event_time_s,$method,$direction,$vendor,$hash]);
  } catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0)==1062) { $pdo->rollBack(); http_response_code(409); echo json_encode(['success'=>false,'message'=>'Duplicate attendance event.']); exit; }
    throw $e;
  }
  $late=0; $early=0; $status='PRESENT'; $sched_id=null;
  if ($sched) { $sched_id=(int)$sched['id']; }
  $st=$pdo->prepare("SELECT * FROM attendance_daily WHERE employee_id=? AND work_date=? LIMIT 1");
  $st->execute([(int)$emp['id'],$work_date]); $daily=$st->fetch();
  if ($direction==='IN') {
    if ($sched) $late=calc_late_minutes($sched['time_in'],(int)$sched['grace_minutes'],$event_time_s);
    $status = $late>0 ? 'LATE' : 'PRESENT';
    if (!$daily) {
      $pdo->prepare("INSERT INTO attendance_daily (employee_id,work_date,time_in,status,late_minutes,schedule_id) VALUES (?,?,?,?,?,?)")->execute([(int)$emp['id'],$work_date,$event_time_s,$status,$late,$sched_id]);
    } else {
      if ($daily['time_in']===null || $event_time_s < $daily['time_in']) {
        $pdo->prepare("UPDATE attendance_daily SET time_in=?, status=?, late_minutes=?, schedule_id=COALESCE(?,schedule_id) WHERE id=?")->execute([$event_time_s,$status,$late,$sched_id,$daily['id']]);
      }
    }
  } else {
    if ($sched) $early=calc_early_minutes($sched['time_out'],$event_time_s);
    if (!$daily) {
      $st2='PRESENT'; if ($sched) { $late=calc_late_minutes($sched['time_in'],(int)$sched['grace_minutes'],$event_time_s); $st2=$late>0?'LATE':'PRESENT'; }
      $pdo->prepare("INSERT INTO attendance_daily (employee_id,work_date,time_out,status,late_minutes,early_out_minutes,schedule_id) VALUES (?,?,?,?,?,?,?)")->execute([(int)$emp['id'],$work_date,$event_time_s,$st2,$late,$early,$sched_id]);
      $status=$st2;
    } else {
      $new_status=$daily['status'];
      if ($early>0 && ($new_status==='PRESENT'||$new_status==='LATE')) $new_status='EARLY_OUT';
      if ($daily['status']==='LATE') $new_status = $early>0 ? 'EARLY_OUT' : 'LATE';
      // keep LATE if already late; EARLY_OUT only as additional flag via minutes + status when early
      if ($daily['status']==='PRESENT' && $early>0) $new_status='EARLY_OUT';
      if ($daily['status']==='EARLY_OUT') $new_status='EARLY_OUT';
      $pdo->prepare("UPDATE attendance_daily SET time_out=?, early_out_minutes=?, status=? WHERE id=?")->execute([$event_time_s,$early,$new_status,$daily['id']]);
      $status=$new_status;
    }
  }
  $pdo->prepare("UPDATE devices SET last_seen_at=NOW() WHERE id=?")->execute([(int)$dev['id']]);
  $pdo->prepare("INSERT INTO audit_logs (action,details,ip_address) VALUES (?,?,?)")->execute(['attendance_event', $employee_no.' '.$direction.' '.$event_time_s, $_SERVER['REMOTE_ADDR']??'']);
  $pdo->commit();
  echo json_encode(['success'=>true,'message'=>'Recorded.','status'=>$status,'late_minutes'=>$late,'early_out_minutes'=>$early]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500); echo json_encode(['success'=>false,'message'=>'Server error.']);
}
```

- [ ] **Step 4: Run test + lint + live API test**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task4.php`
Expected: PASS
Run: `php -l D:\Xampp\htdocs\bio-tendance\includes\attendance_functions.php; php -l D:\Xampp\htdocs\bio-tendance\public\api\attendance.php`
Expected: No syntax errors
Live (after Apache running): `Invoke-RestMethod -Uri http://localhost/bio-tendance/public/api/attendance.php -Method Post -Headers @{'X-Device-Key'='test-device-secret-001';'Content-Type'='application/json'} -Body '{"device_code":"DEVICE-001","employee_no":"25-0001","event_time":"2026-10-01 08:03:22","verification_method":"FINGERPRINT","direction":"IN","vendor_reference":"t1"}'`
Expected: success true. Repeat same → 409 duplicate.

- [ ] **Step 5: Commit**

```bash
git add includes/attendance_functions.php public/api/attendance.php 2>$null; git commit -m "feat: attendance API with duplicate guard" 2>$null; echo done
```

### Task 5: Views — attendance list, reports, simulator, docs

**Files:**
- Create: `public/admin/attendance.php`
- Create: `public/admin/reports.php`
- Create: `device_adapter/simulator.php`
- Create: `device_adapter/integration_guide.md`
- Create: `README.md`, `SETUP.md`, `ALGORITHM.md`

**Interfaces:**
- Consumes: `db()`, API from Task 4.
- Produces: filtered daily view + print, `php simulator.php 25-0001 IN`.

- [ ] **Step 1: Write failing test**

```php
<?php
// C:\Users\doruc\AppData\Local\Temp\opencode\check_task5.php
foreach (['public/admin/attendance.php','public/admin/reports.php','device_adapter/simulator.php','README.md','SETUP.md','ALGORITHM.md'] as $f) {
  if (!file_exists('D:\Xampp\htdocs\bio-tendance\\'.$f)) { echo "FAIL $f\n"; exit(1); }
}
echo "PASS views docs\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task5.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

`public/admin/attendance.php` (filters date/employee/dept/status + finalize absence):
```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_login();
$date=$_GET['date']??date('Y-m-d'); $q=trim($_GET['q']??''); $status=$_GET['status']??'';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check();
  if (($_POST['op']??'')==='absent') {
    $d=$_POST['date']??date('Y-m-d');
    db()->prepare("INSERT INTO attendance_daily (employee_id,work_date,status) SELECT id,?, 'ABSENT' FROM employees WHERE status='active' AND id NOT IN (SELECT employee_id FROM attendance_daily WHERE work_date=?) ON DUPLICATE KEY UPDATE status=VALUES(status)")->execute([$d,$d]);
  }
}
$sql="SELECT a.*, e.employee_no, CONCAT(e.first_name,' ',e.last_name) nm, d.department_code FROM attendance_daily a JOIN employees e ON e.id=a.employee_id LEFT JOIN departments d ON d.id=e.department_id WHERE 1=1";
$p=[];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { $sql.=" AND a.work_date=?"; $p[]=$date; }
if ($status!=='') { $sql.=" AND a.status=?"; $p[]=$status; }
if ($q!=='') { $sql.=" AND (e.employee_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)"; $p[]="%$q%"; $p[]="%$q%"; $p[]="%$q%"; }
$sql.=" ORDER BY a.work_date DESC, e.employee_no LIMIT 200";
$st=db()->prepare($sql); $st->execute($p); $rows=$st->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="wrap"><div class="nav card noprint"><a href="dashboard.php">Dashboard</a><a href="../logout.php">Logout</a></div><div class="card"><h2>Attendance</h2><form method="get" class="noprint"><input type="date" name="date" value="<?=e($date)?>"><input name="q" value="<?=e($q)?>" placeholder="no/name"><select name="status"><option value="">—status—</option><?php foreach(['PRESENT','LATE','ABSENT','EARLY_OUT','ON_LEAVE','REST_DAY','HOLIDAY'] as $s): ?><option <?= $status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select><button>Filter</button> <button type="button" onclick="window.print()">Print</button></form><table><tr><th>Date</th><th>No</th><th>Name</th><th>Dept</th><th>In</th><th>Out</th><th>Status</th><th>Late</th><th>Early</th></tr><?php foreach($rows as $r): ?><tr><td><?=e($r['work_date'])?></td><td><?=e($r['employee_no'])?></td><td><?=e($r['nm'])?></td><td><?=e((string)($r['department_code']??''))?></td><td><?=e((string)$r['time_in'])?></td><td><?=e((string)$r['time_out'])?></td><td><?=e($r['status'])?></td><td><?= (int)$r['late_minutes'] ?></td><td><?= (int)$r['early_out_minutes'] ?></td></tr><?php endforeach; ?></table><form method="post" class="noprint"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="op" value="absent"><input type="date" name="date" value="<?=e($date)?>"><button class="btn">Finalize absences for date</button></form></div></div></body></html>
```

`public/admin/reports.php` (daily + monthly summary + history):
```php
<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/database.php';
require_login();
$mode=$_GET['mode']??'daily'; $date=$_GET['date']??date('Y-m-d'); $month=$_GET['month']??date('Y-m');
if ($mode==='daily') { $st=db()->prepare("SELECT e.employee_no, CONCAT(e.first_name,' ',e.last_name) nm, d.department_code, a.time_in, a.time_out, a.status, a.late_minutes, a.early_out_minutes FROM employees e LEFT JOIN attendance_daily a ON a.employee_id=e.id AND a.work_date=? LEFT JOIN departments d ON d.id=e.department_id WHERE e.status='active' ORDER BY e.employee_no"); $st->execute([$date]); $rows=$st->fetchAll(); }
elseif ($mode==='monthly') { $st=db()->prepare("SELECT e.employee_no, SUM(a.status IN ('PRESENT','LATE','EARLY_OUT')) P, SUM(a.status='LATE') L, SUM(a.status='ABSENT') A, SUM(a.status='EARLY_OUT') E FROM employees e LEFT JOIN attendance_daily a ON a.employee_id=e.id AND DATE_FORMAT(a.work_date,'%Y-%m')=? WHERE e.status='active' GROUP BY e.id ORDER BY e.employee_no"); $st->execute([$month]); $rows=$st->fetchAll(); }
else { $q=trim($_GET['q']??'25-0001'); $st=db()->prepare("SELECT work_date,time_in,time_out,status,late_minutes,early_out_minutes FROM attendance_daily a JOIN employees e ON e.id=a.employee_id WHERE e.employee_no=? ORDER BY work_date DESC LIMIT 100"); $st->execute([$q]); $rows=$st->fetchAll(); }
?>
<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../assets/style.css"></head><body><div class="wrap"><div class="nav card noprint"><a href="dashboard.php">Dashboard</a><a href="?mode=daily">Daily</a><a href="?mode=monthly">Monthly</a><a href="?mode=history">History</a><a href="../logout.php">Logout</a></div><div class="card"><h2>Reports — <?=e($mode)?></h2><form method="get" class="noprint"><select name="mode"><option value="daily">Daily</option><option value="monthly">Monthly</option><option value="history">History</option></select><input type="date" name="date" value="<?=e($date)?>"><input type="month" name="month" value="<?=e($month)?>"><input name="q" placeholder="25-0001" value="<?=e($_GET['q']??'')?>"><button>View</button> <button type="button" onclick="window.print()">Print</button></form><table><?php foreach($rows as $r): ?><tr><?php foreach($r as $v): ?><td><?=e((string)$v)?></td><?php endforeach; ?></tr><?php endforeach; ?></table></div></div></body></html>
```

`device_adapter/simulator.php`:
```php
<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
if ($argc < 3) { fwrite(STDERR, "Usage: php simulator.php <EMP_NO> <IN|OUT> [--time=\"Y-m-d H:i:s\"] [--device=DEVICE-001] [--key=test-device-secret-001]\n"); exit(1); }
$emp=$argv[1]; $dir=strtoupper($argv[2]);
$opt=['time'=>date('Y-m-d H:i:s'),'device'=>'DEVICE-001','key'=>'test-device-secret-001'];
foreach (array_slice($argv,3) as $a) { if (str_starts_with($a,'--time=')) $opt['time']=substr($a,7); if (str_starts_with($a,'--device=')) $opt['device']=substr($a,9); if (str_starts_with($a,'--key=')) $opt['key']=substr($a,6); }
$payload=['device_code'=>$opt['device'],'employee_no'=>$emp,'event_time'=>$opt['time'],'verification_method'=>'FINGERPRINT','direction'=>$dir,'vendor_reference'=>'SIM-'.time()];
$ch=curl_init('http://localhost/bio-tendance/public/api/attendance.php');
curl_setopt_array($ch,['CURLOPT_RETURNTRANSFER'=>true,'CURLOPT_POST'=>true,'CURLOPT_HTTPHEADER'=>['Content-Type: application/json','X-Device-Key: '.$opt['key']],'CURLOPT_POSTFIELDS'=>json_encode($payload)]);
$res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
echo "HTTP $code\n$res\n";
```

`device_adapter/integration_guide.md`, `README.md`, `SETUP.md`, `ALGORITHM.md` — concise, beginner-readable, include: run Apache+MySQL in XAMPP, import `database/schema.sql` via phpMyAdmin or `mysql.exe -u root < schema.sql`, open `http://localhost/bio-tendance/public/`, login `admin/Admin123!`, run `php device_adapter/simulator.php 25-0001 IN` then OUT, duplicate test, vendor checklist (brand/model/fw/comm/API-SDK/push-webhook-poll/ID-time/methods/IN-OUT/docs), adapter contract example.

- [ ] **Step 4: Run test + lint + simulator**

Run: `php C:\Users\doruc\AppData\Local\Temp\opencode\check_task5.php`
Expected: PASS
Run: `php -l D:\Xampp\htdocs\bio-tendance\device_adapter\simulator.php; php device_adapter\simulator.php 25-0001 IN`
Expected: HTTP 200 success; second identical-second run may differ by vendor_reference (SIM-time) so use fixed `--time` to test 409 duplicate.

- [ ] **Step 5: Commit**

```bash
git add public/admin/attendance.php public/admin/reports.php device_adapter README.md SETUP.md ALGORITHM.md 2>$null; git commit -m "feat: attendance views, reports, simulator, docs" 2>$null; echo done
```

## Self-Review (run before handoff)

1. Spec coverage: schema §2→Task1, auth/UI §25→Task2, master §6-9→Task3, API+dup+calc §10-16→Task4, filters/reports/sim §26-27,24→Task5. Absence finalize in Task5. Audit in Task4. Timezone in Tasks1+4.
2. Placeholder scan: no TBD/TODO; every code step has exact file + full code + run command + expected output.
3. Type consistency: `event_hash` 6 args used in API; `calc_*` signatures match tests; `find_schedule_for_employee(PDO,int,string)` matches call.
