# AttendX — Face Recognition (no hardware needed)

## How it works
1. **Register (admin):** Employees → *Register* (Face column). Capture 3–6 samples with the webcam or upload photos. The browser (face-api.js) turns each face into 128 numbers; only those numbers and one profile photo are sent. Consent must be ticked. A face already matching another employee is rejected.
2. **Kiosk (employees):** Admin opens **Face kiosk**, picks the device, leaves the page on a tablet/PC at the entrance. The camera detects a face → sends the 128 numbers to `public/api/face_scan.php` → the **server** compares them with stored templates (templates never reach the browser).
3. **Record:** On a match the existing `process_attendance()` runs with method `FACE` (first scan of the day = IN, later scans = OUT, 60 s cooldown), so lateness/early-out and all reports work unchanged.

## Setup
- Import `database/migrate_face.sql` (also included in `schema.sql`).
- Create at least one **active device** (Devices page) to use as the kiosk.
- First run loads `face-api.js` + models from the jsDelivr CDN (needs internet). **Offline:** download `@vladmandic/face-api` 1.7.12 and place `dist/face-api.js` → `public/assets/vendor/face-api/face-api.js` and the `model/` folder → `public/assets/vendor/face-api/model/` (auto-detected).
- Camera access needs **HTTPS or `http://localhost`**. A tablet using `http://192.168.x.x` will be blocked by the browser.

## Tuning (`includes/face.php`)
`FACE_MATCH_MAX` 0.5 (raise for fewer misses, lower for fewer false accepts) · `FACE_DUP_MAX` 0.4.

## Known limits (be upfront with stakeholders)
- **No liveness detection:** a photo or phone screen of an employee can fool it. Hardware with IR/3D liveness, or a liveness model, is the fix.
- The kiosk runs under an admin login; a dedicated kiosk token/role is the next step.
- Templates are stored unencrypted in MySQL; photos in `storage/faces/` (web-blocked via `.htaccess`, Apache only).
- Accuracy depends on lighting/camera; test with real staff before relying on it. Matching is tuned for dozens–low hundreds of employees.
- Biometric data is sensitive (Data Privacy Act): keep consent records, a retention policy, and use *Remove face data* when someone leaves.
