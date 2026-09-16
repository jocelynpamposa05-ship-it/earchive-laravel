<?php
include('db_conn.php'); 

$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : '';
$source = isset($_GET['source']) ? mysqli_real_escape_string($conn, $_GET['source']) : '';

// Mapping for Department Labels to Database Values
$dept_map = [
    'College of Agriculture and Forestry' => 'AGRICULTURE',
    'College of Computer Studies' => 'COMPUTER STUDIES',
    'College of Hospitality Management' => 'HOSPITALITY MANAGEMENT',
    'College of Teacher Education' => 'TEACHER EDUCATION'
];

// 1. Define the Query based on what was clicked
if ($source == 'dailyUtilizationChart' || $source == 'weeklyUtilizationChart') {
    $short_dept = $dept_map[$filter] ?? $filter;
    
    $where_clause = "p.department = '$short_dept'";
    if ($source == 'dailyUtilizationChart') {
        $where_clause .= " AND DATE(v.viewed_at) = CURDATE()";
    }
} elseif ($source == 'monthlyUtilizationChart') {
    try {
        $date_parts = explode(' ', $filter);
        if (count($date_parts) == 2) {
            $month_name = $date_parts[0];
            $year = $date_parts[1];
            $month_num = date('m', strtotime($month_name));
            $where_clause = "MONTH(v.viewed_at) = '$month_num' AND YEAR(v.viewed_at) = '$year'";
        } else {
            $where_clause = "1=1";
        }
    } catch (Exception $e) {
        $where_clause = "1=1";
    }
} else {
    $where_clause = "1=1";
}

// Final Query Construction for all views
$query = "SELECT u.student_id, u.fullname, p.title, v.viewed_at 
          FROM view_logs v
          JOIN student_users u ON v.user_id = u.id
          JOIN research_papers p ON v.paper_id = p.id
          WHERE $where_clause
          ORDER BY v.viewed_at DESC";

$headers = ['Student ID', 'Full Name', 'Paper Title', 'Date Viewed'];

// 2. Handle Download (Excel-compatible)
if (isset($_GET['download'])) {
    // Use an Excel-friendly content type and .xls extension so Excel can open the CSV content
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=Report_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $filter) . '_' . date('Y-m-d') . '.xls');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Details - <?php echo htmlspecialchars($filter); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding: 30px; }
        .report-header { border-bottom: 2px solid #116913; margin-bottom: 20px; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container bg-white p-4 shadow-sm rounded">
        <div class="report-header d-flex justify-content-between align-items-center">
            <h2>Detailed Report: <span class="text-success"><?php echo htmlspecialchars($filter); ?></span></h2>
            <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>&download=1" class="btn btn-primary">
                <i class="fas fa-download"></i> Download Excel
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <th><?php echo $h; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = mysqli_query($conn, $query);
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            foreach ($row as $data) {
                                echo "<td>" . htmlspecialchars($data) . "</td>";
                            }
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='".count($headers)."' class='text-center'>No records found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <button class="btn btn-secondary" onclick="window.close()">Close Tab</button>
        </div>
    </div>
</body>
</html>