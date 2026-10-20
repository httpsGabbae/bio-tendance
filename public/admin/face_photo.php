<?php
require_once __DIR__ . '/../../includes/bootstrap.php'; require_login();
$f = __DIR__ . '/../../storage/faces/' . (int)($_GET['id'] ?? 0) . '.jpg';
if (!is_file($f)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg'); header('Cache-Control: private, max-age=300'); readfile($f);
