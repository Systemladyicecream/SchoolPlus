<?php
// ชื่อไฟล์: grade_teacher.php
session_start();
require_once 'db_connect.php';

// --- SECURITY: AUTO LOGOUT SYSTEM ---
$timeout_duration = 300; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    setcookie("user_login", "", time() - 3600, "/");
    header("Location: index.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];
$school_row = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school_row['school_name'];

// 1. ดึงปีการศึกษาปัจจุบัน (Active Year)
$ay_q = $conn->query("SELECT year_id, year_name, term FROM academic_years WHERE school_id = $school_id AND is_active = 1");
$active_year_row = $ay_q->fetch_assoc();
$active_year_id = $active_year_row ? $active_year_row['year_id'] : 0;
$current_term_text = $active_year_row ? "ปี {$active_year_row['year_name']}/{$active_year_row['term']}" : "ยังไม่ตั้งค่าปีการศึกษา";

// 2. ดึงข้อมูลครู
$sql_teacher = "SELECT u.*, ud.subjects_taught, ud.room_number 
                FROM users u 
                LEFT JOIN user_year_data ud ON u.user_id = ud.user_id AND ud.year_id = $active_year_id 
                WHERE u.user_id = $user_id";
$teacher = $conn->query($sql_teacher)->fetch_assoc();

// จัดการรูปโปรไฟล์
$img_src = $teacher['profile_img'];
if(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png' && !file_exists("uploads/".$img_src)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'; 
} elseif(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png') {
   $img_src = "uploads/" . $img_src;
} elseif ($img_src == 'default_avatar.png') {
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $teacher['username'];
}

$view = isset($_GET['view']) ? $_GET['view'] : 'overview';
$select_class_key = isset($_GET['select_class_key']) ? $_GET['select_class_key'] : '';

// Auto Migration for Grades (Phase 1-3)
$conn->query("CREATE TABLE IF NOT EXISTS grade_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT,
    teacher_id INT,
    year_id INT,
    course_code VARCHAR(50),
    class_level VARCHAR(50),
    room_number VARCHAR(50),
    criteria_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Phase 3: Add Publish Status Columns safely
$check_pub = $conn->query("SHOW COLUMNS FROM grade_criteria LIKE 'is_published'");
if ($check_pub && $check_pub->num_rows == 0) {
    $conn->query("ALTER TABLE grade_criteria ADD COLUMN is_published TINYINT(1) DEFAULT 0");
}
$check_date = $conn->query("SHOW COLUMNS FROM grade_criteria LIKE 'published_at'");
if ($check_date && $check_date->num_rows == 0) {
    $conn->query("ALTER TABLE grade_criteria ADD COLUMN published_at TIMESTAMP NULL");
}

$conn->query("CREATE TABLE IF NOT EXISTS grade_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criteria_id INT,
    student_id INT,
    scores_json TEXT,
    total_score DECIMAL(5,2),
    grade VARCHAR(10),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Prepare Class List
$my_classes = [];
if(isset($teacher['subjects_taught'])) {
    $json = json_decode($teacher['subjects_taught'], true);
    if(is_array($json)) {
        foreach($json as $j) {
            $r = $j['room'];
            if(isset($j['subjects']) && is_array($j['subjects'])) {
                foreach($j['subjects'] as $s) {
                    $key = "$r|$s";
                    $my_classes[$key] = "ห้อง $r - วิชา $s";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ระบบตัดเกรด - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-bar { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .status-badge { font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; font-weight: bold; margin-left: 5px; white-space: nowrap; display: inline-block; }
        .badge-red { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .badge-yellow { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .badge-green { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        
        /* สไตล์สำหรับกล่องสรุปผล Phase 3 */
        .stat-card {
            background: #fff; border-radius: 16px; padding: 20px;
            box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);
            flex: 1; min-width: 150px; text-align: center;
        }
        .stat-card h3 { font-size: 2rem; color: var(--primary-color); margin-bottom: 5px; }
        .stat-card p { color: var(--text-secondary); font-size: 0.9rem; margin: 0; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <i class="fa-solid fa-chalkboard-user"></i>
            <div>Smart School Plus <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;"><?php echo $school_name; ?></span></div>
        </div>
        <div class="user-info">
            <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $teacher['profile_frame']; ?>">
            <span>อ.<?php echo $teacher['full_name']; ?></span>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $teacher['profile_frame']; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo $teacher['full_name']; ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    ที่ปรึกษา: <?php echo $teacher['room_number'] ?? '-'; ?>
                </div>
                <div style="margin-top:10px; font-size:0.85rem; color:var(--primary-color); font-weight:600;">
                    <i class="fa-regular fa-calendar-check"></i> <?php echo $current_term_text; ?>
                </div>
            </div>

            <h3>เมนูครู</h3>
            <a href="dashboard_teacher.php?tab=home" class="menu-item"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <a href="dashboard_teacher.php?tab=attendance" class="menu-item"><i class="fa-solid fa-clipboard-user"></i> เช็คชื่อ</a>
            <a href="homework_teacher.php" class="menu-item"><i class="fa-solid fa-book-open"></i> ระบบสั่งงาน</a>
            <a href="grade_teacher.php" class="menu-item active"><i class="fa-solid fa-chart-simple"></i> ตัดเกรด</a>
            <a href="dashboard_teacher.php?tab=media" class="menu-item"><i class="fa-solid fa-folder-open"></i> สื่อการสอน</a>
            <a href="dashboard_teacher.php?tab=advisory" class="menu-item"><i class="fa-solid fa-people-roof"></i> ห้องที่ปรึกษา</a>
            <div style="height:1px; background:var(--border-color); margin:10px 0;"></div>
            <a href="dashboard_teacher.php?tab=profile" class="menu-item"><i class="fa-solid fa-user-gear"></i> ข้อมูลส่วนตัว</a>
        </aside>

        <main class="content-area">
            <?php if(isset($_SESSION['msg'])): ?>
                <div class="alert-box alert-success">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert-box alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h2 class="page-title">ระบบตัดเกรด (Grading System)</h2>
            </div>
            
            <div class="filter-bar" style="margin-bottom:20px;">
                <a href="?view=overview" class="btn-action <?php echo $view=='overview'?'btn-add':''; ?>" style="<?php echo $view!='overview'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-layer-group"></i> ภาพรวมรายวิชา</a>
                <?php if($select_class_key): ?>
                    <a href="?view=criteria&select_class_key=<?php echo urlencode($select_class_key); ?>" class="btn-action <?php echo $view=='criteria'?'btn-add':''; ?>" style="<?php echo $view!='criteria'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-gear"></i> ตั้งค่าสัดส่วนคะแนน</a>
                    <a href="?view=entry&select_class_key=<?php echo urlencode($select_class_key); ?>" class="btn-action <?php echo $view=='entry'?'btn-add':''; ?>" style="<?php echo $view!='entry'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-pen-to-square"></i> บันทึกคะแนน</a>
                    <a href="?view=summary&select_class_key=<?php echo urlencode($select_class_key); ?>" class="btn-action <?php echo $view=='summary'?'btn-add':''; ?>" style="<?php echo $view!='summary'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-chart-pie"></i> สรุปผลและส่งออก</a>
                <?php endif; ?>
            </div>

            <?php if($view == 'overview'): ?>
                <div class="menu-grid">
                    <?php foreach($my_classes as $k => $label): ?>
                    <a href="?view=criteria&select_class_key=<?php echo urlencode($k); ?>" class="menu-card" style="align-items:flex-start; aspect-ratio:auto; min-height:120px;">
                        <div style="font-weight:bold; font-size:1.1rem; color:var(--primary-color);"><i class="fa-solid fa-book"></i> <?php echo $label; ?></div>
                        <div style="margin-top:15px;">
                            <span class="btn-action" style="background:#eff6ff; color:var(--primary-color);"><i class="fa-solid fa-arrow-right"></i> จัดการคะแนน</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php 
            if(in_array($view, ['criteria', 'entry', 'summary']) && $select_class_key): 
                list($room_str, $subject_str) = explode('|', $select_class_key);
                list($class_lvl, $room_no) = explode('/', $room_str);
                $course_code_raw = (strpos($subject_str, '(') !== false) ? str_replace(')', '', explode('(', $subject_str)[1]) : $subject_str;

                // ดึง Criteria ปัจจุบัน
                $stmt_c = $conn->prepare("SELECT * FROM grade_criteria WHERE school_id=? AND teacher_id=? AND year_id=? AND course_code=? AND class_level=? AND room_number=?");
                $stmt_c->bind_param("iiisss", $school_id, $user_id, $active_year_id, $course_code_raw, $class_lvl, $room_no);
                $stmt_c->execute();
                $crit_res = $stmt_c->get_result();
                $criteria_data = $crit_res->fetch_assoc();
                $crit_id = $criteria_data ? $criteria_data['id'] : 0;
                $cols = $criteria_data ? json_decode($criteria_data['criteria_json'], true) : [];
                $is_published = $criteria_data ? $criteria_data['is_published'] : 0;
            ?>
                
                <?php if($view == 'criteria'): ?>
                    <div class="card-form">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h3 style="color:var(--primary-color); margin:0;"><i class="fa-solid fa-gear"></i> ตั้งค่าสัดส่วนคะแนน (<?php echo "$course_code_raw $class_lvl/$room_no"; ?>)</h3>
                            <?php if($is_published): ?>
                                <span class="status-badge badge-green"><i class="fa-solid fa-lock"></i> ส่งเกรดแล้ว</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($is_published): ?>
                            <div class="alert-box alert-success" style="background:#f0fdf4;">
                                <i class="fa-solid fa-lock"></i> ข้อมูลถูกล็อกเนื่องจากทำการยืนยันและส่งเกรดไปแล้ว ไม่สามารถแก้ไขโครงสร้างคะแนนได้
                            </div>
                        <?php endif; ?>

                        <form action="teacher_action.php" method="POST">
                            <input type="hidden" name="action" value="save_grade_criteria">
                            <input type="hidden" name="select_class_key" value="<?php echo htmlspecialchars($select_class_key); ?>">
                            <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($course_code_raw); ?>">
                            <input type="hidden" name="class_level" value="<?php echo htmlspecialchars($class_lvl); ?>">
                            <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room_no); ?>">
                            <input type="hidden" name="year_id" value="<?php echo $active_year_id; ?>">

                            <div id="criteria_wrap">
                                <?php 
                                if(empty($cols)) {
                                    echo '<div class="c-row" style="display:flex; gap:10px; margin-bottom:10px;">
                                            <input type="text" name="c_name[]" class="form-control" placeholder="ชื่อช่องคะแนน (เช่น ระหว่างเรียน)" value="ระหว่างเรียน" required>
                                            <input type="number" name="c_max[]" class="form-control" placeholder="คะแนนเต็ม" value="70" required style="width:120px;">
                                          </div>';
                                    echo '<div class="c-row" style="display:flex; gap:10px; margin-bottom:10px;">
                                            <input type="text" name="c_name[]" class="form-control" placeholder="ชื่อช่องคะแนน (เช่น ปลายภาค)" value="ปลายภาค" required>
                                            <input type="number" name="c_max[]" class="form-control" placeholder="คะแนนเต็ม" value="30" required style="width:120px;">
                                          </div>';
                                } else {
                                    foreach($cols as $c) {
                                        $readonly = $is_published ? 'readonly style="background:#f1f5f9; color:#94a3b8;"' : '';
                                        $del_btn = $is_published ? '' : '<button type="button" class="btn-action btn-delete" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>';
                                        echo '<div class="c-row" style="display:flex; gap:10px; margin-bottom:10px;">
                                                <input type="text" name="c_name[]" class="form-control" placeholder="ชื่อช่องคะแนน" value="'.$c['name'].'" required '.$readonly.'>
                                                <input type="number" name="c_max[]" class="form-control" placeholder="คะแนนเต็ม" value="'.$c['max'].'" required style="width:120px;" '.$readonly.'>
                                                '.$del_btn.'
                                              </div>';
                                    }
                                }
                                ?>
                            </div>
                            
                            <?php if(!$is_published): ?>
                                <button type="button" class="btn-action" style="background:#f1f5f9; color:var(--text-main); margin-bottom:20px;" onclick="addCrit()"><i class="fa-solid fa-plus"></i> เพิ่มช่องคะแนน</button>
                                
                                <div style="background:#fffbeb; padding:15px; border-radius:8px; color:#b45309; margin-bottom:20px; border:1px solid #fde68a;">
                                    <strong>คำแนะนำ:</strong> ผลรวมของคะแนนเต็มทุกช่องควรเท่ากับ 100 เพื่อการตัดเกรดที่แม่นยำ 
                                </div>
                                <button type="submit" class="btn-add" style="width:100%;"><i class="fa-solid fa-save"></i> บันทึกสัดส่วนคะแนน</button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <script>
                    function addCrit() {
                        const div = document.createElement('div');
                        div.className = 'c-row';
                        div.style.cssText = 'display:flex; gap:10px; margin-bottom:10px;';
                        div.innerHTML = `<input type="text" name="c_name[]" class="form-control" placeholder="ชื่อช่องคะแนน" required>
                                         <input type="number" name="c_max[]" class="form-control" placeholder="คะแนนเต็ม" required style="width:120px;">
                                         <button type="button" class="btn-action btn-delete" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>`;
                        document.getElementById('criteria_wrap').appendChild(div);
                    }
                    </script>
                <?php endif; ?>

                <?php if($view == 'entry'): ?>
                    <?php if(empty($cols)): ?>
                        <div style="text-align:center; padding:40px; background:white; border-radius:12px; border:1px dashed #cbd5e1;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size:3rem; color:#f59e0b; margin-bottom:15px;"></i>
                            <h3>ยังไม่ได้ตั้งค่าสัดส่วนคะแนน</h3>
                            <p>กรุณาไปที่เมนู "ตั้งค่าสัดส่วนคะแนน" ก่อนทำการบันทึกคะแนน</p>
                            <a href="?view=criteria&select_class_key=<?php echo urlencode($select_class_key); ?>" class="btn-add" style="margin-top:15px;">ไปตั้งค่าเลย</a>
                        </div>
                    <?php else: ?>
                        
                        <?php if($is_published): ?>
                            <div class="alert-box alert-success" style="background:#f0fdf4;">
                                <i class="fa-solid fa-lock"></i> ข้อมูลเกรดถูกยืนยันและส่งแล้ว ไม่สามารถแก้ไขได้ หากต้องการแก้ไขกรุณาติดต่อผู้ดูแลระบบ
                            </div>
                        <?php else: ?>
                            <div class="card-form" style="padding:20px 32px; background:linear-gradient(135deg, #f8fafc, #f1f5f9); margin-bottom:20px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                                    <div>
                                        <h4 style="margin:0; color:var(--text-main);"><i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> เครื่องมือดึงคะแนนอัตโนมัติ</h4>
                                        <p style="margin:0; font-size:0.85rem; color:var(--text-secondary);">ระบบจะคำนวณและแปลงสัดส่วนคะแนนให้พอดีกับช่องคะแนนเป้าหมาย</p>
                                    </div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                        <button type="button" class="btn-action" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a;" onclick="openModal('modal_sync_attendance')">
                                            <i class="fa-solid fa-clock-rotate-left"></i> ดึงคะแนนเวลาเรียน
                                        </button>
                                        <button type="button" class="btn-action" style="background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe;" onclick="openModal('modal_sync_homework')">
                                            <i class="fa-solid fa-book-open"></i> ดึงคะแนนระบบสั่งงาน
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card-form">
                            <h3 style="margin-bottom:15px; color:var(--primary-color);"><i class="fa-solid fa-pen-to-square"></i> บันทึกคะแนน (<?php echo "$course_code_raw $class_lvl/$room_no"; ?>)</h3>
                            
                            <form action="teacher_action.php" method="POST" id="gradeEntryForm">
                                <input type="hidden" name="action" value="save_grade_scores">
                                <input type="hidden" name="criteria_id" value="<?php echo $crit_id; ?>">
                                <input type="hidden" name="select_class_key" value="<?php echo htmlspecialchars($select_class_key); ?>">

                                <div class="table-responsive">
                                    <table class="data-table" id="gradeTable">
                                        <thead>
                                            <tr>
                                                <th style="width:60px; text-align:center;">เลขที่</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <?php foreach($cols as $idx => $c): ?>
                                                    <th style="text-align:center; min-width:80px;">
                                                        <?php echo $c['name']; ?><br>
                                                        <small style="color:var(--primary-color);">(<?php echo $c['max']; ?>)</small>
                                                    </th>
                                                <?php endforeach; ?>
                                                <th style="text-align:center; background:#f8fafc; color:#0f172a;">รวม (100)</th>
                                                <th style="text-align:center; background:#eff6ff; color:var(--primary-color);">เกรด</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // คำนวณค่าคงที่สำหรับ เช็ค มส. และ ร.
                                            // ** แก้ไข id เป็น session_id หรือ id ตามตาราง แต่ใน attendance_sessions มันคือ id ถูกต้องแล้ว
                                            $sess_q = $conn->query("SELECT id FROM attendance_sessions WHERE school_id=$school_id AND teacher_id=$user_id AND class_level='$class_lvl' AND room_number='$room_no' AND course_code='$course_code_raw'");
                                            $total_course_sessions = $sess_q->num_rows;
                                            $sess_ids = [];
                                            while($s = $sess_q->fetch_assoc()) $sess_ids[] = $s['id'];
                                            $sess_in = !empty($sess_ids) ? implode(',', $sess_ids) : '0';
                                            
                                            // ** แก้ไข id เป็น assignment_id สำหรับตาราง assignments ป้องกันปัญหา Unknown column 'id' **
                                            $ass_q = $conn->query("SELECT assignment_id FROM assignments WHERE school_id=$school_id AND teacher_id=$user_id AND class_level='$class_lvl' AND room_number='$room_no' AND course_code='$course_code_raw'");
                                            $total_assignments = $ass_q->num_rows;
                                            $ass_ids = [];
                                            while($a = $ass_q->fetch_assoc()) $ass_ids[] = $a['assignment_id'];
                                            $ass_in = !empty($ass_ids) ? implode(',', $ass_ids) : '0';

                                            $sql_std = "SELECT u.user_id, u.full_name, ud.student_number 
                                                        FROM users u 
                                                        JOIN user_year_data ud ON u.user_id = ud.user_id 
                                                        WHERE u.school_id=$school_id AND u.role='student' 
                                                        AND ud.year_id=$active_year_id 
                                                        AND ud.class_level='$class_lvl' AND ud.room_number='$room_no'
                                                        ORDER BY ud.student_number ASC";
                                            $res_std = $conn->query($sql_std);
                                            
                                            while($std = $res_std->fetch_assoc()):
                                                $sid = $std['user_id'];
                                                $sc_q = $conn->query("SELECT * FROM grade_scores WHERE criteria_id=$crit_id AND student_id=$sid");
                                                $sc_data = $sc_q->fetch_assoc();
                                                $scores_arr = $sc_data ? json_decode($sc_data['scores_json'], true) : [];
                                                $total = $sc_data ? $sc_data['total_score'] : 0;
                                                $grade = $sc_data ? $sc_data['grade'] : '-';

                                                $status_badges = '';
                                                if($total_course_sessions > 0) {
                                                    $att_rec_q = $conn->query("SELECT SUM(score) as total_score FROM attendance_records WHERE session_id IN ($sess_in) AND student_id=$sid");
                                                    $att_row = $att_rec_q->fetch_assoc();
                                                    $att_score = $att_row['total_score'] ?? 0;
                                                    $att_percent = ($att_score / $total_course_sessions) * 100;
                                                    if($att_percent < 80) $status_badges .= '<span class="status-badge badge-red" title="เวลาเรียนไม่ถึง 80%">มส.</span>';
                                                }
                                                if($total_assignments > 0) {
                                                    $sub_q = $conn->query("SELECT count(*) as c FROM submissions WHERE assignment_id IN ($ass_in) AND student_id=$sid");
                                                    $submitted_count = $sub_q->fetch_assoc()['c'];
                                                    $missing = $total_assignments - $submitted_count;
                                                    if($missing > 0) $status_badges .= '<span class="status-badge badge-yellow" title="ค้างส่งงาน">ร. ('.$missing.')</span>';
                                                }
                                            ?>
                                            <tr>
                                                <td style="text-align:center;"><?php echo $std['student_number']; ?></td>
                                                <td><?php echo $std['full_name']; ?> <?php echo $status_badges; ?></td>
                                                <?php foreach($cols as $idx => $c): 
                                                    $val = isset($scores_arr[$idx]) ? $scores_arr[$idx] : '';
                                                    $readonly = $is_published ? 'readonly style="background:transparent; border:none;"' : '';
                                                ?>
                                                    <td>
                                                        <input type="number" step="0.5" name="scores[<?php echo $sid; ?>][<?php echo $idx; ?>]" 
                                                               class="form-control score-input" data-sid="<?php echo $sid; ?>" 
                                                               style="padding:8px; text-align:center;" value="<?php echo $val; ?>" max="<?php echo $c['max']; ?>" <?php echo $readonly; ?>>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td style="text-align:center; font-weight:bold; background:#f8fafc;" id="total_<?php echo $sid; ?>"><?php echo $total; ?></td>
                                                <td style="text-align:center; font-weight:bold; font-size:1.1rem; color:var(--primary-color); background:#eff6ff;" id="grade_<?php echo $sid; ?>"><?php echo $grade; ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if(!$is_published): ?>
                                    <button type="submit" class="btn-add" style="margin-top:20px; width:100%; font-size:1.1rem;"><i class="fa-solid fa-save"></i> บันทึกคะแนนทั้งหมด</button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <?php if(!$is_published): ?>
                        <div id="modal_sync_attendance" class="modal">
                            <div class="modal-content">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                    <h3><i class="fa-solid fa-clock-rotate-left" style="color:#b45309;"></i> ดึงคะแนนเวลาเรียน</h3>
                                    <span class="close-modal" onclick="closeModal('modal_sync_attendance')">&times;</span>
                                </div>
                                <p style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:15px;">ระบบจะคำนวณอัตราส่วนการเข้าเรียนจาก <strong>ระบบเช็คชื่อ</strong> ของวิชานี้ทั้งหมด และปรับให้พอดีกับคะแนนเต็มของช่องที่คุณเลือก</p>
                                <div class="form-group">
                                    <label>เลือกช่องคะแนนเป้าหมาย</label>
                                    <select id="sync_att_col" class="form-control">
                                        <option value="">-- เลือกช่องคะแนน --</option>
                                        <?php foreach($cols as $idx => $c): ?>
                                            <option value="<?php echo $idx; ?>" data-max="<?php echo $c['max']; ?>"><?php echo $c['name']; ?> (เต็ม <?php echo $c['max']; ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="btn-add" style="width:100%; background:#f59e0b;" onclick="syncAttendance()">ดึงคะแนนเดี๋ยวนี้</button>
                            </div>
                        </div>

                        <div id="modal_sync_homework" class="modal">
                            <div class="modal-content">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                    <h3><i class="fa-solid fa-book-open" style="color:#3730a3;"></i> ดึงคะแนนระบบสั่งงาน</h3>
                                    <span class="close-modal" onclick="closeModal('modal_sync_homework')">&times;</span>
                                </div>
                                <p style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:15px;">ดึงคะแนนของงานที่ครู <strong>ตรวจแล้ว (Graded)</strong> มาแปลงสัดส่วนใส่ช่องที่ต้องการ</p>
                                <div class="form-group">
                                    <label>เลือกงาน/การบ้านที่ต้องการ</label>
                                    <select id="sync_hw_id" class="form-control">
                                        <option value="">-- เลือกชิ้นงาน --</option>
                                        <?php 
                                        // ** แก้ไข id เป็น assignment_id **
                                        $hw_q = $conn->query("SELECT assignment_id, title, max_score FROM assignments WHERE school_id=$school_id AND teacher_id=$user_id AND class_level='$class_lvl' AND room_number='$room_no' AND course_code='$course_code_raw' ORDER BY created_at DESC");
                                        while($hw = $hw_q->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $hw['assignment_id']; ?>"><?php echo $hw['title']; ?> (เต็ม <?php echo $hw['max_score']; ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>เลือกช่องคะแนนเป้าหมาย</label>
                                    <select id="sync_hw_col" class="form-control">
                                        <option value="">-- เลือกช่องคะแนน --</option>
                                        <?php foreach($cols as $idx => $c): ?>
                                            <option value="<?php echo $idx; ?>" data-max="<?php echo $c['max']; ?>"><?php echo $c['name']; ?> (เต็ม <?php echo $c['max']; ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="btn-add" style="width:100%; background:#4f46e5;" onclick="syncHomework()">ดึงคะแนนเดี๋ยวนี้</button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <script>
                            // Auto Calculate Total and Grade (Live on page)
                            document.querySelectorAll('.score-input').forEach(input => {
                                input.addEventListener('input', function() {
                                    const sid = this.getAttribute('data-sid');
                                    let total = 0;
                                    document.querySelectorAll(`.score-input[data-sid="${sid}"]`).forEach(inp => {
                                        total += parseFloat(inp.value || 0);
                                    });
                                    document.getElementById(`total_${sid}`).innerText = total.toFixed(1);
                                    
                                    // Grade Logic
                                    let grade = '-';
                                    if(total >= 80) grade = '4';
                                    else if(total >= 75) grade = '3.5';
                                    else if(total >= 70) grade = '3';
                                    else if(total >= 65) grade = '2.5';
                                    else if(total >= 60) grade = '2';
                                    else if(total >= 55) grade = '1.5';
                                    else if(total >= 50) grade = '1';
                                    else if(total > 0) grade = '0';
                                    
                                    document.getElementById(`grade_${sid}`).innerText = grade;
                                });
                            });

                            function syncAttendance() {
                                const colSelect = document.getElementById('sync_att_col');
                                const colIdx = colSelect.value;
                                if(colIdx === '') return alert('กรุณาเลือกช่องคะแนนเป้าหมาย');
                                const colMax = parseFloat(colSelect.options[colSelect.selectedIndex].dataset.max);
                                
                                const btn = event.target;
                                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังดึงข้อมูล...';
                                
                                fetch(`teacher_action.php?action=get_sync_attendance&class_level=<?= urlencode($class_lvl) ?>&room_number=<?= urlencode($room_no) ?>&course_code=<?= urlencode($course_code_raw) ?>&col_max=${colMax}`)
                                .then(res => res.json())
                                .then(res => {
                                    btn.innerHTML = 'ดึงคะแนนเดี๋ยวนี้';
                                    if(res.error) return alert(res.error);
                                    
                                    let count = 0;
                                    for(let sid in res.data) {
                                        const input = document.querySelector(`input[name="scores[${sid}][${colIdx}]"]`);
                                        if(input) {
                                            input.value = res.data[sid];
                                            input.dispatchEvent(new Event('input')); 
                                            count++;
                                        }
                                    }
                                    closeModal('modal_sync_attendance');
                                    alert(`ดึงคะแนนเวลาเรียนสำเร็จ (${count} คน)!\nกรุณาตรวจสอบและกด "บันทึกคะแนนทั้งหมด" เพื่อยืนยัน`);
                                })
                                .catch(err => {
                                    btn.innerHTML = 'ดึงคะแนนเดี๋ยวนี้';
                                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
                                });
                            }

                            function syncHomework() {
                                const hwId = document.getElementById('sync_hw_id').value;
                                const colSelect = document.getElementById('sync_hw_col');
                                const colIdx = colSelect.value;
                                if(hwId === '' || colIdx === '') return alert('กรุณาเลือกงานและช่องคะแนนเป้าหมาย');
                                const colMax = parseFloat(colSelect.options[colSelect.selectedIndex].dataset.max);
                                
                                const btn = event.target;
                                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังดึงข้อมูล...';
                                
                                fetch(`teacher_action.php?action=get_sync_homework&assignment_id=${hwId}&col_max=${colMax}`)
                                .then(res => res.json())
                                .then(res => {
                                    btn.innerHTML = 'ดึงคะแนนเดี๋ยวนี้';
                                    if(res.error) return alert(res.error);
                                    
                                    let count = 0;
                                    for(let sid in res.data) {
                                        const input = document.querySelector(`input[name="scores[${sid}][${colIdx}]"]`);
                                        if(input) {
                                            input.value = res.data[sid];
                                            input.dispatchEvent(new Event('input'));
                                            count++;
                                        }
                                    }
                                    closeModal('modal_sync_homework');
                                    alert(`ดึงคะแนนการบ้านสำเร็จ (${count} คน)!\nกรุณาตรวจสอบและกด "บันทึกคะแนนทั้งหมด" เพื่อยืนยัน`);
                                })
                                .catch(err => {
                                    btn.innerHTML = 'ดึงคะแนนเดี๋ยวนี้';
                                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
                                });
                            }

                            function openModal(id) { document.getElementById(id).style.display = 'block'; }
                            function closeModal(id) { document.getElementById(id).style.display = 'none'; }
                            window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.style.display = 'none'; }
                        </script>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if($view == 'summary'): ?>
                    <?php if(empty($cols)): ?>
                        <div style="text-align:center; padding:40px; background:white; border-radius:12px; border:1px dashed #cbd5e1;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size:3rem; color:#f59e0b; margin-bottom:15px;"></i>
                            <h3>ยังไม่ได้ตั้งค่าสัดส่วนคะแนน</h3>
                            <a href="?view=criteria&select_class_key=<?php echo urlencode($select_class_key); ?>" class="btn-add" style="margin-top:15px;">ไปตั้งค่าเลย</a>
                        </div>
                    <?php else: 
                        // ประมวลผลสถิติเกรด
                        $grade_counts = ['4'=>0, '3.5'=>0, '3'=>0, '2.5'=>0, '2'=>0, '1.5'=>0, '1'=>0, '0'=>0, '-'=>0];
                        $total_students = 0;
                        $passed = 0;
                        $failed = 0;

                        $sql_std = "SELECT u.user_id FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id WHERE u.school_id=$school_id AND u.role='student' AND ud.year_id=$active_year_id AND ud.class_level='$class_lvl' AND ud.room_number='$room_no'";
                        $res_std = $conn->query($sql_std);
                        $total_students = $res_std->num_rows;

                        while($std = $res_std->fetch_assoc()) {
                            $sid = $std['user_id'];
                            $sc_q = $conn->query("SELECT grade FROM grade_scores WHERE criteria_id=$crit_id AND student_id=$sid");
                            $grade = ($sc_q->num_rows > 0) ? $sc_q->fetch_assoc()['grade'] : '-';
                            if(isset($grade_counts[$grade])) {
                                $grade_counts[$grade]++;
                                if(in_array($grade, ['4','3.5','3','2.5','2','1.5','1'])) $passed++;
                                else if($grade == '0') $failed++;
                            }
                        }
                    ?>
                        <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:24px;">
                            <div class="stat-card">
                                <h3><?php echo $total_students; ?></h3>
                                <p><i class="fa-solid fa-users"></i> นักเรียนทั้งหมด</p>
                            </div>
                            <div class="stat-card" style="border-bottom: 4px solid #10b981;">
                                <h3 style="color:#10b981;"><?php echo $passed; ?></h3>
                                <p><i class="fa-solid fa-check-circle"></i> ผ่านเกณฑ์ (เกรด 1 ขึ้นไป)</p>
                            </div>
                            <div class="stat-card" style="border-bottom: 4px solid #ef4444;">
                                <h3 style="color:#ef4444;"><?php echo $failed; ?></h3>
                                <p><i class="fa-solid fa-times-circle"></i> ไม่ผ่าน (ติด 0)</p>
                            </div>
                        </div>

                        <div style="display:flex; gap:24px; flex-wrap:wrap;">
                            <div class="card-form" style="flex:1; min-width:300px;">
                                <h3 style="margin-bottom:15px; color:var(--text-main); font-size:1.1rem;"><i class="fa-solid fa-chart-bar"></i> กราฟแจกแจงระดับผลการเรียน</h3>
                                <div style="height: 300px; width: 100%;">
                                    <canvas id="gradeChart"></canvas>
                                </div>
                            </div>
                            
                            <div class="card-form" style="flex:1; min-width:300px; display:flex; flex-direction:column; justify-content:center;">
                                <div style="text-align:center; padding:20px;">
                                    <h3 style="margin-bottom:20px; color:var(--text-main);"><i class="fa-solid fa-file-export"></i> จัดการข้อมูล</h3>
                                    
                                    <a href="teacher_action.php?action=export_grade_csv&select_class_key=<?php echo urlencode($select_class_key); ?>" target="_blank" class="btn-action" style="background:#10b981; color:white; width:100%; padding:14px; font-size:1.1rem; text-decoration:none; display:inline-block; margin-bottom:15px;">
                                        <i class="fa-solid fa-file-excel"></i> ส่งออกข้อมูล (Export CSV)
                                    </a>
                                    
                                    <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:15px;">
                                        ดาวน์โหลดไฟล์เพื่อนำไปใช้ทำเอกสาร ปพ.5 หรือเปิดใน Excel
                                    </p>

                                    <hr style="border:0; border-top:1px dashed var(--border-color); margin:20px 0;">

                                    <?php if($is_published): ?>
                                        <div class="alert-box alert-success" style="justify-content:center; background:#dcfce7; color:#166534;">
                                            <i class="fa-solid fa-check-circle" style="font-size:1.5rem;"></i> 
                                            <div>
                                                <strong>ส่งเกรดเข้าระบบแล้ว</strong><br>
                                                <small>เมื่อ <?php echo date('d/m/Y H:i', strtotime($criteria_data['published_at'])); ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <form action="teacher_action.php" method="POST" onsubmit="return confirm('ยืนยันที่จะส่งเกรดหรือไม่?\n\nหากส่งแล้วจะไม่สามารถกลับมาแก้ไขคะแนนได้อีก และนักเรียนจะสามารถเห็นเกรดของตัวเองได้');">
                                            <input type="hidden" name="action" value="publish_grades">
                                            <input type="hidden" name="criteria_id" value="<?php echo $crit_id; ?>">
                                            <input type="hidden" name="select_class_key" value="<?php echo htmlspecialchars($select_class_key); ?>">
                                            <button type="submit" class="btn-action" style="background:#4f46e5; color:white; width:100%; padding:14px; font-size:1.1rem;">
                                                <i class="fa-solid fa-paper-plane"></i> ยืนยันและส่งเกรดให้นักเรียน
                                            </button>
                                        </form>
                                        <p style="font-size:0.85rem; color:#b91c1c; margin-top:10px;">
                                            * ตรวจสอบคะแนนให้ครบถ้วนก่อนกดส่ง หากส่งแล้วระบบจะล็อกไม่ให้แก้ไขได้อีก
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <script>
                            // กราฟสรุปเกรด Chart.js
                            const ctx = document.getElementById('gradeChart').getContext('2d');
                            const gradeChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0'],
                                    datasets: [{
                                        label: 'จำนวนนักเรียน (คน)',
                                        data: [
                                            <?php echo $grade_counts['4']; ?>,
                                            <?php echo $grade_counts['3.5']; ?>,
                                            <?php echo $grade_counts['3']; ?>,
                                            <?php echo $grade_counts['2.5']; ?>,
                                            <?php echo $grade_counts['2']; ?>,
                                            <?php echo $grade_counts['1.5']; ?>,
                                            <?php echo $grade_counts['1']; ?>,
                                            <?php echo $grade_counts['0']; ?>
                                        ],
                                        backgroundColor: [
                                            'rgba(99, 102, 241, 0.7)', 'rgba(99, 102, 241, 0.7)', 'rgba(99, 102, 241, 0.7)', 
                                            'rgba(99, 102, 241, 0.7)', 'rgba(99, 102, 241, 0.7)', 'rgba(99, 102, 241, 0.7)', 
                                            'rgba(99, 102, 241, 0.7)', 'rgba(239, 68, 68, 0.7)'
                                        ],
                                        borderColor: [
                                            '#4f46e5', '#4f46e5', '#4f46e5', '#4f46e5', '#4f46e5', '#4f46e5', '#4f46e5', '#b91c1c'
                                        ],
                                        borderWidth: 1,
                                        borderRadius: 8
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                                    },
                                    plugins: { legend: { display: false } }
                                }
                            });
                        </script>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>

    <div class="mobile-bottom-nav">
        <a href="dashboard_teacher.php?tab=home" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-house"></i></span> หน้าหลัก
        </a>
        <a href="dashboard_teacher.php?tab=attendance" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-clipboard-user"></i></span> เช็คชื่อ
        </a>
        <a href="homework_teacher.php" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span> สั่งงาน
        </a>
        <a href="grade_teacher.php" class="nav-item active">
            <span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span> เกรด
        </a>
        <a href="dashboard_teacher.php?tab=profile" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-user"></i></span> ฉัน
        </a>
    </div>

    <script>
        let timeoutTimer;
        const timeoutDuration = 300000;
        function startTimer() {
            clearTimeout(timeoutTimer);
            timeoutTimer = setTimeout(doLogout, timeoutDuration);
        }
        function doLogout() {
            alert("คุณไม่ได้ใช้งานระบบเกิน 5 นาที ระบบจะทำการออกจากระบบอัตโนมัติเพื่อความปลอดภัย");
            window.location.href = 'logout.php?timeout=1';
        }
        window.onload = startTimer; document.onmousemove = startTimer;
        document.onkeypress = startTimer; document.onclick = startTimer;
        document.onscroll = startTimer; document.ontouchstart = startTimer;
    </script>
</body>
</html>