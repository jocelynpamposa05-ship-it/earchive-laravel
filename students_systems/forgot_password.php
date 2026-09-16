<?php 
session_set_cookie_params(0, '/');
session_start(); 
$email_value = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | CPSU E-Archive</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="main-content">
        <div class="login-card">
            <h2>Forgot Password</h2>
            <p>Enter your email to receive a verification code.</p>

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

            <form action="forgot_password_action.php" method="POST">
                <div style="text-align: left;">
                    <label style="font-size: 0.8rem; color: #ffffff; margin-left: 5px;">Email Address</label>
                    <input type="email" name="email" placeholder="Enter your registered email" required value="<?php echo $email_value; ?>"
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px;">
                </div>

                <button type="submit" style="width: 100%; margin-top: 20px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; cursor: pointer;">Send Code</button>
            </form>

            <p style="margin-top: 25px; font-size: 0.9rem;">Remember your password?</p>
            <button type="button" onclick="window.location.href='login.php'" style="width: 100%; margin-top: 10px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; cursor: pointer;">Back to Log In</button>
        </div>
    </div>
</body>
</html>