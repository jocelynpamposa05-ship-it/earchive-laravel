<!DOCTYPE html>
<?php session_set_cookie_params(0, '/'); session_start(); ?>
<?php $loginError = isset($_GET['error']); ?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CPSU E-Archive</title>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="img/cpsu_logo.png">
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div class="login-card">
        <img src="img/cpsu_logo.png" alt="Logo">
        <h2>CPSU VICTORIAS LIBRARY</h2>
        <h5>E-Archive: An AI-Integrated Archiving & Retrieval System 
            for Research Paper</h5>

        <?php if (isset($_GET['error'])) { ?>
            <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php } ?>
        <?php if (isset($_GET['success'])) { ?>
            <p class="error" style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <?php } ?>

        <!-- Ensure this points to loginadmin_action.php -->
        <form action="loginadmin_action.php" method="POST" novalidate>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter Username" required class="<?php echo $loginError ? 'invalid-input invalid-shake' : ''; ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter Password" required class="<?php echo $loginError ? 'invalid-input invalid-shake' : ''; ?>">
            </div>
            <button type="submit" class="btn">LOG IN</button>
           <br> <p style="margin-bottom: 10px;"> or </p>
            <button type="button" onclick="window.location.href='register_admin.php'" class="btn" style="margin-top: 0;">CREATE ACCOUNT</button>
        </form>
    </div>

    <script src="app.js"></script>
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

            const serverError = document.querySelector('.error');
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