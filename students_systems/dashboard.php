<?php 
session_set_cookie_params(0, '/');
session_start(); 
include "../db_conn.php"; 

// SECURITY CHECK: If the user is not logged in or is not a student, send them back to the login page
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

// --- NEW: Fetch data for new sections ---

function is_valid_paper_title($title) {
    $title = trim($title);
    if ($title === '') {
        return false;
    }
    if (!preg_match('/[a-zA-Z]/', $title)) {
        return false;
    }
    $words = preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) === 1) {
        $letters = preg_replace('/[^a-zA-Z]/', '', $title);
        $length = strlen($letters);
        if ($length < 8) {
            return false;
        }
        $vowel_count = preg_match_all('/[aeiouyAEIOUY]/', $letters);
        if ($vowel_count / max(1, $length) < 0.28) {
            return false;
        }
    }
    return true;
}

// Fetch Highlight of the Week: first valid, non-deleted paper ordered by views
$highlight_query = "SELECT * FROM research_papers WHERE (is_deleted = 0 OR is_deleted IS NULL) AND title IS NOT NULL AND TRIM(title) <> '' ORDER BY views DESC, id DESC LIMIT 30";
$highlight_result = mysqli_query($conn, $highlight_query);
$highlight_paper = null;
if ($highlight_result) {
    while ($row = mysqli_fetch_assoc($highlight_result)) {
        if (is_valid_paper_title($row['title'])) {
            $highlight_paper = $row;
            break;
        }
    }
}

// Fetch New Uploads (latest valid non-deleted papers)
$new_uploads_query = "SELECT * FROM research_papers WHERE (is_deleted = 0 OR is_deleted IS NULL) AND title IS NOT NULL AND TRIM(title) <> '' ORDER BY id DESC LIMIT 30";
$new_uploads_result = mysqli_query($conn, $new_uploads_query);

// Simple truncate function to avoid breaking layout
function truncate_text($text, $length) {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length);
        // find the last space
        $text = substr($text, 0, strrpos($text, ' '));
        $text .= '...';
    }
    return $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | CPSU E-Archive</title>
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .card-title-link {
            font-family: 'Inter', sans-serif !important;
        }
        /* Profile Dropdown Styles */
        .profile-container { position: relative; display: flex; align-items: center; }
        .user-profile-icon { cursor: pointer; font-size: 2.2rem; color: #ffffff; transition: color 0.3s ease; }
        .user-profile-icon:hover { color: #ebe80f; }
        .profile-dropdown {
            display: none; position: absolute; right: 0; top: 55px; background: white;
            border-radius: 10px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); width: 350px;
            max-width: 90vw; z-index: 1000; overflow: hidden; border: 1px solid #eee;
            animation: fadeIn 0.2s ease-out;
        }
        .profile-dropdown.show { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-header { display: flex; align-items: center; gap: 15px; padding: 20px; background: #f8f9fa; }
        .dropdown-header .avatar { font-size: 3.5rem; color: #116913; }
        .dropdown-header .user-info h4 { margin: 0; font-size: 1.1rem; color: #333; font-weight: 600; }
        .dropdown-header .user-info .email { margin: 0; font-size: 0.9rem; color: #6c757d; }
        .dropdown-body { padding: 15px 20px; }
        .profile-info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; color: #555; }
        .profile-info-row i { color: #116913; margin-right: 8px; }
        .profile-info-row .value { font-weight: 600; color: #333; }
        .dept-full-row { 
            flex-direction: column; align-items: flex-start; gap: 5px; 
            border-top: 1px solid #eee; padding-top: 10px; margin-top: 5px; 
        }
        .dept-full-row .value { 
            color: #116913; font-size: 0.8rem; white-space: nowrap; width: 100%; 
        }
    </style>

    <!-- PWA Headers -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="../img/cpsu_logo.png">
    <link rel="apple-touch-icon" href="../img/cpsu_logo.png">
    <meta name="theme-color" content="#004d00">
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
                        <div><i class="fa-solid fa-building"></i> Dept. Code</div> <span class="value"><?php echo htmlspecialchars($_SESSION['department'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-info-row dept-full-row">
                        <div><i class="fa-solid fa-building-columns"></i> College Full Name</div> <span class="value"><?php echo $profile_display_department; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-layout">
        <aside class="sidebar">
            <nav>
            <ul>
                <li class="active"><a href="dashboard.php"><i class="fa-solid fa-home"></i> <span>HOME</span></a></li>
                <li><a href="collection.php"><i class="fa-solid fa-book"></i> <span>COLLECTION</span></a></li>
                <li><a href="contact.php"><i class="fa-solid fa-envelope"></i> <span>CONTACT US</span></a></li>
                <li id="navInstallApp" class="navInstallApp"><a href="install_app.php"><i class="fa-solid fa-download"></i> <span>INSTALL APP</span></a></li>
                <li><a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> <span>LOG OUT</span></a></li>
            </ul>
            </nav>
        </aside>

        <main class="content">
            <section class="welcome-box">
                <div class="welcome-header">
                    <h2>Welcome to E-Archive, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2> 
                </div>
                <p><strong style="color: #0a440b;">E-Archive</strong> is the institutional repository of Central Philippines State University, 
                designed to store, manage, preserve, and share the research projects of the students. 
                It serves as a digital repository where students, faculty, and researchers can easily 
                access scholarly works such as theses and capstone projects. It supports knowledge sharing, enhances research activities, and ensures the long-term preservation of the university’s 
                academic outputs for future use.</p>
            </section>

            <section class="college-access">
                <h3>QUICK ACCESS BY DEPARTMENT </h3>
                <div class="quick-access-grid">
                    <a href="collection.php?dept=AGRICULTURE" class="college-card agri">
                        <img src="../img/agri.png" alt="Agribusiness">
                        <span>College of Agriculture and Forestry</span>
                    </a>
                    <a href="collection.php?dept=COMPUTER STUDIES" class="college-card cs">
                        <img src="../img/it.png" alt="Computer Studies">
                        <span>College of Computer Studies</span>
                    </a>
                    <a href="collection.php?dept=HOSPITALITY MANAGEMENT" class="college-card hm">
                        <img src="../img/hm.png" alt="Hospitality Management">
                        <span>College of Hospitality Management</span>
                    </a>
                    <a href="collection.php?dept=TEACHER EDUCATION" class="college-card educ">
                        <img src="../img/educ1.png" alt="Teacher Education">
                        <span>College of Teacher Education</span>
                    </a>
                </div>
            </section>

            <!-- NEW: HIGHLIGHT OF THE WEEK -->
            <section class="highlight-section">
                <h3><i class="fa-solid fa-star"></i> HIGHLIGHT OF THE WEEK</h3>
                <div class="highlight-grid">
                    <?php if ($highlight_paper): 
                        // --- Logic to determine card style ---
                        $logo_file = 'cpsu_logo.png';
                        $display_department = htmlspecialchars($highlight_paper['department']);
                        $dept_class = '';
                        switch ($highlight_paper['department']) {
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
                                <h4><a href="viewingpdf.php?id=<?php echo $highlight_paper['id']; ?>" class="card-title-link"><?php echo htmlspecialchars($highlight_paper['title']); ?></a></h4>
                                <p class="author"><?php echo htmlspecialchars($highlight_paper['author']); ?> | <?php echo $highlight_paper['publish_year']; ?></p>
                                <?php if(!empty($highlight_paper['subject'])): ?>
                                    <p class="card-subject"><strong>Subject:</strong> <?php echo htmlspecialchars($highlight_paper['subject']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="viewingpdf.php?id=<?php echo $highlight_paper['id']; ?>" class="pdf-link" title="View PDF"><span>📄 PDF</span></a>
                                <div class="views"><span><?php echo $highlight_paper['views']; ?></span><i class="fa-regular fa-eye"></i></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>No papers available for highlight at the moment.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- NEW: NEW UPLOADS -->
            <section class="new-uploads-section">
                <h3><i class="fa-solid fa-file-arrow-up"></i> NEW UPLOADS</h3>
                <div class="new-uploads-grid">
                    <?php
                    $displayed_new_uploads = 0;
                    if ($new_uploads_result && mysqli_num_rows($new_uploads_result) > 0):
                        while ($paper = mysqli_fetch_assoc($new_uploads_result)):
                            if (!is_valid_paper_title($paper['title'])) {
                                continue;
                            }
                            if ($displayed_new_uploads >= 10) {
                                break;
                            }

                            // --- Logic from collection.php to determine card style ---
                            $logo_file = 'cpsu_logo.png';
                            $display_department = htmlspecialchars($paper['department']);
                            $dept_class = '';
                            switch ($paper['department']) {
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
                            $displayed_new_uploads++;
                    ?>
                    <div class="research-card <?php echo $dept_class; ?>">
                        <div class="card-header">
                            <img src="../img/<?php echo $logo_file; ?>" onerror="this.onerror=null;this.src='../img/cpsu_logo.png';" alt="Dept Logo" class="dept-logo">
                            <span><?php echo $display_department; ?></span>
                        </div>
                        <div class="card-body">
                            <h4><a href="viewingpdf.php?id=<?php echo $paper['id']; ?>" class="card-title-link"><?php echo htmlspecialchars($paper['title']); ?></a></h4>
                            <p class="author"><?php echo htmlspecialchars($paper['author']); ?> | <?php echo $paper['publish_year']; ?></p>
                            <?php if(!empty($paper['subject'])): ?>
                                <p class="card-subject"><strong>Subject:</strong> <?php echo htmlspecialchars($paper['subject']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="viewingpdf.php?id=<?php echo $paper['id']; ?>" class="pdf-link" title="View PDF"><span>📄 PDF</span></a>
                            <div class="views"><span><?php echo $paper['views']; ?></span><i class="fa-regular fa-eye"></i></div>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>

                    <?php if ($displayed_new_uploads === 0): ?>
                        <p>No new uploads found.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="../app.js"></script>
</body>
</html>