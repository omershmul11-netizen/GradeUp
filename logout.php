<?php

session_set_cookie_params(0);
session_start();

// מחיקת כל משתני ה־SESSION
$_SESSION = [];

// מחיקת SESSION
session_unset();
session_destroy();

// מחיקת קוקי SESSION מהדפדפן
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// חזרה למסך LOGIN
header("Location: login.php");
exit;

?>