<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php";

// Security Check: Ensure only admin can access
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// Fetch User Details
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM student_users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: usersadmin.php");
        exit();
    }
} else {
    header("Location: usersadmin.php");
    exit();
}

// Handle Update Form Submission
if (isset($_POST['update_btn'])) {
    $id = $_POST['user_id'];
    $student_id = $_POST['student_id'];
    $fullname = $_POST['fullname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $department = $_POST['department'];

    // Update Database
    $stmt = $conn->prepare("UPDATE student_users SET student_id=?, fullname=?, username=?, email=?, department=? WHERE id=?");
    $stmt->bind_param("sssssi", $student_id, $fullname, $username, $email, $department, $id);

    if ($stmt->execute()) {
        $_SESSION['update_success'] = true;
        header("Location: " . $_SERVER['REQUEST_URI']); // Redirect to self to show modal
        exit();
    } else {
        echo "<script>alert('Database Error: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Set active page for sidebar
$active_page = 'users';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Admin</title>
    <!-- Reuse uploadpaper styles for the form layout -->
    <link rel="stylesheet" href="uploadpaper.css">
    <link rel="stylesheet" href="edituser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- New Success Modal --- */
        .success-modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            align-items: center; justify-content: center; z-index: 10000;
        }
        .success-modal-box {
            background: #fff; padding: 30px; border-radius: 20px; text-align: center;
            width: 90%; max-width: 380px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            animation: zoomInSuccess 0.3s ease-out;
        }
        @keyframes zoomInSuccess { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .success-badge {
            width: 80px; height: 80px; background: #116913; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 20px; position: relative;
        }
        .success-badge i { font-size: 2.5rem; color: white; }
        /* Sparkle Accents */
        .success-badge::before, .success-badge::after {
            content: '✨'; position: absolute; font-size: 1.5rem;
            animation: sparkle 1.5s infinite;
        }
        .success-badge::before { top: -10px; left: -10px; animation-delay: 0s; }
        .success-badge::after { bottom: -5px; right: -15px; animation-delay: 0.5s; }
        @keyframes sparkle { 0%, 100% { transform: scale(0.8); opacity: 0.5; } 50% { transform: scale(1.2); opacity: 1; } }

        #successModalTitle {
            font-size: 1.5rem; font-weight: bold; color: #333; margin-bottom: 10px;
        }
        #successModalMessage {
            font-size: 0.95rem; color: #666666; margin-bottom: 30px;
        }
        #successModalCloseBtn {
            background: #116913; color: white; border: none; padding: 12px 30px;
            border-radius: 10px; font-size: 1rem; cursor: pointer;
            transition: background-color 0.2s ease; font-weight: bold;
        }
        #successModalCloseBtn:hover { background-color: #004d00; }

        /* Responsive Form Rows */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        <?php include 'admin_sidebar.php'; ?>

        <main class="content">
            <div class="form-container">
                <h2><i class="fa-solid fa-user-pen"></i> Edit Student User</h2>
                
                <form action="edituser.php?id=<?php echo $user['id']; ?>" method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    
                    <div class="form-group full">
                        <label>Student ID</label>
                        <input type="text" name="student_id" value="<?php echo htmlspecialchars($user['student_id']); ?>" required>
                    </div>

                    <div class="form-group full">
                        <label>Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group full">
                        <label>Department</label>
                        <div class="select-wrapper">
                            <select name="department" required>
                                <option value="AGRICULTURE" <?php if($user['department'] == 'AGRICULTURE') echo 'selected'; ?>>College of Agriculture and Forestry</option>
                                <option value="COMPUTER STUDIES" <?php if($user['department'] == 'COMPUTER STUDIES') echo 'selected'; ?>>College of Computer Studies</option>
                                <option value="HOSPITALITY MANAGEMENT" <?php if($user['department'] == 'HOSPITALITY MANAGEMENT') echo 'selected'; ?>>College of Hospitality Management</option>
                                <option value="TEACHER EDUCATION" <?php if($user['department'] == 'TEACHER EDUCATION') echo 'selected'; ?>>College of Teacher Education</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="update_btn" class="upload-btn" style="background-color: #116913; color: #ffffff;">
                        <i class="fa-solid fa-save"></i> SAVE CHANGES
                    </button>
                    <a href="usersadmin.php" style="display:block; text-align:center; margin-top:15px; color:#555; text-decoration:none;">Cancel</a>
                </form>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['update_success']) && $_SESSION['update_success']): ?>
                const successModal = document.createElement('div');
                successModal.className = 'success-modal-overlay';
                successModal.style.display = 'flex';
                successModal.innerHTML = `<div class="success-modal-box"><div class="success-badge"><i class="fa-solid fa-check"></i></div><h3 id="successModalTitle">Successfully Updated!</h3><p id="successModalMessage">The user account has been saved.</p><button id="successModalCloseBtn">OK</button></div>`;
                document.body.appendChild(successModal);
                
                // Redirect to the user list page when the modal is closed
                successModal.querySelector('#successModalCloseBtn').onclick = function() { window.location.href = 'usersadmin.php'; };
                <?php unset($_SESSION['update_success']); ?>
            <?php endif; ?>
        });
    </script>
    <script src="app.js"></script>
</body>
</html>