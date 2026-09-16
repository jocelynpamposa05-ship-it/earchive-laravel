<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php"; // Use the central DB connection

// Step 1: Security Guard - Only Admins allowed
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// --- AUTO-FIX: Ensure 'uploaded_by' column exists ---
$check_col = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'uploaded_by'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN uploaded_by VARCHAR(100) AFTER file_path");
}

// --- AUTO-FIX: Ensure 'file_hash' column exists for duplicate file detection ---
$check_hash_col = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'file_hash'");
if ($check_hash_col->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN file_hash VARCHAR(64) NULL AFTER uploaded_by, ADD INDEX idx_file_hash (file_hash)");
}

$upload_status = "";
$upload_message = "";

// Step 2: Handle Form Submission
if (isset($_POST['upload_btn'])) {
    
    // --- A. Prepare and validate form data ---
    $title = trim($_POST['title']);
    $authors = $_POST['authors'] ?? [];
    if (!is_array($authors)) {
        $authors = [$authors];
    }
    $authors = array_filter(array_map('trim', $authors));
    $author = implode(', ', $authors);
    $dept = $_POST['department'];
    $subject = $_POST['subject'] ?? '';
    $date_input = $_POST['date_published']; // Format: 'YYYY-MM'
    $date = $date_input . '-01'; // Make valid DATE for DB
    $publish_year = (int)substr($date_input, 0, 4);
    $keywords = $_POST['keywords'] ?? '';
    $abstract = $_POST['abstract'] ?? '';
    $uploaded_by = $_POST['uploaded_by'] ?? $_SESSION['username'];

    $file_hash = null;
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
        $tempname = $_FILES["pdf_file"]["tmp_name"];
        $file_hash = hash_file('sha256', $tempname);
        $hash_stmt = $conn->prepare("SELECT id FROM research_papers WHERE file_hash = ? AND is_deleted = 0");
        $hash_stmt->bind_param("s", $file_hash);
        $hash_stmt->execute();
        $hash_stmt->store_result();
        if ($hash_stmt->num_rows > 0) {
            $upload_status = "duplicate_pdf";
            $upload_message = "Validation Error: This exact PDF file has already been uploaded. The upload was cancelled.";
        }
        $hash_stmt->close();
    }

    if (empty($upload_status)) {
        $duplicate_stmt = $conn->prepare("SELECT id FROM research_papers WHERE LOWER(title) = LOWER(?) AND LOWER(author) = LOWER(?) AND date_published = ? AND is_deleted = 0");
        $duplicate_stmt->bind_param("sss", $title, $author, $date);
        $duplicate_stmt->execute();
        $duplicate_stmt->store_result();
        if ($duplicate_stmt->num_rows > 0) {
            $upload_status = "duplicate_metadata";
            $upload_message = "Validation Error: A paper with the same title, author(s), and publication date already exists. The upload was cancelled.";
        }
        $duplicate_stmt->close();
    }

    if (empty($upload_status)) {
        $filename = $_FILES["pdf_file"]["name"];
        $tempname = $_FILES["pdf_file"]["tmp_name"] ?? null;
        $upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $clean_filename = preg_replace("/[^a-zA-Z0-9.]/", "_", $filename);
        $target_file_path = $upload_dir . time() . "_" . $clean_filename; 

        if ($tempname && is_uploaded_file($tempname) && move_uploaded_file($tempname, $target_file_path)) {

            $sql = "INSERT INTO research_papers (title, author, department, subject, date_published, publish_year, keywords, abstract, file_path, uploaded_by, file_hash, views) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

            try {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssisssss", $title, $author, $dept, $subject, $date, $publish_year, $keywords, $abstract, $target_file_path, $uploaded_by, $file_hash);

                if ($stmt->execute()) {
                    // --- Index to Algolia ---
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
                    } catch (Throwable $e) { /* Silently fail if Algolia sync fails */ }

                    $upload_status = "success";
                    $upload_message = "Paper Uploaded Successfully! It is now visible to users.";
                } else {
                    $upload_status = "error";
                    $upload_message = "Database Error: " . $stmt->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $upload_status = "error";
                $upload_message = "Database Error: " . $e->getMessage();
            }
        } else {
            $upload_status = "error";
            $upload_message = "File upload failed. Please select a PDF file and try again.";
        }
    }
}

// Set active page for sidebar
$active_page = 'upload';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Research Paper | Admin</title>

    <!-- PWA Headers -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="img/cpsu_logo.png">
    <meta name="theme-color" content="#116913">

    <link rel="stylesheet" href="uploadpaper.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .select-wrapper { position: relative; width: 100%; }
        .form-group select {
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            display: block; width: 100%; height: 48px; padding: 0 25px;
            line-height: 48px; color: #6d6a6a; font-size: 14px;
            border-radius: 8px; cursor: pointer; padding-right: 45px;
            border: 1px solid #dddddd; transition: 0.3s; background-color: #f9f9f9;
        }
        .form-group select:hover { background-color: #ffffff; border: 1px solid #116913; }
        .form-group select option { background: white; color: #333; }
        .select-wrapper::after {
            content: '\f0d7'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            color: #116913; position: absolute; top: 50%; right: 20px;
            transform: translateY(-50%); pointer-events: none;
        }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 10px; } }

        .form-group .error-message { color: #dc3545; font-size: 0.85rem; margin-top: 5px; font-weight: 500; }
        .form-group input.error, .form-group select.error, .form-group textarea.error {
            border: 2px solid #dc3545 !important;
            background-color: #f8d7da !important;
        }
        .file-upload-section.error {
            border: 2px solid #dc3545;
            background-color: #fff0f0;
            border-radius: 10px;
            padding: 15px;
        }
        .file-upload-section.error .browse-btn {
            border: 2px solid #dc3545 !important;
            background-color: #f8d7da !important;
            color: #721c24 !important;
        }
        .file-upload-section.error .browse-btn.shake {
            animation: shake 0.3s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .shake { animation: shake 0.3s ease-in-out; }
        .author-input-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .author-input-row input { flex: 1; }
        .remove-author-btn {
            background: #dc3545; color: white; border: none; border-radius: 6px;
            padding: 8px 12px; cursor: pointer; font-size: 0.9rem; transition: background 0.2s;
        }
        .remove-author-btn:hover { background: #c82333; }

        #customModal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
            align-items: center; justify-content: center; z-index: 10000;
        }
        #customModal > div {
            background: #fff; padding: 30px; border-radius: 15px; text-align: center;
            width: 90%; max-width: 400px; box-shadow: 0 5px 25px rgba(0,0,0,0.3); animation: zoomIn 0.3s ease-out;
        }
        @keyframes zoomIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        #modalIcon { font-size: 4rem; margin-bottom: 20px; }
        #modalTitle { margin-bottom: 15px; color: #333; font-size: 1.5rem; }
        #modalMessage { font-size: 1.1rem; color: #555; margin-bottom: 30px; }
        #modalCloseBtn {
            background: #116913; color: white; border: none; padding: 12px 30px;
            border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background 0.3s, transform 0.2s; font-weight: bold;
        }
        #modalCloseBtn:hover { filter: brightness(1.1); transform: scale(1.05); }

        /* --- New Success Modal --- */
        /* --- PDF Parsing Loader --- */
        .parsing-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.85);
            z-index: 999;
            border-radius: 12px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            text-align: center;
        }
        .parsing-overlay .spinner {
            width: 40px;
            height: 40px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #116913;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        .parsing-overlay p {
            font-weight: 600;
            color: #333;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* --- Auto-filled Field Highlight --- */
        .autofilled {
            background-color: #e9f5e9 !important; /* Light green tint */
        }
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
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        <?php include 'admin_sidebar.php'; ?>

        <main class="content">
            <div class="form-container">
                <div class="parsing-overlay" id="parsing-overlay">
                    <div class="spinner"></div>
                    <p>Extracting metadata from PDF...</p>
                </div>
                <h2><i class="fa-solid fa-file-pdf"></i> Upload New Research Paper</h2>
                <form id="uploadPaperForm" action="uploadpaper.php" method="POST" enctype="multipart/form-data" novalidate>
                    <div class="form-group full">
                        <label>Thesis Title</label>
                        <input type="text" name="title" placeholder="Enter the full title of the paper" required data-field-name="Title">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Authors</label>
                            <div id="authorFields" data-field-name="Authors">
                                <div class="author-input-row">
                                    <input type="text" name="authors[]" placeholder="e.g John Doe" required>
                                </div>
                            </div>
                            <button type="button" id="addAuthorBtn" class="browse-btn" style="margin-top: 10px; width:100%; background-color: #f9f9f9; border: 1px solid #ddd; color: #555; justify-content: center;">+ Add Another Author</button>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <div class="select-wrapper">
                                <select name="department" required data-field-name="Department">
                                    <option value="">Select Department</option>
                                    <option value="AGRICULTURE">College of Agriculture and Forestry</option>
                                    <option value="COMPUTER STUDIES">College of Computer Studies</option>
                                    <option value="HOSPITALITY MANAGEMENT">College of Hospitality Management</option>
                                    <option value="TEACHER EDUCATION">College of Teacher Education</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" placeholder="e.g Web-based System" required>
                        </div>
                        <div class="form-group">
                            <label>Date Published</label>
                            <input type="text" name="date_published" placeholder="MM/YYYY" onfocus="(this.type='month')" onblur="if(!this.value)this.type='text'" required data-field-name="Date Published">
                        </div>
                    </div>

                    <div class="form-group full">
                        <label>Uploaded By</label>
                        <input type="text" name="uploaded_by" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
                    </div>

                    <div class="form-group full">
                        <label>Keywords</label>
                        <input type="text" name="keywords" placeholder="Enter tags separated by commas (e.g., PHP, MySQL, Automation)" required>
                    </div>

                    <div class="form-group full">
                        <label>Abstract / Summary</label>
                        <textarea name="abstract" rows="4" placeholder="Brief Summary of the research work..." required></textarea>
                    </div>

                    <div class="file-upload-section" data-field-name="PDF File">
                        <input type="file" name="pdf_file" id="pdf_file" hidden required accept="application/pdf">
                        <button type="button" class="browse-btn" onclick="document.getElementById('pdf_file').click()">
                            <i class="fa-solid fa-folder-open"></i> SELECT PDF FILE
                        </button>
                        <span id="file-chosen">No file selected.</span>
                    </div>

                    <button type="submit" name="upload_btn" class="upload-btn">
                        <i class="fa-solid fa-cloud-upload-alt"></i> PUBLISH TO E-ARCHIVE
                    </button>
                </form>
            </div>
        </main>
    </div>

    <!-- Custom Modal Overlay -->
    <div id="successModal" class="success-modal-overlay">
        <div class="success-modal-box">
            <div class="success-badge">
                <i class="fa-solid fa-check"></i>
            </div>
            <h3 id="successModalTitle"></h3>
            <p id="successModalMessage"></p>
            <button id="successModalCloseBtn">OK</button>
        </div>
    </div>

    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <script>
        const actualBtn = document.getElementById('pdf_file');
        const fileChosen = document.getElementById('file-chosen');
        actualBtn.addEventListener('change', function(){
            if(this.files && this.files[0]) {
                fileChosen.textContent = this.files[0].name;
                fileChosen.style.color = "#004d00";
                fileChosen.style.fontWeight = "bold";
                const wrapper = this.parentElement;
                if (wrapper && wrapper.classList.contains('error')) {
                    wrapper.classList.remove('error');
                }
                const browseBtn = wrapper.querySelector('.browse-btn');
                if (browseBtn) {
                    browseBtn.classList.remove('shake');
                }
                const existingError = wrapper.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                // --- NEW: Trigger PDF parsing ---
                parsePdf(this.files[0]);
            }
        });

        const authorFields = document.getElementById('authorFields');
        const addAuthorBtn = document.getElementById('addAuthorBtn');

        addAuthorBtn.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'author-input-row';
            row.innerHTML = '<input type="text" name="authors[]" placeholder="e.g Jane Doe" required> <button type="button" class="remove-author-btn">Remove</button>';
            authorFields.appendChild(row);

            row.querySelector('.remove-author-btn').addEventListener('click', function() {
                row.remove();
            });
        });

        // Client-side Form Validation
        document.getElementById('uploadPaperForm').addEventListener('submit', function(event) {
            let isValid = true;
            
            document.querySelectorAll('.error-message').forEach(el => el.remove());
            document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.shake').forEach(el => el.classList.remove('shake'));

            function showError(element, message) {
                element.classList.add('error');
                const errorSpan = document.createElement('div');
                errorSpan.className = 'error-message';
                errorSpan.textContent = message;
                
                if (element.parentElement.classList.contains('select-wrapper') || element.parentElement.id === 'authorFields') {
                    element.parentElement.parentElement.appendChild(errorSpan);
                } else if (element.id === 'pdf_file') {
                    const wrapper = element.parentElement;
                    wrapper.classList.add('error');
                    const button = wrapper.querySelector('.browse-btn');
                    if (button) {
                        button.insertAdjacentElement('afterend', errorSpan);
                        button.classList.add('shake');
                        setTimeout(() => button.classList.remove('shake'), 300);
                    } else {
                        wrapper.appendChild(errorSpan);
                        wrapper.classList.add('shake');
                        setTimeout(() => wrapper.classList.remove('shake'), 300);
                    }
                } else {
                    element.parentElement.appendChild(errorSpan);
                    element.classList.add('shake');
                    setTimeout(() => element.classList.remove('shake'), 300);
                }
            }

            const title = document.querySelector('input[name="title"]');
            if (title.value.trim() === '') { isValid = false; showError(title, 'Please fill out this field.'); }

            const authors = document.querySelectorAll('input[name="authors[]"]');
            if (authors[0].value.trim() === '') { isValid = false; showError(authors[0], 'Please fill out this field.'); }

            const department = document.querySelector('select[name="department"]');
            if (department.value === '') { isValid = false; showError(department, 'Please fill out this field.'); }

            const datePublished = document.querySelector('input[name="date_published"]');
            if (datePublished.value === '') { isValid = false; showError(datePublished, 'Please fill out this field.'); }

            const subject = document.querySelector('input[name="subject"]');
            if (subject.value.trim() === '') { isValid = false; showError(subject, 'Please fill out this field.'); }

            const keywords = document.querySelector('input[name="keywords"]');
            if (keywords.value.trim() === '') { isValid = false; showError(keywords, 'Please fill out this field.'); }

            const abstract = document.querySelector('textarea[name="abstract"]');
            if (abstract.value.trim() === '') { isValid = false; showError(abstract, 'Please fill out this field.'); }

            const pdfFile = document.getElementById('pdf_file');
            if (pdfFile.files.length === 0) { isValid = false; showError(pdfFile, 'Please select a PDF file.'); }

            if (!isValid) { event.preventDefault(); }
        });

        // Custom Modal Display Logic
        document.addEventListener('DOMContentLoaded', function() {
            const uploadStatus = "<?php echo $upload_status; ?>";
            const uploadMessage = "<?php echo addslashes($upload_message); ?>";

            if (uploadStatus) {
                const successModal = document.getElementById('successModal');
                const modalTitle = document.getElementById('successModalTitle');
                const modalMessage = document.getElementById('successModalMessage');
                const modalCloseBtn = document.getElementById('successModalCloseBtn');

                // Default to error modal for other statuses, but we only handle success here
                let isSuccess = false;
                let titleText = '';
                let iconColor = '';
                let buttonBg = '#116913';
                let redirectUrl = '';

                switch (uploadStatus) {
                    case 'success':
                        isSuccess = true;
                        titleText = 'Success!';
                        modalCloseBtn.textContent = 'OK';
                        redirectUrl = 'collectionadmin.php';
                        break;
                    case 'duplicate_pdf':
                    case 'duplicate_metadata':
                        // Logic for other modals can be added here if needed
                        break;
                    case 'error':
                        break;
                }

                if (isSuccess) {
                    modalTitle.textContent = titleText;
                    modalMessage.textContent = "The paper has been saved to the database.";
                    successModal.style.display = 'flex';
                }

                modalCloseBtn.onclick = function() {
                    successModal.style.display = 'none';
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                };
            }
        });

        // --- NEW: PDF Parsing and Auto-fill Logic ---
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        async function parsePdf(file) {
            const formContainer = document.querySelector('.form-container');
            const overlay = document.getElementById('parsing-overlay');
            overlay.style.display = 'flex';
            formContainer.style.position = 'relative';

            try {
                const fileReader = new FileReader();
                fileReader.onload = async function() {
                    const typedarray = new Uint8Array(this.result);
                    const pdf = await pdfjsLib.getDocument(typedarray).promise;
                    let fullText = '';
 
                    // Extract text from the first two pages to get more context
                    const numPagesToParse = Math.min(pdf.numPages, 2);
                    for (let i = 1; i <= numPagesToParse; i++) {
                        const page = await pdf.getPage(i);
                        const textContent = await page.getTextContent();
                        fullText += textContent.items.map(item => item.str).join('\n') + '\n\n'; // Preserve line breaks
                    }

                    // --- Metadata Extraction ---
                    const metadata = {
                        title: extractTitle(fullText),
                        authors: extractAuthors(fullText),
                        department: extractDepartment(fullText),
                        date: extractDate(fullText),
                        abstract: extractAbstract(fullText),
                        keywords: extractKeywords(fullText),
                        subject: extractSubject(fullText)
                    };

                    // --- Populate Form ---
                    populateField('input[name="title"]', metadata.title);
                    populateField('textarea[name="abstract"]', metadata.abstract);
                    populateField('input[name="keywords"]', metadata.keywords);
                    populateField('input[name="date_published"]', metadata.date, 'month');
                    populateField('input[name="subject"]', metadata.subject);
                    
                    if (metadata.department) {
                        const deptSelect = document.querySelector('select[name="department"]');
                        for (let option of deptSelect.options) {
                            if (option.value.includes(metadata.department)) {
                                option.selected = true;
                                highlightField(deptSelect);
                                break;
                            }
                        }
                    }

                    if (metadata.authors.length > 0) {
                        const authorContainer = document.getElementById('authorFields');
                        authorContainer.innerHTML = ''; // Clear existing
                        metadata.authors.forEach(author => {
                            const row = document.createElement('div');
                            row.className = 'author-input-row';
                            row.innerHTML = `<input type="text" name="authors[]" value="${author}" placeholder="e.g Jane Doe" required> <button type="button" class="remove-author-btn">Remove</button>`;
                            authorContainer.appendChild(row);
                            highlightField(row.querySelector('input'));
                            row.querySelector('.remove-author-btn').addEventListener('click', () => row.remove());
                        });
                    }

                    overlay.style.display = 'none';
                };
                fileReader.readAsArrayBuffer(file);
            } catch (error) {
                console.error('Error parsing PDF:', error);
                overlay.style.display = 'none';
            }
        }

        function populateField(selector, value, type = 'text') {
            if (value) {
                const field = document.querySelector(selector);
                if (field) {
                    field.value = value;
                    if (type === 'month') field.type = 'month';
                    highlightField(field);
                }
            }
        }

        function highlightField(element) {
            element.classList.add('autofilled');
            setTimeout(() => element.classList.remove('autofilled'), 4000); // Highlight for 4 seconds
        }

        // --- Heuristic Extraction Functions ---
        function extractTitle(text) {
            const titleEndMarkers = [/by/i, /authors/i, /submitted to/i, /submitted in partial fulfillment/i, /college of/i, /abstract/i];
            let relevantText = text;
            for (const marker of titleEndMarkers) {
                const match = text.match(marker);
                if (match) {
                    relevantText = text.substring(0, match.index);
                    break;
                }
            }
            // Find the longest line in the remaining text, often the main title.
            // Filter for lines that are not too short and might represent a title.
            const lines = relevantText.split('\n').map(l => l.trim()).filter(l => l.length > 10 && l.length < 200);
            let mainTitle = '';
            if (lines.length > 0) {
                // Prioritize lines with more capitalized words or are generally longer
                mainTitle = lines.reduce((a, b) => {
                    const aCaps = (a.match(/[A-Z]/g) || []).length;
                    const bCaps = (b.match(/[A-Z]/g) || []).length;
                    if (aCaps > bCaps) return a;
                    if (bCaps > aCaps) return b;
                    return a.length > b.length ? a : b;
                }, '');
            }
            return mainTitle || relevantText.trim(); // Fallback if no clear title line
        }

        function extractAuthors(text) {
            // Look for common author indicators, or a block of capitalized names after the title area
            const authorBlockMatch = text.match(/(?:by|Author(?:s)?|Researchers?):\s*([\s\S]*?)(?=College of|Department of|Date Submitted|Abstract|Introduction|\d{4})/i);
            let authorBlock = authorBlockMatch ? authorBlockMatch[1] : '';

            // Fallback: if no explicit "By:" etc., try to find capitalized names in the first few lines after the title area
            if (!authorBlock) {
                // Assuming title is at the very beginning, skip it and look for names
                const titleEndIndex = text.search(/by|authors|submitted to|abstract/i);
                if (titleEndIndex !== -1) {
                    let potentialAuthorArea = text.substring(titleEndIndex).split('\n').slice(0, 5).join('\n'); // Look in next 5 lines
                    // Try to find lines that are mostly capitalized words, often indicative of names
                    const nameLines = potentialAuthorArea.match(/[A-Z][a-z]+(?: [A-Z][a-z]+){1,3}/g); // e.g., "John Doe", "Mary Jane Smith"
                    if (nameLines && nameLines.length > 0) {
                        authorBlock = nameLines.join(', ');
                    }
                }
            }

            if (!authorBlock) return [];

            // Split by common delimiters and clean up
            let authors = authorBlock.split(/and|,|\n|\r/).map(name => {
                name = name.replace(/\b(?:Jr\.|Sr\.|III|II|IV)\b/g, '').trim(); // Remove common suffixes
                // Remove any remaining non-alphanumeric characters except spaces and hyphens
                name = name.replace(/[^a-zA-Z\s-]/g, '');
                return name;
            }).filter(name => name.length > 3); // Filter out very short strings that are likely not names

            // Remove duplicates and return
            return [...new Set(authors)];
        }

        function extractDepartment(text) {
            const departmentKeywords = {
                'AGRICULTURE': /(?:College of|Department of)\s+(Agriculture and Forestry|Agriculture)/i,
                'COMPUTER STUDIES': /(?:College of|Department of)\s+(Computer Studies|Information Technology|IT)/i,
                'HOSPITALITY MANAGEMENT': /(?:College of|Department of)\s+(Hospitality Management|HM)/i,
                'TEACHER EDUCATION': /(?:College of|Department of)\s+(Teacher Education|Education|EDUC)/i,
            };

            for (const deptKey in departmentKeywords) {
                const match = text.match(departmentKeywords[deptKey]);
                if (match) {
                    return deptKey; // Return the exact value for the dropdown
                }
            }
            return '';
        }

        function extractDate(text) {
            // Try to find full month-year first
            const monthYearMatch = text.match(/(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i);
            if (monthYearMatch) {
                const monthMap = { "january": "01", "february": "02", "march": "03", "april": "04", "may": "05", "june": "06", "july": "07", "august": "08", "september": "09", "october": "10", "november": "11", "december": "12" };
                return `${monthYearMatch[2]}-${monthMap[monthYearMatch[1].toLowerCase()]}`;
            }

            // Fallback: try to find a standalone 4-digit year, preferably near the top
            const yearMatch = text.match(/(?:(?:Submitted|Published|Copyright)\s+)?(20\d{2}|19\d{2})/i);
            if (yearMatch) {
                return `${yearMatch[1]}-01`; // Default to January if only year is found
            }

            return '';
        }

        function extractAbstract(text) {
            const match = text.match(/Abstract:\s*([\s\S]*?)(?=Keywords:|Key terms:|Introduction|I\.\s+INTRODUCTION)/i);
            return match ? match[1].replace(/\s+/g, ' ').trim() : '';
        }

        function extractKeywords(text) {
            const match = text.match(/(?:Keywords?|Key terms):\s*(.*)/i);
            return match ? match[1].trim() : '';
        }

        function extractSubject(text) {
            const match = text.match(/(?:Subject|Focus Area|Specialization):\s*(.*?)(?:\n|Date Published|Abstract)/i);
            return match ? match[1].trim() : '';
        }
    </script>
</body>
</html>