# Device Integration Guide (scanner-agnostic)

Core rule: only this folder knows vendor protocol. `public/api/attendance.php` never changes for a new scanner — the adapter converts vendor data to the standard event.

## Standard event (what the API accepts)

POST `http://localhost/bio-tendance/public/api/attendance.php`
Headers: `Content-Type: application/json`, `X-Device-Key: <secret>`
Body:
```json
{
  "device_code": "DEVICE-001",
  "employee_no": "25-0001",
  "event_time": "2026-10-01 08:03:22",
  "verification_method": "FINGERPRINT",
  "direction": "IN",
  "vendor_reference": "123456"
}
```
`vendor_reference` may be empty string. Methods: FINGERPRINT, FACE, CARD, PIN. Direction: IN, OUT.

## Simulator (use until hardware arrives)

```powershell
php device_adapter/simulator.php 25-0001 IN
php device_adapter/simulator.php 25-0001 OUT
php device_adapter/simulator.php 25-0001 IN --time="2026-10-01 08:17:00"
```

## When the scanner is announced, collect (do not guess)

Brand, Model, Firmware, Comm (LAN/Wi-Fi/USB/RS485), API/SDK/docs URL, Protocol, Push vs webhook vs polling, Employee ID format, Timestamp format+tz, Verification methods, IN/OUT support, Auth method.

## Adapter contract

Create `device_adapter/<vendor>_adapter.php` that: connects via vendor method → reads native event → maps to standard event fields → POSTs to API with device secret. Keep raw vendor payload in logs for audit. Test adapter separately before wiring to real device.

## 4-vendor webhook config
Webhook URL per device: `http://YOUR_SERVER/bio-tendance/public/api/vendor/webhook.php?vendor=<ZKTeco|Suprema|Anviz|Hikvision>&device=<DEVICE-CODE>`.
Every device sends header `X-Device-Secret: <secret shown once at device creation>`.
Native body per vendor: ZKTeco `{SN,UserID,VerifyTime,VerifyMode 1/2/4/8,InOut 0/1,RecordID}`; Suprema `{DeviceID,UserID,DateTime ISO8601,AuthMode FP/FACE/CARD/PIN,EventType Entry/Exit,LogID}`; Anviz `{DeviceID,PIN,Time,VerifyMode,Status Entry/Exit,RecordID}`; Hikvision `{deviceID,EmployeeNo,AttendanceTime ISO8601,VerifyMode,Direction Entry/Exit,EventID}`.
Set each device's `vendor_type` in Devices page to match. `Generic` expects the standard event (same as attendance API).
