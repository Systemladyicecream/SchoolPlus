<?php
// ชื่อไฟล์: admin_action.php
ob_start(); // เริ่ม Buffer ทันที
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access Denied']));
}

$school_id = $_SESSION['school_id'];
$user_id = $_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$return_tab = isset($_REQUEST['return_tab']) ? $_REQUEST['return_tab'] : 'overview';

// Helper: หา Active Year ID
$ay_q = $conn->query("SELECT year_id FROM academic_years WHERE school_id=$school_id AND is_active=1");
$active_year_row = $ay_q->fetch_assoc();
$current_year_id = $active_year_row ? $active_year_row['year_id'] : 0;

function get_redirect_url($tab, $params = []) {
    $url = "dashboard_admin.php?tab=" . $tab;
    if (!empty($params)) {
        foreach ($params as $key => $val) {
            $url .= "&" . $key . "=" . urlencode($val);
        }
    }
    return $url;
}

// ========================================================
// SECTION: DATA FETCHING (ส่ง JSON กลับไปให้ JS)
// ========================================================

if (in_array($action, ['get_year_data', 'get_course_data', 'get_user_data'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $data = null;

    if ($action == 'get_year_data') {
        $stmt = $conn->prepare("SELECT * FROM academic_years WHERE year_id = ? AND school_id = ?");
        $stmt->bind_param("ii", $id, $school_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
    } elseif ($action == 'get_course_data') {
        $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ? AND school_id = ?");
        $stmt->bind_param("ii", $id, $school_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
    } elseif ($action == 'get_user_data') {
        // ดึงข้อมูล User ผสมกับข้อมูลรายปี (ถ้ามี)
        $sql = "SELECT u.*, ud.student_number, ud.class_level, ud.room_number, ud.subjects_taught 
                FROM users u 
                LEFT JOIN user_year_data ud ON u.user_id = ud.user_id AND ud.year_id = ? 
                WHERE u.user_id = ? AND u.school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $current_year_id, $id, $school_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
    }

    if (!$data) {
        echo json_encode(['error' => 'Not found']);
    } else {
        echo json_encode($data);
    }
    exit;
}

// ========================================================
// SECTION: ACTION HANDLERS
// ========================================================

// 1. จัดการปีการศึกษา
if ($action == 'add_year') {
    $year = $_POST['year_name'];
    $term = $_POST['term'];
    // รับค่าจำนวนคาบเรียนทั้งหมด (เพิ่มใหม่)
    $total = isset($_POST['total_sessions']) ? intval($_POST['total_sessions']) : 40;
    $active = isset($_POST['is_active']) ? 1 : 0;
    
    if($active){
        $conn->query("UPDATE academic_years SET is_active = 0 WHERE school_id = $school_id");
    }

    // เพิ่ม total_sessions ใน SQL
    $stmt = $conn->prepare("INSERT INTO academic_years (school_id, year_name, term, total_sessions, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $school_id, $year, $term, $total, $active);
    
    if($stmt->execute()) $_SESSION['msg'] = "เพิ่มปีการศึกษาเรียบร้อย";
    else $_SESSION['error'] = "Error: " . $conn->error;
    
    header("Location: dashboard_admin.php?tab=years");
}
elseif ($action == 'update_year') {
    $id = $_POST['year_id'];
    $year = $_POST['year_name'];
    $term = $_POST['term'];
    // รับค่าจำนวนคาบเรียนทั้งหมด (เพิ่มใหม่)
    $total = isset($_POST['total_sessions']) ? intval($_POST['total_sessions']) : 40;
    $active = isset($_POST['is_active']) ? 1 : 0;
    
    if($active){
        $conn->query("UPDATE academic_years SET is_active = 0 WHERE school_id = $school_id");
    }

    // เพิ่ม total_sessions ใน SQL
    $stmt = $conn->prepare("UPDATE academic_years SET year_name=?, term=?, total_sessions=?, is_active=? WHERE year_id=? AND school_id=?");
    $stmt->bind_param("ssiiii", $year, $term, $total, $active, $id, $school_id);
    
    if($stmt->execute()) $_SESSION['msg'] = "แก้ไขปีการศึกษาเรียบร้อย";
    else $_SESSION['error'] = "Error: " . $conn->error;

    header("Location: dashboard_admin.php?tab=years");
}
elseif ($action == 'delete_year') {
    $id = $_GET['id'];
    $conn->query("DELETE FROM academic_years WHERE year_id = $id AND school_id = $school_id");
    // ลบข้อมูลที่ผูกกับปีนี้ด้วย (Optional: เพื่อไม่ให้ขยะค้าง)
    $conn->query("DELETE FROM user_year_data WHERE year_id = $id AND school_id = $school_id");
    header("Location: dashboard_admin.php?tab=years");
}
elseif ($action == 'set_active_year') {
    $id = $_GET['id'];
    $conn->query("UPDATE academic_years SET is_active = 0 WHERE school_id = $school_id");
    $conn->query("UPDATE academic_years SET is_active = 1 WHERE year_id = $id AND school_id = $school_id");
    $_SESSION['msg'] = "เปลี่ยนปีการศึกษาปัจจุบันเรียบร้อยแล้ว";
    header("Location: dashboard_admin.php?tab=years");
}

// 2. จัดการหลักสูตร
elseif ($action == 'add_course') {
    $code = $_POST['course_code'];
    $name = $_POST['course_name'];
    $level = $_POST['class_level'];
    $group = $_POST['subject_group'];
    
    $stmt = $conn->prepare("INSERT INTO courses (school_id, course_code, course_name, class_level, subject_group) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $school_id, $code, $name, $level, $group);
    
    if($stmt->execute()) $_SESSION['msg'] = "เพิ่มรายวิชาเรียบร้อย";
    else $_SESSION['error'] = "Error: " . $conn->error;
    
    header("Location: dashboard_admin.php?tab=courses");
}
elseif ($action == 'update_course') {
    $id = $_POST['course_id'];
    $code = $_POST['course_code'];
    $name = $_POST['course_name'];
    $level = $_POST['class_level'];
    $group = $_POST['subject_group'];
    
    $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, class_level=?, subject_group=? WHERE course_id=? AND school_id=?");
    $stmt->bind_param("ssssii", $code, $name, $level, $group, $id, $school_id);
    
    if($stmt->execute()) $_SESSION['msg'] = "แก้ไขรายวิชาเรียบร้อย";
    else $_SESSION['error'] = "Error: " . $conn->error;
    
    header("Location: dashboard_admin.php?tab=courses");
}
elseif ($action == 'delete_course') {
    $id = $_GET['id'];
    $conn->query("DELETE FROM courses WHERE course_id = $id AND school_id = $school_id");
    header("Location: dashboard_admin.php?tab=courses");
}

// 3. จัดการผู้ใช้งาน (User Management)
elseif ($action == 'add_user') {
    if(!$current_year_id) {
        $_SESSION['error'] = "กรุณาตั้งค่าปีการศึกษาก่อน";
        header("Location: dashboard_admin.php"); exit;
    }

    $role = $_POST['role_type']; 
    $username = trim($_POST['username']);
    $fullname = $_POST['full_name'];
    
    $student_code = isset($_POST['student_code']) ? $_POST['student_code'] : NULL;
    $class_level = isset($_POST['class_level']) ? $_POST['class_level'] : NULL;
    $position = isset($_POST['position']) ? $_POST['position'] : NULL;
    $student_number = isset($_POST['student_number']) ? intval($_POST['student_number']) : 0;

    $redirect_params = [];
    if(isset($_POST['view_class'])) $redirect_params['view_class'] = $_POST['view_class'];
    if(isset($_POST['view_room'])) $redirect_params['view_room'] = $_POST['view_room'];

    // --- เช็ค Username ซ้ำ ---
    $check = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
    if($check->num_rows > 0){
        $_SESSION['error'] = "Username '$username' มีอยู่ในระบบแล้ว!";
        header("Location: " . get_redirect_url($return_tab, $redirect_params));
        exit;
    }

    // --- จัดการ Password Policy ---
    $password_raw = "";
    if ($role == 'teacher') {
        $password_raw = $_POST['password'];
        // บังคับ Policy สำหรับครู
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password_raw)) {
            $_SESSION['error'] = "รหัสผ่านครูต้องมีความยาวอย่างน้อย 8 ตัวอักษร และประกอบด้วยตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก, และตัวเลข";
            header("Location: " . get_redirect_url($return_tab, $redirect_params));
            exit;
        }
    } elseif ($role == 'student') {
        // นักเรียน: ใช้ Username (รหัสนักเรียน) เป็น Password เริ่มต้น
        $password_raw = $username;
    } else {
        $password_raw = $_POST['password'];
    }

    $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

    // --- ส่วนจัดการห้องที่ปรึกษา/เรียน ---
    $room = NULL;
    if(isset($_POST['advisory_level']) && isset($_POST['advisory_room_no']) && $_POST['advisory_level'] != '') {
        $room = $_POST['advisory_level'] . '/' . $_POST['advisory_room_no'];
    } elseif (isset($_POST['room_number'])) {
        $room = $_POST['room_number']; // กรณีนักเรียน
    }

    // --- ส่วนจัดการภาระงานสอน (JSON) ---
    $subjects_taught_json = NULL;
    if (isset($_POST['teaching_level'])) {
        $teaching_data = [];
        $s_values = isset($_POST['teaching_subjects']) ? array_values($_POST['teaching_subjects']) : [];
        foreach ($_POST['teaching_level'] as $key => $lvl) {
            if($lvl != '' && isset($_POST['teaching_room'][$key]) && $_POST['teaching_room'][$key] != '') {
                $teaching_room_str = $lvl . '/' . $_POST['teaching_room'][$key];
                $subjects_in_this_room = isset($s_values[$key]) ? $s_values[$key] : [];
                $teaching_data[] = [ 'room' => $teaching_room_str, 'subjects' => $subjects_in_this_room ];
            }
        }
        if(!empty($teaching_data)) {
            $subjects_taught_json = json_encode($teaching_data, JSON_UNESCAPED_UNICODE);
        }
    }

    if($role == 'executive') $role_db = 'super_teacher'; else $role_db = $role;

    // 1. เพิ่ม User ลงตารางหลัก (Profile)
    $stmt = $conn->prepare("INSERT INTO users (school_id, username, password, full_name, role, student_code) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $school_id, $username, $password_hash, $fullname, $role_db, $student_code);
    
    if($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        
        // 2. เพิ่มข้อมูลรายปีลง user_year_data
        $stmt_yd = $conn->prepare("INSERT INTO user_year_data (school_id, user_id, year_id, student_number, class_level, room_number, subjects_taught, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_yd->bind_param("iiiissss", $school_id, $new_user_id, $current_year_id, $student_number, $class_level, $room, $subjects_taught_json, $position);
        $stmt_yd->execute();

        $_SESSION['msg'] = "เพิ่มผู้ใช้งานสำเร็จ";
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }
    
    header("Location: " . get_redirect_url($return_tab, $redirect_params));
}
elseif ($action == 'update_user') {
    if(!$current_year_id) {
        $_SESSION['error'] = "กรุณาตั้งค่าปีการศึกษาก่อน";
        header("Location: dashboard_admin.php"); exit;
    }

    $id = $_POST['user_id'];
    $username = $_POST['username'];
    $fullname = $_POST['full_name'];
    
    // หา Role ปัจจุบันเพื่อเช็ค Policy
    $q_role = $conn->query("SELECT role FROM users WHERE user_id = $id");
    $current_role = $q_role->fetch_assoc()['role'];

    $student_code = isset($_POST['student_code']) ? $_POST['student_code'] : NULL;
    $class_level = isset($_POST['class_level']) ? $_POST['class_level'] : NULL;
    $position = isset($_POST['position']) ? $_POST['position'] : NULL;
    $student_number = isset($_POST['student_number']) ? intval($_POST['student_number']) : 0;
    
    // --- ส่วนจัดการห้องที่ปรึกษา ---
    $room = NULL;
    if(isset($_POST['advisory_level']) && isset($_POST['advisory_room_no']) && $_POST['advisory_level'] != '') {
        $room = $_POST['advisory_level'] . '/' . $_POST['advisory_room_no'];
    } elseif (isset($_POST['room_number'])) {
        $room = $_POST['room_number'];
    }

    // --- ส่วนจัดการภาระงานสอน (JSON) ---
    $subjects_taught_json = NULL;
    if (isset($_POST['teaching_level'])) {
        $teaching_data = [];
        $s_values = isset($_POST['teaching_subjects']) ? array_values($_POST['teaching_subjects']) : [];
        foreach ($_POST['teaching_level'] as $key => $lvl) {
            if($lvl != '' && isset($_POST['teaching_room'][$key]) && $_POST['teaching_room'][$key] != '') {
                $teaching_room_str = $lvl . '/' . $_POST['teaching_room'][$key];
                $subjects_in_this_room = isset($s_values[$key]) ? $s_values[$key] : [];
                $teaching_data[] = [ 'room' => $teaching_room_str, 'subjects' => $subjects_in_this_room ];
            }
        }
        if(!empty($teaching_data)) {
            $subjects_taught_json = json_encode($teaching_data, JSON_UNESCAPED_UNICODE);
        }
    }

    // 1. อัปเดต Profile หลัก
    $sql = "UPDATE users SET username=?, full_name=?, student_code=?";
    $types = "sss";
    $params = [$username, $fullname, $student_code];

    if(!empty($_POST['password'])){
        $password_raw = $_POST['password'];

        if ($current_role == 'teacher') {
             // ตรวจสอบ Policy สำหรับครู
             if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password_raw)) {
                $_SESSION['error'] = "เปลี่ยนรหัสผ่านไม่สำเร็จ: รหัสผ่านครูต้องมี 8 ตัวอักษรขึ้นไป ผสมตัวพิมพ์ใหญ่ เล็ก และตัวเลข";
                $redirect_params = [];
                if(isset($_POST['view_class'])) $redirect_params['view_class'] = $_POST['view_class'];
                if(isset($_POST['view_room'])) $redirect_params['view_room'] = $_POST['view_room'];
                header("Location: " . get_redirect_url($return_tab, $redirect_params));
                exit;
            }
        }
        
        $password = password_hash($password_raw, PASSWORD_DEFAULT);
        $sql .= ", password=?";
        $types .= "s";
        $params[] = $password;
    }

    $sql .= " WHERE user_id=? AND school_id=?";
    $types .= "ii";
    $params[] = $id;
    $params[] = $school_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    // 2. อัปเดต/เพิ่มข้อมูลรายปี (Upsert)
    // เช็คว่ามีข้อมูลปีนี้หรือยัง
    $check_yd = $conn->query("SELECT id FROM user_year_data WHERE user_id=$id AND year_id=$current_year_id");
    if($check_yd->num_rows > 0) {
        $stmt_yd = $conn->prepare("UPDATE user_year_data SET student_number=?, class_level=?, room_number=?, subjects_taught=?, position=? WHERE user_id=? AND year_id=?");
        $stmt_yd->bind_param("issssii", $student_number, $class_level, $room, $subjects_taught_json, $position, $id, $current_year_id);
    } else {
        $stmt_yd = $conn->prepare("INSERT INTO user_year_data (school_id, user_id, year_id, student_number, class_level, room_number, subjects_taught, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_yd->bind_param("iiiissss", $school_id, $id, $current_year_id, $student_number, $class_level, $room, $subjects_taught_json, $position);
    }
    
    if($stmt_yd->execute()) $_SESSION['msg'] = "แก้ไขข้อมูลผู้ใช้งานเรียบร้อย";
    else $_SESSION['error'] = "Error: " . $conn->error;

    $redirect_params = [];
    if(isset($_POST['view_class'])) $redirect_params['view_class'] = $_POST['view_class'];
    if(isset($_POST['view_room'])) $redirect_params['view_room'] = $_POST['view_room'];

    header("Location: " . get_redirect_url($return_tab, $redirect_params));
}
elseif ($action == 'delete_user') {
    $id = $_GET['id'];
    $conn->query("DELETE FROM users WHERE user_id = $id AND school_id = $school_id");
    $conn->query("DELETE FROM user_year_data WHERE user_id = $id AND school_id = $school_id"); // ลบข้อมูลปีด้วย
    
    $_SESSION['msg'] = "ลบผู้ใช้งานเรียบร้อยแล้ว";
    
    $redirect_params = [];
    if(isset($_GET['view_class'])) $redirect_params['view_class'] = $_GET['view_class'];
    if(isset($_GET['view_room'])) $redirect_params['view_room'] = $_GET['view_room'];
    
    header("Location: " . get_redirect_url($return_tab, $redirect_params));
}
// ------------------------------------------
// เพิ่มใหม่: ลบนักเรียนทั้งห้อง
// ------------------------------------------
elseif ($action == 'delete_students_in_room') {
    $class = $_GET['class_level'];
    $room = $_GET['room_number'];

    // 1. หา User ID ของนักเรียนในห้องนี้
    $sql_find = "SELECT user_id FROM user_year_data WHERE class_level = ? AND room_number = ? AND school_id = ? AND year_id = ?";
    $stmt_find = $conn->prepare($sql_find);
    $stmt_find->bind_param("ssii", $class, $room, $school_id, $current_year_id);
    $stmt_find->execute();
    $res_find = $stmt_find->get_result();

    $count = 0;
    while($row = $res_find->fetch_assoc()) {
        $uid = $row['user_id'];
        // ลบข้อมูลจาก users (Cascade จะลบ user_year_data เอง หรือลบแยกตาม Logic เดิม)
        $conn->query("DELETE FROM users WHERE user_id = $uid AND school_id = $school_id");
        $conn->query("DELETE FROM user_year_data WHERE user_id = $uid AND school_id = $school_id");
        $count++;
    }

    $_SESSION['msg'] = "ลบนักเรียนทั้งหมดในห้อง $class/$room เรียบร้อยแล้ว (จำนวน $count คน)";
    header("Location: dashboard_admin.php?tab=students&view_class=".urlencode($class)."&view_room=".urlencode($room));
}
elseif ($action == 'import_csv') {
    if(!$current_year_id) { 
        $_SESSION['error'] = "กรุณาตั้งค่าปีการศึกษาก่อน"; 
        header("Location: dashboard_admin.php?tab=students"); 
        exit; 
    }

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['size'] > 0) {
        // รับค่าระดับชั้นและห้องจากฟอร์ม
        $import_class = isset($_POST['import_class_level']) ? $_POST['import_class_level'] : '';
        $import_room = isset($_POST['import_room_number']) ? $_POST['import_room_number'] : '';

        if(empty($import_class) || empty($import_room)) {
             $_SESSION['error'] = "ข้อมูลห้องเรียนไม่ถูกต้อง"; 
             header("Location: dashboard_admin.php?tab=students"); 
             exit; 
        }

        $file_name = $_FILES['csv_file']['name'];
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($ext == 'csv') {
            $handle = fopen($file_tmp, "r");
            $row_count = 0;
            $success_count = 0;

            // Prepared Statements
            $stmt_insert_user = $conn->prepare("INSERT INTO users (school_id, username, password, full_name, role, student_code) VALUES (?, ?, ?, ?, 'student', ?)");
            $stmt_check_user = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND school_id = ?");
            // แก้ไข: เพิ่ม student_number ลงใน SQL
            $stmt_insert_yd = $conn->prepare("INSERT INTO user_year_data (school_id, user_id, year_id, student_number, class_level, room_number) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_check_yd = $conn->prepare("SELECT id FROM user_year_data WHERE user_id = ? AND year_id = ?");

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // โครงสร้าง CSV ใหม่:
                // Col 0: เลขที่
                // Col 1: รหัสนักเรียน (Username)
                // Col 2: ชื่อ-นามสกุล

                if (empty($data[0])) continue;

                $std_number_raw = trim($data[0]);
                // ลบ BOM
                $std_number_raw = preg_replace('/^\xEF\xBB\xBF/', '', $std_number_raw);

                // ข้าม Header หากบรรทัดแรกและคอลัมน์แรกไม่ใช่ตัวเลข
                if ($row_count == 0 && !is_numeric($std_number_raw)) {
                    $row_count++;
                    continue;
                }

                $std_number = intval($std_number_raw);
                $std_code = trim($data[1]);
                $full_name = trim($data[2]);

                if($std_code) {
                    $target_uid = 0;

                    // 1. เช็คว่ามี User (รหัสนักเรียน) นี้ในระบบแล้วหรือยัง
                    $stmt_check_user->bind_param("si", $std_code, $school_id);
                    $stmt_check_user->execute();
                    $res_u = $stmt_check_user->get_result();

                    if($res_u->num_rows > 0) {
                        $u_row = $res_u->fetch_assoc();
                        $target_uid = $u_row['user_id'];
                    } else {
                        // สร้างใหม่: Password = Student Code
                        $password_hash = password_hash($std_code, PASSWORD_DEFAULT);
                        $stmt_insert_user->bind_param("issss", $school_id, $std_code, $password_hash, $full_name, $std_code);
                        if($stmt_insert_user->execute()) {
                            $target_uid = $stmt_insert_user->insert_id;
                        }
                    }

                    // 2. เพิ่มข้อมูลลงปีการศึกษาปัจจุบัน (ผูกกับห้องที่เลือกมา)
                    if ($target_uid > 0) {
                        $stmt_check_yd->bind_param("ii", $target_uid, $current_year_id);
                        $stmt_check_yd->execute();
                        $res_yd = $stmt_check_yd->get_result();

                        if ($res_yd->num_rows == 0) {
                            // บันทึก เลขที่, ระดับชั้น, ห้องเรียน
                            $stmt_insert_yd->bind_param("iiiiss", $school_id, $target_uid, $current_year_id, $std_number, $import_class, $import_room);
                            $stmt_insert_yd->execute();
                            $success_count++;
                        }
                    }
                }
                $row_count++;
            }
            fclose($handle);
            $_SESSION['msg'] = "นำเข้าข้อมูลสำเร็จ จำนวน " . $success_count . " รายการ";
        } else {
            $_SESSION['error'] = "รูปแบบไฟล์ไม่ถูกต้อง (รองรับเฉพาะ .csv)";
        }
    } else {
        $_SESSION['error'] = "กรุณาเลือกไฟล์ก่อนอัปโหลด";
    }
    
    $redirect_url = "dashboard_admin.php?tab=students";
    // Redirect กลับไปที่ห้องเดิม
    if(isset($import_class) && isset($import_room)) {
        $redirect_url .= "&view_class=" . urlencode($import_class) . "&view_room=" . urlencode($import_room);
    }
    
    header("Location: " . $redirect_url);
}

// 4. ส่วน Profile
elseif ($action == 'update_profile') {
    $fullname = $_POST['full_name'];
    $sql = "UPDATE users SET full_name=?";
    $types = "s";
    $params = [$fullname];
    
    if(!empty($_POST['password'])){
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password=?";
        $types .= "s";
        $params[] = $password;
    }
    $sql .= " WHERE user_id=?"; $types .= "i"; $params[] = $user_id;
    $stmt = $conn->prepare($sql); $stmt->bind_param($types, ...$params);
    if($stmt->execute()) $_SESSION['msg'] = "บันทึกข้อมูลส่วนตัวเรียบร้อย";
    else $_SESSION['error'] = "Error: " . $conn->error;
    header("Location: dashboard_admin.php?tab=profile");
}
elseif ($action == 'update_profile_pic') {
    if (isset($_POST['frame_style'])) {
        $stmt = $conn->prepare("UPDATE users SET profile_frame = ? WHERE user_id = ?");
        $stmt->bind_param("si", $_POST['frame_style'], $user_id); $stmt->execute();
    }
    if (isset($_POST['preset_avatar'])) {
        $stmt = $conn->prepare("UPDATE users SET profile_img = ? WHERE user_id = ?");
        $stmt->bind_param("si", $_POST['preset_avatar'], $user_id); $stmt->execute(); $_SESSION['msg'] = "เปลี่ยนรูปโปรไฟล์เรียบร้อย";
    }
    if (isset($_FILES['upload_avatar']) && $_FILES['upload_avatar']['size'] > 0) {
        $target_dir = "uploads/"; if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_ext = strtolower(pathinfo($_FILES["upload_avatar"]["name"], PATHINFO_EXTENSION));
        $new_filename = "admin_" . $user_id . "_" . time() . "." . $file_ext;
        if (move_uploaded_file($_FILES["upload_avatar"]["tmp_name"], $target_dir . $new_filename)) {
            $stmt = $conn->prepare("UPDATE users SET profile_img = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_filename, $user_id); $stmt->execute(); $_SESSION['msg'] = "อัปโหลดสำเร็จ";
        }
    }
    header("Location: dashboard_admin.php?tab=profile");
}
else {
    header("Location: dashboard_admin.php");
}
ob_end_flush();
?>