<?php 
session_set_cookie_params(0, '/');
session_start(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPSU E-Archive | Login</title>
    <link rel="stylesheet" href="login.css">
    <style>
        .login-card button {
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .login-card button:hover {
            background-color: #0d5010 !important; /* Override inline style */
            transform: translateY(-2px);
        }
    </style>
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
        <img src="../img/cpsu_logo.png" alt="CPSU Logo">
            <h2>Welcome Cenphilians!</h2>
            <h5>E-Archive: An AI-Integrated Web-Based Archiving 
                and Retrieval for Research Papers</h5>

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

            <?php $loginError = isset($_GET['error']); ?>
            <form action="login_action.php" method="POST" novalidate>
                <div style="text-align: left;">
                    <label style="font-size: 0.8rem; color: #ffffff; margin-left: 5px;">Username</label>
                    <input type="text" name="username" placeholder="Username" required 
                           class="<?php echo $loginError ? 'invalid-input invalid-shake' : ''; ?>"
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px;">
                </div>

                <div style="text-align: left; margin-top: 10px;">
                    <label style="font-size: 0.8rem; color: #ffffff; margin-left: 5px;">Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required 
                           class="<?php echo $loginError ? 'invalid-input invalid-shake' : ''; ?>"
                           style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px;">
                </div>

                <div style="text-align: right; margin-top: 10px;">
                    <a href="forgot_password.php" style="color: #ffffff; font-size: 0.8rem; text-decoration: none;">Forgot Password?</a>
                </div>

                <button type="submit" style="width: 100%; margin-top: 20px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px;">LOG IN</button>
            </form>

            <p style="margin-top: 25px; font-size: 0.9rem;">Don't have an account?</p>
            <button type="button" onclick="window.location.href='verifyid.php'" style="width: 100%; margin-top: 10px; padding: 10px; background: #004d00; color: white; border: none; border-radius: 5px; ">REGISTER</button>
        </div>
    </div>
    <script src="../app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (!form) return;
            form.noValidate = true;
            const fields = Array.from(form.querySelectorAll('input[type="text"], input[type="password"]'));

            function removeFieldError(field) {
                if (!field) return;
                field.classList.remove('invalid-input', 'invalid-shake');
                const prev = field.parentNode.querySelector('.invalid-message');
                if (prev) prev.remove();
            }

            function showFieldError(field, message) {
                if (!field) return;
                removeFieldError(field);
                field.classList.add('invalid-input', 'invalid-shake');
                const errorMessage = document.createElement('span');
                errorMessage.className = 'invalid-message';
                errorMessage.textContent = message;
                field.parentNode.insertBefore(errorMessage, field.nextSibling);
                field.addEventListener('animationend', function() {
                    field.classList.remove('invalid-shake');
                }, { once: true });
            }

            fields.forEach(field => {
                field.addEventListener('input', () => removeFieldError(field));
                field.addEventListener('change', () => removeFieldError(field));
            });

            const serverError = document.querySelector('.error-msg');
            if (serverError) {
                fields.forEach(field => {
                    showFieldError(field, '*Incorrect username or password.');
                });
            }

            form.addEventListener('submit', function(event) {
                let isValid = true;
                fields.forEach(field => {
                    if (!field.value || !field.value.trim()) {
                        isValid = false;
                        showFieldError(field, '*Please input this field.');
                    }
                });
                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                    const firstInvalid = form.querySelector('.invalid-input');
                    if (firstInvalid && typeof firstInvalid.focus === 'function') {
                        firstInvalid.focus();
                    }
                }
            });
        });
    </script>
</body>
</html>