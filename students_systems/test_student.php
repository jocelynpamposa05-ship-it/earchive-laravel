<?php
session_set_cookie_params(0, '/');
session_start();
// Simulate a successful student login
$_SESSION['id'] = 888;
$_SESSION['username'] = 'Test Student';
$_SESSION['role'] = 'student';

header("Location: dashboard.php");
exit();
?>