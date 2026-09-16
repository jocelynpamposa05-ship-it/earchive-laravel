<?php
session_start();
session_unset();
session_destroy();

// Clear the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, '/', $params["domain"], $params["secure"], $params["httponly"]);
}

header("Location: login.php?success=You have been logged out.");
exit();
?>