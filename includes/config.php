<?php
declare(strict_types=1);
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'bio_attendance');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
define('APP_TIMEZONE', 'Asia/Manila');
define('BASE_PATH', dirname(__DIR__));
