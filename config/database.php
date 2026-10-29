<?php
// config/database.php — подключение к БД и запуск сессии.
// Open Server: хост localhost, пользователь root, пароль пустой (при необходимости измените ниже).
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medlife');

mysqli_report(MYSQLI_REPORT_OFF);
$link = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$link) {
    http_response_code(500);
    exit('Ошибка подключения к базе данных. Проверьте config/database.php и импорт database/medlife_db.sql.');
}
mysqli_set_charset($link, 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/functions.php';
