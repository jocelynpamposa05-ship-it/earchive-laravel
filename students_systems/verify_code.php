<?php 
session_set_cookie_params(0, '/');
session_start(); 

// If user hasn't been to forgot_password.php, redirect them
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code | CPSU E-Archive</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="main-content">
        <div class="login-card">
            <h2>Enter Verification Code</h2>
            <p>A 6-digit code was sent to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>.</p>

            <?php if(isset($_GET['error'])): ?>
                <div class="error-msg" style="background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="success-msg" style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center;">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <form action="verify_code_action.php" method="POST">
                <div style="text-align: left;">
                    <label style="font-size: 0.8rem; color: #ffffff; margin-left: 5px;">Verification Code</label>
                    <input type="text" name="code" placeholder="Enter 6-digit code" required maxlength="6"
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; text-align: center; font-size: 1.2rem; letter-spacing: 5px;">
                </div>

                <button type="submit" style="width: 100%; margin-top: 20px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; cursor: pointer;">Verify</button>
            </form>

            <p style="margin-top: 25px; font-size: 0.9rem;">Didn't receive a code?</p>
            <button type="button" onclick="window.location.href='forgot_password.php'" style="width: 100%; margin-top: 10px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; cursor: pointer;">Resend Code</button>
        </div>
    </div>
</body>
</html>