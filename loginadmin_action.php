<?php
session_set_cookie_params(0, '/');
session_start();
// Admin Login Action
include "db_conn.php";

if (isset($_POST['username']) && isset($_POST['password'])) {
    $uname = trim($_POST['username']);
    $pass = $_POST['password'];

    // 1. CHECK ADMIN TABLE FIRST
    try {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    } catch (Exception $e) {
        die("Database Error: The 'admins' table is missing. Please run the SQL setup script in phpMyAdmin.");
    }
    if (!$stmt) {
        die("Database Error (Admins): " . $conn->error);
    }
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        // For simplicity in this phase, we are comparing plain text for admin. 
        // Ideally, use password_verify($pass, $row['password'])
        if ($pass === $row['password'] || password_verify($pass, $row['password'])) {
            // session_regenerate_id();
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'] ?? $row['username']; // Use fullname, fallback to username
            $_SESSION['email'] = $row['email'] ?? 'N/A'; // Add admin email to session
            $_SESSION['role'] = 'admin';
            session_write_close(); // Ensure session is written before redirect
            header("Location: dashboardadmin.php");
            exit();
        }
    }
    $stmt->close();

    // 2. CHECK STUDENT TABLE
    try {
        $stmt = $conn->prepare("SELECT * FROM student_users WHERE username = ?");
    } catch (Exception $e) {
        die("Database Error: The 'student_users' table is missing.");
    }
    if (!$stmt) {
        die("Database Error (Students): " . $conn->error);
    }
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            // session_regenerate_id();
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = 'student';
            session_write_close(); // Ensure session is written before redirect
            header("Location: students_systems/dashboard.php");
            exit();
        }
    }
    $stmt->close();

    // 3. IF NO MATCH
    header("Location: loginadmin.php?error=Incorrect Username or Password");
    exit();

} else {
    header("Location: loginadmin.php");
    exit();
}
?>