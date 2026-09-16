<?php
session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php"; 

// --- AUTO-FIX: Create authorized_ids table if it doesn't exist ---
$create_table_sql = "CREATE TABLE IF NOT EXISTS `authorized_ids` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_number` VARCHAR(255) NOT NULL UNIQUE,
    `is_used` TINYINT(1) NOT NULL DEFAULT 0
)";
$conn->query($create_table_sql);

if (isset($_POST['student_id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['student_id']);
    
    // Check if ID is authorized and not yet used
    // This query now works because we added 'is_used' to the database
    $query = "SELECT * FROM authorized_ids WHERE id_number='$id' AND is_used=0";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $_SESSION['temp_id'] = $id;
        header("Location: create_account.php");
        exit(); // Always exit after a header redirect
    } else {
        // Redirect back with a clear error message
        header("Location: login.php?error=Invalid or already used ID");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>