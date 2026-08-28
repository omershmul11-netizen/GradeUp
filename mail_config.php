<?php

require_once __DIR__ . '/config.php';

if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', gradeup_config('BREVO_API_KEY', ''));
}

if (!defined('MAIL_FROM_EMAIL')) {
    define('MAIL_FROM_EMAIL', gradeup_config('MAIL_FROM_EMAIL', ''));
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', gradeup_config('MAIL_FROM_NAME', 'GradeUp'));
}
