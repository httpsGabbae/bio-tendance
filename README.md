# AttendX — Biometric Attendance System

Pure PHP + MySQL (XAMPP). Scanner-brand agnostic: adapter posts a standard event to `public/api/attendance.php`.

- Web: `http://localhost/bio-tendance/public/` (first visit runs one-time admin setup)
- API: `POST public/api/attendance.php` with `X-Device-Key`
- Simulator: `php device_adapter/simulator.php 25-0001 IN`
- Docs: `SETUP.md` (install), `ALGORITHM.md` (rules), `device_adapter/integration_guide.md` (hardware later)

Tables: admins, departments, employees, schedules, employee_schedules, devices, attendance_events (raw, hash-unique), attendance_daily (summary), audit_logs. Timezone Asia/Manila. Optional face enrollment stores templates in MySQL and a profile photo in `storage/faces/`; see `docs/FACE.md` for consent and retention requirements.
