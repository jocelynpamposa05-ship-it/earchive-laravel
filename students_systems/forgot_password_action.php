<?php
// Load Composer's autoloader first.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_set_cookie_params(0, '/');
session_start();
include "../db_conn.php";
// Auto-create password_resets table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL,
    expires_at INT NOT NULL,
    KEY email (email)
)";
$conn->query($create_table_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // 1. Check if email exists in student_users table
    $stmt = $conn->prepare("SELECT id FROM student_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: forgot_password.php?error=No user found with that email address.&email=" . urlencode($email));
        exit();
    }
    $stmt->close();

    // 2. Generate a 6-digit code and expiry time
    $code = rand(100000, 999999);
    $expires = time() + (15 * 60); // Code expires in 15 minutes

    // 3. Store the code in the database (delete any old codes for this email first)
    $stmt_del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
    $stmt_del->bind_param("s", $email);
    $stmt_del->execute();
    $stmt_del->close();

    $stmt_ins = $conn->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
    $stmt_ins->bind_param("ssi", $email, $code, $expires);
    $stmt_ins->execute();
    $stmt_ins->close();

    // 4. Send the email with PHPMailer
    try {
        $mail = new PHPMailer(true);

        //Server settings - CONFIGURE THESE
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // Set the SMTP server to send through
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@example.com'; // SMTP username
        $mail->Password   = 'your_smtp_password'; // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        //Recipients
        $mail->setFrom('no-reply@cpsu-archive.com', 'CPSU E-Archive');
        $mail->addAddress($email);

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code';
        $mail->Body    = "Hi,<br><br>Your password reset code is: <b>$code</b><br>This code will expire in 15 minutes.<br><br>If you did not request a password reset, please ignore this email.<br><br>Thanks,<br>The CPSU E-Archive Team";
        $mail->AltBody = "Your password reset code is: $code. This code will expire in 15 minutes.";

        $mail->send();
        
        // 5. Redirect to the verification page
        $_SESSION['reset_email'] = $email;
        header("Location: verify_code.php?success=A verification code has been sent to your email.");
        exit();

    } catch (Throwable $e) { // Catch any error or exception
        $errorMessage = 'Message could not be sent. Please contact an admin.';
        // Check if the specific "class not found" error occurred, which indicates a setup problem.
        if (str_contains($e->getMessage(), 'Class "PHPMailer\\PHPMailer\\PHPMailer" not found')) {
            // Provide a more specific error for the admin/developer.
            header("Location: ../system_check.php");
            exit();
        }
        header("Location: forgot_password.php?error=" . urlencode($errorMessage) . "&email=" . urlencode($email));
        exit();
    }

} else {
    header("Location: forgot_password.php");
    exit();
}