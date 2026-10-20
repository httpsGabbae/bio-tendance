# Multi-Vendor Biometric Adapter — Design Spec (2026-10-02)

## Goal
Support ZKTeco, Suprema, Anviz, Hikvision scanners simultaneously without changing core attendance API. Each device row stores its vendor; unified webhook routes to vendor-specific adapter that normalizes to standard event.

## Architecture

```
VENDOR DEVICE (ZKTeco/Suprema/Anviz/Hikvision)
       │
       ▼ HTTP POST (vendor-native JSON)
┌─────────────────────────────────────────────┐
│ /api/vendor/webhook.php?vendor=ZKTeco       │
│ &device=DEVICE-001                          │
│                                             │
│ 1. Find device by device_code               │
│ 2. Read vendor_type from device row         │
│ 3. AdapterFactory::get($vendor_type)        │
│ 4. adapter->validateSignature($raw, $secret)│
│ 5. $stdEvent = adapter->normalize($raw)     │
│ 6. Forward to /api/attendance.php           │
└─────────────────────────────────────────────┘
       │
       ▼ Standard event JSON
┌─────────────────────────────────────────────┐
│ public/api/attendance.php (UNCHANGED)       │
└─────────────────────────────────────────────┘
```

## Database Changes

```sql
ALTER TABLE `devices` 
ADD COLUMN `vendor_type` ENUM('ZKTeco','Suprema','Anviz','Hikvision','Generic') 
NOT NULL DEFAULT 'Generic' AFTER `device_type`;
```

## Adapter Interface

```php
// device_adapter/VendorAdapterInterface.php
interface VendorAdapterInterface {
  /** Convert vendor payload to standard event array */
  public function normalize(array $raw): array;
  
  /** Verify request authenticity using device secret */
  public function validateSignature(array $raw, string $secret): bool;
}
```

## Standard Event (output of normalize())
```php
[
  'device_code' => 'DEVICE-001',
  'employee_no' => '25-0001',
  'event_time' => '2026-10-01 08:03:22',  // Asia/Manila, Y-m-d H:i:s
  'verification_method' => 'FINGERPRINT', // FINGERPRINT|FACE|CARD|PIN
  'direction' => 'IN',                    // IN|OUT
  'vendor_reference' => '123456',         // optional
]
```

## Vendor Field Mappings

| Standard | ZKTeco | Suprema | Anviz | Hikvision |
|----------|--------|---------|-------|-----------|
| device_code | SN | DeviceID | DeviceID | deviceID |
| employee_no | UserID | UserID | PIN/UserID | EmployeeNo |
| event_time | VerifyTime | DateTime | Time | AttendanceTime |
| verification_method | VerifyMode (1/2/4/8) | AuthMode | VerifyMode | VerifyMode |
| direction | InOut (0/1) | DoorStatus/EventType | Status | Direction |
| vendor_reference | RecordID/LogID | LogID/EventID | RecordID | EventID |

**Method mapping:**
- ZKTeco: 1→FINGERPRINT, 2→FACE, 4→CARD, 8→PIN
- Suprema: "FP"→FINGERPRINT, "FACE"→FACE, "CARD"→CARD, "PIN"→PIN
- Anviz: "FP"/"FINGERPRINT"→FINGERPRINT, "FACE"→FACE, "CARD"→CARD, "PIN"/"PASSWORD"→PIN
- Hikvision: "Fingerprint"→FINGERPRINT, "Face"→FACE, "Card"→CARD, "Password"→PIN

**Direction mapping:**
- ZKTeco: 0→IN, 1→OUT
- Suprema: "In"/"Entry"→IN, "Out"/"Exit"→OUT
- Anviz: "In"/"Entry"→IN, "Out"/"Exit"→OUT
- Hikvision: "Entry"/"In"→IN, "Exit"/"Out"→OUT

## Signature Validation

| Vendor | Method |
|--------|--------|
| ZKTeco | HMAC-SHA256(body, secret) → `X-ZK-Signature` header |
| Suprema | JWT Bearer or HMAC → `Authorization` / `X-Suprema-Signature` |
| Anviz | Plain token → `X-Anviz-Token` header |
| Hikvision | Digest auth or HMAC → `X-Hik-Signature` |

## File Structure

```
device_adapter/
  VendorAdapterInterface.php
  ZKTecoAdapter.php
  SupremaAdapter.php
  AnvizAdapter.php
  HikvisionAdapter.php
  GenericAdapter.php          // fallback: expects standard event already
  AdapterFactory.php
  webhook.php                 // unified entry point
  integration_guide.md        // updated with 4-vendor config
```

## Devices Page Updates

- Add `vendor_type` dropdown when adding/editing device
- Show vendor_type in device list
- Device secret used for both API key (attendance API) AND webhook signature validation

## Configuration on Vendor Devices

Each vendor device must be configured to POST to:
```
http://YOUR_SERVER/bio-tendance/public/api/vendor/webhook.php?vendor=ZKTeco&device=DEVICE-001
```
Headers: vendor-specific auth (see above)
Body: vendor-native JSON

## Testing

- Simulator extended: `php device_adapter/simulator.php 25-0001 IN --vendor=ZKTeco`
- Unit tests for each adapter's `normalize()` and `validateSignature()`
- Integration test: POST vendor-sample-JSON → webhook → attendance API → DB

## Out of Scope (YAGNI)
- Enrollment from PHP (stays in vendor software)
- User sync / device config from PHP
- Polling fallback (only push/webhook supported)
- Vendors beyond the 4 named