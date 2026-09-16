<?php
session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php";

if (isset($_POST['fullname'], $_POST['username'], $_POST['email'], $_POST['password'], $_POST['department'])) {

    if (!isset($_SESSION['temp_id'])) {
        header("Location: verifyid.php?error=Session expired. Please verify ID again.");
        exit();
    }
    
    $id_num     = $_SESSION['temp_id']; 
    $fullname   = $_POST['fullname'];
    $username   = $_POST['username']; 
    $email      = $_POST['email'];
    $pass       = $_POST['password'];
    $department = $_POST['department'];

    $error_msgs = [];

    // Basic Empty Checks
    if (empty($fullname)) $error_msgs[] = "Fullname is required.";
    if (empty($username)) $error_msgs[] = "Username is required.";
    if (empty($department)) $error_msgs[] = "Department is required.";

    // Password Security Validation
    if (strlen($pass) < 8) {
        $error_msgs[] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) || !preg_match('/[^a-zA-Z0-9]/', $pass)) {
        $error_msgs[] = "Password must contain at least one uppercase letter, one lowercase letter, and one symbol.";
    }
    
    // Validate Email Format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msgs[] = "Invalid email format.";
    }

    if (!empty($error_msgs)) {
        header("Location: create_account.php?error=" . urlencode(implode("\n", $error_msgs)));
        exit();
    }
    
    // Use a transaction to ensure both user creation and ID marking succeed or fail together
    $conn->begin_transaction();

    try {
        // 1. Check if the USERNAME is already taken
        $stmt = $conn->prepare("SELECT id FROM student_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Username is already taken. Choose another.");
        }
        $stmt->close();

        // 2. Check if the EMAIL is already taken
        $stmt = $conn->prepare("SELECT id FROM student_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("This email is already registered.");
        }
        $stmt->close();

        // 3. Hash the password
        $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);

        // 4. Save to database
        $stmt = $conn->prepare("INSERT INTO student_users (student_id, username, fullname, email, password, department) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $id_num, $username, $fullname, $email, $hashed_pass, $department);
        if (!$stmt->execute()) { throw new Exception("Registration failed during user creation."); }
        $stmt->close();

        // 5. Mark ID as used
        $stmt = $conn->prepare("UPDATE authorized_ids SET is_used = 1 WHERE id_number = ?");
        $stmt->bind_param("s", $id_num);
        if (!$stmt->execute()) { throw new Exception("Registration failed while updating ID status."); }
        $stmt->close();

        // If all queries were successful, commit the transaction
        $conn->commit();
        
        unset($_SESSION['temp_id']);
        header("Location: login.php?success=Account created! Log in with username: " . urlencode($username));
        exit();

    } catch (Exception $e) {
        // If any query failed, roll back the transaction
        $conn->rollback();
        header("Location: create_account.php?error=" . urlencode($e->getMessage()));
        exit();
    }

} else {
    header("Location: create_account.php?error=Please fill out all fields.");
    exit();
}
?>