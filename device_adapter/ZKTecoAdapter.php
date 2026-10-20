<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class ZKTecoAdapter implements VendorAdapterInterface {
  public function validateSignature(array $raw, string $secret): bool {
    $sig = (string)($raw['_headers']['x-zk-signature'] ?? '');
    if ($sig === '' || $secret === '') return false;
    return hash_equals(hash_hmac('sha256', (string)($raw['_body'] ?? ''), $secret), $sig);
  }
  public function normalize(array $raw): array {
    $modes = [1=>'FINGERPRINT',2=>'FACE',4=>'CARD',8=>'PIN'];
    $m = $modes[(int)($raw['VerifyMode'] ?? 0)] ?? 'FINGERPRINT';
    return [
      'device_code' => trim((string)($raw['_device_code'] ?? $raw['SN'] ?? '')),
      'employee_no' => trim((string)($raw['UserID'] ?? '')),
      'event_time' => trim((string)($raw['VerifyTime'] ?? '')),
      'verification_method' => $m,
      'direction' => ((int)($raw['InOut'] ?? 0) === 1) ? 'OUT' : 'IN',
      'vendor_reference' => (string)($raw['RecordID'] ?? $raw['LogID'] ?? ''),
    ];
  }
}
