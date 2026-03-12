<?php
// ชื่อไฟล์: user_action.php
ob_start();
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

// 1. อัปเดตข้อมูลส่วนตัว
if ($action == 'update_profile') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, password=? WHERE user_id=?");
        $stmt->bind_param("sssi", $full_name, $username, $hashed, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, username=? WHERE user_id=?");
        $stmt->bind_param("ssi", $full_name, $username, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $_SESSION['msg'] = "อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
    }
    
    $redirect = ($_SESSION['role'] == 'teacher') ? 'dashboard_teacher.php?tab=profile' : 'dashboard_student.php?tab=profile';
    header("Location: " . $redirect);
    exit;
}

// 2. เปลี่ยนรูปโปรไฟล์
elseif ($action == 'update_profile_pic') {
    if (isset($_POST['preset_avatar'])) {
        $avatar = $_POST['preset_avatar'];
        $stmt = $conn->prepare("UPDATE users SET profile_img=? WHERE user_id=?");
        $stmt->bind_param("si", $avatar, $user_id);
        $stmt->execute();
        $_SESSION['msg'] = "เปลี่ยนรูปโปรไฟล์สำเร็จ";
    } 
    elseif (isset($_POST['frame_style'])) {
        $frame = $_POST['frame_style'];
        $stmt = $conn->prepare("UPDATE users SET profile_frame=? WHERE user_id=?");
        $stmt->bind_param("si", $frame, $user_id);
        $stmt->execute();
        $_SESSION['msg'] = "เปลี่ยนกรอบรูปสำเร็จ";
    }
    elseif (isset($_FILES['upload_avatar']) && $_FILES['upload_avatar']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['upload_avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_name = "avatar_" . $user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['upload_avatar']['tmp_name'], $upload_dir . $new_name)) {
                $stmt = $conn->prepare("UPDATE users SET profile_img=? WHERE user_id=?");
                $stmt->bind_param("si", $new_name, $user_id);
                $stmt->execute();
                $_SESSION['msg'] = "อัปโหลดรูปโปรไฟล์สำเร็จ";
            }
        } else {
            $_SESSION['error'] = "ไฟล์รูปภาพไม่ถูกต้อง";
        }
    }
    $redirect = ($_SESSION['role'] == 'teacher') ? 'dashboard_teacher.php?tab=profile' : 'dashboard_student.php?tab=profile';
    header("Location: " . $redirect);
    exit;
}

// 3. ตั้งค่าการแจ้งเตือน (Phase 3: LINE Notify Settings)
elseif ($action == 'update_settings') {
    $line_token = trim($_POST['line_token']);
    $is_active = isset($_POST['notify_active']) ? 1 : 0;

    $check = $conn->query("SELECT user_id FROM user_settings WHERE user_id=$user_id");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE user_settings SET line_token=?, notify_line=? WHERE user_id=?");
        $stmt->bind_param("sii", $line_token, $is_active, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO user_settings (user_id, line_token, notify_line) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $user_id, $line_token, $is_active);
    }
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "บันทึกการตั้งค่าการแจ้งเตือนเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกการตั้งค่า";
    }
    
    $redirect = ($_SESSION['role'] == 'teacher') ? 'dashboard_teacher.php?tab=profile' : 'dashboard_student.php?tab=profile';
    header("Location: " . $redirect);
    exit;
}
?>