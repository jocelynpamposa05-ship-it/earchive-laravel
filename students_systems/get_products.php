<?php
header('Content-Type: application/json');

// 1. Include the central connection file to ensure accurate name and path
// This uses the settings from C:\xampp\htdocs\Earchive\db_conn.php
include "../db_conn.php";

// 2. Check if connection was successful (db_conn.php usually handles this, but we add a fallback check)
if (!$conn) {
    // Fallback: Send TEST DATA so the app interface still loads if DB is down
    $test_data = [
        [
            "id" => 1,
            "student_id" => "2024-001",
            "fullname" => "Clint Ver (Test Data)",
            "email" => "clint@example.com",
            "department" => "Computer Studies"
        ],
        [
            "id" => 2,
            "student_id" => "2024-002",
            "fullname" => "John Doe",
            "email" => "john@example.com",
            "department" => "Agribusiness"
        ]
    ];
    echo json_encode($test_data);
    exit();
}

// 3. Query your table
$sql = "SELECT id, student_id, fullname, email, department FROM student_users";
$result = $conn->query($sql);

$products = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// 4. Output as JSON
echo json_encode($products);

$conn->close();
?>
