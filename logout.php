<?php
// ชื่อไฟล์: logout.php
session_start();
session_destroy();
setcookie("user_login", "", time() - 3600, "/"); // ลบ Cookie

// ตรวจสอบว่า Logout เพราะหมดเวลาหรือไม่
$url = "index.php";
if(isset($_GET['timeout'])) {
    $url .= "?timeout=1";
}

header("Location: " . $url);
exit;
?>