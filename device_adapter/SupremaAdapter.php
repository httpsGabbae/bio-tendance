<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class SupremaAdapter implements VendorAdapterInterface {
  public function validateSignature(array $raw, string $secret): bool {
    $h = $raw['_headers'] ?? [];
    if (hash_equals('Bearer '.$secret, (string)($h['authorization'] ?? ''))) return true;
    $sig = (string)($h['x-suprema-signature'] ?? '');
    return $sig !== '' && hash_equals(hash_hmac('sha256', (string)($raw['_body'] ?? ''), $secret), $sig);
  }
  public function normalize(array $raw): array {
    $am = strtoupper(trim((string)($raw['AuthMode'] ?? '')));
    $m = $am === 'FACE' ? 'FACE' : ($am === 'CARD' ? 'CARD' : ($am === 'PIN' ? 'PIN' : 'FINGERPRINT'));
    if ($am === 'FP') $m = 'FINGERPRINT';
    $d = strtoupper(trim((string)($raw['EventType'] ?? $raw['DoorStatus'] ?? '')));
    return [
      'device_code' => trim((string)($raw['_device_code'] ?? $raw['DeviceID'] ?? '')),
      'employee_no' => trim((string)($raw['UserID'] ?? '')),
      'event_time' => $this->toLocal((string)($raw['DateTime'] ?? '')),
      'verification_method' => $m,
      'direction' => (strpos($d, 'EXIT') !== false || $d === 'OUT') ? 'OUT' : 'IN',
      'vendor_reference' => (string)($raw['LogID'] ?? $raw['EventID'] ?? ''),
    ];
  }
  private function toLocal(string $t): string {
    if ($t === '') return '';
    $ts = strtotime($t);
    return $ts === false ? '' : date('Y-m-d H:i:s', $ts);
  }
}
