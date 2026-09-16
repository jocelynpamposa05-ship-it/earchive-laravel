<?php 
session_set_cookie_params(0, '/');
session_start(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify ID | CPSU E-Archive</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="sidebar">
        <img src="../img/cpsu_logo.png" alt="CPSU LOGO" class="logo">
        <h2 style="margin: 0; font-size: 1.5rem;">CENTRAL PHILIPPINES STATE UNIVERSITY</h2>
        <p style="letter-spacing: 3px; font-weight: 300; margin-top: 10px;">VICTORIAS CAMPUS</p>
        <div class="yellow-badge">
            E-ARCHIVE: AN AI-INTEGRATED WEB-BASED ARCHIVING AND RETRIEVAL FOR RESEARCH PAPER
        </div>
    </div>

    <div class="main-content">
        <div class="login-card">
            <!-- Added CPSU Logo as requested -->
            <img src="../img/cpsu_logo.png" alt="CPSU Logo">
            <h2>Hi Cenphilians!</h2>
            <p>Enter your Student ID to verify.</p>

            <?php if(isset($_GET['error'])): ?>
                <div class="error-msg" style="background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <form action="verify_action.php" method="POST">
                <div style="text-align: left;">
                    <label style="font-size: 0.8rem; color: #ffffff; margin-left: 5px;">Student ID</label>
                    <input type="text" name="student_id" placeholder="Enter Student ID" required 
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px;">
                </div>

                <button type="submit" style="width: 100%; margin-top: 20px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; cursor: pointer;">VERIFY ID</button>
            </form>

            <p style="margin-top: 25px; font-size: 0.9rem;">Already have an account?</p>
            <button type="button" onclick="window.location.href='login.php'" style="width: 100%; margin-top: 10px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; cursor: pointer;">LOG IN</button>
        </div>
    </div>
</body>
</html>