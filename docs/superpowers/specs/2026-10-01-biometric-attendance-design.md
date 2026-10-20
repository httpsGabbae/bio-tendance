# Biometric Attendance Checker — Design Spec (2026-10-01)

- DB: `bio_attendance`, timezone `Asia/Manila`, PHP 8.2 + PDO + MariaDB (XAMPP)
- Root: `D:\Xampp\htdocs\bio-tendance\` → web `http://localhost/bio-tendance/public/`
- Principle: scanner-brand agnostic. Only `device_adapter/` knows vendor format. Core API uses standard event only. No raw fingerprint/face images.

## 1. Architecture

```
BIOMETRIC SCANNER
  -> device_adapter/ (simulator.php now, vendor_adapter later)
  -> STANDARD EVENT JSON {device_code, employee_no, event_time, verification_method, direction, vendor_reference}
  -> public/api/attendance.php (validation, auth, tx, calc)
  -> MySQL bio_attendance
  -> public/admin/* (dashboard, reports)
```

- `includes/config.php`: DB host/name/user/pass (env `DB_*` fallback to XAMPP defaults `localhost/root/''`), `APP_TIMEZONE='Asia/Manila'`, paths. No hard-coded secrets in code.
- `includes/database.php`: `db(): PDO` singleton, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `SET time_zone='+08:00'`, `charset utf8mb4`.
- `includes/bootstrap.php`: `date_default_timezone_set`, `session_start`, CSRF `csrf_token()/csrf_check()`, `e()` escaping, `json_response($code,$arr)`, `require_login()` helper.
- `includes/auth.php`: `login($u,$p)`, `logout()`, `current_admin()`, `require_login()` guard for all `admin/*`.
- `includes/attendance_functions.php`: `event_hash(...)`, `parse_event_time()`, `find_schedule(PDO,employee_id)`, `calc_late($sched_in,$grace,$actual)`, `calc_early($sched_out,$actual)`, `upsert_daily(...)`.
- `.htaccess` at root denies `includes/`, `database/`; `public/.htaccess` allows.
- UI: clean PHP+HTML+vanilla CSS/JS, responsive, `window.print()` reports. Pages: login, dashboard (today present/late/absent/early + recent events + device status), employees, departments, schedules, devices, attendance (filters), reports (daily/monthly/history), logout.

## 2. Database (database/schema.sql, all upfront)

- `admins(id, username UNIQUE, password_hash, full_name, status, created_at)`
- `departments(id, department_code UNIQUE, department_name, status)`
- `employees(id, employee_no UNIQUE e.g. 25-0001, first_name, middle_name, last_name, email UNIQUE NULL, department_id FK, status ENUM active/inactive, created_at, updated_at)`
- `schedules(id, schedule_name, time_in TIME, time_out TIME, grace_minutes INT DEFAULT 10, status)`
- `employee_schedules(id, employee_id FK, schedule_id FK, effective_date DATE NULL, UNIQUE(employee_id,effective_date))` — lookup: latest effective <= work_date else first. Note: MySQL UNIQUE allows multiple NULLs, so only one NULL (default) schedule per employee is enforced in app code, not by DB alone.
- `devices(id, device_code UNIQUE e.g. DEVICE-001, device_name, device_type, location, api_key_hash CHAR(64) (SHA256 of secret), status, last_seen_at NULL, created_at)` — secret shown once at creation, never stored plain.
- `attendance_events(id, device_id FK, employee_id FK, device_code, employee_no, event_time DATETIME, verification_method ENUM FINGERPRINT,FACE,CARD,PIN, direction ENUM IN,OUT, vendor_reference NULL, event_hash CHAR(64) UNIQUE, created_at)` + index `(employee_id,event_time)`.
- `attendance_daily(id, employee_id FK, work_date DATE, time_in DATETIME NULL, time_out DATETIME NULL, status ENUM PRESENT,LATE,ABSENT,EARLY_OUT,ON_LEAVE,REST_DAY,HOLIDAY, late_minutes INT DEFAULT 0, early_out_minutes INT DEFAULT 0, schedule_id FK NULL, updated_at, UNIQUE(employee_id,work_date))`
- `audit_logs(id, admin_id NULL, action, details TEXT NULL, ip_address, created_at)`
- Seed: admin `admin/Admin123!` (hash), dept `CCTE`, schedule `Day 08:00-17:00 grace 10`, device `DEVICE-001` secret `test-device-secret-001` (hash stored), employee `25-0001` linked.

## 3. Attendance API (public/api/attendance.php)

Request: `POST .../public/api/attendance.php`, `Content-Type: application/json`, `X-Device-Key: SECRET`. Body standard event.
Steps: parse JSON → require 5 required fields (device_code, employee_no, event_time, verification_method, direction) + optional vendor_reference (empty string allowed, included in hash as '') → auth device via `hash_equals(SHA256(key), api_key_hash)` + `status=active` else 401 → employee `employee_no` + active else 404 → `event_time Y-m-d H:i:s`, not >5min future, not >1yr past → method/direction whitelist → `event_hash=SHA256(device|employee|time|method|dir|vendor)` → duplicate pre-check + rely on UNIQUE → tx: insert event, `work_date` from event_time (Manila), schedule lookup, `calc_late/early`, upsert daily (first IN wins, every OUT updates time_out; IN never overwrites earlier IN; OUT ignores if before IN except keeps earliest IN), update `last_seen_at`, audit → commit → 200 `{success:true, status, late_minutes, early_out_minutes}`. On dup: 409 `{success:false,message:Duplicate attendance event.}`. Never expose SQL/traces.

Late: `late = max(0, actual_in - (sched_in+grace))` minutes. Ex: 08:00+10, 08:17 → LATE 17; 08:07 → PRESENT 0. Early: `early = max(0, sched_out - actual_out)`; 17:00 vs 16:40 → 20; >=17:00 → 0. Overtime not paid auto. Multi-scan: raw kept, daily summarized. Absence: only via admin finalize/cron after cutoff, not auto on empty.

## 4. Simulator + integration

- `device_adapter/simulator.php 25-0001 IN|OUT [--time="Y-m-d H:i:s"] [--device=DEVICE-001] [--key=SECRET]` builds standard event (defaults now Manila) and POSTs to local API, prints response. Tests full flow pre-hardware.
- `device_adapter/integration_guide.md`: checklist for later (brand/model/fw/comm/LAN-WiFi-USB/API-SDK/push-webhook-poll/ID-timeformat/methods/IN-OUT/docs) + adapter contract (vendor → standard event). Core API unchanged on vendor swap.

## 5. Security / errors / docs

PDO-only, hash/verify, sessions+CSRF, validation+escaping, tx, UNIQUE hash, status checks, audit, HTTPS prod note, backups. HTTP: 200 ok, 400 bad JSON, 401 device, 404 employee, 409 dup, 422 validation. `README.md` (run), `SETUP.md` (XAMPP import + seed + login + simulate), `ALGORITHM.md` (flowchart + rules).

## 6. Acceptance (skeleton)

Import schema ok; login/logout + guard works; dashboard shows counts; CRUD stubs load; valid IN/OUT via simulator creates event+daily; dup→409; bad device→401; unknown→404; late/early math matches §3 ex; print CSS ok; no biometric blobs.

## Out of scope (YAGNI)

No leave/holiday/OT/overtime-pay/portal/notifications/roles — stubs only. No vendor SDK. No framework.
