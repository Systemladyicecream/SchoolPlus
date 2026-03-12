<?php
// ชื่อไฟล์: superadmin_action.php
ob_start();
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ (Security Check)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    die(json_encode(['error' => 'Access Denied']));
}

$user_id = $_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// ========================================================
// SECTION: DATA FETCHING (ส่ง JSON กลับไปให้ JS)
// ========================================================
if (in_array($action, ['get_school_data', 'get_user_data'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($action == 'get_school_data') {
        $res = $conn->query("SELECT * FROM schools WHERE school_id = $id");
        echo json_encode($res->fetch_assoc());
    } elseif ($action == 'get_user_data') {
        $res = $conn->query("SELECT * FROM users WHERE user_id = $id");
        echo json_encode($res->fetch_assoc());
    }
    exit;
}

// ========================================================
// SECTION: ACTION HANDLERS
// ========================================================

// 1. จัดการโรงเรียน (เพิ่ม/ลบ/แก้ไข)
if ($action == 'add_school') {
    $name = $_POST['school_name'];
    $level = $_POST['education_level'];
    
    $stmt = $conn->prepare("INSERT INTO schools (school_name, education_level) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $level);
    
    if($stmt->execute()){
        $_SESSION['msg'] = "เพิ่มโรงเรียนเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
    header("Location: dashboard_superadmin.php?tab=schools");
}
elseif ($action == 'update_school') {
    $id = $_POST['school_id'];
    $name = $_POST['school_name'];
    $level = $_POST['education_level'];
    
    $stmt = $conn->prepare("UPDATE schools SET school_name=?, education_level=? WHERE school_id=?");
    $stmt->bind_param("ssi", $name, $level, $id);
    
    if($stmt->execute()){
        $_SESSION['msg'] = "แก้ไขข้อมูลโรงเรียนเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
    header("Location: dashboard_superadmin.php?tab=schools");
}
elseif ($action == 'delete_school') {
    $id = $_GET['id'];
    // ลบโรงเรียนและ User ที่สังกัด
    $conn->query("DELETE FROM schools WHERE school_id = $id");
    $conn->query("DELETE FROM users WHERE school_id = $id");
    
    $_SESSION['msg'] = "ลบข้อมูลเรียบร้อยแล้ว";
    header("Location: dashboard_superadmin.php?tab=schools");
}

// 2. จัดการ Admin โรงเรียน
elseif ($action == 'add_admin') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $fullname = $_POST['full_name'];
    $school_id = $_POST['school_id'];
    
    $check = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
    if($check->num_rows > 0){
        $_SESSION['error'] = "Username นี้มีอยู่แล้ว";
        header("Location: dashboard_superadmin.php?tab=admins");
        exit;
    }

    $pass_hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'admin';
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, school_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $pass_hash, $fullname, $role, $school_id);
    
    if($stmt->execute()){
        $_SESSION['msg'] = "เพิ่ม Admin เรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }
    header("Location: dashboard_superadmin.php?tab=admins");
}
elseif ($action == 'update_admin') {
    $id = $_POST['user_id'];
    $username = $_POST['username'];
    $fullname = $_POST['full_name'];
    $school_id = $_POST['school_id'];
    
    $sql = "UPDATE users SET username=?, full_name=?, school_id=?";
    $types = "ssi";
    $params = [$username, $fullname, $school_id];

    if(!empty($_POST['password'])){
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password=?";
        $types .= "s";
        $params[] = $password;
    }

    $sql .= " WHERE user_id=?";
    $types .= "i";
    $params[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if($stmt->execute()){
        $_SESSION['msg'] = "แก้ไขข้อมูล Admin เรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }
    header("Location: dashboard_superadmin.php?tab=admins");
}
elseif ($action == 'delete_user') {
    $id = $_GET['id'];
    $conn->query("DELETE FROM users WHERE user_id = $id");
    $_SESSION['msg'] = "ลบผู้ใช้งานเรียบร้อยแล้ว";
    header("Location: dashboard_superadmin.php?tab=admins");
}

// 3. อัปเดตโปรไฟล์ Superadmin
elseif ($action == 'update_profile') {
    $fullname = $_POST['full_name'];
    $username = $_POST['username'];
    
    if(!empty($_POST['password'])){
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, password=? WHERE user_id=?");
        $stmt->bind_param("sssi", $fullname, $username, $password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, username=? WHERE user_id=?");
        $stmt->bind_param("ssi", $fullname, $username, $user_id);
    }
    
    if($stmt->execute()){
        $_SESSION['full_name'] = $fullname;
        $_SESSION['msg'] = "แก้ไขข้อมูลส่วนตัวสำเร็จ";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }
    header("Location: dashboard_superadmin.php?tab=profile");
}

// 4. อัปเดตรูปโปรไฟล์
elseif ($action == 'update_profile_pic') {
    if (isset($_POST['frame_style'])) {
        $stmt = $conn->prepare("UPDATE users SET profile_frame = ? WHERE user_id = ?");
        $stmt->bind_param("si", $_POST['frame_style'], $user_id); $stmt->execute();
    }
    if (isset($_POST['preset_avatar'])) {
        $stmt = $conn->prepare("UPDATE users SET profile_img = ? WHERE user_id = ?");
        $stmt->bind_param("si", $_POST['preset_avatar'], $user_id); $stmt->execute(); 
        $_SESSION['msg'] = "เปลี่ยนรูปโปรไฟล์เรียบร้อย";
    }
    if (isset($_FILES['upload_avatar']) && $_FILES['upload_avatar']['size'] > 0) {
        $target_dir = "uploads/"; if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_ext = strtolower(pathinfo($_FILES["upload_avatar"]["name"], PATHINFO_EXTENSION));
        $new_filename = "superadmin_" . $user_id . "_" . time() . "." . $file_ext;
        if (move_uploaded_file($_FILES["upload_avatar"]["tmp_name"], $target_dir . $new_filename)) {
            $stmt = $conn->prepare("UPDATE users SET profile_img = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_filename, $user_id); $stmt->execute(); 
            $_SESSION['msg'] = "อัปโหลดสำเร็จ";
        }
    }
    header("Location: dashboard_superadmin.php?tab=profile");
}

else {
    header("Location: dashboard_superadmin.php");
}
ob_end_flush();
?>