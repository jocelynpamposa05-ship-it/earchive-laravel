<?php
// Run this file once to fix missing database columns
include "db_conn.php";

echo "<h2>Checking Database Structure...</h2>";

// 1. Check 'subject' column
$check = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'subject'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN subject VARCHAR(255) AFTER department");
    echo "✅ Added missing column: <strong>subject</strong><br>";
} else {
    echo "☑️ Column <strong>subject</strong> already exists.<br>";
}

// 2. Check 'keywords' column
$check = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'keywords'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN keywords TEXT AFTER publish_year");
    echo "✅ Added missing column: <strong>keywords</strong><br>";
} else {
    echo "☑️ Column <strong>keywords</strong> already exists.<br>";
}

// 3. Check 'abstract' column
$check = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'abstract'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN abstract TEXT AFTER keywords");
    echo "✅ Added missing column: <strong>abstract</strong><br>";
} else {
    echo "☑️ Column <strong>abstract</strong> already exists.<br>";
}

// 4. Check 'uploaded_by' column
$check = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'uploaded_by'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN uploaded_by VARCHAR(100) AFTER file_path");
    echo "✅ Added missing column: <strong>uploaded_by</strong><br>";
} else {
    echo "☑️ Column <strong>uploaded_by</strong> already exists.<br>";
}

// 5. Check 'is_deleted' column in research_papers
$check = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'is_deleted'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    $conn->query("CREATE INDEX idx_is_deleted_rp ON research_papers(is_deleted)");
    echo "✅ Added missing column: <strong>is_deleted</strong> to research_papers<br>";
} else {
    echo "☑️ Column <strong>is_deleted</strong> already exists in research_papers.<br>";
}

// 6. Check 'is_deleted' column in student_users
$check = $conn->query("SHOW COLUMNS FROM student_users LIKE 'is_deleted'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE student_users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
    echo "✅ Added missing column: <strong>is_deleted</strong> to student_users<br>";
} else {
    echo "☑️ Column <strong>is_deleted</strong> already exists in student_users.<br>";
}

// 7. Check 'fullname' column in admins table
$check = $conn->query("SHOW COLUMNS FROM admins LIKE 'fullname'");
if ($check->num_rows == 0) {
    // Add it after username, and allow it to be NULL for existing admins
    $conn->query("ALTER TABLE admins ADD COLUMN fullname VARCHAR(255) NULL AFTER username");
    echo "✅ Added missing column: <strong>fullname</strong> to admins<br>";
} else {
    echo "☑️ Column <strong>fullname</strong> already exists in admins.<br>";
}

// 8. Check 'permissions' column in admins table
$check = $conn->query("SHOW COLUMNS FROM admins LIKE 'permissions'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE admins ADD COLUMN permissions TEXT NULL AFTER email");
    echo "✅ Added missing column: <strong>permissions</strong> to admins<br>";
} else {
    echo "☑️ Column <strong>permissions</strong> already exists in admins.<br>";
}

echo "<br><h3>Database is ready! You can now upload papers.</h3>";
echo "<a href='uploadpaper.php'>Go back to Upload Page</a>";
?>