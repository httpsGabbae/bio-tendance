# AttendX — Hardening & Usability Update

## Upgrade steps (existing install)
1. Back up the `bio_attendance` database.
2. phpMyAdmin → Import → `database/migrate_hardening.sql` (safe to re-run). Fresh installs: just import `schema.sql`.
3. **Replace the default admin.** If your admin password is still `Admin123!`, run `DELETE FROM admins;` in phpMyAdmin, then open `/public/` — you will be sent to the one-time setup page to create your own admin.
4. **Devices:** the migration disables the seed `DEVICE-001` (its secret was public). Go to Devices → *Activate* → *Reset key*, copy the new secret once, and put it in your device/simulator config.

## Security changes
| Area | Before | Now |
|---|---|---|
| First admin | Auto-created `admin / Admin123!` | One-time setup page, min. 10-char password, locks after first admin |
| Login | Unlimited attempts | 5 failures per IP / 15 min lockout, session ID regenerated on login |
| Logout | GET link | POST + CSRF token |
| Session cookie | Default | HttpOnly, SameSite=Lax, Secure on HTTPS |
| Device secret | Public seed secret shown in UI | Seed device disabled; per-device **Reset key** |
| Audit | Table unused | Logins, failures, adds, toggles, key resets, finalize, leave/holiday changes; viewable under **Audit log** |

## Usability / correctness changes
- **Finalize past day** (Attendance) now respects each employee's schedule: marks `REST_DAY` (per-schedule rest days), `HOLIDAY`, `ON_LEAVE`, else `ABSENT`. Employees with no schedule are skipped and reported. Today/future dates are refused.
- **Calendar** page: manage holidays and employee leave.
- **Schedules** have rest days (default Sat/Sun).
- **Deactivate / Activate** for employees, departments, schedules, devices (nothing is hard-deleted, history stays intact).

## Production checklist
- Set a MySQL password and change `includes/config.php`; don't run as `root` without a password.
- Serve over HTTPS; restrict `/public/api/attendance.php` by network if devices are on a LAN.
- Block direct web access to `includes/`, `database/`, `docs/`, `device_adapter/` (move them above the web root if possible).
- Keep daily database backups.

## Face recognition
See `docs/FACE.md` (import `database/migrate_face.sql`).

## Still open (next up)
1. Edit forms for employees, departments, schedules (typos currently need DB access); change-password page; multiple admins/roles.
2. Manual attendance corrections with reason + audit trail.
3. Hours worked, overtime, night-shift handling.
4. CSV/Excel export, pagination, date-range reports.
5. API: record server receive time and flag device clock drift; per-device rate limiting.
6. Real scanner integration test; biometric consent/retention policy (Data Privacy Act).
7. Automated tests.
