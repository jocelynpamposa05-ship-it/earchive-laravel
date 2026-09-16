<?php
session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php";

function resolveLocalFilePath($filePath) {
    if (empty($filePath)) {
        return '';
    }

    $filePath = str_replace('\\', '/', $filePath);
    if (preg_match('#^https?://#i', $filePath)) {
        return $filePath;
    }

    $rootDir = realpath(__DIR__ . '/..');
    $relativePath = ltrim($filePath, '/\\');
    $fullPath = $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    return $fullPath;
}

function getPdfEndpoint($paperId) {
    return 'viewingpdf.php?pdf=1&id=' . urlencode($paperId);
}

function fetchRelatedPapers($conn, $paperId, $title, $subject, $keywords, $department) {
    $searchBase = trim($title . ' ' . $keywords . ' ' . $subject);
    $searchPattern = '%' . preg_replace('/[^a-z0-9]+/i', '%', $searchBase) . '%';
    $searchPattern = str_replace('%%', '%', $searchPattern);

    $stmt = $conn->prepare("SELECT id, title, author, publish_year, department, subject FROM research_papers WHERE is_deleted = 0 AND id != ? AND (title LIKE ? OR subject LIKE ? OR keywords LIKE ? OR department = ?) ORDER BY CASE WHEN department = ? THEN 2 WHEN title LIKE ? THEN 1 ELSE 0 END DESC, id DESC LIMIT 4");
    $stmt->bind_param("isssssi", $paperId, $searchPattern, $searchPattern, $searchPattern, $department, $department, $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    if (!empty($items)) {
        return $items;
    }

    $fallbackStmt = $conn->prepare("SELECT id, title, author, publish_year, department, subject FROM research_papers WHERE is_deleted = 0 AND id != ? AND department = ? ORDER BY id DESC LIMIT 4");
    $fallbackStmt->bind_param("is", $paperId, $department);
    $fallbackStmt->execute();
    $fallbackResult = $fallbackStmt->get_result();
    $fallbackItems = [];
    while ($row = $fallbackResult->fetch_assoc()) {
        $fallbackItems[] = $row;
    }
    $fallbackStmt->close();

    return $fallbackItems;
}

// --- NEW: Security Check ---
// Ensure user is logged in and is a student
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// --- NEW: Secure PDF streaming endpoint ---
if (isset($_GET['pdf']) && isset($_GET['id'])) {
    $paper_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT file_path FROM research_papers WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paperFile = $result->fetch_assoc();
    $stmt->close();

    if (!$paperFile || empty($paperFile['file_path'])) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        exit();
    }

    $resolvedFile = resolveLocalFilePath($paperFile['file_path']);
    if (empty($resolvedFile) || !file_exists($resolvedFile) || !is_readable($resolvedFile)) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        exit();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($resolvedFile) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    readfile($resolvedFile);
    exit();
}

// --- Map department short name to full name for profile dropdown ---
$department_short = $_SESSION['department'] ?? '';
$profile_display_department = htmlspecialchars($department_short);
switch ($department_short) {
    case 'AGRICULTURE':
        $profile_display_department = 'College of Agriculture and Forestry';
        break;
    case 'COMPUTER STUDIES':
        $profile_display_department = 'College of Computer Studies';
        break;
    case 'HOSPITALITY MANAGEMENT':
        $profile_display_department = 'College of Hospitality Management';
        break;
    case 'TEACHER EDUCATION':
        $profile_display_department = 'College of Teacher Education';
        break;
}

// Get the Paper ID from the URL
if (isset($_GET['id'])) {
    $paper_id = (int)$_GET['id'];

    // --- Use Prepared Statements for Security & Reliability ---
    $stmt = $conn->prepare("SELECT * FROM research_papers WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paper = $result->fetch_assoc();
    $stmt->close();

    if (!$paper) {
        header("Location: collection.php");
        exit();
    }

    // --- NEW: Map department short name to full name ---
    $display_department = htmlspecialchars($paper['department']);
    switch ($paper['department']) {
        case 'COMPUTER STUDIES':
            $display_department = 'College of Computer Studies';
            break;
        case 'HOSPITALITY MANAGEMENT':
            $display_department = 'College of Hospitality Management';
            break;
        case 'TEACHER EDUCATION':
            $display_department = 'College of Teacher Education';
            break;
        case 'AGRICULTURE':
            $display_department = 'College of Agriculture and Forestry';
            break;
    }

    $file_path = $paper['file_path'] ?? '';
    $resolvedPath = resolveLocalFilePath($file_path);
    $paper_url = getPdfEndpoint($paper['id']);
    $related_papers = fetchRelatedPapers($conn, $paper['id'], $paper['title'] ?? '', $paper['subject'] ?? '', $paper['keywords'] ?? '', $paper['department'] ?? '');

    // --- NEW: Log the view for daily tracking and update total views ---
    $create_view_logs_table = "CREATE TABLE IF NOT EXISTS view_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        paper_id INT NOT NULL,
        user_id INT DEFAULT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_view_logs_table);

    $conn->begin_transaction();
    try {
        $stmt_update = $conn->prepare("UPDATE research_papers SET views = views + 1 WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("i", $paper_id);
            $stmt_update->execute();
            $stmt_update->close();
        }

        $stmt_log = $conn->prepare("INSERT INTO view_logs (paper_id, user_id) VALUES (?, ?)");
        if ($stmt_log) {
            $user_id = $_SESSION['id'];
            $stmt_log->bind_param("ii", $paper_id, $user_id);
            $stmt_log->execute();
            $stmt_log->close();
        }

        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
    }
} else {
    header("Location: collection.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paper['title']); ?> | Viewer</title>
    <!-- Added version to force CSS reload -->
    <link rel="stylesheet" href="viewingpdf.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- NEW: Background and Header Styles to match Dashboard --- */
        body {
            background-color: #edeff2;
            font-family: 'Inter', sans-serif;
        }
        .header {
            background: #116913;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-section img {
            height: 50px;
        }
        .header-text h1 {
            margin: 0;
            font-size: 1.1rem;
            color: white;
            font-weight: 700;
            line-height: 1.2;
        }
        .header-text h1:last-child {
            font-size: 1rem;
            font-weight: 600;
        }
        .user-profile {
            font-weight: 500;
            color: white;
        }
        /* Profile Dropdown Styles */
        .profile-container {
            position: relative; display: flex; align-items: center;
        }
        .user-profile-icon {
            cursor: pointer; font-size: 2.2rem; color: #ffffff; transition: color 0.3s ease;
        }
        .user-profile-icon:hover { color: #ebe80f; }
        .profile-dropdown {
            display: none; position: absolute; right: 0; top: 55px; background: white;
            border-radius: 10px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); width: 350px;
            max-width: 90vw; z-index: 1000; overflow: hidden; border: 1px solid #eee;
            animation: fadeIn 0.2s ease-out;
        }
        .profile-dropdown.show { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .dropdown-header {
            display: flex; align-items: center; gap: 15px; padding: 20px; background: #f8f9fa;
        }
        .dropdown-header .avatar { font-size: 3.5rem; color: #116913; }
        .dropdown-header .user-info h4 { margin: 0; font-size: 1.1rem; color: #333; font-weight: 600; }
        .dropdown-header .user-info .email { margin: 0; font-size: 0.9rem; color: #6c757d; }
        .dropdown-body { padding: 15px 20px; }
        .profile-info-row {
            display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; color: #555;
        }
        .profile-info-row i { color: #116913; margin-right: 8px; }
        .profile-info-row .value { font-weight: 600; color: #333; }
        .dept-full-row { 
            flex-direction: column; align-items: flex-start; gap: 5px; 
            border-top: 1px solid #eee; padding-top: 10px; margin-top: 5px; 
        }
        .dept-full-row .value { 
            color: #116913; font-size: 0.8rem; white-space: nowrap; width: 100%; 
        }

        /* --- NEW: Protection Banner Style --- */
        .protection-banner {
            background-color: #e5ff54; /* Light red for warning */
            padding: 15px 20px;
            text-align: center;
            font-weight: 500;
            font-size: 0.9rem;
            position: relative;
        }
        .protection-banner .back-btn {
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5rem;
            color: #145205;
            transition: transform 0.2s ease;
        }
        .protection-banner .back-btn:hover {
            transform: translateY(-50%) translateX(-5px) scale(1.2);
        }

        /* --- NEW: Responsive Main Layout --- */
        .main-wrapper {
            display: flex;
            flex-direction: column;
        }
        .container {
            display: flex;
            flex: 1;
            width: 100%;
            max-width: 1800px; /* Limit max width for very large screens */
            margin: 0 auto;
        }

        /* PDF Viewer Styles */
        #pdf-viewer {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center; /* Center canvases inside */
        }
        #pdf-viewer canvas {
            box-shadow: 0 2px 8px rgba(0,0,0,0.15); /* Paper shadow effect */
            margin-bottom: 20px;
            max-width: 100%; /* Ensure canvas is responsive */
            height: auto; /* Maintain aspect ratio */
        }
       .pdf-container {
            flex: 1; /* Takes up remaining space */
            background: #edeff2;
            overflow-y: auto; /* Vertical scroll for pages */
            overflow-x: hidden; /* Hide horizontal scroll */
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sidebar {
            width: 380px; /* Fixed width for sidebar */
            flex-shrink: 0; /* Prevent sidebar from shrinking */
            background: #ffffff;
            padding: 25px;
            overflow-y: auto;
            border-left: 1px solid #e0e0e0;
        }

        .detail-title {
            font-family: 'Inter', sans-serif !important;
            font-size: 0.95rem;
        }
        
        /* --- NEW: Responsive Media Queries --- */
        @media (max-width: 1024px) {
            .container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                border-left: none;
                border-top: 2px solid #e0e0e0;
            }
            .pdf-container {
                padding: 10px; /* Reduce padding on smaller screens */
            }
        }
        /* Improved Keywords Design */
        .keywords-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .keyword-tag {
            background-color: #e9f5e9; /* Light green */
            color: #004d00; /* Dark green text */
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            border: 1px solid #d4edda;
            transition: all 0.2s ease;
            cursor: default;
        }
        .keyword-tag:hover {
            background-color: #d4edda;
            transform: scale(1.05);
        }

        /* Improved AI Recommendations Design */
        .algolia-rec-link {
            text-decoration: none;
            color: inherit;
            display: block;
            margin-bottom: 10px;
        }
        .algolia-rec-card {
            display: flex;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            transition: background 0.2s, transform 0.2s;
            border: 1px solid #f0f0f0;
        }
        .algolia-rec-card:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            border-color: #e0e0e0;
        }
        .rec-icon { color: #004d00; font-size: 1.2rem; margin-top: 2px; }
        .rec-title { font-size: 0.9rem; font-weight: 600; color: #333; line-height: 1.3; margin-bottom: 4px; }
        .rec-meta { font-size: 0.8rem; color: #666; }
        .recommendation-meta { font-size: 0.85rem; color: #777; font-style: italic; }

    </style>
</head>
<body>

    <header class="header">
        <div class="logo-section">
            <img src="../img/cpsu_logo.png" alt="CPSU Logo">
            <div class="header-text">
                <h1>CENTRAL PHILIPPINES STATE UNIVERSITY</h1>
                <h1>E-Archive</h1>
            </div>
        </div>
        <div class="profile-container">
            <div class="user-profile-icon" id="user-menu-toggle">
                <i class="fa-solid fa-circle-user"></i>
            </div>
            <div class="profile-dropdown" id="profile-dropdown">
                <div class="dropdown-header">
                    <div class="avatar"><i class="fa-solid fa-circle-user"></i></div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($_SESSION['username'] ?? 'Student'); ?></h4>
                        <p class="email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                    </div>
                </div>
                <div class="dropdown-body">
                    <div class="profile-info-row">
                        <div><i class="fa-solid fa-building"></i> Dept. Code</div> <span class="value"><?php echo htmlspecialchars($_SESSION['department'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-info-row dept-full-row">
                        <div><i class="fa-solid fa-building-columns"></i> College Full Name</div> <span class="value"><?php echo $profile_display_department; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <div class="protection-banner">
        <a href="collection.php">
            <i class="fa-solid fa-caret-left back-btn"></i>
        </a>
    </div>

    <div class="main-wrapper" id="mainContent">
        <div class="container">
            <section class="pdf-container">
                <!-- PDF.js Viewer Container --> 
                <div class="pdf-frame" id="pdf-viewer">
                    <!-- Skeleton Loader: Simulates pages loading -->
                    <div id="skeleton-loader">
                        <div class="skeleton-page">
                            <div class="skeleton-line title"></div>
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line short"></div>
                            <div class="skeleton-block"></div>
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line"></div>
                        </div>
                        <div class="skeleton-page">
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line short"></div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="sidebar">
                <!-- Restored Details Section -->
                <div class="sidebar-card protected-content">
                    <h3><i class="fa-solid fa-info-circle"></i> Details</h3>
                    <p class="detail-item detail-title"><strong>Title:</strong> <?php echo htmlspecialchars($paper['title']); ?></p>
                    <p class="detail-item detail-author"><strong>Author:</strong> <?php echo htmlspecialchars($paper['author']); ?></p>
                    <p class="detail-item detail-dept"><strong>Department:</strong> <?php echo $display_department; ?></p>
                    <?php if(!empty($paper['subject'])): ?>
                        <p class="detail-item"><strong>Subject:</strong> <?php echo htmlspecialchars($paper['subject']); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Restored Abstract Section -->
                <?php if(!empty($paper['abstract'])): ?>
                <div class="sidebar-card">
                    <h3><i class="fa-solid fa-align-left"></i> Abstract</h3>
                    <p class="abstract-text protected-content">
                        <?php echo nl2br(htmlspecialchars($paper['abstract'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="sidebar-card">
                    <h3><i class="fa-solid fa-quote-left"></i> APA Citation</h3>

                    <!-- Citation Content -->
                    <div class="citation-box protected-content" id="apa-citation">
                        <p class="citation-text">
                            <?php echo htmlspecialchars($paper['author']); ?> (<?php echo date('Y', strtotime($paper['date_published'])); ?>). 
                            <em><?php echo htmlspecialchars($paper['title']); ?></em>.
                        </p>
                    </div>
                </div>

                <?php if(!empty($paper['keywords'])): ?>
                <div class="sidebar-card">
                    <h3><i class="fa-solid fa-tags"></i> Keywords</h3>
                    <div class="keywords-container protected-content">
                        <?php 
                            $kw = explode(',', $paper['keywords']);
                            foreach($kw as $k) {
                                echo '<span class="keyword-tag">' . htmlspecialchars(trim($k)) . '</span>';
                            }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="sidebar-card">
                    <h3><i class="fa-solid fa-robot"></i> AI Recommendation</h3>
                    <p class="powered-by">Suggested based on the selected paper's title, subject, and department.</p>
                    <div id="recommendations">
                        <?php if (!empty($related_papers)): ?>
                            <?php foreach ($related_papers as $related): ?>
                                <a href="viewingpdf.php?id=<?php echo (int)$related['id']; ?>" class="algolia-rec-link">
                                    <div class="algolia-rec-card">
                                        <div class="rec-icon"><i class="fa-solid fa-file-lines"></i></div>
                                        <div class="rec-content">
                                            <div class="rec-title"><?php echo htmlspecialchars($related['title']); ?></div>
                                            <div class="rec-meta"><?php echo htmlspecialchars($related['author']); ?><?php echo !empty($related['publish_year']) ? ' | ' . (int)$related['publish_year'] : ''; ?></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="recommendation-meta">No related papers found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>     
        </div>
    </div>

    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // --- PDF.js Implementation ---
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const pdfUrl = <?php echo json_encode($paper_url); ?>;
        const viewer = document.getElementById('pdf-viewer');

        function showFallbackViewer() {
            viewer.innerHTML = '<div class="pdf-error">Unable to display the PDF. Please refresh or contact support.</div>';
        }

        async function renderPDF() {
            if (!pdfUrl) {
                viewer.innerHTML = '<div class="pdf-error">No PDF file is available for this paper.</div>';
                return;
            }

            try {
                const loadingTask = pdfjsLib.getDocument(pdfUrl);
                const pdf = await loadingTask.promise;
                const skeleton = document.getElementById('skeleton-loader');
                if (skeleton) skeleton.remove();

                const containerWidth = viewer.clientWidth;

                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    const page = await pdf.getPage(pageNum);
                    const unscaledViewport = page.getViewport({ scale: 1 });
                    const scale = containerWidth / unscaledViewport.width;
                    const viewport = page.getViewport({scale: scale});

                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    viewer.appendChild(canvas);

                    const renderContext = { canvasContext: context, viewport: viewport };
                    await page.render(renderContext).promise;
                }
            } catch (error) {
                console.error('Error rendering PDF:', error);
                showFallbackViewer();
            }
        }

        renderPDF();
    </script>

    <script>
        // --- PROFILE DROPDOWN TOGGLE ---
        function initProfileToggle() {
            const userMenuToggle = document.getElementById('user-menu-toggle');
            const profileDropdown = document.getElementById('profile-dropdown');

            if (userMenuToggle && profileDropdown) {
                userMenuToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.profile-container')) {
                        profileDropdown.classList.remove('show');
                    }
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initProfileToggle);
        } else {
            initProfileToggle();
        }
    </script>

    <script>
        // --- ENHANCED SECURITY: Anti-Screenshot, Watermark & Input Blocking ---
        document.addEventListener('DOMContentLoaded', function() {
            
            // 1. Disable Right-Click
            document.addEventListener('contextmenu', event => event.preventDefault());

            // 2. Disable Key Combinations (Ctrl+C, Ctrl+U, Ctrl+P, Ctrl+S, F12, PrintScreen)
            document.onkeydown = function(e) {
                // Detect Ctrl+P (Print)
                if (e.ctrlKey && (e.keyCode === 80 || e.key === 'p' || e.key === 'P')) {
                    e.preventDefault();
                    alert('Printing is disabled for this document.');
                    return false;
                }
                
                // Detect Print Screen (Key Code 44)
                if (e.keyCode === 44 || e.key === 'PrintScreen') {
                    e.preventDefault();
                    alert('Taking screenshots is prohibited.');
                    return false;
                }

                // Block other shortcuts: Ctrl+C (Copy), Ctrl+S (Save), Ctrl+U (Source), F12 (DevTools)
                if ((e.ctrlKey && [67, 86, 85, 117, 83].includes(e.keyCode)) || e.keyCode === 123) {
                    e.preventDefault();
                    return false;
                }
            };

            // Additional listener for PrintScreen on keyup (catches some OS behaviors)
            document.addEventListener('keyup', function(e) {
                if (e.keyCode === 44 || e.key === 'PrintScreen') {
                    alert('Taking screenshots is prohibited.');
                    // Attempt to clear clipboard to remove the screenshot
                    try { navigator.clipboard.writeText(''); } catch(err) {}
                }
            });

            // 3. Clear Clipboard on Copy Attempt (Ensures "no results")
            document.addEventListener('copy', function(e) {
                // Set clipboard data to empty string
                e.clipboardData.setData('text/plain', '');
                e.preventDefault();
            });
        });
    </script>
    <script src="../app.js"></script>
</body>
</html>