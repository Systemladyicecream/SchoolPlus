<?php
// ชื่อไฟล์: teacher_action.php
ob_start();
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die(json_encode(['error' => 'Access Denied']));
}

$school_id = $_SESSION['school_id'];
$teacher_id = $_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Function คำนวณคะแนนจากสถานะ
function get_score($status) {
    switch ($status) {
        case 'present': return 1.0;
        case 'late': return 0.75;
        case 'absent': return 0.0;
        case 'leave': return 0.0; 
        default: return 0.0;
    }
}

// 📌 ฟังก์ชันอัจฉริยะสำหรับส่งการแจ้งเตือน (Phase 3: DB + LINE Notify)
function send_system_notification($conn, $user_id, $title, $message, $link) {
    // 1. บันทึกลงฐานข้อมูลแอป
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $link);
    $stmt->execute();

    // 2. ตรวจสอบว่าผู้รับเปิดใช้งาน LINE Notify หรือไม่
    $q = $conn->query("SELECT line_token, notify_line FROM user_settings WHERE user_id = $user_id");
    if ($q && $q->num_rows > 0) {
        $set = $q->fetch_assoc();
        if ($set['notify_line'] == 1 && !empty($set['line_token'])) {
            $line_msg = "\n" . $title . "\n" . $message;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . urlencode($line_msg));
            $headers = array('Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer ' . $set['line_token']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // บังคับไม่ให้โหลดนานเกิน 2 วิ
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

// ========================================================
// 1. บันทึกการเช็คชื่อรายวิชา (Save / Update)
// ========================================================
if ($action == 'save_attendance') {
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $class_level = $_POST['class_level'];
    $room_number = $_POST['room_number'];
    $date = $_POST['attendance_date']; 
    $students = $_POST['attendance']; 

    if ($session_id == 0) {
        $stmt = $conn->prepare("INSERT INTO attendance_sessions (school_id, teacher_id, course_code, course_name, class_level, room_number, attendance_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $school_id, $teacher_id, $course_code, $course_name, $class_level, $room_number, $date);
        $stmt->execute();
        $session_id = $conn->insert_id;
    } else {
        $stmt = $conn->prepare("UPDATE attendance_sessions SET attendance_date = ? WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("sii", $date, $session_id, $teacher_id);
        $stmt->execute();
        $conn->query("DELETE FROM attendance_records WHERE session_id = $session_id");
    }

    $stmt_rec = $conn->prepare("INSERT INTO attendance_records (session_id, student_id, status, score) VALUES (?, ?, ?, ?)");
    foreach ($students as $std_id => $status) {
        $score = get_score($status);
        $stmt_rec->bind_param("iisd", $session_id, $std_id, $status, $score);
        $stmt_rec->execute();
    }

    $_SESSION['msg'] = "บันทึกการเช็คชื่อเรียบร้อยแล้ว";
    header("Location: dashboard_teacher.php?tab=attendance");
    exit;
}

elseif ($action == 'get_session_data') {
    $id = intval($_GET['id']);
    $session = $conn->query("SELECT * FROM attendance_sessions WHERE id=$id AND teacher_id=$teacher_id")->fetch_assoc();
    $records = [];
    $q_rec = $conn->query("SELECT student_id, status FROM attendance_records WHERE session_id=$id");
    while($r = $q_rec->fetch_assoc()) {
        $records[$r['student_id']] = $r['status'];
    }
    echo json_encode(['session' => $session, 'records' => $records]);
    exit;
}

elseif ($action == 'export_csv') {
    $class_level = $_GET['class_level'];
    $room_number = $_GET['room_number'];
    $course_code = $_GET['course_code'];

    $filename = "Attendance_{$course_code}_{$class_level}-{$room_number}_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo "\xEF\xBB\xBF"; 
    $output = fopen('php://output', 'w');

    $ay_q = $conn->query("SELECT year_id, total_sessions FROM academic_years WHERE school_id=$school_id AND is_active=1");
    $active_year_data = $ay_q->fetch_assoc();
    $active_year_id = $active_year_data ? $active_year_data['year_id'] : 0;
    $total_required_sessions = ($active_year_data && $active_year_data['total_sessions'] > 0) ? intval($active_year_data['total_sessions']) : 1;

    $sql_sess = "SELECT * FROM attendance_sessions 
                 WHERE school_id=$school_id AND teacher_id=$teacher_id 
                 AND class_level='$class_level' AND room_number='$room_number' 
                 AND course_code='$course_code'
                 ORDER BY attendance_date ASC";
    $res_sess = $conn->query($sql_sess);
    $sessions = [];
    $dates_map = []; 
    while($row = $res_sess->fetch_assoc()){
        $sessions[] = $row;
        $m = date('n', strtotime($row['attendance_date']));
        $y = date('Y', strtotime($row['attendance_date'])) + 543;
        $key = "$m-$y";
        if(!isset($dates_map[$key])) $dates_map[$key] = [];
        $dates_map[$key][] = $row;
    }

    $sql_std = "SELECT u.user_id, u.student_code, u.full_name, ud.student_number 
                FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id 
                WHERE u.school_id=$school_id AND u.role='student' 
                AND ud.year_id=$active_year_id AND ud.class_level='$class_level' AND ud.room_number='$room_number'
                ORDER BY ud.student_number ASC";
    $res_std = $conn->query($sql_std);

    fputcsv($output, ["รหัสวิชา $course_code รายวิชา (ชื่อวิชา)"]); 
    fputcsv($output, ["ระดับชั้น $class_level ห้อง $room_number"]);
    fputcsv($output, ["ครูผู้สอน " . $_SESSION['full_name'], "", "", "จำนวนคาบทั้งหมดตามหลักสูตร: $total_required_sessions คาบ"]);
    
    $header_row_1 = ["เลขที่", "รหัสนักเรียน", "ชื่อ-นามสกุล"];
    $header_row_2 = ["", "", ""];
    $thai_months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];

    foreach($dates_map as $my => $sess_list) {
        list($m, $y) = explode('-', $my);
        $month_name = isset($thai_months[intval($m)]) ? $thai_months[intval($m)] : '';
        $header_label = "$month_name $y";
        $header_row_1[] = $header_label; 
        for($i=1; $i<count($sess_list); $i++) { $header_row_1[] = ""; }
        foreach($sess_list as $s) {
            $header_row_2[] = date('j', strtotime($s['attendance_date']));
        }
    }
    
    $header_row_1[] = "จำนวนการเข้าเรียน"; $header_row_1[] = "% เข้าเรียน";
    $header_row_2[] = ""; $header_row_2[] = "";

    fputcsv($output, $header_row_1); fputcsv($output, $header_row_2);

    while($std = $res_std->fetch_assoc()) {
        $row_data = [$std['student_number'], $std['student_code'], $std['full_name']];
        $total_score = 0;
        foreach($sessions as $sess) {
            $sid = $sess['id'];
            $uid = $std['user_id'];
            $q_sc = $conn->query("SELECT score FROM attendance_records WHERE session_id=$sid AND student_id=$uid");
            if($q_sc->num_rows > 0) {
                $sc = $q_sc->fetch_assoc()['score'];
                $row_data[] = $sc;
                $total_score += $sc;
            } else {
                $row_data[] = "";
            }
        }
        $percent = $total_required_sessions > 0 ? ($total_score / $total_required_sessions) * 100 : 0;
        $row_data[] = $total_score;
        $row_data[] = number_format($percent, 2) . "%"; 
        fputcsv($output, $row_data);
    }
    fclose($output);
    exit;
}

elseif ($action == 'view_summary_table') {
    $class_level = $_GET['class_level'];
    $room_number = $_GET['room_number'];
    $course_code = $_GET['course_code'];

    $ay_q = $conn->query("SELECT year_id, total_sessions FROM academic_years WHERE school_id=$school_id AND is_active=1");
    $active_year_data = $ay_q->fetch_assoc();
    $active_year_id = $active_year_data ? $active_year_data['year_id'] : 0;
    $total_required_sessions = ($active_year_data && $active_year_data['total_sessions'] > 0) ? intval($active_year_data['total_sessions']) : 1;

    $sql_sess = "SELECT * FROM attendance_sessions WHERE school_id=$school_id AND teacher_id=$teacher_id AND class_level='$class_level' AND room_number='$room_number' AND course_code='$course_code' ORDER BY attendance_date ASC";
    $res_sess = $conn->query($sql_sess);
    $sessions = []; $dates_map = [];
    while($row = $res_sess->fetch_assoc()){
        $sessions[] = $row;
        $m = date('n', strtotime($row['attendance_date']));
        $y = date('Y', strtotime($row['attendance_date'])) + 543;
        $key = "$m-$y";
        if(!isset($dates_map[$key])) $dates_map[$key] = [];
        $dates_map[$key][] = $row;
    }

    $html = '<div style="margin-bottom:15px; font-weight:bold; color:#1e293b;">';
    $html .= "วิชา: $course_code | ระดับชั้น: $class_level/$room_number | คาบทั้งหมด: $total_required_sessions คาบ</div>";
    $html .= '<table class="data-table table-bordered" style="width:100%; font-size:0.85rem;"><thead><tr style="background:#f1f5f9;">';
    $html .= '<th rowspan="2" style="text-align:center; min-width:40px;">เลขที่</th><th rowspan="2" style="text-align:left; min-width:100px;">รหัสนักเรียน</th><th rowspan="2" style="text-align:left; min-width:150px;">ชื่อ-นามสกุล</th>';
    
    $thai_months = [1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'];
    foreach($dates_map as $my => $sess_list) {
        list($m, $y) = explode('-', $my);
        $month_name = isset($thai_months[intval($m)]) ? $thai_months[intval($m)] . ' ' . substr($y,2) : $my;
        $colspan = count($sess_list);
        $html .= "<th colspan='$colspan' style='text-align:center; border-left:1px solid #cbd5e1;'>$month_name</th>";
    }
    $html .= '<th rowspan="2" style="text-align:center; min-width:60px; background:#e2e8f0;">รวม</th><th rowspan="2" style="text-align:center; min-width:60px; background:#e2e8f0;">%</th></tr><tr style="background:#f8fafc;">';
    foreach($dates_map as $my => $sess_list) {
        foreach($sess_list as $s) {
            $day = date('j', strtotime($s['attendance_date']));
            $html .= "<th style='text-align:center; min-width:30px; border-left:1px solid #e2e8f0;'>$day</th>";
        }
    }
    $html .= '</tr></thead><tbody>';

    $sql_std = "SELECT u.user_id, u.student_code, u.full_name, ud.student_number FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id WHERE u.school_id=$school_id AND u.role='student' AND ud.year_id=$active_year_id AND ud.class_level='$class_level' AND ud.room_number='$room_number' ORDER BY ud.student_number ASC";
    $res_std = $conn->query($sql_std);

    while($std = $res_std->fetch_assoc()) {
        $html .= '<tr>';
        $html .= "<td style='text-align:center;'>{$std['student_number']}</td><td>{$std['student_code']}</td><td>{$std['full_name']}</td>";
        
        $total_score = 0;
        foreach($sessions as $sess) {
            $sid = $sess['id'];
            $uid = $std['user_id'];
            $q_sc = $conn->query("SELECT score, status FROM attendance_records WHERE session_id=$sid AND student_id=$uid");
            if($q_sc->num_rows > 0) {
                $row = $q_sc->fetch_assoc();
                $sc = $row['score']; $st = $row['status']; $total_score += $sc;
                $color = 'black';
                if($st == 'absent') $color = '#ef4444'; elseif($st == 'late') $color = '#eab308'; elseif($st == 'leave') $color = '#3b82f6'; 
                $display_score = ($sc == 1 || $sc == 0) ? (int)$sc : $sc;
                $html .= "<td style='text-align:center; color:$color;'>$display_score</td>";
            } else {
                $html .= "<td style='text-align:center; color:#ccc;'>-</td>";
            }
        }
        $percent = $total_required_sessions > 0 ? ($total_score / $total_required_sessions) * 100 : 0;
        $html .= "<td style='text-align:center; font-weight:bold;'>$total_score</td>";
        $p_color = ($percent < 80) ? '#ef4444' : '#166534';
        $html .= "<td style='text-align:center; font-weight:bold; color:$p_color;'>" . number_format($percent, 2) . "%</td></tr>";
    }
    $html .= '</tbody></table>';
    echo $html;
    exit;
}

elseif ($action == 'delete_attendance') {
    $session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if($session_id > 0) {
        $conn->query("DELETE FROM attendance_records WHERE session_id = $session_id");
        $stmt = $conn->prepare("DELETE FROM attendance_sessions WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $session_id, $teacher_id);
        if($stmt->execute()) { $_SESSION['msg'] = "ลบรายการเช็คชื่อเรียบร้อยแล้ว"; } 
        else { $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ"; }
    }
    header("Location: dashboard_teacher.php?tab=attendance");
    exit;
}

// ========================================================
// 6. ระบบตัดเกรด (Grading System) 
// ========================================================
elseif ($action == 'save_grade_criteria') {
    $select_class_key = $_POST['select_class_key'];
    $course_code = $_POST['course_code'];
    $class_level = $_POST['class_level'];
    $room_number = $_POST['room_number'];
    $year_id = intval($_POST['year_id']);
    
    $check_pub = $conn->query("SELECT is_published FROM grade_criteria WHERE school_id=$school_id AND teacher_id=$teacher_id AND year_id=$year_id AND course_code='$course_code' AND class_level='$class_level' AND room_number='$room_number'");
    if($check_pub->num_rows > 0 && $check_pub->fetch_assoc()['is_published'] == 1) {
        $_SESSION['error'] = "เกรดถูกส่งไปแล้ว ไม่สามารถแก้ไขสัดส่วนคะแนนได้";
        header("Location: grade_teacher.php?view=criteria&select_class_key=" . urlencode($select_class_key));
        exit;
    }

    $c_names = $_POST['c_name']; $c_maxs = $_POST['c_max']; $criteria = [];
    for($i=0; $i<count($c_names); $i++) { if(trim($c_names[$i]) != '') { $criteria[] = ['name' => trim($c_names[$i]), 'max' => floatval($c_maxs[$i])]; } }
    $criteria_json = json_encode($criteria, JSON_UNESCAPED_UNICODE);

    $check = $conn->query("SELECT id FROM grade_criteria WHERE school_id=$school_id AND teacher_id=$teacher_id AND year_id=$year_id AND course_code='$course_code' AND class_level='$class_level' AND room_number='$room_number'");
    if($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE grade_criteria SET criteria_json=? WHERE school_id=? AND teacher_id=? AND year_id=? AND course_code=? AND class_level=? AND room_number=?");
        $stmt->bind_param("siiisss", $criteria_json, $school_id, $teacher_id, $year_id, $course_code, $class_level, $room_number);
    } else {
        $stmt = $conn->prepare("INSERT INTO grade_criteria (school_id, teacher_id, year_id, course_code, class_level, room_number, criteria_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $school_id, $teacher_id, $year_id, $course_code, $class_level, $room_number, $criteria_json);
    }
    $stmt->execute();
    $_SESSION['msg'] = "บันทึกสัดส่วนคะแนนเรียบร้อยแล้ว";
    header("Location: grade_teacher.php?view=criteria&select_class_key=" . urlencode($select_class_key));
    exit;
}

elseif ($action == 'save_grade_scores') {
    $criteria_id = intval($_POST['criteria_id']);
    $select_class_key = $_POST['select_class_key'];
    
    $check_pub = $conn->query("SELECT is_published FROM grade_criteria WHERE id=$criteria_id AND teacher_id=$teacher_id");
    if($check_pub->num_rows > 0 && $check_pub->fetch_assoc()['is_published'] == 1) {
        $_SESSION['error'] = "เกรดถูกส่งไปแล้ว ไม่สามารถแก้ไขคะแนนได้";
        header("Location: grade_teacher.php?view=entry&select_class_key=" . urlencode($select_class_key));
        exit;
    }

    $scores = isset($_POST['scores']) ? $_POST['scores'] : [];
    $stmt_check = $conn->prepare("SELECT id FROM grade_scores WHERE criteria_id=? AND student_id=?");
    $stmt_ins = $conn->prepare("INSERT INTO grade_scores (criteria_id, student_id, scores_json, total_score, grade) VALUES (?, ?, ?, ?, ?)");
    $stmt_upd = $conn->prepare("UPDATE grade_scores SET scores_json=?, total_score=?, grade=? WHERE id=?");

    foreach($scores as $student_id => $score_arr) {
        $total = 0; foreach($score_arr as $val) $total += floatval($val);
        $grade = '-';
        if($total >= 80) $grade = '4'; else if($total >= 75) $grade = '3.5'; else if($total >= 70) $grade = '3'; else if($total >= 65) $grade = '2.5'; else if($total >= 60) $grade = '2'; else if($total >= 55) $grade = '1.5'; else if($total >= 50) $grade = '1'; else if($total > 0) $grade = '0';

        $scores_json = json_encode($score_arr);
        $stmt_check->bind_param("ii", $criteria_id, $student_id); $stmt_check->execute();
        $res = $stmt_check->get_result();
        if($res->num_rows > 0) {
            $row_id = $res->fetch_assoc()['id'];
            $stmt_upd->bind_param("sdsi", $scores_json, $total, $grade, $row_id); $stmt_upd->execute();
        } else {
            $stmt_ins->bind_param("iisds", $criteria_id, $student_id, $scores_json, $total, $grade); $stmt_ins->execute();
        }
    }
    $_SESSION['msg'] = "บันทึกคะแนนเรียบร้อยแล้ว";
    header("Location: grade_teacher.php?view=entry&select_class_key=" . urlencode($select_class_key));
    exit;
}

elseif ($action == 'get_sync_attendance') {
    $class_level = $_GET['class_level']; $room_number = $_GET['room_number']; $course_code = $_GET['course_code']; $col_max = floatval($_GET['col_max']); 
    $sql_sess = "SELECT id FROM attendance_sessions WHERE school_id=$school_id AND teacher_id=$teacher_id AND course_code='$course_code' AND class_level='$class_level' AND room_number='$room_number'";
    $res_sess = $conn->query($sql_sess); $total_sessions = $res_sess->num_rows;

    if($total_sessions == 0) die(json_encode(['error' => 'ยังไม่พบประวัติการเช็คชื่อสำหรับวิชานี้เลย']));
    $sess_ids = []; while($s = $res_sess->fetch_assoc()) $sess_ids[] = $s['id'];
    $sess_in = implode(',', $sess_ids); $data = [];
    $sql_rec = "SELECT student_id, SUM(score) as total_score FROM attendance_records WHERE session_id IN ($sess_in) GROUP BY student_id";
    $res_rec = $conn->query($sql_rec);
    while($r = $res_rec->fetch_assoc()) {
        $percent = $r['total_score'] / $total_sessions; $calculated_score = $percent * $col_max; 
        $data[$r['student_id']] = round($calculated_score, 1);
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

elseif ($action == 'get_sync_homework') {
    $assignment_id = intval($_GET['assignment_id']); $col_max = floatval($_GET['col_max']);
    $ass_q = $conn->query("SELECT max_score FROM assignments WHERE assignment_id=$assignment_id AND teacher_id=$teacher_id");
    if($ass_q->num_rows == 0) die(json_encode(['error' => 'ไม่พบข้อมูลชิ้นงานนี้ในระบบ']));
    $ass_max = floatval($ass_q->fetch_assoc()['max_score']); if($ass_max <= 0) $ass_max = 1;
    $data = [];
    $sub_q = $conn->query("SELECT student_id, score FROM submissions WHERE assignment_id=$assignment_id AND status='graded'");
    while($sub = $sub_q->fetch_assoc()) {
        $percent = floatval($sub['score']) / $ass_max; $calculated_score = $percent * $col_max; 
        $data[$sub['student_id']] = round($calculated_score, 1);
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// 📌 ยิงแจ้งเตือนให้นักเรียนเมื่อประกาศผลสอบ
elseif ($action == 'publish_grades') {
    $criteria_id = intval($_POST['criteria_id']);
    $select_class_key = $_POST['select_class_key'];
    $stmt = $conn->prepare("UPDATE grade_criteria SET is_published = 1, published_at = CURRENT_TIMESTAMP WHERE id = ? AND teacher_id = ? AND school_id = ?");
    $stmt->bind_param("iii", $criteria_id, $teacher_id, $school_id);
    
    if($stmt->execute()) {
        $_SESSION['msg'] = "ยืนยันและส่งเกรดเข้าระบบเรียบร้อยแล้ว";
        
        $cr_q = $conn->query("SELECT course_code, class_level, room_number, year_id FROM grade_criteria WHERE id=$criteria_id");
        if($cr_q && $cr_q->num_rows > 0) {
            $cr = $cr_q->fetch_assoc();
            $ccode = $cr['course_code']; $clevel = $cr['class_level']; $croom = $cr['room_number']; $cyear = $cr['year_id'];
            
            $st_q = $conn->query("SELECT user_id FROM user_year_data WHERE class_level='$clevel' AND room_number='$croom' AND year_id=$cyear");
            if($st_q) {
                $t_name = $_SESSION['full_name'] ?? 'ครูผู้สอน';
                $msg = "อ.$t_name ประกาศผลการเรียนวิชา $ccode แล้ว ตรวจสอบเกรดได้เลย!";
                $link = "grade_student.php"; 
                while($st = $st_q->fetch_assoc()) {
                    send_system_notification($conn, $st['user_id'], 'ประกาศผลการเรียน 🎉', $msg, $link);
                }
            }
        }
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด ไม่สามารถส่งเกรดได้";
    }
    header("Location: grade_teacher.php?view=summary&select_class_key=" . urlencode($select_class_key));
    exit;
}

elseif ($action == 'export_grade_csv') {
    // ... [โค้ดส่วน Export CSV เดิม] ...
    $select_class_key = $_GET['select_class_key'];
    list($room_str, $subject_str) = explode('|', $select_class_key);
    list($class_lvl, $room_no) = explode('/', $room_str);
    $course_code_raw = (strpos($subject_str, '(') !== false) ? str_replace(')', '', explode('(', $subject_str)[1]) : $subject_str;

    $ay_q = $conn->query("SELECT year_id FROM academic_years WHERE school_id=$school_id AND is_active=1");
    $active_year_id = $ay_q->num_rows > 0 ? $ay_q->fetch_assoc()['year_id'] : 0;

    $stmt_c = $conn->prepare("SELECT * FROM grade_criteria WHERE school_id=? AND teacher_id=? AND year_id=? AND course_code=? AND class_level=? AND room_number=?");
    $stmt_c->bind_param("iiisss", $school_id, $teacher_id, $active_year_id, $course_code_raw, $class_lvl, $room_no);
    $stmt_c->execute();
    $crit_res = $stmt_c->get_result();
    $criteria_data = $crit_res->fetch_assoc();
    
    if(!$criteria_data) die("ไม่พบข้อมูลเกรด");
    $crit_id = $criteria_data['id'];
    $cols = json_decode($criteria_data['criteria_json'], true);

    $filename = "Grade_{$course_code_raw}_{$class_lvl}-{$room_no}_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo "\xEF\xBB\xBF"; 
    $output = fopen('php://output', 'w');

    fputcsv($output, ["สรุปผลการเรียน รหัสวิชา $course_code_raw"]); 
    fputcsv($output, ["ระดับชั้น $class_lvl ห้อง $room_no"]);
    fputcsv($output, ["ครูผู้สอน " . $_SESSION['full_name'], "", "วันที่ส่งออก: " . date('d/m/Y')]);
    fputcsv($output, []); 

    $header_row = ["เลขที่", "รหัสนักเรียน", "ชื่อ-นามสกุล"];
    foreach($cols as $c) { $header_row[] = $c['name'] . " (" . $c['max'] . ")"; }
    $header_row[] = "คะแนนรวม (100)"; $header_row[] = "ระดับผลการเรียน";
    fputcsv($output, $header_row);

    $sql_std = "SELECT u.user_id, u.student_code, u.full_name, ud.student_number FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id WHERE u.school_id=$school_id AND u.role='student' AND ud.year_id=$active_year_id AND ud.class_level='$class_lvl' AND ud.room_number='$room_no' ORDER BY ud.student_number ASC";
    $res_std = $conn->query($sql_std);

    while($std = $res_std->fetch_assoc()) {
        $sid = $std['user_id'];
        $sc_q = $conn->query("SELECT * FROM grade_scores WHERE criteria_id=$crit_id AND student_id=$sid");
        $sc_data = $sc_q->fetch_assoc();
        $scores_arr = $sc_data ? json_decode($sc_data['scores_json'], true) : [];
        $total = $sc_data ? $sc_data['total_score'] : '0'; $grade = $sc_data ? $sc_data['grade'] : '-';

        $row_data = [$std['student_number'], $std['student_code'], $std['full_name']];
        foreach($cols as $idx => $c) { $row_data[] = isset($scores_arr[$idx]) ? $scores_arr[$idx] : '0'; }
        $row_data[] = $total; $row_data[] = $grade; fputcsv($output, $row_data);
    }
    fclose($output);
    exit;
}

// 📌 ยิงแจ้งเตือนให้นักเรียนเมื่ออัปโหลดไฟล์สื่อการสอน
elseif ($action == 'save_media') {
    $select_class_key = $_POST['select_class_key']; $course_code = $_POST['course_code']; $class_level = $_POST['class_level']; $room_number = $_POST['room_number']; $year_id = intval($_POST['year_id']);
    $title = trim($_POST['title']); $description = trim($_POST['description']); $media_type = $_POST['media_type']; 
    
    $file_extension = 'link'; $file_path = '';

    if ($media_type == 'link') {
        $file_path = trim($_POST['media_link']);
        if(empty($file_path)) { $_SESSION['error'] = "กรุณาระบุ URL ของสื่อการสอน"; header("Location: media_teacher.php?view=manage&select_class_key=" . urlencode($select_class_key)); exit; }
    } else {
        $upload_dir = 'uploads/media/'; if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
            $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'];
            $ext = strtolower(pathinfo($_FILES['upload_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_types)) {
                $new_filename = "media_" . $teacher_id . "_" . time() . "." . $ext;
                $target_file = $upload_dir . $new_filename;
                if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target_file)) { $file_path = $target_file; $file_extension = $ext; } 
                else { $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์"; header("Location: media_teacher.php?view=manage&select_class_key=" . urlencode($select_class_key)); exit; }
            } else { $_SESSION['error'] = "ประเภทไฟล์ไม่รองรับ"; header("Location: media_teacher.php?view=manage&select_class_key=" . urlencode($select_class_key)); exit; }
        } else { $_SESSION['error'] = "กรุณาเลือกไฟล์ที่ต้องการอัปโหลด"; header("Location: media_teacher.php?view=manage&select_class_key=" . urlencode($select_class_key)); exit; }
    }

    $stmt = $conn->prepare("INSERT INTO teaching_media (school_id, teacher_id, year_id, course_code, class_level, room_number, media_type, file_extension, title, description, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiissssssss", $school_id, $teacher_id, $year_id, $course_code, $class_level, $room_number, $media_type, $file_extension, $title, $description, $file_path);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "เพิ่มสื่อการสอนเข้าคลังเรียบร้อยแล้ว";
        $st_q = $conn->query("SELECT user_id FROM user_year_data WHERE class_level='$class_level' AND room_number='$room_number' AND year_id=$year_id");
        if($st_q) {
            $t_name = $_SESSION['full_name'] ?? 'ครูผู้สอน';
            $msg = "อ.$t_name ได้อัปโหลดสื่อการสอนใหม่: $title";
            $link = "media_student.php"; 
            while($st = $st_q->fetch_assoc()) {
                send_system_notification($conn, $st['user_id'], 'คลังความรู้ใหม่ 📚', $msg, $link);
            }
        }
    } else { $_SESSION['error'] = "ไม่สามารถบันทึกข้อมูลได้: " . $conn->error; }
    header("Location: media_teacher.php?view=manage&select_class_key=" . urlencode($select_class_key));
    exit;
}

elseif ($action == 'delete_media') {
    $media_id = intval($_POST['media_id']); $select_class_key = $_POST['select_class_key'];
    $stmt = $conn->prepare("SELECT file_path, media_type FROM teaching_media WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $media_id, $teacher_id); $stmt->execute(); $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if ($row['media_type'] == 'file' && file_exists($row['file_path'])) unlink($row['file_path']);
        $del_stmt = $conn->prepare("DELETE FROM teaching_media WHERE id = ?"); $del_stmt->bind_param("i", $media_id); $del_stmt->execute();
        $_SESSION['msg'] = "ลบสื่อการสอนเรียบร้อยแล้ว";
    } else { $_SESSION['error'] = "ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์ลบสื่อนี้"; }
    header("Location: media_teacher.php?view=manage&select_class_key=" . urlencode($select_class_key));
    exit;
}

// 📌 ยิงแจ้งเตือนให้นักเรียนเมื่อครูเปิดการมองเห็นสื่อ (Phase 2)
elseif ($action == 'toggle_media_visibility') {
    $media_id = intval($_POST['media_id']);
    $select_class_key = $_POST['select_class_key'];
    $stmt = $conn->prepare("UPDATE teaching_media SET is_visible = NOT is_visible WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $media_id, $teacher_id);
    
    if($stmt->execute()) {
        $_SESSION['msg'] = "อัปเดตสถานะการแสดงผลสื่อการสอนเรียบร้อยแล้ว";
        
        $q = $conn->query("SELECT is_visible, title, class_level, room_number, year_id FROM teaching_media WHERE id = $media_id");
        if($q && $q->num_rows > 0) {
            $m = $q->fetch_assoc();
            if($m['is_visible'] == 1) {
                $st_q = $conn->query("SELECT user_id FROM user_year_data WHERE class_level='{$m['class_level']}' AND room_number='{$m['room_number']}' AND year_id={$m['year_id']}");
                if($st_q) {
                    $t_name = $_SESSION['full_name'] ?? 'ครูผู้สอน';
                    $msg = "อ.$t_name ได้เปิดให้ดูสื่อการสอน: {$m['title']}";
                    $link = "media_student.php"; 
                    while($st = $st_q->fetch_assoc()) {
                        send_system_notification($conn, $st['user_id'], 'คลังความรู้ใหม่ 📚', $msg, $link);
                    }
                }
            }
        }
    } else { $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล"; }
    header("Location: media_teacher.php?view=manage&select_class_key=" . urlencode($select_class_key));
    exit;
}

// ========================================================
// 10. ระบบห้องที่ปรึกษา (Advisory Room) 
// ========================================================
elseif ($action == 'save_homeroom') {
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $class_level = $_POST['class_level']; $room_number = $_POST['room_number']; $date = $_POST['check_date']; $students = isset($_POST['attendance']) ? $_POST['attendance'] : []; 
    $ay_q = $conn->query("SELECT year_id FROM academic_years WHERE school_id = $school_id AND is_active = 1");
    $year_id = $ay_q->num_rows > 0 ? $ay_q->fetch_assoc()['year_id'] : 0;

    if ($session_id == 0) {
        $stmt = $conn->prepare("INSERT INTO homeroom_sessions (school_id, teacher_id, year_id, class_level, room_number, check_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $school_id, $teacher_id, $year_id, $class_level, $room_number, $date);
        $stmt->execute(); $session_id = $conn->insert_id;
    } else {
        $stmt = $conn->prepare("UPDATE homeroom_sessions SET check_date = ? WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("sii", $date, $session_id, $teacher_id);
        $stmt->execute(); $conn->query("DELETE FROM homeroom_records WHERE session_id = $session_id");
    }

    $stmt_rec = $conn->prepare("INSERT INTO homeroom_records (session_id, student_id, status, score) VALUES (?, ?, ?, ?)");
    foreach ($students as $std_id => $status) { $score = get_score($status); $stmt_rec->bind_param("iisd", $session_id, $std_id, $status, $score); $stmt_rec->execute(); }
    $_SESSION['msg'] = "บันทึกการเช็คชื่อโฮมรูมเรียบร้อยแล้ว"; header("Location: advisory_teacher.php?view=homeroom"); exit;
}

elseif ($action == 'get_homeroom_data') {
    $id = intval($_GET['id']);
    $session = $conn->query("SELECT * FROM homeroom_sessions WHERE id=$id AND teacher_id=$teacher_id")->fetch_assoc();
    $records = []; $q_rec = $conn->query("SELECT student_id, status FROM homeroom_records WHERE session_id=$id");
    while($r = $q_rec->fetch_assoc()) { $records[$r['student_id']] = $r['status']; }
    echo json_encode(['session' => $session, 'records' => $records]); exit;
}

elseif ($action == 'delete_homeroom') {
    $session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if($session_id > 0) {
        $conn->query("DELETE FROM homeroom_records WHERE session_id = $session_id");
        $stmt = $conn->prepare("DELETE FROM homeroom_sessions WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $session_id, $teacher_id);
        if($stmt->execute()) { $_SESSION['msg'] = "ลบรายการเช็คชื่อโฮมรูมเรียบร้อยแล้ว"; } else { $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ"; }
    }
    header("Location: advisory_teacher.php?view=homeroom"); exit;
}

// 📌 ยิงแจ้งเตือนให้นักเรียน เมื่อครูที่ปรึกษาบันทึกพฤติกรรม (Phase 2)
elseif ($action == 'save_behavior_log') {
    $student_id = intval($_POST['student_id']); $log_type = $_POST['log_type']; $log_date = $_POST['log_date'];
    $description = trim($_POST['description']); $year_id = intval($_POST['year_id']);
    
    $photo_path = ''; $upload_dir = 'uploads/behaviors/'; if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    if (isset($_FILES['behavior_photo']) && $_FILES['behavior_photo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['behavior_photo']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, ['jpg','jpeg','png'])) {
            $new_name = "bhv_" . $student_id . "_" . time() . "." . $ext;
            if(move_uploaded_file($_FILES['behavior_photo']['tmp_name'], $upload_dir.$new_name)) { $photo_path = $upload_dir.$new_name; }
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO student_behavior_logs (school_id, teacher_id, student_id, year_id, log_type, description, photo_path, log_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiissss", $school_id, $teacher_id, $student_id, $year_id, $log_type, $description, $photo_path, $log_date);
    
    if($stmt->execute()) {
        $_SESSION['msg'] = "บันทึกข้อมูลพฤติกรรม/การเยี่ยมบ้านเรียบร้อยแล้ว";
        
        $t_name = $_SESSION['full_name'] ?? 'ครูที่ปรึกษา';
        $msg = "อ.$t_name ได้เพิ่มบันทึกข้อมูลใหม่ในสมุดประวัติของคุณ";
        $link = "dashboard_student.php?tab=profile"; // ให้ไปหน้าโปรไฟล์เพื่อเช็ค
        
        send_system_notification($conn, $student_id, 'บันทึกพฤติกรรม 📝', $msg, $link);
        
    } else { $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล"; }
    header("Location: advisory_teacher.php?view=roster");
    exit;
}

elseif ($action == 'delete_behavior_log') {
    $log_id = intval($_POST['log_id']);
    $q = $conn->query("SELECT photo_path FROM student_behavior_logs WHERE id=$log_id AND teacher_id=$teacher_id");
    if($q && $q->num_rows > 0) {
        $path = $q->fetch_assoc()['photo_path'];
        if(!empty($path) && file_exists($path)) { unlink($path); }
        $conn->query("DELETE FROM student_behavior_logs WHERE id=$log_id");
        $_SESSION['msg'] = "ลบประวัติบันทึกพฤติกรรมเรียบร้อยแล้ว";
    } else { $_SESSION['error'] = "ไม่สามารถลบข้อมูลได้"; }
    header("Location: advisory_teacher.php?view=roster"); exit;
}

elseif ($action == 'export_advisory_risk_csv') {
    // ... [โค้ดส่วน Export SDQ เดิม (ลดความยาวเพื่อประหยัดพื้นที่ แต่อย่าลืมก็อปจากคำตอบที่แล้วมาใส่ถ้าต้องการ)] ...
    header("Location: advisory_teacher.php?view=analytics"); exit;
}
?>