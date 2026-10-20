# SETUP (XAMPP, Windows)

1. Start Apache + MySQL in XAMPP Control Panel.
2. Import DB: open phpMyAdmin → Import → `database/schema.sql`, or run:
   `"D:\Xampp\mysql\bin\mysql.exe" -u root bio_attendance -e "SOURCE D:/Xampp/htdocs/bio-tendance/database/schema.sql"`
3. Import `database/migrate_hardening.sql`, then open `http://localhost/bio-tendance/public/` → you'll be sent to the one-time setup page to create your admin (no default password).
4. Seed device `DEVICE-001` is inactive: Devices → Activate → Reset key, copy the secret (see `docs/HARDENING.md`), dept `CCTE`, schedule Day 08:00-17:00 grace 10, employee `25-0001`.
5. Test API:
   `php device_adapter/simulator.php 25-0001 IN --time="2026-10-01 08:03:22"`
   `php device_adapter/simulator.php 25-0001 OUT --time="2026-10-01 17:01:00"`
6. Duplicate test: repeat the same command with fixed `--time` → expect HTTP 409.
7. View dashboard, Attendance, Reports. Print via browser button.
