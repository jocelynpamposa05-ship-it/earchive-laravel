<?php
session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php"; // Go up one level to the main Earchive folder for the connection

if (isset($_POST['username']) && isset($_POST['password'])) {
    $uname = $_POST['username'];
    $pass = $_POST['password'];

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM student_users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Verify the hashed password
        if (password_verify($pass, $row['password'])) {
            // session_regenerate_id(); // Removed to fix redirect loop
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['student_id'] = $row['student_id'];
            $_SESSION['department'] = $row['department'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = 'student'; // Set a role for students
            
            // CRITICAL: Save the session data before jumping to the next page
            session_write_close(); 
            header("Location: dashboard.php");
            exit();
        } else {
            header("Location: login.php?error=Incorrect Password");
            exit();
        }
    } else {
        header("Location: login.php?error=Username not found");
        exit();
    }
    $stmt->close();
} else {
    header("Location: login.php");
    exit();
}
?>