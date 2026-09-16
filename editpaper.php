<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php";

// Security Check: Ensure only admin can access
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// --- AUTO-FIX: Ensure 'uploaded_by' column exists ---
$check_col = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'uploaded_by'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN uploaded_by VARCHAR(100) AFTER file_path");
}

// Fetch Paper Details
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM research_papers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paper = $result->fetch_assoc();
    $stmt->close();

    if (!$paper) {
        header("Location: collectionadmin.php");
        exit();
    }
} else {
    header("Location: collectionadmin.php");
    exit();
}

// Handle Update Form Submission
if (isset($_POST['update_btn'])) {
    $id = $_POST['paper_id'];
    $title = $_POST['title'];
    $authors = $_POST['authors'] ?? [];
    if (!is_array($authors)) {
        $authors = [$authors];
    }
    $authors = array_filter(array_map('trim', $authors));
    $author = implode(', ', $authors);
    $department = $_POST['department'];
    $subject = $_POST['subject'];
    $keywords = $_POST['keywords'] ?? '';
    $abstract = $_POST['abstract'] ?? '';
    $date_input = $_POST['date_published'];
    $date_published = $date_input . '-01'; // Append day to make it a valid DATE format
    $publish_year = (int)substr($date_input, 0, 4); // Automatically extract the year
    $uploaded_by = $_POST['uploaded_by'];
    
    $new_file_path = null;
    // --- Handle new file upload ---
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $filename = $_FILES["pdf_file"]["name"];
        $tempname = $_FILES["pdf_file"]["tmp_name"];
        $upload_dir = 'uploads/';
        
        $clean_filename = preg_replace("/[^a-zA-Z0-9.]/", "_", $filename);
        $target_file_path = $upload_dir . time() . "_" . $clean_filename;

        if (move_uploaded_file($tempname, $target_file_path)) {
            // New file moved successfully, delete the old one
            if (!empty($paper['file_path']) && file_exists($paper['file_path'])) {
                unlink($paper['file_path']);
            }
            $new_file_path = $target_file_path;
        }
    }

    // --- Update Database ---
    if ($new_file_path) {
        // If a new file was uploaded, update the file_path field
        $stmt = $conn->prepare("UPDATE research_papers SET title=?, author=?, department=?, subject=?, date_published=?, publish_year=?, uploaded_by=?, keywords=?, abstract=?, file_path=? WHERE id=?");
        $stmt->bind_param("ssssssisssi", $title, $author, $department, $subject, $date_published, $publish_year, $uploaded_by, $keywords, $abstract, $new_file_path, $id);
    } else {
        // Otherwise, update everything except the file_path
        $stmt = $conn->prepare("UPDATE research_papers SET title=?, author=?, department=?, subject=?, date_published=?, publish_year=?, uploaded_by=?, keywords=?, abstract=? WHERE id=?");
        $stmt->bind_param("sssssisssi", $title, $author, $department, $subject, $date_published, $publish_year, $uploaded_by, $keywords, $abstract, $id);
    }

    if ($stmt->execute()) {
        // --- Algolia Sync (Update Search Index) ---
        try {
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
                $appId = 'WGL9LUXGKD';
                $adminApiKey = '7dea79ab13a168dbe7c73fa8d58e48b5';
                
                if ($appId !== 'YOUR_ALGOLIA_APP_ID' && class_exists('\Algolia\AlgoliaSearch\SearchClient')) {
                    $client = \Algolia\AlgoliaSearch\SearchClient::create($appId, $adminApiKey);
                    $index = $client->initIndex('research_papers');
                    
                    $record = [
                        'objectID' => (string)$id, 'title' => $title, 'author' => $author,
                        'department' => $department, 'subject' => $subject, 'publish_year' => $publish_year,
                        'keywords' => $keywords
                    ];
                    $index->partialUpdateObject($record); // Sync changes to Algolia
                }
            }
        } catch (Throwable $e) { /* Ignore errors if offline */ }

        $_SESSION['update_success'] = true;
        header("Location: " . $_SERVER['REQUEST_URI']); // Redirect to the same page to show modal
        exit();
    } else {
        echo "<script>alert('Database Error: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Set active page for sidebar
$active_page = 'collection';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Paper | Admin</title>
    <!-- Reuse uploadpaper styles for the form -->
    <link rel="stylesheet" href="uploadpaper.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- New Success Modal --- */
        .success-modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            align-items: center; justify-content: center; z-index: 10000;
        }
        .success-modal-box {
            background: #fff; padding: 30px; border-radius: 20px; text-align: center;
            width: 90%; max-width: 380px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            animation: zoomInSuccess 0.3s ease-out;
        }
        @keyframes zoomInSuccess { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .success-badge {
            width: 80px; height: 80px; background: #116913; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 20px; position: relative;
        }
        .success-badge i { font-size: 2.5rem; color: white; }
        /* Sparkle Accents */
        .success-badge::before, .success-badge::after {
            content: '✨'; position: absolute; font-size: 1.5rem;
            animation: sparkle 1.5s infinite;
        }
        .success-badge::before { top: -10px; left: -10px; animation-delay: 0s; }
        .success-badge::after { bottom: -5px; right: -15px; animation-delay: 0.5s; }
        @keyframes sparkle { 0%, 100% { transform: scale(0.8); opacity: 0.5; } 50% { transform: scale(1.2); opacity: 1; } }

        #successModalTitle {
            font-size: 1.5rem; font-weight: bold; color: #333; margin-bottom: 10px;
        }
        #successModalMessage {
            font-size: 0.95rem; color: #666666; margin-bottom: 30px;
        }
        #successModalCloseBtn {
            background: #116913; color: white; border: none; padding: 12px 30px;
            border-radius: 10px; font-size: 1rem; cursor: pointer;
            transition: background-color 0.2s ease; font-weight: bold;
        }
        #successModalCloseBtn:hover { background-color: #004d00; }

        .author-input-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .author-input-row input {
            flex: 1;
        }
        .remove-author-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
        }
        .remove-author-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        <?php include 'admin_sidebar.php'; ?>

        <main class="content">
            <div class="form-container">
                <h2><i class="fa-solid fa-pen-to-square"></i> Edit Research Paper</h2>
                
                <form action="editpaper.php?id=<?php echo $paper['id']; ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="paper_id" value="<?php echo $paper['id']; ?>">
                    
                    <div class="form-group full">
                        <label>Thesis Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($paper['title']); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Authors</label>
                            <div id="authorFields">
                                <?php
                                    $existing_authors = array_filter(array_map('trim', explode(',', $paper['author'])));
                                    if (empty($existing_authors)) {
                                        $existing_authors = [''];
                                    }
                                    foreach ($existing_authors as $idx => $existing_author):
                                ?>
                                    <div class="author-input-row">
                                        <input type="text" name="authors[]" value="<?php echo htmlspecialchars($existing_author); ?>" placeholder="e.g John Doe" required>
                                        <?php if ($idx > 0): ?>
                                            <button type="button" class="remove-author-btn">Remove</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="addAuthorBtn" class="browse-btn" style="margin-top: 10px; width:100%; background-color: #f9f9f9; border: 1px solid #ddd; color: #555; justify-content: center;">+ Add Another Author</button>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department" required>
                                <option value="AGRICULTURE" <?php if($paper['department'] == 'AGRICULTURE') echo 'selected'; ?>>College of Agriculture and Forestry</option>
                                <option value="COMPUTER STUDIES" <?php if($paper['department'] == 'COMPUTER STUDIES') echo 'selected'; ?>>College of Computer Studies</option>
                                <option value="HOSPITALITY MANAGEMENT" <?php if($paper['department'] == 'HOSPITALITY MANAGEMENT') echo 'selected'; ?>>College of hospitality Management</option>
                                <option value="TEACHER EDUCATION" <?php if($paper['department'] == 'TEACHER EDUCATION') echo 'selected'; ?>>College of Teacher Education</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" value="<?php echo htmlspecialchars($paper['subject']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Date Published</label>
                            <input type="text" name="date_published" value="<?php echo date("Y-m", strtotime($paper['date_published'])); ?>" placeholder="MM/YYYY" onfocus="(this.type='month')" onblur="if(!this.value)this.type='text'" required>
                        </div>
                    </div>

                    <div class="form-group full">
                        <label>Uploaded By</label>
                        <input type="text" name="uploaded_by" value="<?php echo htmlspecialchars($paper['uploaded_by']); ?>">
                    </div>

                    <div class="form-group full">
                        <label>Keywords</label>
                        <input type="text" name="keywords" placeholder="Enter tags separated by commas" value="<?php echo htmlspecialchars($paper['keywords'] ?? ''); ?>">
                    </div>

                    <div class="form-group full">
                        <label>Abstract / Summary</label>
                        <textarea name="abstract" rows="4" placeholder="Brief Summary of the research work..."><?php echo htmlspecialchars($paper['abstract'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full">
                        <label>Update PDF File (Leave empty to keep current)</label>
                        <p style="font-size: 0.85rem; margin-bottom: 5px; color: #666;">
                            Current: <a href="<?php echo htmlspecialchars($paper['file_path']); ?>" target="_blank" style="color: #004d00; text-decoration:none;"><?php echo htmlspecialchars(basename($paper['file_path'])); ?></a>
                        </p>
                        <input type="file" name="pdf_file" accept="application/pdf" style="padding: 10px; border: 1px solid #ccc; width: 100%; background: #fff; border-radius: 8px;">
                    </div>

                    <button type="submit" name="update_btn" class="upload-btn" style="background-color: #FFD700; color: #004d00;">
                        <i class="fa-solid fa-save"></i> SAVE CHANGES
                    </button>
                    <a href="collectionadmin.php" style="display:block; text-align:center; margin-top:15px; color:#555; text-decoration:none;">Cancel</a>
                </form>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['update_success']) && $_SESSION['update_success']): ?>
                const successModal = document.createElement('div');
                successModal.className = 'success-modal-overlay';
                successModal.style.display = 'flex';
                successModal.innerHTML = `<div class="success-modal-box"><div class="success-badge"><i class="fa-solid fa-check"></i></div><h3 id="successModalTitle">Successfully Updated!</h3><p id="successModalMessage">Your changes have been saved to the database.</p><button id="successModalCloseBtn">OK</button></div>`;
                document.body.appendChild(successModal);
                successModal.querySelector('#successModalCloseBtn').onclick = function() { window.location.href = 'collectionadmin.php'; };
                <?php unset($_SESSION['update_success']); ?>
            <?php endif; ?>
        });

        const authorFieldsEdit = document.getElementById('authorFields');
        const addAuthorBtnEdit = document.getElementById('addAuthorBtn');

        if (addAuthorBtnEdit) {
            addAuthorBtnEdit.addEventListener('click', function() {
                const row = document.createElement('div');
                row.className = 'author-input-row';
                row.innerHTML = '<input type="text" name="authors[]" placeholder="e.g Jane Doe" required> <button type="button" class="remove-author-btn">Remove</button>';
                authorFieldsEdit.appendChild(row);

                const removeBtn = row.querySelector('.remove-author-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        row.remove();
                    });
                }
            });
        }

        document.querySelectorAll('#authorFields .remove-author-btn').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.author-input-row').remove();
            });
        });
    </script>
    <script src="app.js"></script>
</body>
</html>