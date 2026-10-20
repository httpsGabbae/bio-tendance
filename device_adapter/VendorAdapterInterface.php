<?php
declare(strict_types=1);
interface VendorAdapterInterface {
  public function normalize(array $raw): array;
  public function validateSignature(array $raw, string $secret): bool;
}
