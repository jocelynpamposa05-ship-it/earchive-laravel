<?php
session_set_cookie_params(0, '/');
session_start();
include "db_conn.php";

// Security Guard: Only Admins allowed
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    header("Location: loginadmin.php");
    exit();
}

// Fetch all admin accounts
$result = $conn->query("SELECT id, fullname, username, email FROM admins ORDER BY id DESC");

$active_page = 'staff_accounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management | Admin</title>
    <link rel="stylesheet" href="collectionadmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-layout">
        <?php include 'admin_sidebar.php'; ?>
        <main class="content">
            <div class="collection-container">
                <div class="collection-header">
                    <h2>Account Management</h2>
                    <a href="register_admin.php" class="view-btn" style="font-size: 0.9rem; padding: 8px 14px; height: auto; width: auto; background: #116913; color: white; border-radius: 8px;">+ Add Staff</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['fullname'] ?: $row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td>
                                            <a href="register_admin.php?edit=<?php echo $row['id']; ?>" class="view-btn" title="Edit Account"><i class="fa-solid fa-pen-to-square"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:20px;">No staff accounts found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script src="app.js"></script>
</body>
</html>
