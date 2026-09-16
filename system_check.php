<?php
// This is a diagnostic script to help developers check the system configuration.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f0f2f5; color: #333; margin: 0; padding: 30px; }
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #116913; border-bottom: 2px solid #ebe80f; padding-bottom: 10px; }
        h2 { color: #0d550f; margin-top: 30px; }
        ul, ol { padding-left: 20px; }
        li { margin-bottom: 10px; line-height: 1.5; }
        code { background-color: #eef; padding: 3px 6px; border-radius: 4px; font-family: "Courier New", Courier, monospace; }
        .status { padding: 8px 12px; border-radius: 5px; font-weight: bold; }
        .status.ok { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .solution { border: 1px solid #ffeeba; background: #fff3cd; padding: 20px; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fa-solid fa-heart-pulse"></i> System Health Check</h1>
        <p>This page checks for common configuration issues. All checks must pass for the system to be fully functional.</p>

        <h2>PHP Environment</h2>
        <ul>
            <li>PHP Version: <strong><?php echo phpversion(); ?></strong>
                <?php if (version_compare(phpversion(), '8.0', '>=')): ?>
                    <span class="status ok">OK</span>
                <?php else: ?>
                    <span class="status error">ERROR</span> - Version is too old. Please upgrade PHP to 8.0 or higher.
                <?php endif; ?>
            </li>
        </ul>

        <h2>Composer Dependencies</h2>
        <?php
        $autoloader_path = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoloader_path)) {
            echo '<ul><li><span class="status ok">OK</span> Composer autoloader found.</li>';
            require_once $autoloader_path;

            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                echo '<li><span class="status ok">OK</span> PHPMailer (Email Library) is installed.</li></ul>';
            } else {
                echo '<li><span class="status error">ERROR</span> PHPMailer class not found.</li></ul>';
                echo '<div class="solution">';
                echo '<h4><i class="fa-solid fa-wrench"></i> How to Fix:</h4>';
                echo '<p>The main application logic is working, but the required email library (PHPMailer) is missing from your project\'s dependencies. To fix this, you must run a command in your terminal.</p>';
                echo '<ol>';
                echo '<li>Open a command prompt (like CMD or PowerShell on Windows, or Terminal on Mac/Linux).</li>';
                echo '<li>Navigate to your project\'s root directory by running:<br><code>cd c:\xampp\htdocs\Earchive</code></li>';
                echo '<li>Once you are in that directory, run the Composer command to download the library:<br><code>composer require phpmailer/phpmailer</code></li>';
                echo '<li>After the command finishes, this error will be resolved. You can refresh this page to confirm.</li>';
                echo '</ol>';
                echo '</div>';
            }
        } else {
            echo '<ul><li><span class="status error">ERROR</span> Composer autoloader not found at <code>' . htmlspecialchars($autoloader_path) . '</code>.</li></ul>';
            echo '<div class="solution">';
            echo '<h4><i class="fa-solid fa-wrench"></i> How to Fix:</h4>';
            echo '<p>The `vendor` directory, which contains all third-party code, is missing or incomplete. This usually happens if you downloaded the source code as a ZIP file without running the installation step.</p>';
            echo '<ol>';
            echo '<li>If you don\'t have it, install Composer from <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a>.</li>';
            echo '<li>Open a command prompt (like CMD or PowerShell on Windows, or Terminal on Mac/Linux).</li>';
            echo '<li>Navigate to your project\'s root directory by running:<br><code>cd c:\xampp\htdocs\Earchive</code></li>';
            echo '<li>Once you are in that directory, run the Composer command to download all dependencies:<br><code>composer install</code></li>';
            echo '</ol>';
            echo '</div>';
        }
        ?>

        <h2>Network Access</h2>
        <p>To access this system from another device on the same network (e.g., another laptop or phone), use your computer's IP address.</p>
        <ul>
            <li><strong>Your Local IP Address:</strong> 
                <code><?php echo getHostByName(getHostName()); ?></code> 
                (If this says 127.0.0.1, check your Network Settings or run <code>ipconfig</code> in CMD)
            </li>
            <li><strong>Access URL for Students:</strong>
                <br>
                <a href="http://<?php echo getHostByName(getHostName()); ?>/Earchive/loginadmin.php">
                    http://<?php echo getHostByName(getHostName()); ?>/Earchive/loginadmin.php
                </a>
            </li>
        </ul>

        <br>
        <a href="students_systems/forgot_password.php" style="display: inline-block; margin-top: 20px; text-decoration: none; background: #116913; color: white; padding: 10px 20px; border-radius: 5px;">&larr; Back to Forgot Password</a>
    </div>
    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>