<?php

require_once __DIR__ . '/../config.php';

$host = gradeup_config('DB_HOST', '127.0.0.1');
$dbname = gradeup_config('DB_NAME');
$username = gradeup_config('DB_USER');
$password = gradeup_config('DB_PASS');

if ($dbname === null || $username === null || $password === null) {
    error_log('GradeUp mysqli configuration is incomplete.');
    http_response_code(500);
    die('Database configuration is missing.');
}

$conn = @new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log('GradeUp mysqli connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('Unable to connect to the database.');
}

$conn->set_charset('utf8mb4');
