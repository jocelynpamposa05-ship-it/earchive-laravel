<?php 
session_set_cookie_params(0, '/');
session_start(); 
if(!isset($_SESSION['temp_id'])) { 
    header("Location: verifyid.php"); 
    exit(); 
}

// Check for specific errors to highlight fields
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
$email_class = '';
$email_err_text = '';
$pass_class = '';
$pass_err_text = '';
$other_err_text = '';

if (!empty($error_msg)) {
    $errors = explode("\n", $error_msg); // Errors are separated by newline in register_action
    foreach($errors as $err) {
        if (stripos($err, 'email') !== false) {
            $email_class = 'error shake';
            $email_err_text = $err;
        } elseif (stripos($err, 'Password') !== false) {
            $pass_class = 'error shake';
            $pass_err_text = $err;
        } else {
            $other_err_text .= $err . "<br>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | CPSU E-Archive</title>
    <link rel="stylesheet" href="createaccount.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <img src="../img/LOGO.png" alt="CPSU Logo" class="logo">
        <div class="sidebar-text">
            <h2>CENTRAL PHILIPPINES STATE UNIVERSITY</h2>
            <p>VICTORIAS CAMPUS</p>
        </div>
        <div class="yellow-badge">
            E-ARCHIVE: AN AI-INTEGRATED WEB-BASED ARCHIVING AND RETRIEVAL FOR RESEARCH PAPER
        </div>
    </div>

    <div class="main-content">
        <div class="registration-card">
            <h2>Create Account</h2>
            <p>Registering for Student ID: <strong><?php echo $_SESSION['temp_id']; ?></strong></p>

            <?php if(!empty($other_err_text)): ?>
                <div class="error-msg">
                    <?php echo $other_err_text; ?>
                </div>
            <?php endif; ?>

            <form action="register_action.php" method="POST" novalidate>
                <div class="input-group">
                    <label>Fullname</label>
                    <input type="text" name="fullname" placeholder="Enter Fullname" required>
                </div>

                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Enter Nickname" required>
                </div>

                <div class="input-group">
                    <label>Department</label>
                    <div class="select-wrapper">
                        <select name="department" required>
                            <option value="" disabled selected>Select your Department</option>
                            <option value="AGRICULTURE">College of Agriculture and Forestry</option>
                            <option value="COMPUTER STUDIES">College of Computer Studies</option>
                            <option value="HOSPITALITY MANAGEMENT">College of Hospitality Management</option>
                            <option value="TEACHER EDUCATION">College of Teacher Education</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Enter Email" required class="<?php echo $email_class; ?>">
                    <?php if(!empty($email_err_text)): ?>
                        <small class="hint error-text" style="display:block;"><?php echo htmlspecialchars($email_err_text); ?></small>
                    <?php else: ?>
                        <small class="hint">Valid email required (e.g. name@domain.com).</small>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter Password" required class="<?php echo $pass_class; ?>">
                        <i class="fa-regular fa-eye" id="togglePassword" style="cursor: pointer;"></i>
                    </div>
                    <?php if(!empty($pass_err_text)): ?>
                        <small class="hint error-text" style="display:block;"><?php echo htmlspecialchars($pass_err_text); ?></small>
                    <?php else: ?>
                        <small class="hint">Must be 8+ chars with uppercase, lowercase & symbol.</small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-submit">Register</button>
            </form>

            <div class="login-link">
                <p>Already have an account?</p>
                <a href="login.php">Log In</a>
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>