<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php";

// Security Check: Ensure only admin can access
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// --- AUTO-FIX: Ensure 'student_ids' table exists ---
$create_table = "CREATE TABLE IF NOT EXISTS student_ids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    department VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by VARCHAR(100)
)";
$conn->query($create_table);

$success_message = '';
$error_message = '';
$upload_stats = '';

// Handle Excel file upload
if (isset($_POST['upload_btn']) && isset($_FILES['excel_file'])) {
    $file_tmp = $_FILES['excel_file']['tmp_name'];
    $file_name = $_FILES['excel_file']['name'];
    $file_error = $_FILES['excel_file']['error'];
    
    // Validate file
    if ($file_error === UPLOAD_ERR_OK) {
        // Check file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Accept both .xlsx and .csv
        if (!in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
            $error_message = "❌ Invalid file format. Please upload an Excel (.xlsx, .xls) or CSV (.csv) file.";
        } else {
            try {
                // If CSV file
                if ($file_ext === 'csv') {
                    $handle = fopen($file_tmp, 'r');
                    $row_count = 0;
                    $success_count = 0;
                    $duplicate_count = 0;
                    
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $row_count++;
                        
                        // Skip header row
                        if ($row_count === 1) continue;
                        
                        // Ensure we have at least student ID
                        if (empty($data[0])) continue;
                        
                        $student_id = trim($data[0]);
                        $first_name = trim($data[1] ?? '');
                        $last_name = trim($data[2] ?? '');
                        $department = trim($data[3] ?? '');
                        $uploaded_by = $_SESSION['username'];
                        
                        // Insert or skip if duplicate
                        $stmt = $conn->prepare("INSERT INTO student_ids (student_id, first_name, last_name, department, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $student_id, $first_name, $last_name, $department, $uploaded_by);
                        
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            // Check if duplicate key error
                            if (strpos($stmt->error, 'Duplicate entry') !== false) {
                                $duplicate_count++;
                            }
                        }
                        $stmt->close();
                    }
                    
                    fclose($handle);
                    
                    $upload_stats = "📊 Upload Summary:<br>
                        ✅ Successfully imported: $success_count student(s)<br>";
                    
                    if ($duplicate_count > 0) {
                        $upload_stats .= "⚠️ Duplicates skipped: $duplicate_count student(s)<br>";
                    }
                    
                    $success_message = "✅ File processed successfully! " . $upload_stats;
                    
                } else if (in_array($file_ext, ['xlsx', 'xls'])) {
                    // For Excel files, we need to use a library
                    // For now, we'll provide instructions to convert to CSV
                    $error_message = "💡 Excel files (.xlsx, .xls) need to be converted to CSV format first.<br>
                    <strong>Steps:</strong><br>
                    1. Open your Excel file<br>
                    2. Click 'Save As'<br>
                    3. Select 'CSV (Comma delimited)' format<br>
                    4. Upload the CSV file here<br><br>
                    <strong>CSV Format Required:</strong><br>
                    <code>student_id, first_name, last_name, department</code><br>
                    <code>2023001, John, Doe, COMPUTER STUDIES</code>";
                }
            } catch (Exception $e) {
                $error_message = "❌ Error processing file: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "❌ File upload error. Please try again.";
    }
}

// Fetch existing student IDs
$result = $conn->query("SELECT * FROM student_ids ORDER BY uploaded_at DESC LIMIT 100");
$recent_uploads = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_uploads[] = $row;
    }
}

// Get statistics
$total_stmt = $conn->query("SELECT COUNT(*) as total FROM student_ids");
$total_row = $total_stmt->fetch_assoc();
$total_students = $total_row['total'] ?? 0;

$active_page = 'upload_student_id';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Student ID | Admin</title>

    <!-- PWA Headers -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="img/cpsu_logo.png">
    <meta name="theme-color" content="#116913">

    <link rel="stylesheet" href="uploadpaper.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #116913 0%, #1a9b1f 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        .csv-format-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-family: monospace;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        
        .csv-format-table th,
        .csv-format-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .csv-format-table th {
            background-color: #116913;
            color: white;
        }
        
        .recent-uploads {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .recent-uploads h3 {
            margin-top: 0;
            color: #116913;
        }
        
        .upload-list {
            list-style: none;
            padding: 0;
        }
        
        .upload-list li {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .upload-list li:last-child {
            border-bottom: none;
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-info .id {
            font-weight: bold;
            color: #116913;
        }
        
        .student-info .meta {
            font-size: 0.85rem;
            color: #999;
        }
        
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .delete-btn:hover {
            background-color: #c82333;
        }

        /* --- New Modal Styles --- */
        .modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }
        .modal-container {
            background: #ffffff;
            padding: 25px;
            border-radius: 16px;
            width: 90%;
            max-width: 550px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: bold;
            color: #333333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header .close-btn {
            background: transparent;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #888;
            line-height: 1;
        }
        .modal-header .close-btn:hover { color: #333; }

        .drag-drop-box {
            text-align: center;
            padding: 40px 20px;
            background: #f9f9f9;
            border: 2px dashed #116913;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .drag-drop-box.drag-over { background-color: #e8f5e9; }
        .drag-drop-box i { font-size: 2.5rem; color: #116913; margin-bottom: 15px; }
        .drag-drop-box h4 { font-weight: bold; color: #333333; }
        .drag-drop-box.error {
            border-color: #dc3545;
            background-color: #f8d7da;
            animation: shake 0.3s ease-in-out;
        }
        #file-chosen { color: #666; margin-top: 5px; }
        #file-chosen.selected { color: #004d00; font-weight: bold; }

        .format-info { margin-top: 15px; padding: 12px; background: #f1f1f1; border-radius: 8px; text-align: left; }
        .format-info strong { color: #333333; }
        .format-info code { font-family: monospace; margin-top: 8px; display: block; background: #e9e9e9; padding: 5px; border-radius: 4px; }

        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px; }
        .modal-btn { padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: background-color 0.2s; }
        .modal-btn.cancel { background: #fff; border: 1px solid #ddd; color: #555; }
        .modal-btn.cancel:hover { background: #f1f1f1; }
        .modal-btn.upload { background: #116913; border: none; color: #fff; }
        .modal-btn.upload:hover { background: #004d00; }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        <?php include 'admin_sidebar.php'; ?>

        <main class="content">
            <div class="form-container">
                <h2><i class="fa-solid fa-id-card"></i> Student IDs</h2>

                <!-- Top controls: total + upload button -->
                <div style="display:flex; align-items:center; justify-content:space-between; gap:15px; margin-bottom:20px;">
                    <div class="stat-card" style="flex:0 0 220px; text-align:left; padding:15px;">
                        <h3 style="font-size:0.9rem; margin:0 0 6px 0;">Total Students</h3>
                        <div class="number" style="font-size:1.6rem;"><?php echo $total_students; ?></div>
                    </div>

                    <div style="margin-left:auto;">
                        <button id="openUploadModal" class="view-btn" style="background:#116913; color:white; border-radius:8px; padding:10px 14px; font-size:1rem;">
                            <i class="fa-solid fa-plus"></i> Add Student ID
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <strong>Success!</strong><br>
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <strong>Error:</strong><br>
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Upload Modal (hidden by default). Triggered by the + button -->
                <div id="uploadModal" class="modal-overlay">
                    <div class="modal-container">
                        <div class="modal-header">
                            <h3><i class="fa-solid fa-file-csv"></i> Upload CSV File</h3>
                            <button id="closeUploadModal" class="close-btn">&times;</button>
                        </div>
                        <form id="uploadCsvForm" action="upload_student_id.php" method="POST" enctype="multipart/form-data" novalidate>
                            <div class="drag-drop-box" id="dropZone">
                                <i class="fa-solid fa-file-excel"></i>
                                <h4>Drag and drop your CSV file here or click to browse</h4>
                                <input type="file" name="excel_file" id="excel_file" hidden required accept=".csv,.xlsx,.xls">
                                <div id="file-chosen">No file selected.</div>
                            </div>

                            <div class="format-info">
                                <strong>CSV Format Required:</strong>
                                <code>student_id,first_name,last_name,department</code>
                            </div>

                            <div class="modal-actions">
                                <button type="button" id="cancelUpload" class="modal-btn cancel">Cancel</button>
                                <button type="submit" name="upload_btn" class="modal-btn upload">Upload Student IDs</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Student List -->
                <div class="recent-uploads">
                    <h3><i class="fa-solid fa-list"></i> Students</h3>
                    <ul class="upload-list">
                        <?php if (!empty($recent_uploads)): ?>
                            <?php foreach ($recent_uploads as $upload): ?>
                                <li>
                                    <div class="student-info">
                                        <div class="id"><?php echo htmlspecialchars($upload['student_id']); ?></div>
                                        <div class="meta">
                                            <?php echo htmlspecialchars($upload['first_name'] . ' ' . $upload['last_name']); ?> &middot; 
                                            <?php echo htmlspecialchars($upload['department']); ?> 
                                            <div style="font-size:0.8rem; color:#888;">Uploaded by: <?php echo htmlspecialchars($upload['uploaded_by']); ?> on <?php echo date('M d, Y H:i', strtotime($upload['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="padding:15px; text-align:center; color:#666;">No student IDs found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script>
        // File input handling
        const fileInput = document.getElementById('excel_file');
        const fileChosen = document.getElementById('file-chosen');
        const dropZone = document.getElementById('dropZone');

        // Handle file selection
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileChosen.textContent = this.files[0].name;
                dropZone.classList.remove('error');
                fileChosen.classList.add('selected');
            }
        });

        // Handle drag and drop
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', function() {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileChosen.textContent = files[0].name;
                dropZone.classList.remove('error');
                fileChosen.classList.add('selected');
            }
        });

        // Trigger file input when drop zone is clicked
        dropZone.addEventListener('click', function() {
            fileInput.click();
        });

        // Modal controls
        const uploadModal = document.getElementById('uploadModal');
        const openUploadModal = document.getElementById('openUploadModal');
        const closeUploadModal = document.getElementById('closeUploadModal');
        const cancelUpload = document.getElementById('cancelUpload');

        if (openUploadModal) {
            openUploadModal.addEventListener('click', () => {
                uploadModal.style.display = 'flex';
            });
        }
        if (closeUploadModal) closeUploadModal.addEventListener('click', () => uploadModal.style.display = 'none');
        if (cancelUpload) cancelUpload.addEventListener('click', () => uploadModal.style.display = 'none');

        // Form validation
        const uploadForm = document.getElementById('uploadCsvForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(event) {
                if (fileInput.files.length === 0) {
                    event.preventDefault(); // Stop form submission
                    dropZone.classList.add('error');
                    fileChosen.textContent = "Please select a file before uploading.";
                }
            });
        }
    </script>
    <script src="app.js"></script>
</body>
</html>
