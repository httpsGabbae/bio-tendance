<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
date_default_timezone_set(APP_TIMEZONE);
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
  session_start();
}
function audit(string $action, string $details=''): void {
  try { require_once __DIR__.'/database.php';
    db()->prepare('INSERT INTO audit_logs (admin_id,action,details,ip_address) VALUES (?,?,?,?)')->execute([$_SESSION['admin_id']??null,$action,$details,$_SERVER['REMOTE_ADDR']??'']);
  } catch (Throwable $e) {}
}
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  $t = $_POST['csrf'] ?? '';
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
    http_response_code(419); echo 'Invalid CSRF token.'; exit;
  }
}
function json_response(int $code, array $data): void {
  http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit;
}
function require_login(): void {
  if (empty($_SESSION['admin_id'])) { header('Location: '.(basename(dirname($_SERVER['SCRIPT_NAME']))==='admin' ? '../index.php' : 'index.php')); exit; }
}
