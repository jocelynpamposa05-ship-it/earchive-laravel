<?php
// Set headers to allow API access
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from the mobile app
header('Access-Control-Allow-Methods: POST');

session_set_cookie_params(0, '/');
session_start();
include "db_conn.php";

// 1. Security: Check if user is logged in as admin
// Note: Your React Native app must handle cookies/session persistence for this to work.
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// --- AUTO-FIX: Ensure 'uploaded_by' column exists ---
$check_col = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'uploaded_by'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN uploaded_by VARCHAR(100) AFTER file_path");
}

// 2. Handle POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_FILES["pdf_file"])) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        exit();
    }

    $filename = $_FILES["pdf_file"]["name"];
    $tempname = $_FILES["pdf_file"]["tmp_name"];
    $upload_dir = 'uploads/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $clean_filename = preg_replace("/[^a-zA-Z0-9.]/", "_", $filename);
    $target_file_path = $upload_dir . time() . "_" . $clean_filename; 

    if (move_uploaded_file($tempname, $target_file_path)) {
        
        $sql = "INSERT INTO research_papers (title, author, department, subject, date_published, publish_year, keywords, abstract, file_path, uploaded_by, views) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
        
        try {
            $stmt = $conn->prepare($sql);
            
            $title = $_POST['title'];
            $author = $_POST['author'];
            $dept = $_POST['department'];
            $subject = $_POST['subject'] ?? '';
            $date = $_POST['date_published'];
            $publish_year = (int)date("Y", strtotime($date));
            $keywords = $_POST['keywords'] ?? '';
            $abstract = $_POST['abstract'] ?? '';
            $uploaded_by = $_POST['uploaded_by'] ?? $_SESSION['username'];

            $stmt->bind_param("sssssissss", $title, $author, $dept, $subject, $date, $publish_year, $keywords, $abstract, $target_file_path, $uploaded_by);

            if ($stmt->execute()) {
                // Algolia Sync Logic
                try {
                    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                        require_once __DIR__ . '/vendor/autoload.php';
                        $appId = 'WGL9LUXGKD'; 
                        $adminApiKey = '7dea79ab13a168dbe7c73fa8d58e48b5'; 
                        
                        if ($appId !== 'YOUR_ALGOLIA_APP_ID' && class_exists('\Algolia\AlgoliaSearch\SearchClient')) {
                            $client = \Algolia\AlgoliaSearch\SearchClient::create($appId, $adminApiKey);
                            $index = $client->initIndex('research_papers');
                            $new_paper_id = $stmt->insert_id;
                            $record = [
                                'objectID' => (string)$new_paper_id,
                                'title' => $title,
                                'author' => $author,
                                'department' => $dept,
                                'subject' => $subject,
                                'publish_year' => $publish_year,
                                'keywords' => $keywords
                            ];
                            $index->saveObject($record);
                        }
                    }
                } catch (Throwable $e) { /* Ignore Algolia errors */ }

                echo json_encode(['status' => 'success', 'message' => 'Paper uploaded successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>