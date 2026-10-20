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
