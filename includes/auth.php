<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';
function login_locked(): bool {
  try { $st = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND created_at > (NOW() - INTERVAL 15 MINUTE)");
    $st->execute([$_SERVER['REMOTE_ADDR'] ?? '']); return (int)$st->fetchColumn() >= 5; } catch (Throwable $e) { return false; }
}
function login(string $username, string $password): bool {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $st = db()->prepare("SELECT * FROM admins WHERE username=? AND status='active' LIMIT 1");
  $st->execute([$username]); $a = $st->fetch();
  if (!$a || !password_verify($password, $a['password_hash'])) {
    try { db()->prepare("INSERT INTO login_attempts (ip_address,username) VALUES (?,?)")->execute([$ip, substr($username,0,50)]); } catch (Throwable $e) {}
    audit('login_failed', substr($username,0,50)); return false;
  }
  session_regenerate_id(true);
  $_SESSION['admin_id'] = (int)$a['id']; $_SESSION['admin_user'] = $a['username'];
  try { db()->prepare("DELETE FROM login_attempts WHERE ip_address=?")->execute([$ip]); } catch (Throwable $e) {}
  audit('login'); return true;
}
function logout(): void { $_SESSION = []; if (session_status()===PHP_SESSION_ACTIVE) session_destroy(); }
function current_admin(): ?array {
  if (empty($_SESSION['admin_id'])) return null;
  $st = db()->prepare("SELECT id, username, full_name FROM admins WHERE id=? LIMIT 1");
  $st->execute([$_SESSION['admin_id']]);
  $r = $st->fetch(); return $r ?: null;
}
