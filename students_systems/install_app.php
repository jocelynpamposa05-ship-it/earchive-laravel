<?php 
session_set_cookie_params(0, '/');
session_start(); 
include "../db_conn.php"; 

// Security check
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install App | CPSU E-Archive</title>
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="contact.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- PWA Headers -->
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="../img/cpsu_logo.png">
    <link rel="apple-touch-icon" href="../img/cpsu_logo.png">
    <meta name="theme-color" content="#004d00">

    <style>
        .install-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .install-icon {
            font-size: 4rem;
            color: #116913;
            margin-bottom: 20px;
        }
        .features-list {
            text-align: left;
            margin: 30px 0;
            padding: 0 20px;
        }
        .features-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.1rem;
            color: #444;
        }
        .features-list i {
            color: #ebe80f; /* Gold */
            background: #004d00;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        .install-action-btn {
            background-color: #ebe80f;
            color: #004d00;
            border: none;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: 800;
            border-radius: 50px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 5px 15px rgba(235, 232, 15, 0.4);
        }
        .install-action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(235, 232, 15, 0.6);
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

    <div class="main-layout">
        <aside class="sidebar">
            <ul>
                <li><a href="dashboard.php"><i class="fa-solid fa-home"></i> <span>HOME</span></a></li>
                <li><a href="collection.php"><i class="fa-solid fa-book"></i> <span>COLLECTION</span></a></li>
                <li><a href="contact.php"><i class="fa-solid fa-envelope"></i> <span>CONTACT US</span></a></li>
                <li id="navInstallApp" class="navInstallApp active"><a href="install_app.php"><i class="fa-solid fa-download"></i> <span>INSTALL APP</span></a></li>
                <li><a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> <span>LOG OUT</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="install-card">
                <div class="install-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
                <h2 style="color: #004d00; margin-bottom: 10px;">Install E-Archive App</h2>
                <p style="color: #666;">Experience better performance and offline access.</p>
                
                <ul class="features-list">
                    <li><i class="fa-solid fa-bolt"></i> <span><strong>One-tap Access:</strong> Launch directly from your home screen.</span></li>
                    <li><i class="fa-solid fa-expand"></i> <span><strong>Immersive View:</strong> Full-screen research browsing.</span></li>
                    <li><i class="fa-solid fa-wifi"></i> <span><strong>Offline Mode:</strong> Access metadata without internet.</span></li>
                </ul>

                <button id="installActionBtn" class="install-action-btn">
                    <i class="fa-solid fa-download"></i> INSTALL NOW
                </button>
                <p id="installMsg" style="margin-top: 15px; color: #888; font-size: 0.9rem; display: none;">App is already installed or not supported in this browser.</p>
            </div>
        </main>
    </div>

    <script src="../app.js"></script>
</body>
</html>