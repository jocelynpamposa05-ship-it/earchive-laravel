<?php
session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php";

function buildPaperUrl($filePath) {
    if (empty($filePath)) {
        return '';
    }

    $normalizedPath = str_replace('\\', '/', $filePath);
    if (preg_match('#^https?://#i', $normalizedPath) || substr($normalizedPath, 0, 1) === '/') {
        return $normalizedPath;
    }

    $currentDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $prefix = (strpos($currentDir, '/students_systems') !== false) ? '../' : '';

    return $prefix . ltrim($normalizedPath, '/');
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
if(isset($_GET['id'])) {
    $paper_id = $_GET['id'];

    // --- Use Prepared Statements for Security & Reliability ---
    // 1. Fetch the paper details
    $stmt = $conn->prepare("SELECT * FROM research_papers WHERE id = ?");
    $stmt->bind_param("i", $paper_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $paper = $result->fetch_assoc();
    $stmt->close();
    
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

    if (!$paper) {
        header("Location: collection.php");
        exit();
    }

    $paper_url = buildPaperUrl($paper['file_path'] ?? '');
    $related_papers = fetchRelatedPapers($conn, $paper['id'], $paper['title'] ?? '', $paper['subject'] ?? '', $paper['keywords'] ?? '', $paper['department'] ?? '');

    // --- NEW: Log the view for daily tracking and update total views ---
    $create_view_logs_table = "CREATE TABLE IF NOT EXISTS view_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        paper_id INT NOT NULL,
        user_id INT DEFAULT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_view_logs_table);

    // Start a transaction to ensure both operations succeed or fail together.
    $conn->begin_transaction();
    try {
        // Increment total views on the paper
        $stmt_update = $conn->prepare("UPDATE research_papers SET views = views + 1 WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("i", $paper_id);
            $stmt_update->execute();
            $stmt_update->close();
        }

        // Log this specific view event into the view_logs table with user info
        $stmt_log = $conn->prepare("INSERT INTO view_logs (paper_id, user_id) VALUES (?, ?)");
        if ($stmt_log) {
            $user_id = $_SESSION['id'];
            $stmt_log->bind_param("ii", $paper_id, $user_id);
            $stmt_log->execute();
            $stmt_log->close();
        }

        // If both queries were successful, commit the transaction
        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback(); // If anything went wrong, roll back the changes
    }
} else {
    header("Location: collection.php"); // Redirect if no ID
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
        /* PDF Viewer Styles */
        #pdf-viewer {
            background-color: #edeff2; /* Light gray background */

            display: flex;
            flex-direction: column;
            /* align-items: center; removed to prevent clipping on overflow */
        }
        #pdf-viewer canvas {
            box-shadow: 0 2px 8px rgba(0,0,0,0.15); /* Paper shadow effect */
            margin: 0 auto 20px auto; /* Center horizontally safely */
            display: block;
        }
       .pdf-container {
            flex: 1;
            background:  #edeff2;
            overflow: auto; /* Allow both horizontal and vertical scrolling */
            padding: 20px;
        }

        .detail-title {
            font-family: 'Inter', sans-serif !important;
            font-size: 0.95rem;
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
                        <h4><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Student'); ?></h4>
                        <p class="email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
                    </div>
                </div>
                <div class="dropdown-body">
                    <div class="profile-info-row">
                        <div><i class="fa-solid fa-id-card"></i> Student ID</div> <span class="value"><?php echo htmlspecialchars($_SESSION['student_id'] ?? ''); ?></span>
                    </div>
                    <div class="profile-info-row">
                        <div><i class="fa-solid fa-building-columns"></i> Department</div> <span class="value"><?php echo $profile_display_department; ?></span>
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

    <!-- Algolia Recommend Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/algoliasearch@4/dist/algoliasearch-lite.umd.js"></script>
    
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // --- PDF.js Implementation ---
        // Set the worker source (required for PDF.js)
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const pdfUrl = '../<?php echo htmlspecialchars($paper['file_path']); ?>';
        const container = document.getElementById('pdf-viewer');

        async function renderPDF() {
            try {
                // Load the PDF document
                const loadingTask = pdfjsLib.getDocument(pdfUrl);
                const pdf = await loadingTask.promise;

                // Remove skeleton loader once PDF data is ready
                const skeleton = document.getElementById('skeleton-loader');
                if (skeleton) skeleton.remove();

                // Loop through all pages and render them
                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    const page = await pdf.getPage(pageNum);
                    
                    // Set scale to 1.5 (Larger size for better viewing)
                    const scale = 1.5;
                    const viewport = page.getViewport({scale: scale});

                    // Create canvas for this page
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    // Append canvas to viewer
                    container.appendChild(canvas);

                    // Render page content
                    const renderContext = { canvasContext: context, viewport: viewport };
                    await page.render(renderContext).promise;
                }
            } catch (error) {
                console.error('Error rendering PDF:', error);
                container.innerHTML = '<div class="pdf-error">Unable to load document protected view.</div>';
            }
        }

        renderPDF();
    </script>

    <script>
                // --- Algolia Search Integration (Simulating Recommendations) ---
        const appId = 'WGL9LUXGKD';
        const apiKey = '7dea79ab13a168dbe7c73fa8d58e48b5'; // <--- Replace with Search-Only Key from Algolia Dashboard


        // Search for papers with similar keywords or department
        // Use json_encode for safe JavaScript string output (handles quotes/newlines)
        const searchKeywords = <?php echo json_encode($paper['keywords'] ?? ''); ?>;
        const searchSubject = <?php echo json_encode($paper['subject'] ?? ''); ?>;
        const searchDepartment = <?php echo json_encode($paper['department'] ?? ''); ?>;
        const currentID = <?php echo json_encode((string)$paper['id']); ?>;
        const currentTitle = <?php echo json_encode(strtolower(trim($paper['title']))); ?>;

        if (apiKey === '' || apiKey.includes('PASTE_YOUR')) {
            document.getElementById('recommendations').innerHTML = '<p class="recommendation-meta">AI Recommendations not configured.</p>';
        } else {
            const client = algoliasearch(appId, apiKey);
            const index = client.initIndex('research_papers');

            // Logic: If keywords exist, search them. If not, skip straight to department.
            let searchPromise;
            
                        const queryString = currentTitle;

            if (queryString.trim() !== "") {
                searchPromise = index.search(queryString, {
                    filters: `NOT objectID:"${currentID}"`,
                    hitsPerPage: 10 // Fetch more hits to allow for deduplication
                });
            } else {
                // Resolve with empty hits to trigger fallback
                searchPromise = Promise.resolve({ hits: [] });
            }

            searchPromise.then(response => {
                let recommendations = response.hits;

                // If we have fewer than 3 hits, try to fill up from the same Department
                if (recommendations.length < 5) {
                    // Exclude current paper AND any papers already found
                    const excludeFilters = recommendations.map(h => `NOT objectID:"${h.objectID}"`);
                    excludeFilters.push(`NOT objectID:"${currentID}"`);


                    return index.search(searchDepartment, {
                        filters: excludeFilters.join(' AND '),
                        hitsPerPage: 10
                    }).then(res => {
                        processAndRender(recommendations.concat(res.hits));
                    });
                } else {
                    processAndRender(recommendations);
                }
            }).catch(err => {
                console.error("Algolia search error:", err);
                document.getElementById('recommendations').innerHTML = '<p class="recommendation-meta">Could not load recommendations.</p>';
            });

            function processAndRender(hits) {
                const uniqueHits = [];
                const seenIDs = new Set();
                const seenTitles = new Set();

                // Helper: Normalize string (remove spaces/symbols) for strict comparison
                const normalize = (str) => (str || '').toLowerCase().replace(/[^a-z0-9]/g, '');

                // Add current paper details to exclusion lists to prevent self-recommendation
                seenIDs.add(currentID);
                seenTitles.add(normalize(currentTitle));

                hits.forEach(hit => {
                    const hitTitle = normalize(hit.title);
                    
                    // Filter by ID and Title to ensure no duplicates
                    if (!seenIDs.has(hit.objectID) && !seenTitles.has(hitTitle)) {
                        seenIDs.add(hit.objectID);
                        seenTitles.add(hitTitle);
                        uniqueHits.push(hit);
                    }
                });

                renderHtml(uniqueHits.slice(0, 3)); // Display top 3 results
            }

            function renderHtml(hits) {
                const container = document.getElementById('recommendations');
                
                if (hits.length === 0) {
                    container.innerHTML = '<p class="recommendation-meta">No similar papers found.</p>';
                    return;
                }

                let htmlContent = '';
                hits.forEach(hit => {
                    if (!hit.objectID) return; // Prevent broken links if ID is missing
                    const title = hit.title || 'Untitled';
                    const author = hit.author || 'Unknown';
                    const year = hit.publish_year || '';

                    htmlContent += `
                        <a href="viewingpdf.php?id=${hit.objectID}" class="algolia-rec-link">
                            <div class="algolia-rec-card">
                                <div class="rec-icon"><i class="fa-solid fa-file-lines"></i></div>
                                <div class="rec-content">
                                    <div class="rec-title">${title}</div>
                                    <div class="rec-meta">${author} ${year ? '| ' + year : ''}</div>
                                </div>
                            </div>
                        </a>
                    `;
                });
                container.innerHTML = htmlContent;
            }
        }
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