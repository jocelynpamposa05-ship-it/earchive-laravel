<?php
session_set_cookie_params(0, '/');
include "db_conn.php";

if (isset($_POST['fullname'], $_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password'])) {

    // Sanitize inputs
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $pass     = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. Validate Passwords
    if ($pass !== $confirm_pass) {
        header("Location: register_admin.php?error=Passwords do not match.");
        exit();
    }

    // 2. Check if username is already taken
    $stmt_check_user = $conn->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt_check_user->bind_param("s", $username);
    $stmt_check_user->execute();
    $stmt_check_user->store_result();
    if ($stmt_check_user->num_rows > 0) {
        header("Location: register_admin.php?error=Username is already taken.");
        exit();
    }
    $stmt_check_user->close();

    // 3. Check if email is already taken
    // NOTE: This requires an 'email' column in the 'admins' table.
    $stmt_check_email = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    if ($stmt_check_email) {
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            header("Location: register_admin.php?error=Email is already registered.");
            exit();
        }
        $stmt_check_email->close();
    }

    // 4. Hash the password for security
    $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

    // 5. Save the new admin to the database
    $stmt_insert = $conn->prepare("INSERT INTO admins (fullname, username, email, password) VALUES (?, ?, ?, ?)");
    if ($stmt_insert) {
        $stmt_insert->bind_param("ssss", $fullname, $username, $email, $hashed_pass);

        if ($stmt_insert->execute()) {
            // On success, redirect to the login page with a success message
            header("Location: loginadmin.php?success=Admin account created! You can now log in.");
            exit();
        } else {
            // On failure, redirect back with a generic error
            header("Location: register_admin.php?error=Registration failed. Please try again.");
            exit();
        }
        $stmt_insert->close();
    } else {
        header("Location: register_admin.php?error=Database schema error. Please contact support.");
        exit();
    }

} else {
    // If the form was not submitted correctly, redirect back to the registration page
    header("Location: register_admin.php?error=Please fill out all fields.");
    exit();
}
?>