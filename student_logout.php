<?php
session_start();

unset($_SESSION['student_id']);
unset($_SESSION['student_name']);

header("Location: student_login.php");
exit;
?>