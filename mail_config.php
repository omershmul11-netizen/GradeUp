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

if (!function_exists('gradeup_mail_demo_enabled')) {
    function gradeup_mail_demo_enabled()
    {
        $value = strtolower(trim((string) gradeup_config('GRADEUP_DEMO_MODE', '0')));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gradeup_record_demo_email')) {
    function gradeup_record_demo_email($toEmail, $toName, $subject, $htmlBody)
    {
        global $pdo;

        if (!gradeup_mail_demo_enabled() || !($pdo instanceof PDO)) {
            return false;
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO email_outbox (recipient_email, recipient_name, subject, html_body, delivery_mode, status) '
                . "VALUES (?, ?, ?, ?, 'demo', 'simulated')"
            );
            $statement->execute([
                trim((string) $toEmail),
                trim((string) $toName),
                trim((string) $subject),
                (string) $htmlBody,
            ]);
            return true;
        } catch (Throwable $exception) {
            error_log('GradeUp demo email could not be recorded: ' . $exception->getMessage());
            return false;
        }
    }
}
