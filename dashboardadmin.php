<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php";

// Security Check
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// 2. Fetch Recent Uploads (Excluding deleted papers)
$query = "SELECT * FROM research_papers WHERE (is_deleted = 0 OR is_deleted IS NULL) ORDER BY id DESC LIMIT 5";
$result = mysqli_query($conn, $query);

// 3. Get counts for summary (Excluding deleted papers)
$count_query = "SELECT COUNT(*) as total FROM research_papers WHERE (is_deleted = 0 OR is_deleted IS NULL)";
$count_result = mysqli_query($conn, $count_query);
$count_res = ($count_result) ? mysqli_fetch_assoc($count_result) : ['total' => 0];

// Set the active page for the sidebar
$active_page = 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CPSU E-Archive</title>
    
    <!-- PWA Headers -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="img/cpsu_logo.png">
    <meta name="theme-color" content="#116913">

    <link rel="stylesheet" href="dashboardadmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .welcome-card a {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .research-card {
            background: #f9f9f9;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            border: none;
            overflow: hidden;
        }
        .research-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            font-weight: bold;
            color: #004d00;
            margin-bottom: 10px;
        }
        .dept-logo { width: 25px; }
        .card-body h4 { font-size: 0.9rem; margin-bottom: 5px; color: #333; }
        .card-body h4 a { text-decoration: none; color: inherit; font-family: 'Inter', sans-serif; }
        .card-body .author { font-size: 0.9rem; color: #666; margin-bottom: 15px; }
        .card-subject { font-size: 0.85rem; color: #555; margin-top: 5px; }
        .card-footer {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pdf-link { color: #116913; font-size: 1.2rem; text-decoration: none; }
        .views { font-size: 0.9rem; color: #666; display: flex; align-items: center; gap: 5px; }

        .welcome-card a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            background-color: #0d5010 !important;
        }

        /* Department Colors for Borders */
        .research-card.agri {
            border-right: 6px solid #228B22;
        }
        .research-card.cs {
            border-right: 6px solid #b61db6;
        }
        .research-card.hm {
            border-right: 6px solid #fc46c5;
        }
        .research-card.educ {
            border-right: 6px solid #0056b3;
        }

        /* Responsive Grid Layout */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 1200px) {
            .card-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 900px) {
            .card-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 600px) {
            .card-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        
        <?php include 'admin_sidebar.php'; ?>

        <main class="content">
            
            <div class="welcome-card">
                <h2>Welcome to E-Archive!</h2>
                <p>
                    <strong>E-Archive</strong> is the institutional repository of Central Philippine State University that manages, preserves, and disseminates digital materials representing the scholarly works of the academic community. It serves as a centralized digital library where students, faculty, and researchers can access a wide range of academic resources, including theses and capstone projects.

                    <br><br>
                    You currently have <strong><?php echo isset($count_res['total']) ? $count_res['total'] : 0; ?></strong> papers archived in the system.
                </p>
                <div style="margin-top: 20px;">
                    <a href="algolia_indexer.php" target="_blank" style="background-color: #004d00; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-rotate"></i> Re-index Search Database
                    </a>
                </div>
            </div>

            <h3 style="margin-bottom: 20px; color: #004d00;">Recent Uploads</h3>

            <div class="card-grid">
                <?php 
                if($result && mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                        // Map department names to specific logo filenames and full names
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
                                <img src="img/<?php echo $logo_file; ?>" onerror="this.onerror=null;this.src='img/cpsu_logo.png';" alt="Dept Logo" class="dept-logo">
                                <span><?php echo $display_department; ?></span>
                            </div>
                            <div class="card-body">
                                <h4><a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank"><?php echo htmlspecialchars(ucwords(strtolower($row['title']))); ?></a></h4>
                                <p class="author"><?php echo htmlspecialchars($row['author']); ?> | <?php echo $row['publish_year']; ?></p>
                                <?php if(!empty($row['subject'])): ?>
                                    <p class="card-subject"><strong>Subject:</strong> <?php echo htmlspecialchars($row['subject']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank" class="pdf-link"><span>📄 PDF</span></a>
                                <div class="views"><span><?php echo $row['views']; ?></span> <i class="fa-solid fa-eye"></i></div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<p>No papers uploaded yet.</p>";
                }
                ?>
            </div>
        </main>
    </div>

    <script src="app.js"></script>
</body>
</html>