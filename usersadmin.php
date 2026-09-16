<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php";

// Security Check: Ensure only admin can access
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// --- PERMANENT DELETE LOGIC ---
if (isset($_GET['perm_delete'])) {
    $id = $_GET['perm_delete'];
    $stmt = $conn->prepare("DELETE FROM student_users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: usersadmin.php?view=trash&msg=UserPermanentlyDeleted");
        exit();
    } else {
        echo "<script>alert('Error deleting user. Details: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// --- SOFT DELETE LOGIC (Move to Bin) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("UPDATE student_users SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: usersadmin.php?msg=UserMovedToBin&undo_id=$id");
    exit();
}

// --- RESTORE LOGIC ---
if (isset($_GET['restore'])) {
    $id = $_GET['restore'];
    $stmt = $conn->prepare("UPDATE student_users SET is_deleted = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: usersadmin.php?msg=UserRestored");
    exit();
}

// --- AUTO-FIX: Ensure 'is_deleted' column exists ---
$check_col = $conn->query("SHOW COLUMNS FROM student_users LIKE 'is_deleted'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE student_users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
}

// --- Get department filter ---
$dept_filter = $_GET['dept'] ?? '';

// --- Get View Mode ---
$view_trash = (isset($_GET['view']) && $_GET['view'] == 'trash');
$is_deleted_status = $view_trash ? 1 : 0;

// --- FETCH STUDENTS with filtering ---
$query = "SELECT * FROM student_users WHERE is_deleted = $is_deleted_status";
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
$active_page = 'users';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Admin</title>
    <!-- We reuse collectionadmin.css because it has the perfect table styling -->

    <!-- PWA Headers -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="img/cpsu_logo.png">
    <meta name="theme-color" content="#116913">

    <link rel="stylesheet" href="collectionadmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Remove shadows */
        .collection-container, .table-wrapper {
            box-shadow: none !important;
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
                    <h2><?php echo $view_trash ? 'Recycle Bin - Users' : 'Manage Student Accounts'; ?></h2>
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
                            <a href="usersadmin.php">All Departments</a>
                            <a href="usersadmin.php?dept=AGRICULTURE">College of Agriculture and Forestry</a>
                            <a href="usersadmin.php?dept=COMPUTER STUDIES">College of Computer Studies</a>
                            <a href="usersadmin.php?dept=HOSPITALITY MANAGEMENT">College of Hospitality Management</a>
                            <a href="usersadmin.php?dept=TEACHER EDUCATION">College of Teacher Education</a>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Notification Messages -->
                <?php if(isset($_GET['msg'])): ?>
                    <div style="padding: 15px; margin-bottom: 20px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; font-weight: 500;
                        <?php echo (in_array($_GET['msg'], ['UserPermanentlyDeleted', 'UserMovedToBin', 'UserRestored'])) ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;'; ?>">
                        
                        <span>
                            <?php 
                            if($_GET['msg'] == 'UserPermanentlyDeleted') echo '<i class="fa-solid fa-check-circle"></i> User account permanently deleted.';
                            if($_GET['msg'] == 'UserMovedToBin') {
                                echo '<i class="fa-solid fa-trash"></i> User moved to bin. ';
                                if(isset($_GET['undo_id'])) {
                                    echo '<a href="usersadmin.php?restore='.htmlspecialchars($_GET['undo_id']).'" style="color: #856404; font-weight: bold; text-decoration: underline; margin-left: 10px;">Undo?</a>';
                                }
                            }
                            if($_GET['msg'] == 'UserRestored') echo '<i class="fa-solid fa-undo"></i> User account restored.';
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($result) > 0) {
                                while($row = mysqli_fetch_assoc($result)): ?>
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
                                    <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo $display_dept; ?></td>
                                    <td style="text-align: center;">
                                        <?php if ($view_trash): ?>
                                            <a href="usersadmin.php?restore=<?php echo $row['id']; ?>" class="action-btn" style="background-color: #28a745; color: white;" title="Restore">
                                                <i class="fa-solid fa-undo"></i>
                                            </a>
                                            <a href="#"
                                               onclick="openDeleteModal('usersadmin.php?perm_delete=<?php echo $row['id']; ?>', true); return false;" 
                                               class="delete-btn action-btn" style="background-color: #dc3545; color: white;" title="Delete Permanently">
                                                <i class="fa-solid fa-times"></i>
                                            </a>
                                        <?php else: ?>
                                        <a href="edituser.php?id=<?php echo $row['id']; ?>" class="action-btn" style="background-color: #ebe80f; color: #004d00;" title="Edit User">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="#"
                                           onclick="openDeleteModal('usersadmin.php?delete=<?php echo $row['id']; ?>', false); return false;" 
                                           class="delete-btn action-btn" style="background-color: #dc3545; color: white;" title="Move to Bin"><i class="fa-solid fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; 
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>No registered students found.</td></tr>";
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
            document.getElementById('deleteModalText').innerHTML = isPermanent ? "Permanently delete this user? <br><span style='font-size: 0.8rem; color: #cc0000;'>This action cannot be undone.</span>" : "Move this user account to the Recycle Bin?";
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