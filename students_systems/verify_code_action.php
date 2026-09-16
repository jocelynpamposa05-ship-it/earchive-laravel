<?php
session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php";

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $email = $_SESSION['reset_email'];
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $current_time = time();

    // 1. Check if the code is valid and not expired
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND code = ? AND expires_at > ?");
    $stmt->bind_param("ssi", $email, $code, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Code is correct, set a session flag and redirect to reset password page
        $_SESSION['code_verified'] = true;
        header("Location: reset_password.php");
        exit();
    } else {
        // Code is incorrect or expired
        header("Location: verify_code.php?error=Invalid or expired verification code.");
        exit();
    }
    $stmt->close();

} else {
    header("Location: verify_code.php");
    exit();
}