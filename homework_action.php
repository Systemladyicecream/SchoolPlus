<?php
// ชื่อไฟล์: homework_action.php
ob_start();
session_start();
require_once 'db_connect.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    die("Access Denied: กรุณาเข้าสู่ระบบ");
}

$user_id = $_SESSION['user_id'];
$school_id = isset($_SESSION['school_id']) ? $_SESSION['school_id'] : 0;
$role = $_SESSION['role'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Auto Migration: ตรวจสอบและสร้างตารางที่เกี่ยวข้องกับการบ้านและการแจ้งเตือน (ป้องกัน Error)
$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, teacher_id INT, year_id INT, 
    course_code VARCHAR(50), class_level VARCHAR(50), room_number VARCHAR(50), 
    title VARCHAR(255), description TEXT, due_date DATETIME, max_score DECIMAL(5,2), 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY, assignment_id INT, student_id INT, 
    file_path TEXT, submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    status VARCHAR(20) DEFAULT 'submitted', score DECIMAL(5,2) DEFAULT NULL, teacher_comment TEXT
)");
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, title VARCHAR(255), message TEXT, 
    link VARCHAR(255), is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


// =========================================================================
// ส่วนที่ 1: ระบบจัดการฝั่ง "ครูผู้สอน" (TEACHER)
// =========================================================================
if ($role === 'teacher') {
    
    // 1.1 ครูสั่งการบ้าน หรือ อัปเดตการบ้าน (Save Assignment)
    if ($action === 'save_assignment') {
        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        $select_class_key = $_POST['select_class_key'] ?? '';
        
        list($room_str, $subject_str) = explode('|', $select_class_key);
        list($class_level, $room_number) = explode('/', $room_str);
        $course_code = (strpos($subject_str, '(') !== false) ? str_replace(')', '', explode('(', $subject_str)[1]) : $subject_str;
        
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date'];
        $max_score = floatval($_POST['max_score']);
        
        $ay_q = $conn->query("SELECT year_id FROM academic_years WHERE school_id=$school_id AND is_active=1");
        $year_id = $ay_q->num_rows > 0 ? $ay_q->fetch_assoc()['year_id'] : 0;

        if ($assignment_id == 0) {
            // สร้างการบ้านใหม่
            $stmt = $conn->prepare("INSERT INTO assignments (school_id, teacher_id, year_id, course_code, class_level, room_number, title, description, due_date, max_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissssssd", $school_id, $user_id, $year_id, $course_code, $class_level, $room_number, $title, $description, $due_date, $max_score);
            
            if($stmt->execute()) {
                $_SESSION['msg'] = "สั่งการบ้านเรียบร้อยแล้ว";
                
                // 🔔 แจ้งเตือนที่ 3: "แจ้งเตือนครูสั่งการบ้าน" ยิงไปหานักเรียนทั้งห้อง
                $st_q = $conn->query("SELECT user_id FROM user_year_data WHERE class_level='$class_level' AND room_number='$room_number' AND year_id=$year_id");
                if($st_q) {
                    $t_name = $_SESSION['full_name'] ?? 'ครูผู้สอน';
                    $due_str = date('d/m/Y H:i', strtotime($due_date));
                    $msg = "อ.$t_name สั่งการบ้านใหม่วิชา $course_code: $title (กำหนดส่ง $due_str)";
                    $link = "homework_student.php"; 
                    
                    while($st = $st_q->fetch_assoc()) {
                        $sid = $st['user_id'];
                        $conn->query("INSERT INTO notifications (user_id, title, message, link) VALUES ($sid, 'การบ้านใหม่ 📚', '$msg', '$link')");
                    }
                }
            } else {
                $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        } else {
            // อัปเดตการบ้านเดิม
            $stmt = $conn->prepare("UPDATE assignments SET title=?, description=?, due_date=?, max_score=? WHERE assignment_id=? AND teacher_id=?");
            $stmt->bind_param("sssdii", $title, $description, $due_date, $max_score, $assignment_id, $user_id);
            if($stmt->execute()) $_SESSION['msg'] = "อัปเดตการบ้านเรียบร้อยแล้ว";
            else $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดต";
        }
        header("Location: homework_teacher.php?select_class_key=" . urlencode($select_class_key));
        exit;
    }
    
    // 1.2 ครูลบการบ้าน (Delete Assignment)
    elseif ($action === 'delete_assignment') {
        $assignment_id = intval($_POST['assignment_id']);
        $select_class_key = $_POST['select_class_key'] ?? '';
        
        // ลบไฟล์งานของนักเรียนในเซิร์ฟเวอร์ก่อน
        $sub_q = $conn->query("SELECT file_path FROM submissions WHERE assignment_id=$assignment_id");
        while($sub = $sub_q->fetch_assoc()) {
            if(!empty($sub['file_path']) && file_exists($sub['file_path'])) unlink($sub['file_path']);
        }
        
        // ลบข้อมูลออกจากฐานข้อมูล
        $conn->query("DELETE FROM submissions WHERE assignment_id=$assignment_id");
        $conn->query("DELETE FROM assignments WHERE assignment_id=$assignment_id AND teacher_id=$user_id");
        
        $_SESSION['msg'] = "ลบการบ้านและข้อมูลการส่งงานเรียบร้อยแล้ว";
        header("Location: homework_teacher.php?select_class_key=" . urlencode($select_class_key));
        exit;
    }

    // 1.3 ครูตรวจการบ้านและให้คะแนน (Grade Submission)
    elseif ($action === 'grade_submission') {
        $submission_id = intval($_POST['submission_id']);
        $score = floatval($_POST['score']);
        $comment = trim($_POST['teacher_comment'] ?? '');
        $select_class_key = $_POST['select_class_key'] ?? '';
        $assignment_id = intval($_POST['assignment_id'] ?? 0);

        $stmt = $conn->prepare("UPDATE submissions SET score=?, teacher_comment=?, status='graded' WHERE submission_id=?");
        $stmt->bind_param("dsi", $score, $comment, $submission_id);
        
        if($stmt->execute()) {
            $_SESSION['msg'] = "บันทึกคะแนนเรียบร้อยแล้ว";

            // 🔔 แจ้งเตือนที่ 1: "ครูตรวจการบ้าน" ยิงไปหานักเรียนเจ้าของผลงาน
            $sub_q = $conn->query("SELECT s.student_id, a.title, a.course_code FROM submissions s JOIN assignments a ON s.assignment_id = a.assignment_id WHERE s.submission_id=$submission_id");
            if($sub_q && $sub_q->num_rows > 0) {
                $sub = $sub_q->fetch_assoc();
                $sid = $sub['student_id'];
                $t_name = $_SESSION['full_name'] ?? 'ครูผู้สอน';
                $msg = "อ.$t_name ตรวจงาน '{$sub['title']}' วิชา {$sub['course_code']} และให้คะแนนแล้ว";
                $link = "homework_student.php"; // ให้นักเรียนกดเข้ามาดูคะแนน
                $conn->query("INSERT INTO notifications (user_id, title, message, link) VALUES ($sid, 'ตรวจงานแล้ว ✅', '$msg', '$link')");
            }
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกคะแนน";
        }
        // กลับไปหน้าตรวจงานเดิมของครู
        header("Location: homework_teacher.php?view=submissions&assignment_id=$assignment_id&select_class_key=" . urlencode($select_class_key));
        exit;
    }
} 

// =========================================================================
// ส่วนที่ 2: ระบบจัดการฝั่ง "นักเรียน" (STUDENT)
// =========================================================================
elseif ($role === 'student') {
    
    // 2.1 นักเรียนส่งการบ้าน (Submit Homework)
    if ($action === 'submit_homework') {
        $assignment_id = intval($_POST['assignment_id']);
        
        $file_path = '';
        $upload_dir = 'uploads/homework/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        // จัดการอัปโหลดไฟล์
        if (isset($_FILES['homework_file']) && $_FILES['homework_file']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['homework_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','jpg','jpeg','png','zip','rar', 'xls', 'xlsx', 'ppt', 'pptx'];
            if(in_array($ext, $allowed)) {
                $new_name = "hw_" . $assignment_id . "_" . $user_id . "_" . time() . "." . $ext;
                if(move_uploaded_file($_FILES['homework_file']['tmp_name'], $upload_dir.$new_name)) {
                    $file_path = $upload_dir.$new_name;
                }
            } else {
                $_SESSION['error'] = "ประเภทไฟล์ไม่รองรับ กรุณาใช้ไฟล์เอกสาร รูปภาพ หรือไฟล์บีบอัดเท่านั้น";
                header("Location: homework_student.php");
                exit;
            }
        }

        // ตรวจสอบว่าเคยส่งงานนี้หรือยัง
        $check = $conn->query("SELECT submission_id, file_path FROM submissions WHERE assignment_id=$assignment_id AND student_id=$user_id");
        if($check->num_rows > 0) {
            $old_sub = $check->fetch_assoc();
            // ถ้ามีการแนบไฟล์ใหม่มา ให้ลบไฟล์เก่าทิ้ง
            if(!empty($file_path)) {
                if(!empty($old_sub['file_path']) && file_exists($old_sub['file_path'])) unlink($old_sub['file_path']);
                $stmt = $conn->prepare("UPDATE submissions SET file_path=?, submitted_at=CURRENT_TIMESTAMP, status='submitted' WHERE submission_id=?");
                $stmt->bind_param("si", $file_path, $old_sub['submission_id']);
                $stmt->execute();
            } else {
                // ถ้าไม่แนบไฟล์ใหม่ แค่อัปเดตเวลา
                $conn->query("UPDATE submissions SET submitted_at=CURRENT_TIMESTAMP, status='submitted' WHERE submission_id={$old_sub['submission_id']}");
            }
        } else {
            // เพิ่งส่งครั้งแรก
            $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, status) VALUES (?, ?, ?, 'submitted')");
            $stmt->bind_param("iis", $assignment_id, $user_id, $file_path);
            $stmt->execute();
        }

        $_SESSION['msg'] = "ส่งการบ้านเรียบร้อยแล้ว";

        // 🔔 แจ้งเตือนที่ 2: "ครูได้รับการแจ้งเตือนเมื่อนักเรียนส่งงาน"
        $ass_q = $conn->query("SELECT teacher_id, title, course_code, class_level, room_number, year_id FROM assignments WHERE assignment_id=$assignment_id");
        if($ass_q && $ass_q->num_rows > 0) {
            $ass = $ass_q->fetch_assoc();
            $t_id = $ass['teacher_id'];
            $s_name = $_SESSION['full_name'] ?? 'นักเรียน';
            
            // หาเลขที่นักเรียน
            $st_q = $conn->query("SELECT student_number FROM user_year_data WHERE user_id=$user_id AND year_id={$ass['year_id']}");
            $s_num = ($st_q && $st_q->num_rows > 0) ? $st_q->fetch_assoc()['student_number'] : '-';

            $msg = "เลขที่ $s_num ($s_name) ส่งงาน '{$ass['title']}' วิชา {$ass['course_code']} (ห้อง {$ass['class_level']}/{$ass['room_number']})";
            // ให้ครูกดแล้วเด้งไปที่หน้าตรวจงานชิ้นนั้นทันที
            $link = "homework_teacher.php?view=submissions&assignment_id=$assignment_id";
            
            $conn->query("INSERT INTO notifications (user_id, title, message, link) VALUES ($t_id, 'ส่งงานใหม่ 📩', '$msg', '$link')");
        }

        header("Location: homework_student.php");
        exit;
    }

    // 2.2 นักเรียนยกเลิกการส่งงาน (Cancel Submission)
    elseif ($action === 'cancel_submission') {
        $assignment_id = intval($_POST['assignment_id']);
        $check = $conn->query("SELECT submission_id, file_path, status FROM submissions WHERE assignment_id=$assignment_id AND student_id=$user_id");
        
        if($check->num_rows > 0) {
            $sub = $check->fetch_assoc();
            // เช็คว่าครูตรวจไปหรือยัง ถ้าตรวจแล้วห้ามยกเลิก
            if($sub['status'] !== 'graded') {
                if(!empty($sub['file_path']) && file_exists($sub['file_path'])) unlink($sub['file_path']);
                $conn->query("DELETE FROM submissions WHERE submission_id={$sub['submission_id']}");
                $_SESSION['msg'] = "ยกเลิกการส่งงานและลบไฟล์เรียบร้อยแล้ว";
            } else {
                $_SESSION['error'] = "ไม่สามารถยกเลิกได้เนื่องจากครูตรวจและให้คะแนนแล้ว";
            }
        }
        header("Location: homework_student.php");
        exit;
    }
}
?>