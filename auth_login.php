<?php
// ชื่อไฟล์: auth_login.php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // 2. ตรวจสอบใน Database
    $stmt = $conn->prepare("SELECT user_id, password, full_name, role, school_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // ตรวจสอบ Hash Password
        if (password_verify($password, $row['password'])) {
            // Login สำเร็จ
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['school_id'] = $row['school_id'];
            
            // เพิ่ม: กำหนดเวลาเริ่มต้นสำหรับการทำ Auto Logout
            $_SESSION['last_activity'] = time();

            // บันทึก Log
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address) VALUES (?, ?)");
            $log_stmt->bind_param("is", $row['user_id'], $ip);
            $log_stmt->execute();

            // ระบบ Remember Me (Cookie 30 วัน)
            if ($remember) {
                setcookie("user_login", $username, time() + (86400 * 30), "/");
            } else {
                setcookie("user_login", "", time() - 3600, "/");
            }

            // Redirect ไปยัง Dashboard ตาม Role
            header("Location: dashboard_" . $row['role'] . ".php");
            exit;

        } else {
            $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
            header("Location: index.php");
        }
    } else {
        $_SESSION['error'] = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ";
        header("Location: index.php");
    }
} else {
    header("Location: index.php");
}
?>