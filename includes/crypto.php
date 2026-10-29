<?php
// includes/crypto.php — шифрование персональных и медицинских данных (AES-256-CBC).
// Подключается там, где поля с суффиксом _enc записываются или читаются.

// ВАЖНО: в рабочей версии ключ хранится вне репозитория (переменная окружения / отдельный конфиг).
// Демо-значение ниже совпадает с тестовыми данными из medlife_db_v2.sql.
define('DATA_KEY', hash('sha256', 'MedLifeDemoKey2026', true));

// Шифрует строку; в БД хранится base64(IV . шифртекст)
function encrypt_field($plain) {
    if ($plain === null || $plain === '') {
        return null;
    }
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', DATA_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

// Расшифровывает значение из БД; при ошибке возвращает пустую строку
function decrypt_field($stored) {
    if ($stored === null || $stored === '') {
        return '';
    }
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', DATA_KEY, OPENSSL_RAW_DATA, substr($raw, 0, 16));
    return $plain === false ? '' : $plain;
}
