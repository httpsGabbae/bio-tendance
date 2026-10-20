<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
class GenericAdapter implements VendorAdapterInterface {
  public function normalize(array $raw): array {
    return [
      'device_code' => trim((string)($raw['device_code'] ?? '')),
      'employee_no' => trim((string)($raw['employee_no'] ?? '')),
      'event_time' => trim((string)($raw['event_time'] ?? '')),
      'verification_method' => strtoupper(trim((string)($raw['verification_method'] ?? ''))),
      'direction' => strtoupper(trim((string)($raw['direction'] ?? ''))),
      'vendor_reference' => (string)($raw['vendor_reference'] ?? ''),
    ];
  }
  public function validateSignature(array $raw, string $secret): bool { return true; }
}
