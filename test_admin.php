<?php
session_set_cookie_params(0, '/');
session_start();
// Simulate a successful admin login
$_SESSION['id'] = 999;
$_SESSION['username'] = 'Test Admin';
$_SESSION['fullname'] = 'Test Administrator';
$_SESSION['email'] = 'testadmin@example.com';
$_SESSION['role'] = 'admin';

header("Location: dashboardadmin.php");
exit();
?>