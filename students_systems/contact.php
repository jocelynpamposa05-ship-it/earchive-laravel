<?php 
session_set_cookie_params(0, '/');
session_start(); 
include "../db_conn.php";

// Security check: Ensure user is logged in and is a student
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
    <title>Contact Us | CPSU E-Archive</title>
    
    <!-- PWA Headers -->
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/png" href="../img/cpsu_logo.png">
    <link rel="apple-touch-icon" href="../img/cpsu_logo.png">
    <meta name="theme-color" content="#004d00">
    
    <link rel="stylesheet" href="contact.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    
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
                <li class="active"><a href="contact.php"><i class="fa-solid fa-envelope"></i> <span>CONTACT US</span></a></li>
                <li id="navInstallApp" class="navInstallApp"><a href="install_app.php"><i class="fa-solid fa-download"></i> <span>INSTALL APP</span></a></li>
                <li><a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> <span>LOG OUT</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="contact-page-wrapper">
                <h2 class="page-title">Get in Touch</h2>
                <p class="page-subtitle">We'd love to hear from you. Here's how you can reach us.</p>


                <!-- Contact Information Section -->
                <div class="contact-section">
                    <h3 class="section-title">Contact Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon"><i class="fa-solid fa-location-dot"></i></div>
                            <div class="info-content">
                                <h4>Our Location</h4>
                                <p><a href="https://www.google.com/maps/place/Central+Philippines+State+University+-+Victorias+Campus/@10.8835643,123.1059824,17z/data=!3m1!4b1!4m6!3m5!1s0x33a8d3116ac8ed9d:0xe3e62e93957b716!8m2!3d10.8835643!4d123.1085573!16s%2Fg%2F1pp2tzsms?entry=ttu&g_ep=EgoyMDI2MDMwNC4xIKXMDSoASAFQAw%3D%3D" target="_blank">Hda. Estrella, Barangay 
                                    XIV, Victorias City, 
                                    Negros Occidental</a></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="fa-brands fa-facebook"></i></div>
                            <div class="info-content">
                                <h4>Facebook Page</h4>
                                <p><a href="https://www.facebook.com/profile.php?id=100086594925860" target="_blank">CPSU Victorias Campus Library</a></p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="fa-solid fa-envelope"></i></div>
                            <div class="info-content">
                                <h4>Email</h4>
                                <p><a href="mailto:cpsuvictoriaslibrary@gmail.com">cpsuvictoriaslibrary
                                    @gmail.com</a></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- This script should be loaded on all pages where the install button is present -->
    <script src="../app.js"></script>

</body>
</html>