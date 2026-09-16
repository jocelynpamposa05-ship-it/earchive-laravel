<?php
$sname = "127.0.0.1"; // Changed from localhost to 127.0.0.1 to fix connection error
$uname = "root";
$password = "";
$db_name = "cpsu_archive_db";

try {
    $conn = mysqli_connect($sname, $uname, $password, $db_name);
} catch (mysqli_sql_exception $e) {
    die("Connection failed: " . $e->getMessage());
}

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
