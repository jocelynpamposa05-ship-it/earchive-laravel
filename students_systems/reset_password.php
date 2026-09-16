<?php 
session_set_cookie_params(0, '/');
session_start(); 

// If user hasn't verified their code, redirect them
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['code_verified']) || $_SESSION['code_verified'] !== true) {
    header("Location: forgot_password.php?error=Please verify your code first.");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | CPSU E-Archive</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="main-content">
        <div class="login-card">
            <h2>Create New Password</h2>
            <h5>Enter and confirm your new password.</h5>

            <?php if(isset($_GET['error'])): ?>
                <div class="error-msg" style="background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <form action="update_password.php" method="POST">
                <div style="text-align: left; margin-top: 10px;">
                    <label style="font-size: 0.8rem; color: #ffffff; margin-left: 5px;">New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required minlength="8"
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px;">
                    <small style="color: #ddd; font-size: 0.7rem; display: block; margin-top: 2px;">Use 8+ chars, uppercase, lowercase & symbol.</small>
                </div>
                <div style="text-align: left; margin-top: 10px;">
                    <label style="font-size: 0.8rem; color: #ffffff; margin-left: 5px;">Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="8"
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px;">
                </div>

                <button type="submit" style="width: 100%; margin-top: 20px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; cursor: pointer;">Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>