<?php
// Конфигурация базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'excel_data_db');
define('DB_USER', 'homestead'); // Измените на ваш пользователь БД
define('DB_PASS', 'secret'); // Измените на ваш пароль БД
define('DB_CHARSET', 'utf8mb4');

// Настройки загрузки файлов
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['xlsx', 'xls']);

// Функция для подключения к базе данных
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Ошибка подключения к базе данных: " . $e->getMessage());
    }
}

// Функция для извлечения кода страны из имени файла
function extractCountryCode($filename) {
    // Ищем паттерн вида "-XX-" где XX - код страны
    if (preg_match('/-([A-Z]{2})-/', $filename, $matches)) {
        return $matches[1];
    }
    return 'UNKNOWN';
}

// Функция для извлечения типа ИИ-агента из имени файла
function extractAgentType($filename) {
    // Ищем паттерн до первого дефиса (например, Genspark1 из Genspark1-UK-20250715120738.xlsx)
    if (preg_match('/^([^-]+)-/', $filename, $matches)) {
        return $matches[1];
    }
    return 'UNKNOWN';
}

// Функция для безопасного вывода данных
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Функция для форматирования даты
function formatDate($date) {
    return date('d.m.Y H:i', strtotime($date));
}

// Создание директории для загрузок если не существует
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>

