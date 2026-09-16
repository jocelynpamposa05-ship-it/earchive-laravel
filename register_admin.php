<!DOCTYPE html>
<?php session_set_cookie_params(0, '/'); session_start(); ?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account | CPSU E-Archive</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div class="login-card">
        <img src="img/cpsu_logo.png" alt="Logo">
        <h2>Create Admin Account</h2>
        <h5>E-Archive: An AI-Integrated Archiving & Retrieval System for Research Paper</h5>

        <?php if (isset($_GET['error'])) { ?>
            <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php } ?>

        <form action="register_admin_action.php" method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="fullname" placeholder="Enter Full Name" required>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter Username" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter Email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <button type="submit" class="btn">REGISTER</button>
        </form>

        <div style="margin-top: 20px; text-align: center;">
            <p>Already have an account?</p>
            <button type="button" onclick="window.location.href='loginadmin.php'" class="btn" style="margin-top: 0; background-color: #116913;">Log In</button>
        </div>
    </div>

</body>
</html>