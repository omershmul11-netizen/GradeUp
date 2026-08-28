<?php

if (!function_exists('gradeup_config')) {
    $gradeupLocalConfig = [];
    $gradeupLocalConfigPath = __DIR__ . '/config.local.php';

    if (is_file($gradeupLocalConfigPath)) {
        $loadedConfig = require $gradeupLocalConfigPath;
        if (is_array($loadedConfig)) {
            $gradeupLocalConfig = $loadedConfig;
        }
    }

    function gradeup_config($key, $default = null)
    {
        global $gradeupLocalConfig;

        $environmentValue = getenv($key);
        if ($environmentValue !== false && $environmentValue !== '') {
            return $environmentValue;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        if (isset($gradeupLocalConfig[$key]) && $gradeupLocalConfig[$key] !== '') {
            return $gradeupLocalConfig[$key];
        }

        return $default;
    }

    function gradeup_verify_password($plainPassword, $storedPassword)
    {
        if (password_verify($plainPassword, $storedPassword)) {
            return true;
        }

        // Backward-compatible migration for legacy GradeUp accounts.
        return hash_equals((string) $storedPassword, (string) $plainPassword);
    }

    function gradeup_upgrade_password(PDO $pdo, $userId, $plainPassword, $storedPassword)
    {
        $passwordInfo = password_get_info($storedPassword);
        $isLegacyPassword = empty($passwordInfo['algo']);

        if (!$isLegacyPassword && !password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
            return;
        }

        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $statement = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $statement->execute([$newHash, (int) $userId]);
    }
}
