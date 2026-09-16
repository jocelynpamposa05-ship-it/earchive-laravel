<?php 
session_set_cookie_params(0, '/');
session_start(); 
include "../db_conn.php"; // Go up one level for the DB connection

// 1. SECURITY CHECK: Ensure user is logged in and is a student
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

// 2. GET AND SANITIZE FILTERS
$dept_filter = $_GET['dept'] ?? '';
$year_filter = $_GET['year'] ?? '';
$search_query = $_GET['search'] ?? '';

// NEW: Check for an AJAX/JSON request
$is_json_request = isset($_GET['json']) && $_GET['json'] === '1';

// Function to calculate string similarity (Levenshtein)
function stringSimilarity($str1, $str2) {
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    $maxLen = max($len1, $len2);
    if ($maxLen === 0) return 100;
    return round((1 - levenshtein($str1, $str2) / $maxLen) * 100);
}

// Function to get suggestions for misspelled words
function getSuggestions($searchQuery, $conn, $limit = 5) {
    $suggestions = [];
    $searchQuery = trim(strtolower($searchQuery));
    if ($searchQuery === '') {
        return [];
    }

    $searchTerms = preg_split('/[^a-z0-9]+/i', $searchQuery, -1, PREG_SPLIT_NO_EMPTY);
    $stmt = $conn->prepare("SELECT DISTINCT title FROM research_papers WHERE is_deleted = 0 UNION SELECT DISTINCT author FROM research_papers WHERE is_deleted = 0 UNION SELECT DISTINCT keywords FROM research_papers WHERE is_deleted = 0");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $field = trim(array_values($row)[0]);
        if ($field === '') {
            continue;
        }

        $fieldLower = strtolower($field);
        $fieldTerms = preg_split('/[^a-z0-9]+/i', $fieldLower, -1, PREG_SPLIT_NO_EMPTY);
        $fieldTerms[] = $fieldLower; // also compare the full phrase

        foreach ($fieldTerms as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $similarity = 0;
            if (stripos($candidate, $searchQuery) !== false || stripos($searchQuery, $candidate) !== false) {
                $similarity = 100;
            } else {
                $similarity = stringSimilarity($searchQuery, $candidate);
            }

            if ($similarity >= 55) {
                $suggestions[$candidate] = max($suggestions[$candidate] ?? 0, $similarity);
            }

            foreach ($searchTerms as $searchTerm) {
                if ($searchTerm === '') {
                    continue;
                }

                if (stripos($candidate, $searchTerm) !== false || stripos($searchTerm, $candidate) !== false) {
                    $suggestions[$candidate] = max($suggestions[$candidate] ?? 0, 90);
                    continue;
                }

                $termSimilarity = stringSimilarity($searchTerm, $candidate);
                if ($termSimilarity >= 55) {
                    $suggestions[$candidate] = max($suggestions[$candidate] ?? 0, $termSimilarity);
                }
            }
        }
    }

    $stmt->close();

    arsort($suggestions);
    return array_slice(array_keys($suggestions), 0, $limit);
}

// 3. BUILD DYNAMIC QUERY WITH PREPARED STATEMENTS
$sql = "SELECT * FROM research_papers WHERE is_deleted = 0";
$params = [];
$types = "";

if (!empty($dept_filter)) { $sql .= " AND department = ?"; $params[] = $dept_filter; $types .= "s"; }
if (!empty($year_filter)) {
    if (strpos($year_filter, '-') !== false) {
        list($start, $end) = explode('-', $year_filter);
        $sql .= " AND publish_year BETWEEN ? AND ?";
        $params[] = (int)$start; $params[] = (int)$end;
        $types .= "ii";
    } else {
        $sql .= " AND publish_year = ?";
        $params[] = (int)$year_filter;
        $types .= "i";
    }
}

$no_results = false;
$suggestions = [];

if (!empty($search_query)) { 
    $sql .= " AND (title LIKE ? OR author LIKE ? OR keywords LIKE ?)"; 
    $like_query = "%" . $search_query . "%";
    $params[] = $like_query; $params[] = $like_query; $params[] = $like_query;
    $types .= "sss";
}

$sql .= " ORDER BY id DESC"; 

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Check if we got any results
$row_count = $result->num_rows;
if (!empty($search_query)) {
    $suggestions = getSuggestions($search_query, $conn);
    if ($row_count === 0) {
        $no_results = true;
    }
}

// Build display suggestions (map candidate -> matching paper title and URL)
$display_suggestions = [];
if (!empty($suggestions)) {
    foreach ($suggestions as $candidate) {
        // Try to find a paper title that contains the candidate (prefer exact phrase)
        $stmt2 = $conn->prepare("SELECT id, title FROM research_papers WHERE is_deleted = 0 AND (title LIKE ? OR keywords LIKE ? OR author LIKE ?) LIMIT 1");
        $likeCan = '%' . $candidate . '%';
        $stmt2->bind_param('sss', $likeCan, $likeCan, $likeCan);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2 && $res2->num_rows > 0) {
            $r = $res2->fetch_assoc();
            $display_suggestions[] = ['label' => $r['title'], 'url' => 'viewingpdf.php?id=' . urlencode($r['id'])];
        } else {
            // Fallback: link to collection search for the candidate (capitalized)
            $label = mb_convert_case($candidate, MB_CASE_TITLE, 'UTF-8');
            $display_suggestions[] = ['label' => $label, 'url' => 'collection.php?search=' . urlencode($candidate)];
        }
        $stmt2->close();
    }
}

// NEW: If this is a JSON request, output the data and stop
if ($is_json_request) {
    $papers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $papers[] = $row;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($papers);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection | CPSU E-Archive</title>
    <link rel="stylesheet" href="collection.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- PWA Headers -->
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="../img/cpsu_logo.png">
    <link rel="apple-touch-icon" href="../img/cpsu_logo.png">
    <meta name="theme-color" content="#004d00">
    <style>
        /* Remove hover effects from filter dropdown buttons */
        .dropdown .dropbtn:hover {
            background-color: #116913 !important; /* Keep default background color */
            box-shadow: none !important;
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="logo-section">
            <img src="../img/cpsu_logo.png" alt="CPSU Logo">
            <div class="header-text">
                <h1>CENTRAL PHILIPPINES STATE UNIVERSITY</h1>
                <h1>E-ARCHIVE</h1>
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
                        <div><i class="fa-solid fa-id-card"></i> Student ID</div> <span class="value"><?php echo htmlspecialchars($_SESSION['student_id'] ?? $_SESSION['id'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-info-row dept-full-row">
                        <div><i class="fa-solid fa-building-columns"></i> Department</div> <span class="value"><?php echo $profile_display_department; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <ul>
                <li><a href="dashboard.php"><i class="fa-solid fa-home"></i> <span>HOME</span></a></li>
                <li class="active"><a href="collection.php"><i class="fa-solid fa-book"></i> <span>COLLECTION</span></a></li>
                <li><a href="contact.php"><i class="fa-solid fa-envelope"></i> <span>CONTACT US</span></a></li>
                <li id="navInstallApp" class="navInstallApp"><a href="install_app.php"><i class="fa-solid fa-download"></i> <span>INSTALL APP</span></a></li>
                <li><a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> <span>LOG OUT</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="filter-container">
                <form action="collection.php" method="GET" class="search-box">
                    <?php if(!empty($dept_filter)): ?><input type="hidden" name="dept" value="<?php echo $dept_filter; ?>"><?php endif; ?>
                    <?php if(!empty($year_filter)): ?><input type="hidden" name="year" value="<?php echo $year_filter; ?>"><?php endif; ?>
                    <input type="text" name="search" placeholder="Search title or author..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit"><i class="fa fa-search"></i></button>
                </form>

                <div class="dropdown">
                    <button type="button" class="dropbtn" id="deptBtn">
                        <?php echo !empty($dept_filter) ? htmlspecialchars($dept_filter) : "Select Department"; ?> 
                        <i class="fa fa-caret-down"></i>
                    </button>
                    <div class="dropdown-content" id="deptMenu">
                        <a href="collection.php?year=<?php echo $year_filter; ?>&search=<?php echo urlencode($search_query); ?>">ALL DEPARTMENTS</a>
                        <a href="collection.php?dept=AGRICULTURE&year=<?php echo $year_filter; ?>&search=<?php echo urlencode($search_query); ?>">College of Agriculture and Forestry</a>
                        <a href="collection.php?dept=COMPUTER STUDIES&year=<?php echo $year_filter; ?>&search=<?php echo urlencode($search_query); ?>">College of Computer Studies</a>
                        <a href="collection.php?dept=HOSPITALITY MANAGEMENT&year=<?php echo $year_filter; ?>&search=<?php echo urlencode($search_query); ?>">College of Hospitality Management</a>
                        <a href="collection.php?dept=TEACHER EDUCATION&year=<?php echo $year_filter; ?>&search=<?php echo urlencode($search_query); ?>">College of Teacher Education</a>
                    </div>
                </div>

                <div class="dropdown">
                    <button type="button" class="dropbtn" id="yearBtn">
                        <?php echo !empty($year_filter) ? htmlspecialchars($year_filter) : "Year"; ?> 
                        <i class="fa fa-caret-down"></i>
                    </button>
                    <div class="dropdown-content" id="yearMenu">
                        <a href="collection.php?dept=<?php echo $dept_filter; ?>&search=<?php echo urlencode($search_query); ?>">ALL YEARS</a>
                        <a href="collection.php?year=2023&dept=<?php echo $dept_filter; ?>&search=<?php echo urlencode($search_query); ?>">2023</a>
                        <a href="collection.php?year=2024&dept=<?php echo $dept_filter; ?>&search=<?php echo urlencode($search_query); ?>">2024</a>
                        <div class="custom-range-label">Specific Year / Range:</div>
                        <div class="custom-range-inputs">
                            <input type="number" id="startYear" placeholder="Year" class="year-input">
                            <span class="range-separator">-</span>
                            <input type="number" id="endYear" placeholder="To" class="year-input">
                            <button type="button" onclick="applyCustomRange()" class="apply-range-btn">Go</button>
                        </div>
                    </div>
                </div>

                <button type="button" id="clearFiltersBtn" class="clear-btn">
                    <i class="fa-solid fa-times"></i> Clear
                </button>
            </div>

            <h3 class="section-title">
                <?php
                    if (!empty($search_query)) {
                        // If there are results and we have a top display suggestion that is different, show corrected header
                        if ($row_count > 0 && !empty($display_suggestions)) {
                            $topLabel = $display_suggestions[0]['label'];
                            // If top label doesn't contain the original query exactly, display the "These are results for" message
                            if (stripos($topLabel, $search_query) === false && strcasecmp(trim($topLabel), trim($search_query)) !== 0) {
                                echo "These are results for: '" . htmlspecialchars($topLabel) . "'";
                                // Provide link to search instead for the original term
                                echo " <small style=\"margin-left:10px; font-weight:400;\">(<a href=\"collection.php?search=" . urlencode($search_query) . "\">Search instead for '" . htmlspecialchars($search_query) . "'</a>)</small>";
                            } else {
                                echo "Search results for: '" . htmlspecialchars($search_query) . "'";
                            }
                        } else {
                            echo "Search results for: '" . htmlspecialchars($search_query) . "'";
                        }
                    } else {
                        echo "Recent Archive";
                    }
                ?>
            </h3>

            <div class="results-grid">
                <?php 
                // 4. DISPLAY LOGIC: Show results if found, otherwise show "Empty" message
                if ($result && mysqli_num_rows($result) > 0): 
                    while($row = mysqli_fetch_assoc($result)): 
                        // Map department names to specific logo filenames
                        $logo_file = 'cpsu_logo.png'; 
                        $display_department = htmlspecialchars($row['department']);
                        $dept_class = '';
                        switch ($row['department']) {
                            case 'COMPUTER STUDIES':
                                $logo_file = 'it.png';
                                $display_department = 'College of Computer Studies';
                                $dept_class = 'cs';
                                break;
                            case 'HOSPITALITY MANAGEMENT':
                                $logo_file = 'hm.png';
                                $display_department = 'College of Hospitality Management';
                                $dept_class = 'hm';
                                break;
                            case 'TEACHER EDUCATION':
                                $logo_file = 'educ1.png';
                                $display_department = 'College of Teacher Education';
                                $dept_class = 'educ';
                                break;
                            case 'AGRICULTURE':
                                $logo_file = 'agri.png';
                                $display_department = 'College of Agriculture and Forestry';
                                $dept_class = 'agri';
                                break;
                        }
                ?>
                <div class="research-card <?php echo $dept_class; ?>">
                    <div class="card-header">
                        <img src="../img/<?php echo $logo_file; ?>" onerror="this.onerror=null;this.src='../img/cpsu_logo.png';" alt="Dept Logo" class="dept-logo">
                        <span><?php echo $display_department; ?></span>
                    </div>
                    <div class="card-body">
                        <h4>
                            <a href="viewingpdf.php?id=<?php echo $row['id']; ?>" class="card-title-link"><?php echo htmlspecialchars($row['title']); ?></a>
                        </h4>
                        <p class="author"><?php echo htmlspecialchars($row['author']); ?> | <?php echo $row['publish_year']; ?></p>
                        <?php if(!empty($row['subject'])): ?>
                            <p class="card-subject"><strong>Subject:</strong> <?php echo htmlspecialchars($row['subject']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="viewingpdf.php?id=<?php echo $row['id']; ?>" class="pdf-link" title="View PDF"><span>📄 PDF</span></a>
                        <div class="views"><span><?php echo $row['views']; ?></span><i class="fa-regular fa-eye"></i></div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="no-results">
                        <i class="fa-solid fa-folder-open no-results-icon"></i>
                        <?php if (!empty($search_query) && $no_results): ?>
                            <p class="no-results-title">❓ No results found for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                            <?php if (!empty($display_suggestions)): ?>
                                <p style="text-align: center;"><strong>Did you mean?</strong></p>
                                <div style="margin-top: 15px; text-align: center;">
                                    <?php foreach ($display_suggestions as $ds): ?>
                                        <a href="<?php echo htmlspecialchars($ds['url']); ?>" style="display: inline-block; margin: 5px 5px 5px 0; padding: 8px 12px; background-color: #116913; color: white; text-decoration: none; border-radius: 5px; font-size: 0.9rem;">
                                            🔍 <?php echo htmlspecialchars($ds['label']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="margin-top: 10px; color: #666;">No similar suggestions found. Try different keywords.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="no-results-title">The E-Archive is currently empty.</p>
                            <p>Thesis papers will appear here once they are uploaded via the Admin panel.</p>
                        <?php endif; ?>
                        <br>
                        <a href="collection.php" class="reset-btn">Clear All Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Clear Confirmation Modal -->
    <div id="clearConfirmModal" class="custom-modal-overlay">
        <div class="custom-modal-box">
            <p>Are you sure you want to clear this?</p>
            <div class="confirm-buttons">
                <button id="confirmClearBtn" class="btn-confirm-yes">Yes</button>
                <button id="cancelClearBtn" class="btn-confirm-no">No</button>
            </div>
        </div>
    </div>

    <script>
        const deptBtn = document.getElementById('deptBtn');
        const yearBtn = document.getElementById('yearBtn');
        const deptMenu = document.getElementById('deptMenu');
        const yearMenu = document.getElementById('yearMenu');

        deptBtn.onclick = function(e) {
            e.stopPropagation();
            deptMenu.classList.toggle('show');
            yearMenu.classList.remove('show');
        };

        yearBtn.onclick = function(e) {
            e.stopPropagation();
            yearMenu.classList.toggle('show');
            deptMenu.classList.remove('show');
        };

        // Prevent dropdown from closing when clicking inside it (fixes typing issue)
        deptMenu.onclick = function(e) { e.stopPropagation(); };
        yearMenu.onclick = function(e) { e.stopPropagation(); };

        window.onclick = function() {
            deptMenu.classList.remove('show');
            yearMenu.classList.remove('show');
        };

        function applyCustomRange() {
            const start = document.getElementById('startYear').value;
            const end = document.getElementById('endYear').value;
            if(start || end) {
                const currentUrl = new URL(window.location.href);
                if(start && end) {
                    currentUrl.searchParams.set('year', start + '-' + end);
                } else {
                    currentUrl.searchParams.set('year', start || end);
                }
                window.location.href = currentUrl.toString();
            }
        }
    </script>

    <script>
        // --- NEW: AJAX functionality for a smoother experience ---
        document.addEventListener('DOMContentLoaded', function() {
            const clearButton = document.getElementById('clearFiltersBtn');
            const clearModal = document.getElementById('clearConfirmModal');
            const confirmClearBtn = document.getElementById('confirmClearBtn');
            const cancelClearBtn = document.getElementById('cancelClearBtn');

            if (clearButton) {
                clearButton.addEventListener('click', function(event) {
                    event.preventDefault(); // Stop default form submission
                    if(clearModal) clearModal.style.display = 'flex';
                });
            }

            if (cancelClearBtn) {
                cancelClearBtn.addEventListener('click', function() {
                    if(clearModal) clearModal.style.display = 'none';
                });
            }

            if (confirmClearBtn) {
                confirmClearBtn.addEventListener('click', function() {
                    if(clearModal) clearModal.style.display = 'none';
                    
                    // 1. Visually clear the form fields
                    document.querySelector('input[name="search"]').value = '';
                    document.getElementById('deptBtn').innerHTML = 'Select Department <i class="fa fa-caret-down"></i>';
                    document.getElementById('yearBtn').innerHTML = 'Year <i class="fa fa-caret-down"></i>';

                    // 2. Update the URL in the browser bar without reloading
                    history.pushState(null, '', 'collection.php');

                    // 3. Fetch the unfiltered results from the server
                    fetch('collection.php?json=1')
                        .then(response => response.json())
                        .then(data => {
                            // 4. Render the new results and update title
                            renderResults(data);
                            document.querySelector('.section-title').innerText = 'Recent Archive';
                        })
                        .catch(error => console.error('Error fetching papers:', error));
                });
            }

            function renderResults(papers) {
                const grid = document.querySelector('.results-grid');
                grid.innerHTML = ''; // Clear existing results

                if (!papers || papers.length === 0) {
                    grid.innerHTML = `<div class="no-results"><i class="fa-solid fa-folder-open no-results-icon"></i><p class="no-results-title">No results found.</p></div>`;
                    return;
                }

                let cardsHtml = papers.map(paper => {
                    let logoFile = 'cpsu_logo.png'; 
                    let displayDepartment = escapeHTML(paper.department);
                    let deptClass = '';

                    switch(paper.department) {
                        case 'COMPUTER STUDIES': 
                            logoFile = 'it.png'; 
                            displayDepartment = 'College of Computer Studies';
                            deptClass = 'cs';
                            break;
                        case 'HOSPITALITY MANAGEMENT': 
                            logoFile = 'hm.png'; 
                            displayDepartment = 'College of Hospitality Management';
                            deptClass = 'hm';
                            break;
                        case 'TEACHER EDUCATION': 
                            logoFile = 'educ1.png'; 
                            displayDepartment = 'College of Teacher Education';
                            deptClass = 'educ';
                            break;
                        case 'AGRICULTURE': 
                            logoFile = 'agri.png'; 
                            displayDepartment = 'College of Agriculture and Forestry';
                            deptClass = 'agri';
                            break;
                    }

                    const subjectHtml = paper.subject ? `<p class="card-subject"><strong>Subject:</strong> ${escapeHTML(paper.subject)}</p>` : '';

                    return `
                        <div class="research-card ${deptClass}">
                            <div class="card-header">
                                <img src="../img/${logoFile}" onerror="this.onerror=null;this.src='../img/cpsu_logo.png';" alt="Dept Logo" class="dept-logo">
                                <span>${displayDepartment}</span>
                            </div>
                            <div class="card-body"><h4><a href="viewingpdf.php?id=${paper.id}" class="card-title-link">${escapeHTML(paper.title)}</a></h4><p class="author">${escapeHTML(paper.author)} | ${paper.publish_year}</p>${subjectHtml}</div>
                            <div class="card-footer"><a href="viewingpdf.php?id=${paper.id}" class="pdf-link" title="View PDF"><span>📄 PDF</span></a><div class="views"><span>${paper.views}</span><i class="fa-regular fa-eye"></i></div></div>
                        </div>
                    `;
                }).join('');

                grid.innerHTML = cardsHtml;
            }

            // Helper function to prevent XSS attacks when rendering data
            function escapeHTML(str) {
                return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
        });
    </script>
    <script src="../app.js"></script>
</body>
</html>