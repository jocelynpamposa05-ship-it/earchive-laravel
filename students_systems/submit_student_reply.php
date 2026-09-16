<?php
session_set_cookie_params(0, '/');
session_start();
header('Content-Type: application/json');
include "../db_conn.php";

// Security check: Ensure user is logged in as student
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Auto-create table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS inquiry_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    reply_message LONGTEXT NOT NULL,
    reply_type ENUM('admin', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
)";
$conn->query($create_table_sql);

// Auto-create notifications table if it doesn't exist
$create_notif_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('admin', 'student') NOT NULL,
    inquiry_id INT NOT NULL,
    notification_type ENUM('new_inquiry', 'new_reply') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
)";
$conn->query($create_notif_table);

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inquiry_id = isset($_POST['inquiry_id']) ? intval($_POST['inquiry_id']) : 0;
    $user_id = $_SESSION['id'];
    $username = htmlspecialchars($_SESSION['username']);
    $reply_message = htmlspecialchars($_POST['reply_message'] ?? '');

    // Validate inputs
    if ($inquiry_id <= 0 || empty($reply_message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit();
    }

    // Verify that the inquiry belongs to this student
    $verify_query = "SELECT id FROM inquiries WHERE id = ? AND user_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("ii", $inquiry_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only reply to your own inquiries']);
        exit();
    }
    $verify_stmt->close();

    // Insert reply into database
    $sql = "INSERT INTO inquiry_replies (inquiry_id, user_id, username, reply_message, reply_type) 
            VALUES (?, ?, ?, ?, 'student')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("iiss", $inquiry_id, $user_id, $username, $reply_message);
    
    if ($stmt->execute()) {
        // --- START NOTIFICATION LOGIC ---
        // Notify all admins about the new student reply
        $admin_users_query = "SELECT id FROM users WHERE role = 'admin'";
        $admin_users_result = $conn->query($admin_users_query);

        if ($admin_users_result && $admin_users_result->num_rows > 0) {
            $notif_message = "A student has replied to inquiry #{$inquiry_id}.";
            $notif_sql = "INSERT INTO notifications (user_id, user_role, inquiry_id, notification_type, message) VALUES (?, 'admin', ?, 'new_reply', ?)";
            $notif_stmt = $conn->prepare($notif_sql);

            if ($notif_stmt) {
                while ($admin_row = $admin_users_result->fetch_assoc()) {
                    $admin_id = $admin_row['id'];
                    $notif_stmt->bind_param("iis", $admin_id, $inquiry_id, $notif_message);
                    $notif_stmt->execute();
                }
                $notif_stmt->close();
            }
        }
        // --- END NOTIFICATION LOGIC ---

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Your reply has been sent successfully!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error sending reply: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>