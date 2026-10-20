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
