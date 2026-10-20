<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD']==='POST') { csrf_check(); audit('logout'); logout(); }
header('Location: index.php'); exit;
