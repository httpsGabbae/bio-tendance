<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
if ((int)db()->query("SELECT COUNT(*) FROM admins")->fetchColumn() > 0) { header('Location: index.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $u = trim($_POST['username'] ?? ''); $n = trim($_POST['full_name'] ?? ''); $p = (string)($_POST['password'] ?? '');
  if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $u) || $n === '') $err = 'Enter a name and a valid username (3-50 letters, numbers, . _ -).';
  elseif (strlen($p) < 10) $err = 'Password must be at least 10 characters.';
  elseif ($p !== (string)($_POST['password2'] ?? '')) $err = 'Passwords do not match.';
  else { db()->prepare('INSERT INTO admins (username,password_hash,full_name) VALUES (?,?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT), $n]); audit('setup_admin_created', $u); header('Location: index.php'); exit; }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup · AttendX</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="alternate icon" href="assets/favicon.ico"><link rel="apple-touch-icon" href="assets/apple-touch-icon.png"><meta name="theme-color" content="#4F46E5"><link rel="stylesheet" href="assets/style.css"></head>
<body><div class="auth"><section class="auth-brand"><img src="assets/logo-light.svg" alt="AttendX">
<div><h2>Attendance, verified at the door.</h2><p>Biometric check-ins from any scanner, turned into clean daily records, late and early-out tracking, and print-ready reports.</p>
<ul><li>Works with ZKTeco, Suprema, Anviz &amp; Hikvision</li><li>Automatic late &amp; early-out calculation</li><li>Tamper-proof raw event log</li></ul></div>
<small style="color:#94A3B8">© <?=date('Y')?> AttendX · Biometric Attendance System</small></section>
<section class="auth-form"><form method="post"><img class="auth-mobile-logo" src="assets/logo.svg" alt="AttendX"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<h1>Create admin account</h1><p class="lead">One-time setup. This page locks itself once an admin exists.</p>
<?php if($err): ?><div class="alert err"><?=e($err)?></div><?php endif; ?>
<label for="n">Full name</label><input id="n" name="full_name" required>
<label for="u">Username</label><input id="u" name="username" autocomplete="username" required pattern="[A-Za-z0-9_.-]{3,50}">
<label for="p">Password <small style="font-weight:400;color:var(--mute)">(min. 10 characters)</small></label><input id="p" type="password" name="password" autocomplete="new-password" minlength="10" required>
<label for="p2">Confirm password</label><input id="p2" type="password" name="password2" autocomplete="new-password" required>
<button class="btn">Create account</button><p class="foot">Authorized administrators only.</p></form></section></div></body></html>
