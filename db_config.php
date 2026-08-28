<?php

require_once __DIR__ . '/config.php';

$DB_HOST = gradeup_config('DB_HOST', '127.0.0.1');
$DB_NAME = gradeup_config('DB_NAME');
$DB_USER = gradeup_config('DB_USER');
$DB_PASS = gradeup_config('DB_PASS');
$DB_CHARSET = gradeup_config('DB_CHARSET', 'utf8mb4');

$missingDatabaseSettings = [];
foreach (['DB_NAME' => $DB_NAME, 'DB_USER' => $DB_USER, 'DB_PASS' => $DB_PASS] as $key => $value) {
    if ($value === null || $value === '') {
        $missingDatabaseSettings[] = $key;
    }
}

if ($missingDatabaseSettings) {
    error_log('GradeUp database configuration is incomplete: ' . implode(', ', $missingDatabaseSettings));
    http_response_code(500);
    die('Database configuration is missing. See README.md for setup instructions.');
}

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('GradeUp database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    die('Unable to connect to the database.');
}
