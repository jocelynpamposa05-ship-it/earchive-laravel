<?php
// This script is included by db_conn.php to ensure the database schema is always up-to-date.

function setup_database($conn) {
    if (!$conn) {
        // If $conn is not valid, do nothing.
        return;
    }

    // 1. Ensure 'research_papers' table has all required columns
    $queries = [
        "ALTER TABLE research_papers ADD COLUMN IF NOT EXISTS subject VARCHAR(255) AFTER department",
        "ALTER TABLE research_papers ADD COLUMN IF NOT EXISTS keywords TEXT AFTER publish_year",
        "ALTER TABLE research_papers ADD COLUMN IF NOT EXISTS abstract TEXT AFTER keywords",
        "ALTER TABLE research_papers ADD COLUMN IF NOT EXISTS uploaded_by VARCHAR(100) AFTER file_path",
        "ALTER TABLE research_papers ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0",
        "ALTER TABLE research_papers ADD COLUMN IF NOT EXISTS views INT DEFAULT 0"
    ];

    foreach ($queries as $query) {
        @$conn->query($query);
    }

    // 2. Ensure 'student_users' table has 'is_deleted' column
    @$conn->query("ALTER TABLE student_users ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0");

    // 3. Ensure 'admins' table has 'fullname', 'email', and 'permissions' columns
    @$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS fullname VARCHAR(255) NULL AFTER username");
    @$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER fullname");
    @$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS permissions TEXT NULL AFTER email");

    // 4. Ensure 'view_logs' table exists for tracking paper views
    $create_view_logs_table = "CREATE TABLE IF NOT EXISTS view_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        paper_id INT NOT NULL,
        user_id INT DEFAULT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (paper_id) REFERENCES research_papers(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES student_users(id) ON DELETE SET NULL
    )";
    $conn->query($create_view_logs_table);

    // NEW: Ensure 'user_id' column exists in 'view_logs' table
    $check = $conn->query("SHOW COLUMNS FROM view_logs LIKE 'user_id'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE view_logs ADD COLUMN user_id INT DEFAULT NULL AFTER paper_id");
        echo "✅ Added missing column: <strong>user_id</strong> to view_logs<br>";
    } else {
        echo "☑️ Column <strong>user_id</strong> already exists in view_logs.<br>";
    }

    // 5. Ensure 'password_resets' table exists for password recovery
    $create_password_resets_table = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires BIGINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_password_resets_table);

    // Create an index on the token for faster lookups
    @$conn->query("CREATE INDEX idx_token ON password_resets(token)");
}
?>