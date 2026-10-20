<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
if (!empty($_SESSION['admin_id'])) {
  header('Location: admin/dashboard.php');
  exit;
}
if ((int)db()->query("SELECT COUNT(*) FROM admins")->fetchColumn() === 0) {
  header('Location: setup.php');
  exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if (login_locked()) $err = 'Too many failed attempts. Try again in 15 minutes.';
  elseif (login(trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
    header('Location: admin/dashboard.php');
    exit;
  } else $err = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign in · AttendX</title>
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="alternate icon" href="assets/favicon.ico">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <meta name="theme-color" content="#4F46E5">
  <link rel="stylesheet" href="assets/style.css">
</head>

<body>
  <div class="auth">
    <section class="auth-brand"><img src="assets/logo-light.svg" alt="AttendX">
      <div>
        <h2>Attendance, verified at the door.</h2>
        <p>Biometric check-ins from any scanner, turned into clean daily records, late and early-out tracking, and print-ready reports.</p>
        <ul>
          <li>Works with ZKTeco, Suprema, Anviz &amp; Hikvision</li>
          <li>Automatic late &amp; early-out calculation</li>
          <li>Tamper-proof raw event log</li>
        </ul>
      </div>
      <small style="color:#94A3B8">© <?= date('Y') ?> AttendX · Biometric Attendance System</small>
    </section>
    <section class="auth-form">
      <form method="post"><img class="auth-mobile-logo" src="assets/logo.svg" alt="AttendX"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <h1>Welcome back</h1>
        <p class="lead">Sign in to the admin console.</p>
        <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
        <label for="u">Username</label><input id="u" name="username" autocomplete="username" autofocus required>
        <label for="p">Password</label>
        <div class="pw"><input id="p" type="password" name="password" autocomplete="current-password" required><button type="button" onclick="var i=document.getElementById('p');i.type=i.type=='password'?'text':'password';this.textContent=i.type=='password'?'Show':'Hide'">Show</button></div>
        <button class="btn">Sign in</button>
        <p class="foot">Authorized administrators only.</p>
      </form>
    </section>
  </div>
  <script type="module">
  // Import the functions you need from the SDKs you need
  import { initializeApp } from "https://www.gstatic.com/firebasejs/13.0.0/firebase-app.js";
  import { getAnalytics } from "https://www.gstatic.com/firebasejs/13.0.0/firebase-analytics.js";
  // TODO: Add SDKs for Firebase products that you want to use
  // https://firebase.google.com/docs/web/setup#available-libraries

  // Your web app's Firebase configuration
  // For Firebase JS SDK v7.20.0 and later, measurementId is optional
  const firebaseConfig = {
    apiKey: "AIzaSyAby3r1shAdyqx-p2ncT4oI8pvmLf7Wfu4",
    authDomain: "astra-d344a.firebaseapp.com",
    projectId: "astra-d344a",
    storageBucket: "astra-d344a.firebasestorage.app",
    messagingSenderId: "427453100868",
    appId: "1:427453100868:web:53eec0050f44d00c279d11",
    measurementId: "G-5KMX8EGX0W"
  };

  // Initialize Firebase
  const app = initializeApp(firebaseConfig);
  const analytics = getAnalytics(app);
</script>
</body>

</html>