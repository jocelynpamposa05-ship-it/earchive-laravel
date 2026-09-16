<?php
session_set_cookie_params(0, '/');
session_start();
header('Content-Type: application/json');
include "../db_conn.php";

// Security check: Ensure user is logged in
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Auto-create inquiries table if it doesn't exist. This is placed first as other tables depend on it.
$create_inquiries_table = "CREATE TABLE IF NOT EXISTS inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES student_users(id) ON DELETE CASCADE
)";
$conn->query($create_inquiries_table);

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
    $user_id = $_SESSION['id'];
    $username = htmlspecialchars($_SESSION['username']);
    $subject = htmlspecialchars($_POST['subject'] ?? '');
    $message = htmlspecialchars($_POST['message'] ?? '');

    // Validate inputs
    if (empty($subject) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        exit();
    }

    // Insert inquiry into database
    $sql = "INSERT INTO inquiries (user_id, username, subject, message, status) 
            VALUES (?, ?, ?, ?, 'unread')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("isss", $user_id, $username, $subject, $message);
    
    if ($stmt->execute()) {
        $inquiry_id = $conn->insert_id;

        // --- START NOTIFICATION LOGIC ---
        // Notify all admins about the new inquiry
        $admin_users_query = "SELECT id FROM users WHERE role = 'admin'";
        $admin_users_result = $conn->query($admin_users_query);

        if ($admin_users_result && $admin_users_result->num_rows > 0) {
            $notif_message = "New inquiry #{$inquiry_id} from {$username}.";
            $notif_sql = "INSERT INTO notifications (user_id, user_role, inquiry_id, notification_type, message) VALUES (?, 'admin', ?, 'new_inquiry', ?)";
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
        echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully!']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error sending message: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
