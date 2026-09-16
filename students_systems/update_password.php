<?php
session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php";

// Security check
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['code_verified']) || $_SESSION['code_verified'] !== true) {
    header("Location: forgot_password.php?error=Unauthorized access.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    $email = $_SESSION['reset_email'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. Validate passwords
    if (strlen($new_pass) < 8) {
        header("Location: reset_password.php?error=Password must be at least 8 characters.");
        exit();
    }
    if (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[a-z]/', $new_pass) || !preg_match('/[^a-zA-Z0-9]/', $new_pass)) {
        header("Location: reset_password.php?error=Password must contain at least one uppercase letter, one lowercase letter, and one symbol.");
        exit();
    }
    if ($new_pass !== $confirm_pass) {
        header("Location: reset_password.php?error=Passwords do not match.");
        exit();
    }

    // 2. Hash the new password
    $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

    // 3. Update the user's password in the database
    $stmt = $conn->prepare("UPDATE student_users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed_pass, $email);
    
    if ($stmt->execute()) {
        // 4. Delete the reset code from the database
        $stmt_del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt_del->bind_param("s", $email);
        $stmt_del->execute();
        $stmt_del->close();

        // 5. Clean up session and redirect to login with success message
        unset($_SESSION['reset_email']);
        unset($_SESSION['code_verified']);
        session_destroy();

        header("Location: login.php?success=Password has been reset successfully. You can now log in.");
        exit();
    } else {
        header("Location: reset_password.php?error=Failed to update password. Please try again.");
        exit();
    }
    $stmt->close();

} else {
    header("Location: reset_password.php");
    exit();
}