<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php"; // Use the central DB connection

// Security Check
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// --- NEW: Auto-create view_logs table for daily tracking ---
$create_view_logs_table = "CREATE TABLE IF NOT EXISTS view_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paper_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paper_id) REFERENCES research_papers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES student_users(id) ON DELETE SET NULL
)";
$conn->query($create_view_logs_table);

// --- Fetch Real Data for the Report Cards ---
$total_papers = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM research_papers"));
$total_students = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM student_users"));

$views_query = mysqli_query($conn, "SELECT SUM(views) as total_views FROM research_papers");
$total_views_data = mysqli_fetch_assoc($views_query);
$total_views = $total_views_data['total_views'] ?? 0; // Use null coalescing operator for safety

// Ensure view_logs exists before querying it
$create_view_logs_table = "CREATE TABLE IF NOT EXISTS view_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paper_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paper_id) REFERENCES research_papers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES student_users(id) ON DELETE SET NULL
)";
$conn->query($create_view_logs_table);

// --- NEW: Fetch Data for Charts (Consolidated Logic) ---

// 1. Define Departments and their specific Colors (Identity)
$departments_list = [
    'AGRICULTURE' => ['label' => 'College of Agriculture and Forestry', 'color' => '#228B22'],
    'COMPUTER STUDIES' => ['label' => 'College of Computer Studies', 'color' => '#b61db6'],
    'HOSPITALITY MANAGEMENT' => ['label' => 'College of Hospitality Management', 'color' => '#fc46c5'],
    'TEACHER EDUCATION' => ['label' => 'College of Teacher Education', 'color' => '#0056b3']
];

// --- REVISED: Fetch data for "Daily Repository Utilization" ---
// This now fetches views for TODAY, not total views.
$daily_dept_views_data = [];
foreach($departments_list as $key => $val) {
    $daily_dept_views_data[$key] = 0;
}

// Fetch today's view counts from the new view_logs table
$daily_dept_query = mysqli_query($conn, "
    SELECT p.department, COUNT(v.id) as daily_views
    FROM view_logs v
    JOIN research_papers p ON v.paper_id = p.id
    WHERE DATE(v.viewed_at) = CURDATE()
    GROUP BY p.department
");

if ($daily_dept_query) {
    while($row = mysqli_fetch_assoc($daily_dept_query)) {
        $dept_key = strtoupper($row['department']);
        if(isset($daily_dept_views_data[$dept_key])) {
            $daily_dept_views_data[$dept_key] = (int)$row['daily_views'];
        }
    }
}

// Prepare data for the "Daily" chart (now a bar chart)
$daily_chart_labels = [];
$daily_chart_counts = [];
$daily_chart_bg = [];

foreach($departments_list as $key => $info) {
    $daily_chart_labels[] = $info['label'];
    $daily_chart_counts[] = $daily_dept_views_data[$key];
    $daily_chart_bg[] = $info['color'];
}

// 5. Prepare Data for "Weekly Repository Utilization" (Bar Chart: Sorted by Views)
$dept_views_data = [];
foreach($departments_list as $key => $val) { $dept_views_data[$key] = 0; }
$dept_query = mysqli_query($conn, "SELECT department, SUM(views) as total_views FROM research_papers GROUP BY department");
if ($dept_query) {
    while($row = mysqli_fetch_assoc($dept_query)) {
        $dept_key = strtoupper($row['department']);
        if(isset($dept_views_data[$dept_key])) { $dept_views_data[$dept_key] = (int)$row['total_views']; }
    }
}
$sorted_views = $dept_views_data;
arsort($sorted_views); // Sort high to low

$dept_view_labels = [];
$dept_view_counts = [];
$dept_view_bg = [];

foreach($sorted_views as $key => $count) {
    $dept_view_labels[] = $departments_list[$key]['label'];
    $dept_view_counts[] = $count;
    $dept_view_bg[] = $departments_list[$key]['color'];
}

// --- NEW: Fetch Data for "Monthly Repository Utilization" ---
// Determine the latest year available in view_logs, otherwise use the current year.
$latest_year = date('Y');
$year_result = mysqli_query($conn, "SELECT YEAR(viewed_at) AS latest_year FROM view_logs ORDER BY viewed_at DESC LIMIT 1");
if ($year_result && $year_row = mysqli_fetch_assoc($year_result)) {
    $latest_year = $year_row['latest_year'] ?? $latest_year;
}

// Initialize arrays for all 12 months
$month_labels = [];
$month_counts = array_fill(0, 12, 0); // Create an array with 12 zeros

// Populate month labels for the entire year (Jan to Dec)
for ($m = 1; $m <= 12; $m++) {
    $month_labels[] = date('M Y', mktime(0, 0, 0, $m, 1, $latest_year));
}

// Now, fetch the actual monthly counts for that year from the database
$month_query = mysqli_query($conn, "
    SELECT
        MONTH(viewed_at) as month_number,
        COUNT(id) as count
    FROM
        view_logs
    WHERE
        YEAR(viewed_at) = '{$latest_year}'
    GROUP BY
        month_number
");

// Populate the counts array with data from the database, leaving 0 for months with no papers
if ($month_query) {
    while($row = mysqli_fetch_assoc($month_query)) {
        $month_index = $row['month_number'] - 1; // Adjust month (1-12) to array index (0-11)
        if ($month_index >= 0 && $month_index < 12) {
            $month_counts[$month_index] = (int)$row['count'];
        }
    }
}

// Set the active page for the sidebar
$active_page = 'reports';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Admin Dashboard</title>

    <!-- PWA Headers -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="img/cpsu_logo.png">
    <meta name="theme-color" content="#116913">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="reportsadmin.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .charts-grid {
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); /* Responsive grid */
        }
        .chart-box.full-width {
            grid-column: 1 / -1; /* Span full width */
        }
        .chart-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-top: -10px;
            margin-bottom: 15px;
        }
        .canvas-container {
            position: relative;
            height: 350px; /* Consistent height */
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        
        <?php include 'admin_sidebar.php'; ?>

        <main class="content">
            <div class="reports-container">
                <h2>Reports</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fa-solid fa-file-pdf card-icon"></i>
                        <h3>TOTAL PAPERS</h3>
                        <p class="stat-number"><?php echo $total_papers; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-user-graduate card-icon"></i>
                        <h3>TOTAL STUDENTS</h3>
                        <p class="stat-number"><?php echo $total_students; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fa-solid fa-eye card-icon"></i>
                        <h3>TOTAL VIEWS</h3>
                        <p class="stat-number"><?php echo $total_views; ?></p>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="chart-box">
                        <h3><i class="fa-solid fa-chart-pie"></i> Daily Repository Utilization</h3>
                        <div class="canvas-container">
                            <canvas id="dailyUtilizationChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-box">
                        <h3><i class="fa-solid fa-ranking-star"></i> Weekly Repository Utilization</h3>
                        <p class="chart-subtitle"></p>
                        <div class="canvas-container">
                            <canvas id="weeklyUtilizationChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-box full-width">
                        <h3><i class="fa-solid fa-chart-bar"></i> Monthly Repository Utilization</h3>
                        <div class="canvas-container">
                            <canvas id="monthlyUtilizationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // --- Chart Colors & General Options ---
        const logoColors = {
            green: '#004d00',
            gold: '#FFD700',
            lightGreen: '#006400',
            darkGold: '#B8860B',
            paleGreen: 'rgba(0, 77, 0, 0.1)',
            paleGold: 'rgba(255, 215, 0, 0.2)'
        };

        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            onClick: (event, elements, chart) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const label = chart.data.labels[index];
                    // Identify which chart was clicked by its canvas ID
                    const source = chart.canvas.id; 
                    const url = `admin_report_details.php?source=${source}&filter=${encodeURIComponent(label)}`;
                    window.open(url, '_blank');
                }
            },
            plugins: { 
                legend: { 
                    position: 'bottom',
                    labels: {
                        color: '#333',
                        font: {
                            weight: 'bold'
                        }
                    }
                } 
            }
        };

        // --- 1. Daily Utilization (Animated Spinning Pie Chart) ---
        const dailyCtx = document.getElementById('dailyUtilizationChart');
        if (dailyCtx) {
            new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($daily_chart_labels); ?>,
                    datasets: [{
                        label: 'Views Today',
                        data: <?php echo json_encode($daily_chart_counts); ?>,
                        backgroundColor: <?php echo json_encode($daily_chart_bg); ?>,
                        borderColor: '#fff',
                        borderWidth: 2,
                        borderRadius: 5
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1 // Ensure integer ticks for view counts
                            }
                        },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                }
            });
        }

        // --- 2. Weekly Utilization (Racing Bar Chart - Views) ---
        const weeklyCtx = document.getElementById('weeklyUtilizationChart');
        if (weeklyCtx) {
            new Chart(weeklyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($dept_view_labels); ?>,
                    datasets: [{
                        label: 'Total Views',
                        data: <?php echo json_encode($dept_view_counts); ?>,
                        backgroundColor: <?php echo json_encode($dept_view_bg); ?>,
                        borderColor: <?php echo json_encode($dept_view_bg); ?>,
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: { 
                    ...chartOptions, 
                    indexAxis: 'y', // Makes it a horizontal bar chart
                    scales: {
                        x: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#ddd' } },
                        y: { grid: { display: false } }
                    },
                    plugins: {
                        legend: {
                            display: false // Hide legend for single-dataset bar chart
                        }
                    }
                }
            });
        }

        // --- 3. Monthly Utilization (Bar Chart - Racing Months) ---
        const monthlyCtx = document.getElementById('monthlyUtilizationChart');
        if (monthlyCtx) {
            new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($month_labels); ?>,
                    datasets: [{
                        label: 'Monthly Activity',
                        data: <?php echo json_encode($month_counts); ?>,
                        backgroundColor: logoColors.green,
                        borderColor: logoColors.darkGold,
                        borderWidth: 2,
                        borderRadius: 5,
                        hoverBackgroundColor: logoColors.gold
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#ddd' } },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    </script>
    <script src="app.js"></script>
</body>
</html>