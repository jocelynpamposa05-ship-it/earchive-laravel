<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php"; // Use the central DB connection

// Security Guard: Prevents unauthorized access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginadmin.php?error=Unauthorized Access");
    exit();
}

// --- PERMANENT DELETE LOGIC ---
if(isset($_GET['perm_delete'])) {
    $id_to_delete = $_GET['perm_delete'];

    // 1. Get the file path before deleting the record
    $stmt = $conn->prepare("SELECT file_path FROM research_papers WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    $stmt->execute();
    $result = $stmt->get_result();
    $file_data = $result->fetch_assoc();
    $stmt->close();

    // 2. Delete the physical file from the 'uploads' folder if it exists
    if ($file_data && file_exists($file_data['file_path'])) {
        unlink($file_data['file_path']);
    }
    
    // 3. Delete the record from the database
    $stmt = $conn->prepare("DELETE FROM research_papers WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    $stmt->execute();
    $stmt->close();
    
    // --- 4. Delete from Algolia Index ---
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';

            $appId = 'WGL9LUXGKD'; // Replace with your App ID
            $adminApiKey = '7dea79ab13a168dbe7c73fa8d58e48b5'; // <--- Replace this with the key from Algolia Dashboard

            if ($appId !== 'YOUR_ALGOLIA_APP_ID' && class_exists('\Algolia\AlgoliaSearch\SearchClient')) {
                $client = \Algolia\AlgoliaSearch\SearchClient::create($appId, $adminApiKey);
                $index = $client->initIndex('research_papers');
                $index->deleteObject((string)$id_to_delete);
            }
        }
    } catch (Throwable $e) { /* Silently fail if Algolia sync fails */ }
    
    header("Location: collectionadmin.php?view=trash&msg=PermanentlyDeleted");
    exit();
}

// --- SOFT DELETE LOGIC (Move to Bin) ---
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("UPDATE research_papers SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: collectionadmin.php?msg=MovedToBin");
    exit();
}

// --- RESTORE LOGIC ---
if(isset($_GET['restore'])) {
    $id = $_GET['restore'];
    $stmt = $conn->prepare("UPDATE research_papers SET is_deleted = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: collectionadmin.php?view=trash&msg=Restored");
    exit();
}

// --- AUTO-FIX: Ensure 'is_deleted' column exists (To hide old trash items) ---
$check_col = $conn->query("SHOW COLUMNS FROM research_papers LIKE 'is_deleted'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE research_papers ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
}

// --- Get department filter ---
$dept_filter = $_GET['dept'] ?? '';

// --- Get View Mode ---
$view_trash = (isset($_GET['view']) && $_GET['view'] == 'trash');
$is_deleted_status = $view_trash ? 1 : 0;

// --- FETCH PAPERS with filtering ---
$query = "SELECT * FROM research_papers WHERE is_deleted = $is_deleted_status";
$params = [];
$types = "";

if (!empty($dept_filter)) {
    $query .= " AND department = ?";
    $params[] = $dept_filter;
    $types .= "s";
}
$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

// Set the active page for the sidebar
$active_page = 'collection';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Collection | Admin</title>

    <!-- PWA Headers -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="img/cpsu_logo.png">
    <meta name="theme-color" content="#004d00">

    <link rel="stylesheet" href="collectionadmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        table td strong {
            font-family: 'Inter', sans-serif !important;
            font-size: 0.85rem;
        }
        /* Remove shadows */
        .collection-container, .table-wrapper {
            box-shadow: none !important;
        }
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        /* Standardized Action Buttons */
        .action-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 35px !important;
            height: 35px !important;
            padding: 0 !important;
            border-radius: 5px;
            font-size: 1rem !important;
            margin: 0 2px;
            text-decoration: none;
            transition: transform 0.2s;
        }
        .action-btn:hover {
            transform: scale(1.1);
        }
        /* Custom Delete Modal */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .custom-modal-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            width: 90%;
            max-width: 350px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .custom-modal-box p { font-size: 1.1rem; color: #333; margin-bottom: 25px; font-weight: 500; }
        .confirm-buttons { display: flex; justify-content: center; gap: 15px; }
        .btn-confirm-yes {
            background-color: #dc3545; color: white; border: none; padding: 10px 30px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block;
        }
        .btn-confirm-yes:hover { background-color: #c82333; }
        .btn-confirm-no {
            background-color: #116913; color: white; border: none; padding: 10px 30px; border-radius: 8px; font-weight: bold; cursor: pointer;
        }
        .btn-confirm-no:hover { background-color: #0d5010; }

        .recycle-bin-btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: 0.3s;
        }
        .recycle-bin-btn.trash { background: #dc3545; color: white; }
        .recycle-bin-btn.active { background: #116913; color: white; }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        
        <?php include 'admin_sidebar.php'; ?>

        <main class="content">
            <div class="collection-container">
                <div class="collection-header">
                    <h2><?php echo $view_trash ? 'Recycle Bin - Collections' : 'Manage Collection'; ?></h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                    <div class="dropdown">
                        <button type="button" class="dropbtn" id="deptBtn">
                            <?php 
                                $filter_display = "Filter by Department";
                                if ($dept_filter == 'AGRICULTURE') $filter_display = 'College of Agriculture and Forestry';
                                if ($dept_filter == 'COMPUTER STUDIES') $filter_display = 'College of Computer Studies';
                                if ($dept_filter == 'HOSPITALITY MANAGEMENT') $filter_display = 'College of Hospitality Management';
                                if ($dept_filter == 'TEACHER EDUCATION') $filter_display = 'College of Teacher Education';
                                echo $filter_display;
                            ?> 
                            <i class="fa fa-caret-down"></i>
                        </button>
                        <div class="dropdown-content" id="deptMenu">
                            <a href="collectionadmin.php">All Departments</a>
                            <a href="collectionadmin.php?dept=AGRICULTURE">College of Agriculture and Forestry</a>
                            <a href="collectionadmin.php?dept=COMPUTER STUDIES">College of Computer Studies</a>
                            <a href="collectionadmin.php?dept=HOSPITALITY MANAGEMENT">College of Hospitality Management</a>
                            <a href="collectionadmin.php?dept=TEACHER EDUCATION">College of Teacher Education</a>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Notification Messages -->
                <?php if(isset($_GET['msg'])): ?>
                    <div style="padding: 15px; margin-bottom: 20px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; font-weight: 500;
                        <?php echo (in_array($_GET['msg'], ['PermanentlyDeleted', 'MovedToBin', 'Restored'])) ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;'; ?>">
                        
                        <span>
                            <?php 
                            if($_GET['msg'] == 'PermanentlyDeleted') echo '<i class="fa-solid fa-check-circle"></i> Item permanently removed.';
                            if($_GET['msg'] == 'MovedToBin') echo '<i class="fa-solid fa-trash"></i> Item moved to Recycle Bin.';
                            if($_GET['msg'] == 'Restored') echo '<i class="fa-solid fa-undo"></i> Item restored to collection.';
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Department</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Uploaded By</th>
                                <th style="text-align: center;">No. of Views</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($result && mysqli_num_rows($result) > 0) {
                                while($row = mysqli_fetch_assoc($result)): 
                            ?>
                            <?php 
                                $display_dept = htmlspecialchars($row['department']);
                                switch ($row['department']) {
                                    case 'AGRICULTURE':
                                        $display_dept = 'College of Agriculture and Forestry';
                                        break;
                                    case 'COMPUTER STUDIES':
                                        $display_dept = 'College of Computer Studies';
                                        break;
                                    case 'HOSPITALITY MANAGEMENT':
                                        $display_dept = 'College of Hospitality Management';
                                        break;
                                    case 'TEACHER EDUCATION':
                                        $display_dept = 'College of Teacher Education';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(ucwords(strtolower($row['title']))); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['author']); ?></td>
                                <td><?php echo $display_dept; ?></td>
                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                <td><?php echo date("M Y", strtotime($row['date_published'])); ?></td>
                                <td><?php echo htmlspecialchars($row['uploaded_by']); ?></td>
                                <td style="text-align: center; white-space: nowrap;"><?php echo htmlspecialchars($row['views'] ?? 0); ?></td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?php if ($view_trash): ?>
                                        <a href="collectionadmin.php?restore=<?php echo $row['id']; ?>" class="action-btn" style="background-color: #28a745; color: white; width: 30px; height: 30px;" title="Restore Paper">
                                            <i class="fa-solid fa-undo"></i>
                                        </a>
                                        <a href="#"
                                           onclick="openDeleteModal('collectionadmin.php?perm_delete=<?php echo $row['id']; ?>', true); return false;" 
                                           class="delete-btn action-btn" style="background-color: #dc3545; color: white; width: 30px; height: 30px;" title="Delete Permanently">
                                            <i class="fa-solid fa-times"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="editpaper.php?id=<?php echo $row['id']; ?>" class="action-btn" style="background-color: #ebe80f; color: #116913; width: 30px; height: 30px;" title="Edit Details">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="#"
                                           onclick="openDeleteModal('collectionadmin.php?delete=<?php echo $row['id']; ?>', false); return false;" 
                                           class="delete-btn action-btn" style="background-color: #dc3545; color: white; width: 30px; height: 30px;" title="Move to Bin"><i class="fa-solid fa-trash"></i></a>
                                        <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank" class="action-btn" style="background-color: #004d00; color: white; width: 30px; height: 30px;" title="View PDF">
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            } else {
                                echo "<tr><td colspan='8' style='text-align:center; padding: 20px;'>No papers found for the selected filter.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Custom Delete Modal -->
    <div id="deleteModal" class="custom-modal-overlay">
        <div class="custom-modal-box">
            <p id="deleteModalText">Are you sure you want to delete this?</p>
            <div class="confirm-buttons">
                <a href="#" id="confirmDeleteBtn" class="btn-confirm-yes">Yes</a>
                <button onclick="closeDeleteModal()" class="btn-confirm-no">No</button>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal(url, isPermanent) { 
            document.getElementById('confirmDeleteBtn').href = url; 
            document.getElementById('deleteModalText').innerHTML = isPermanent ? "Are you sure? <br><small style='color:red'>This will permanently remove the file and database record.</small>" : "Move this item to the Recycle Bin?";
            document.getElementById('deleteModal').style.display = 'flex'; 
        }
        function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    </script>

    <script>
        // --- Dropdown Menu Logic ---
        const deptBtn = document.getElementById('deptBtn');
        const deptMenu = document.getElementById('deptMenu');

        if (deptBtn && deptMenu) {
            deptBtn.onclick = function(e) {
                e.stopPropagation();
                deptMenu.classList.toggle('show');
            };
            window.onclick = function(e) {
                if (!e.target.matches('.dropbtn')) {
                    if (deptMenu.classList.contains('show')) { deptMenu.classList.remove('show'); }
                }
            };
        }
    </script>
    <script src="app.js"></script>
</body>
</html>