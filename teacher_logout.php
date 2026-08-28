<?php
session_start();

unset($_SESSION['teacher_id']);
unset($_SESSION['teacher_username']);
unset($_SESSION['teacher_name']);
unset($_SESSION['teacher_email']);

header("Location: teacher_login.php");
exit;
?>