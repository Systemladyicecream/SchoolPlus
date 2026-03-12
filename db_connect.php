<?php
// ชื่อไฟล์: db_connect.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "smart_school_plus";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่า Timezone ไทย
date_default_timezone_set('Asia/Bangkok');
?>