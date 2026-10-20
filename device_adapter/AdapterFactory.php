<?php
declare(strict_types=1);
require_once __DIR__ . '/VendorAdapterInterface.php';
require_once __DIR__ . '/GenericAdapter.php';
class AdapterFactory {
  public static function get(string $vendor): VendorAdapterInterface {
    $file = __DIR__ . '/' . $vendor . 'Adapter.php';
    if (preg_match('/^(ZKTeco|Suprema|Anviz|Hikvision)$/', $vendor) && file_exists($file)) {
      require_once $file;
      $class = $vendor . 'Adapter';
      return new $class();
    }
    return new GenericAdapter();
  }
}
