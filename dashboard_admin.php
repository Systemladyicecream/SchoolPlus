<?php
// ชื่อไฟล์: dashboard_admin.php
session_start();
require_once 'db_connect.php';

// --- SECURITY: AUTO LOGOUT SYSTEM (PHP Check) ---
// ตรวจสอบว่าไม่มีการใช้งานเกิน 5 นาที (300 วินาที) หรือไม่
$timeout_duration = 300; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    setcookie("user_login", "", time() - 3600, "/");
    header("Location: index.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาล่าสุดที่มีการโหลดหน้าเว็บ
// ------------------------------------------------

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$school_id = $_SESSION['school_id'];
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลโรงเรียนและระดับการศึกษา
$sc_query = $conn->query("SELECT school_name, education_level FROM schools WHERE school_id = $school_id");
$sc_row = $sc_query->fetch_assoc();
$school_name = $sc_row['school_name'];
$school_type = $sc_row['education_level']; // ประถมศึกษา, ขยายโอกาส, มัธยมศึกษา

// กำหนด Array ระดับชั้น ตามประเภทโรงเรียน
$class_levels = [];
if ($school_type == 'ประถมศึกษา') {
    $class_levels = ['ป.1', 'ป.2', 'ป.3', 'ป.4', 'ป.5', 'ป.6'];
} elseif ($school_type == 'มัธยมศึกษา') {
    $class_levels = ['ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6'];
} elseif ($school_type == 'ขยายโอกาส') {
    // ขยายโอกาสปกติคือ ป.1 - ม.3
    $class_levels = ['ป.1', 'ป.2', 'ป.3', 'ป.4', 'ป.5', 'ป.6', 'ม.1', 'ม.2', 'ม.3'];
} else {
    // Default (เผื่อกรณีอื่น หรือไม่ระบุ) แสดงทั้งหมด
    $class_levels = ['ป.1', 'ป.2', 'ป.3', 'ป.4', 'ป.5', 'ป.6', 'ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6'];
}

$admin = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();

// 1. ดึงปีการศึกษาปัจจุบัน (Active)
$act_y_query = $conn->query("SELECT * FROM academic_years WHERE school_id = $school_id AND is_active = 1");
$current_academic_year = $act_y_query->fetch_assoc();
$current_year_id = $current_academic_year ? $current_academic_year['year_id'] : 0;
$current_year_text = $current_academic_year ? "ปี " . $current_academic_year['year_name'] . " เทอม " . $current_academic_year['term'] : '<span style="color:#ef4444;">กรุณาตั้งค่าปีการศึกษา</span>';

// === AUTO MIGRATION SYSTEM ===
$conn->query("CREATE TABLE IF NOT EXISTS user_year_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    user_id INT NOT NULL,
    year_id INT NOT NULL,
    student_number INT DEFAULT 0,
    class_level VARCHAR(50),
    room_number VARCHAR(50),
    subjects_taught TEXT,
    position VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, year_id),
    INDEX (school_id, year_id)
)");

if ($current_year_id > 0) {
    $chk_data = $conn->query("SELECT id FROM user_year_data WHERE school_id = $school_id LIMIT 1");
    if ($chk_data->num_rows == 0) {
        $sql_migrate = "INSERT IGNORE INTO user_year_data (school_id, user_id, year_id, student_number, class_level, room_number, subjects_taught, position)
                        SELECT school_id, user_id, $current_year_id, 0, class_level, room_number, subjects_taught, position
                        FROM users 
                        WHERE school_id = $school_id AND role IN ('teacher', 'student') 
                        AND (room_number IS NOT NULL AND room_number != '')";
        $conn->query($sql_migrate);
    }
}

$img_src = $admin['profile_img'];
if(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png' && !file_exists("uploads/".$img_src)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Admin'; 
} elseif(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png') {
   $img_src = "uploads/" . $img_src;
} elseif ($img_src == 'default_avatar.png') {
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $admin['username'];
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

$all_courses = [];
$c_query = $conn->query("SELECT * FROM courses WHERE school_id=$school_id ORDER BY class_level, course_code ASC");
while($c = $c_query->fetch_assoc()){
    $all_courses[] = $c;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#ffffff">
    <title>Admin Dashboard - <?php echo $school_name; ?></title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-bar { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .filter-bar select { padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; flex: 1; min-width: 120px; }
        
        .teaching-row { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; margin-bottom: 10px; position: relative; }
        .btn-remove-row { position: absolute; top: 10px; right: 10px; color: #ef4444; background: #fee2e2; border: none; width: 25px; height: 25px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
        .subject-checkbox-list { max-height: 120px; overflow-y: auto; background: white; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; margin-top: 5px; -webkit-overflow-scrolling: touch; }
        .subject-checkbox-item { display: flex; align-items: flex-start; gap: 8px; padding: 4px 0; border-bottom: 1px dashed #f1f5f9; font-size: 0.9rem; }
        .subject-checkbox-item:last-child { border-bottom: none; }
        .subject-checkbox-item input { margin-top: 3px; }

        .cell-block { padding: 8px 0; border-bottom: 1px dashed #e2e8f0; min-height: 40px; display: flex; align-items: center; }
        .cell-block:last-child { border-bottom: none; }
        .room-badge { background: #eff6ff; color: var(--primary-color); padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; border: 1px solid #dbeafe; }
        
        .year-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
        .status-active { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .btn-activate { background: #fff; border: 1px solid #e2e8f0; color: #64748b; padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-activate:hover { background: #f0f9ff; color: var(--primary-color); border-color: var(--primary-color); }
        
        /* CSV Import Styles */
        .csv-import-box { border: 2px dashed #cbd5e1; background: #f8fafc; padding: 25px; border-radius: 16px; text-align: center; transition: 0.2s; }
        .csv-import-box:hover { border-color: var(--primary-color); background: #f0f9ff; }
        .btn-download-template { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--primary-color); text-decoration: none; background: white; padding: 8px 16px; border-radius: 50px; border: 1px solid #e2e8f0; margin-bottom: 15px; box-shadow: var(--shadow-sm); transition: 0.2s; }
        .btn-download-template:hover { border-color: var(--primary-color); transform: translateY(-2px); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <i class="fa-solid fa-school-flag"></i> 
            <div>Smart School Plus <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;"><?php echo $school_name; ?></span></div>
        </div>
        <div class="user-info">
            <div class="profile-container"><img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $admin['profile_frame']; ?>"></div>
            <span>Admin: <?php echo $admin['full_name']; ?></span>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $admin['profile_frame']; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo $admin['full_name']; ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    ผู้ดูแลระบบ
                </div>
                <div style="margin-top:10px; font-size:0.9rem; color:var(--primary-color); font-weight:600; background:#eff6ff; padding:8px; border-radius:8px;">
                    <i class="fa-regular fa-calendar-check"></i> <?php echo $current_year_text; ?>
                </div>
            </div>
            <h3>เมนูจัดการ</h3>
            <a href="?tab=overview" class="menu-item <?php echo $tab=='overview'?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> ภาพรวม</a>
            <a href="?tab=years" class="menu-item <?php echo $tab=='years'?'active':''; ?>"><i class="fa-regular fa-calendar-days"></i> ปีการศึกษา</a>
            <a href="?tab=courses" class="menu-item <?php echo $tab=='courses'?'active':''; ?>"><i class="fa-solid fa-book-bookmark"></i> หลักสูตร</a>
            <a href="?tab=teachers" class="menu-item <?php echo $tab=='teachers'?'active':''; ?>"><i class="fa-solid fa-chalkboard-user"></i> ครูอาจารย์</a>
            <a href="?tab=students" class="menu-item <?php echo $tab=='students'?'active':''; ?>"><i class="fa-solid fa-user-graduate"></i> นักเรียน</a>
            <div style="height:1px; background:var(--border-color); margin:10px 0;"></div>
            <a href="?tab=profile" class="menu-item <?php echo $tab=='profile'?'active':''; ?>"><i class="fa-solid fa-user-gear"></i> ข้อมูลส่วนตัว</a>
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

            <?php if ($tab == 'overview'): 
                $cnt_student = $conn->query("SELECT COUNT(*) as c FROM users WHERE school_id=$school_id AND role='student'")->fetch_assoc()['c'];
                $cnt_teacher = $conn->query("SELECT COUNT(*) as c FROM users WHERE school_id=$school_id AND role='teacher'")->fetch_assoc()['c'];
                $cnt_exec = $conn->query("SELECT COUNT(*) as c FROM users WHERE school_id=$school_id AND role='super_teacher'")->fetch_assoc()['c'];
            ?>
                <div class="page-header"><h2 class="page-title">ภาพรวมโรงเรียน</h2></div>
                <div class="menu-grid">
                    <div class="menu-card"><div class="icon-circle"><i class="fa-solid fa-user-tie"></i></div><h3>ผู้บริหาร <?php echo $cnt_exec; ?> คน</h3></div>
                    <div class="menu-card"><div class="icon-circle"><i class="fa-solid fa-chalkboard-user"></i></div><h3>ครู <?php echo $cnt_teacher; ?> คน</h3></div>
                    <div class="menu-card"><div class="icon-circle"><i class="fa-solid fa-user-graduate"></i></div><h3>นักเรียน <?php echo $cnt_student; ?> คน</h3></div>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'years'): ?>
                <div class="page-header"><h2 class="page-title">จัดการปีการศึกษา</h2></div>
                <div class="card-form">
                    <h4 id="year_form_title" style="margin-bottom:15px;"><i class="fa-solid fa-plus-circle"></i> เพิ่มปีการศึกษาใหม่</h4>
                    <form id="yearForm" action="admin_action.php" method="POST" style="display:flex; gap:15px; align-items: flex-end; flex-wrap: wrap;">
                        <input type="hidden" name="action" id="year_action" value="add_year">
                        <input type="hidden" name="year_id" id="year_id">
                        <div style="flex:1; min-width:150px;">
                            <label>ปีการศึกษา</label>
                            <input type="text" name="year_name" id="year_name" class="form-control" placeholder="เช่น 2567" required>
                        </div>
                        <div style="flex:1; min-width:150px;">
                            <label>ภาคเรียน</label>
                            <select name="term" id="term" class="form-control">
                                <option value="1">ภาคเรียนที่ 1</option>
                                <option value="2">ภาคเรียนที่ 2</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:150px;">
                            <label>จำนวนคาบทั้งหมด</label>
                            <input type="number" name="total_sessions" id="total_sessions" class="form-control" placeholder="เช่น 40" value="40" required>
                            <small style="color:#64748b; font-size:0.75rem;">(ใช้คำนวณ % การมาเรียน)</small>
                        </div>
                        <div style="display:flex; align-items:center; width:200px; padding-bottom: 12px;">
                            <input type="checkbox" name="is_active" id="active_year" value="1" style="width:20px; height:20px; accent-color:var(--primary-color);"> 
                            <label for="active_year" style="margin:0 0 0 8px;">ตั้งเป็นเทอมปัจจุบัน</label>
                        </div>
                        <div style="width:100%;">
                            <button type="submit" class="btn-add" id="btn_year_submit"><i class="fa-solid fa-save"></i> บันทึก</button>
                            <button type="button" class="btn-cancel" id="btn_year_cancel" onclick="resetYearForm()"><i class="fa-solid fa-xmark"></i> ยกเลิก</button>
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>ปีการศึกษา</th><th>ภาคเรียน</th><th>จำนวนคาบ</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
                        <tbody>
                            <?php 
                            $res = $conn->query("SELECT * FROM academic_years WHERE school_id=$school_id ORDER BY year_name DESC, term DESC");
                            while($row = $res->fetch_assoc()): ?>
                            <tr style="<?php echo $row['is_active'] ? 'background:#f0fdf4;' : ''; ?>">
                                <td data-label="ปีการศึกษา"><?php echo $row['year_name']; ?></td>
                                <td data-label="ภาคเรียน"><?php echo $row['term']; ?></td>
                                <td data-label="จำนวนคาบ"><?php echo isset($row['total_sessions']) ? $row['total_sessions'] : '40'; ?></td>
                                <td data-label="สถานะ">
                                    <?php if($row['is_active']): ?>
                                        <span class="year-status-badge status-active"><i class="fa-solid fa-circle-check"></i> ใช้งานอยู่ (ปัจจุบัน)</span>
                                    <?php else: ?>
                                        <a href="admin_action.php?action=set_active_year&id=<?php echo $row['year_id']; ?>" class="btn-activate" onclick="return confirm('ต้องการเปลี่ยนปีการศึกษาปัจจุบันเป็น <?php echo $row['year_name'].'/'.$row['term']; ?> ใช่หรือไม่?');">
                                            <i class="fa-regular fa-circle-play"></i> คลิกเพื่อใช้งาน
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td data-label="จัดการ">
                                    <button type="button" class="btn-action btn-edit" onclick="editYear(<?php echo $row['year_id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                    <a href="admin_action.php?action=delete_year&id=<?php echo $row['year_id']; ?>" class="btn-action btn-delete" onclick="return confirm('ยืนยันการลบ?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if ($tab == 'courses'): ?>
                 <div class="page-header"><h2 class="page-title">จัดการหลักสูตร</h2></div>
                 <div class="card-form">
                    <h4 id="course_form_title" style="margin-bottom:15px;"><i class="fa-solid fa-plus-circle"></i> เพิ่มรายวิชาใหม่</h4>
                    <form id="courseForm" action="admin_action.php" method="POST" style="display:grid; grid-template-columns: 1fr 2fr 1fr 1fr; gap:15px; align-items: flex-end;">
                        <input type="hidden" name="action" id="course_action" value="add_course">
                        <input type="hidden" name="course_id" id="course_id">
                        <div><label>รหัสวิชา</label><input type="text" name="course_code" id="course_code" class="form-control" placeholder="เช่น ว101" required></div>
                        <div><label>ชื่อรายวิชา</label><input type="text" name="course_name" id="course_name" class="form-control" required></div>
                        <div>
                            <label>ระดับ</label>
                            <select name="class_level" id="course_level" class="form-control" required>
                                <option value="">-เลือก-</option>
                                <?php foreach($class_levels as $l) echo "<option value='$l'>$l</option>"; ?>
                            </select>
                        </div>
                        <div><label>กลุ่มสาระ</label><select name="subject_group" id="subject_group" class="form-control"><option value="วิทยาศาสตร์">วิทยาศาสตร์</option><option value="คณิตศาสตร์">คณิตศาสตร์</option><option value="ภาษาไทย">ภาษาไทย</option><option value="อังกฤษ">อังกฤษ</option><option value="สังคม">สังคม</option><option value="ศิลปะ">ศิลปะ</option><option value="การงาน">การงาน</option><option value="พละ">พละ</option></select></div>
                        <div style="grid-column: 1 / -1;">
                            <button type="submit" class="btn-add" id="btn_course_submit"><i class="fa-solid fa-save"></i> บันทึก</button>
                            <button type="button" class="btn-cancel" id="btn_course_cancel" onclick="resetCourseForm()"><i class="fa-solid fa-xmark"></i> ยกเลิก</button>
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>รหัส</th><th>ชื่อวิชา</th><th>ระดับ</th><th>กลุ่มสาระ</th><th>จัดการ</th></tr></thead>
                        <tbody>
                            <?php 
                            $res = $conn->query("SELECT * FROM courses WHERE school_id=$school_id ORDER BY class_level, course_code ASC");
                            while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td data-label="รหัส"><?php echo $row['course_code']; ?></td>
                                <td data-label="ชื่อวิชา"><?php echo $row['course_name']; ?></td>
                                <td data-label="ระดับ"><span style="background:#eff6ff; color:var(--primary-color); padding:2px 8px; border-radius:6px; font-size:0.9rem;"><?php echo $row['class_level']; ?></span></td>
                                <td data-label="กลุ่มสาระ"><?php echo $row['subject_group']; ?></td>
                                <td data-label="จัดการ">
                                    <button type="button" class="btn-action btn-edit" onclick="editCourse(<?php echo $row['course_id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                    <a href="admin_action.php?action=delete_course&id=<?php echo $row['course_id']; ?>" class="btn-action btn-delete" onclick="return confirm('ยืนยัน?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'teachers'): ?>
                <div class="page-header"><h2 class="page-title">จัดการครู</h2></div>
                <div class="card-form">
                    <h4 id="teacher_form_title" style="margin-bottom:15px;"><i class="fa-solid fa-user-plus"></i> เพิ่มครู</h4>
                    <form id="teacherForm" action="admin_action.php" method="POST">
                        <input type="hidden" name="action" id="teacher_action" value="add_user">
                        <input type="hidden" name="role_type" value="teacher">
                        <input type="hidden" name="return_tab" value="teachers">
                        <input type="hidden" name="user_id" id="teacher_user_id">
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                            <input type="text" name="username" id="teacher_username" class="form-control" placeholder="Username" required>
                            <input type="text" name="password" id="teacher_password" class="form-control" placeholder="Password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล</label>
                            <input type="text" name="full_name" id="teacher_fullname" class="form-control" placeholder="ชื่อ-นามสกุล" required>
                        </div>

                        <div class="form-group" style="background:#f1f5f9; padding:15px; border-radius:12px; margin-bottom:15px;">
                            <label style="margin-bottom:10px;"><i class="fa-solid fa-people-roof"></i> ห้องที่ปรึกษา (เลือก 1 ห้อง)</label>
                            <div style="display:flex; gap:10px;">
                                <select name="advisory_level" id="advisory_level" class="form-control">
                                    <option value="">-ระดับชั้น-</option>
                                    <?php foreach($class_levels as $l) echo "<option value='$l'>$l</option>"; ?>
                                </select>
                                <select name="advisory_room_no" id="advisory_room_no" class="form-control">
                                    <option value="">-ห้อง-</option>
                                    <?php for($i=1; $i<=10; $i++) echo "<option value='$i'>$i</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-chalkboard-user"></i> ภาระงานสอน</label>
                            <div id="teaching_container">
                                </div>
                            <button type="button" class="btn-action" style="background:#eff6ff; color:var(--primary-color); border:1px dashed var(--primary-color); width:100%; padding:10px; margin-top:5px;" onclick="addTeachingRow()">
                                <i class="fa-solid fa-plus"></i> เพิ่มห้องสอน
                            </button>
                        </div>

                        <div style="margin-top:20px;">
                            <button type="submit" class="btn-add" id="btn_teacher_submit"><i class="fa-solid fa-save"></i> บันทึก</button>
                            <button type="button" class="btn-cancel" id="btn_teacher_cancel" onclick="resetTeacherForm()"><i class="fa-solid fa-xmark"></i> ยกเลิก</button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>ชื่อ-สกุล</th><th>วิชาที่สอน</th><th>ห้องที่สอน</th><th>ห้องที่ปรึกษา</th><th>จัดการ</th></tr></thead>
                        <tbody>
                            <?php 
                            $sql = "SELECT u.*, ud.subjects_taught, ud.room_number as advisory_room
                                    FROM users u 
                                    LEFT JOIN user_year_data ud ON u.user_id = ud.user_id AND ud.year_id = $current_year_id 
                                    WHERE u.school_id=$school_id AND u.role='teacher' 
                                    ORDER BY u.created_at DESC";
                            $res = $conn->query($sql);
                            while($row = $res->fetch_assoc()): 
                                $subjects_html = '';
                                $rooms_html = '';
                                
                                if(!empty($row['subjects_taught'])) {
                                    $decoded = json_decode($row['subjects_taught'], true);
                                    if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        foreach($decoded as $item) {
                                            $room_txt = $item['room'];
                                            $subjects_txt = (isset($item['subjects']) && is_array($item['subjects'])) ? implode(', ', $item['subjects']) : '-';
                                            $subjects_html .= "<div class='cell-block'>$subjects_txt</div>";
                                            $rooms_html .= "<div class='cell-block'><span class='room-badge'>$room_txt</span></div>";
                                        }
                                    } else {
                                        $subjects_html = "<div class='cell-block'>".$row['subjects_taught']."</div>";
                                        $rooms_html = "<div class='cell-block'>-</div>";
                                    }
                                } else {
                                    $subjects_html = "<div class='cell-block' style='color:#ccc;'>-</div>";
                                    $rooms_html = "<div class='cell-block' style='color:#ccc;'>-</div>";
                                }
                            ?>
                            <tr>
                                <td data-label="ชื่อ"><?php echo $row['full_name']; ?></td>
                                <td data-label="วิชาที่สอน" style="padding:0 10px;">
                                    <?php echo $subjects_html; ?>
                                </td>
                                <td data-label="ห้องที่สอน" style="padding:0 10px;">
                                    <?php echo $rooms_html; ?>
                                </td>
                                <td data-label="ห้องที่ปรึกษา">
                                    <?php 
                                    if($row['advisory_room']) {
                                        echo '<span style="color:var(--primary-color); font-weight:600;"><i class="fa-solid fa-star" style="font-size:0.7rem;"></i> '.$row['advisory_room'].'</span>';
                                    } else { echo '-'; } 
                                    ?>
                                </td>
                                <td data-label="จัดการ">
                                    <button type="button" class="btn-action btn-edit" onclick="editTeacher(<?php echo $row['user_id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                    <a href="admin_action.php?action=delete_user&id=<?php echo $row['user_id']; ?>&return_tab=teachers" class="btn-action btn-delete" onclick="return confirm('ยืนยันการลบ?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'students'): $view_class = isset($_GET['view_class']) ? $_GET['view_class'] : ''; $view_room = isset($_GET['view_room']) ? $_GET['view_room'] : ''; ?>
                <div class="page-header"><h2 class="page-title">จัดการนักเรียน</h2></div>
                <div class="filter-bar">
                    <strong><i class="fa-solid fa-filter"></i> เลือกห้องเรียน:</strong>
                    <form action="dashboard_admin.php" method="GET" style="display:flex; gap:10px; flex:1;">
                        <input type="hidden" name="tab" value="students">
                        <select name="view_class" required>
                            <option value="">-ระดับ-</option>
                            <?php foreach($class_levels as $l) { $sel = ($view_class == $l) ? 'selected' : ''; echo "<option value='$l' $sel>$l</option>"; } ?>
                        </select>
                        <select name="view_room" required>
                            <option value="">-ห้อง-</option>
                            <?php for($i=1; $i<=10; $i++){ $sel = ($view_room == $i) ? 'selected' : ''; echo "<option value='$i' $sel>$i</option>"; } ?>
                        </select>
                        <button type="submit" class="btn-add" style="padding:10px 15px;"><i class="fa-solid fa-magnifying-glass"></i> ตกลง</button>
                    </form>
                </div>
                
                <?php if($view_class && $view_room): ?>
                    <div class="card-form">
                        <h4 id="student_form_title" style="margin-bottom:15px;"><i class="fa-solid fa-user-plus"></i> เพิ่มนักเรียน <?php echo "$view_class/$view_room"; ?></h4>
                        <form id="studentForm" action="admin_action.php" method="POST">
                            <input type="hidden" name="action" id="student_action" value="add_user">
                            <input type="hidden" name="role_type" value="student">
                            <input type="hidden" name="return_tab" value="students">
                            <input type="hidden" name="view_class" value="<?php echo $view_class; ?>">
                            <input type="hidden" name="view_room" value="<?php echo $view_room; ?>">
                            <input type="hidden" name="class_level" value="<?php echo $view_class; ?>">
                            <input type="hidden" name="room_number" value="<?php echo $view_room; ?>">
                            <input type="hidden" name="user_id" id="student_user_id">
                            <div style="display:grid; grid-template-columns: 0.5fr 1fr 1fr 2fr; gap:15px; margin-bottom:15px;">
                                <input type="number" name="student_number" id="student_number" class="form-control" placeholder="เลขที่" required min="1">
                                <input type="text" name="username" id="student_username" class="form-control" placeholder="รหัสนักเรียน" required>
                                <input type="hidden" name="student_code" id="hidden_student_code">
                                <input type="text" name="password" id="student_password" class="form-control" placeholder="รหัสผ่าน" required>
                                <input type="text" name="full_name" id="student_fullname" class="form-control" placeholder="ชื่อ-สกุล" required>
                            </div>
                            <script>document.getElementById('student_username').addEventListener('input', function() { document.getElementById('hidden_student_code').value = this.value; });</script>
                            <button type="submit" class="btn-add" id="btn_student_submit"><i class="fa-solid fa-save"></i> บันทึก</button>
                            <button type="button" class="btn-cancel" id="btn_student_cancel" onclick="resetStudentForm()"><i class="fa-solid fa-xmark"></i> ยกเลิก</button>
                        </form>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h3 style="margin:0;">รายชื่อนักเรียนในห้องเรียน</h3>
                        <a href="admin_action.php?action=delete_students_in_room&class_level=<?php echo urlencode($view_class); ?>&room_number=<?php echo urlencode($view_room); ?>" 
                        class="btn-action btn-delete" 
                        onclick="return confirm('⚠️ คำเตือนรุนแรง!\n\nคุณกำลังจะลบข้อมูลนักเรียนทั้งหมดในห้อง <?php echo $view_class.'/'.$view_room; ?>\n\nข้อมูลการเรียน คะแนน และการเช็คชื่อทั้งหมดจะหายไปและกู้คืนไม่ได้\n\nยืนยันที่จะทำต่อหรือไม่?');"
                        style="padding:8px 15px;">
                            <i class="fa-solid fa-trash-can"></i> ลบนักเรียนทั้งห้อง
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>เลขที่</th><th>รหัสนักเรียน</th><th>ชื่อ-สกุล</th><th>ระดับชั้น</th><th>ห้องเรียน</th><th>จัดการ</th></tr></thead>
                            <tbody>
                                <?php 
                                $sql = "SELECT u.*, ud.student_number, ud.class_level, ud.room_number 
                                        FROM users u 
                                        JOIN user_year_data ud ON u.user_id = ud.user_id 
                                        WHERE u.school_id=$school_id AND u.role='student' 
                                        AND ud.year_id = $current_year_id
                                        AND ud.class_level='$view_class' AND ud.room_number='$view_room' 
                                        ORDER BY ud.student_number ASC";
                                $res = $conn->query($sql);
                                
                                if($res->num_rows == 0) {
                                    echo "<tr><td colspan='6' style='text-align:center; padding:30px; color:#94a3b8;'>
                                        <i class='fa-solid fa-folder-open' style='font-size:2rem; margin-bottom:10px;'></i><br>
                                        ไม่พบข้อมูลนักเรียนในห้อง $view_class/$view_room สำหรับปีการศึกษานี้
                                    </td></tr>";
                                }

                                while($row = $res->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="เลขที่"><?php echo $row['student_number'] > 0 ? $row['student_number'] : '-'; ?></td>
                                    <td data-label="รหัสนักเรียน"><?php echo $row['student_code']; ?></td>
                                    <td data-label="ชื่อ-สกุล"><?php echo $row['full_name']; ?></td>
                                    <td data-label="ระดับชั้น"><?php echo $row['class_level']; ?></td>
                                    <td data-label="ห้องเรียน"><?php echo $row['room_number']; ?></td>
                                    <td data-label="จัดการ">
                                        <button type="button" class="btn-action btn-edit" onclick="editStudent(<?php echo $row['user_id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                        <a href="admin_action.php?action=delete_user&id=<?php echo $row['user_id']; ?>&return_tab=students&view_class=<?php echo $view_class; ?>&view_room=<?php echo $view_room; ?>" class="btn-action btn-delete" onclick="return confirm('ยืนยันการลบ?');"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-form csv-import-box" style="margin-top:20px;">
                        <div style="max-width:600px; margin:0 auto;">
                            <h3 style="margin-bottom:10px; color:#1e293b;"><i class="fa-solid fa-file-csv" style="color:#2563eb;"></i> นำเข้าข้อมูลนักเรียน (CSV Import)</h3>
                            <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">
                                นำเข้ารายชื่อนักเรียนจำนวนมากด้วยไฟล์ .csv สำหรับห้อง <strong><?php echo "$view_class/$view_room"; ?></strong>
                            </p>
                            
                            <a href="data:text/csv;charset=utf-8,StudentNumber,StudentCode,FullName%0A1,66001,Somchai%20Jaidee%0A2,66002,Somsak%20Rakrean" download="template_student_<?php echo $view_class.'_'.$view_room; ?>.csv" class="btn-download-template">
                                <i class="fa-solid fa-download"></i> ดาวน์โหลดไฟล์ตัวอย่าง (ใหม่)
                            </a>

                            <div style="background:white; padding:20px; border:1px solid #e2e8f0; border-radius:12px; text-align:left;">
                                 <p style="font-size:0.85rem; font-weight:600; margin-bottom:10px;">โครงสร้างไฟล์:</p>
                                 <ul style="font-size:0.85rem; color:#475569; padding-left:20px; margin-bottom:15px; line-height:1.6;">
                                     <li><b>Column 1:</b> เลขที่ (Student Number)</li>
                                     <li><b>Column 2:</b> รหัสนักเรียน (Student ID)</li>
                                     <li><b>Column 3:</b> ชื่อ-นามสกุล (Full Name)</li>
                                 </ul>
                                 <form action="admin_action.php" method="POST" enctype="multipart/form-data" style="display:flex; gap:10px;">
                                    <input type="hidden" name="action" value="import_csv">
                                    <input type="hidden" name="import_class_level" value="<?php echo $view_class; ?>">
                                    <input type="hidden" name="import_room_number" value="<?php echo $view_room; ?>">
                                    
                                    <input type="file" name="csv_file" class="form-control" required accept=".csv">
                                    <button type="submit" class="btn-add" style="background:#10b981; border:none; padding:10px 25px;"><i class="fa-solid fa-upload"></i> อัปโหลด</button>
                                </form>
                                <small style="color:#ef4444; display:block; margin-top:5px;">* กรุณาบันทึกไฟล์เป็น <b>CSV UTF-8</b> เพื่อป้องกันภาษาต่างด้าว</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <?php if ($tab == 'profile'): ?>
                <div class="page-header"><h2 class="page-title">ข้อมูลส่วนตัว</h2></div>
                <div style="display:flex; gap:25px; flex-wrap:wrap;">
                    <div class="card-form" style="flex:1; min-width:300px; text-align:center;">
                        <h3 style="margin-bottom:20px;">รูปโปรไฟล์</h3>
                        <div style="margin:25px 0;">
                            <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $admin['profile_frame']; ?>" id="preview_img">
                        </div>
                        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                            <button type="button" class="btn-action btn-add" onclick="openModal('modal_avatar')"><i class="fa-solid fa-camera"></i> เปลี่ยนรูป</button>
                            <button type="button" class="btn-action btn-edit" onclick="openModal('modal_frame')"><i class="fa-solid fa-crop-simple"></i> เปลี่ยนกรอบ</button>
                        </div>
                    </div>
                    <div class="card-form" style="flex:1; min-width:300px;">
                        <h3 style="margin-bottom:20px;">แก้ไขข้อมูล</h3>
                        <form action="admin_action.php" method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-group"><label>โรงเรียน</label><input type="text" class="form-control" value="<?php echo $school_name; ?>" readonly style="background:#f1f5f9; color:#94a3b8;"></div>
                            <div class="form-group"><label>ชื่อ-นามสกุล</label><input type="text" name="full_name" class="form-control" value="<?php echo $admin['full_name']; ?>" required></div>
                            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo $admin['username']; ?>" readonly style="background:#f1f5f9;"></div>
                            <div class="form-group"><label>รหัสผ่านใหม่</label><input type="password" name="password" class="form-control" placeholder="เว้นว่างไว้ถ้าไม่เปลี่ยน"></div>
                            <button type="submit" class="btn-add" style="width:100%; margin-top:10px;"><i class="fa-solid fa-save"></i> บันทึก</button>
                        </form>
                    </div>
                </div>
                 <div id="modal_avatar" class="modal">
                    <div class="modal-content">
                        <div style="display:flex;justify-content:space-between;align-items:center; margin-bottom:15px;">
                            <h3>เลือกรูปโปรไฟล์ (20 แบบ)</h3>
                            <span class="close-modal" onclick="closeModal('modal_avatar')">&times;</span>
                        </div>
                        <div class="selection-grid" style="grid-template-columns: repeat(5, 1fr); gap: 10px;">
                            <?php 
                            // สร้าง Array 20 Seeds ที่เกี่ยวกับบทบาทในโรงเรียน
                            $schoolSeeds = [
                                'StudentM1', 'StudentF1', 'PrefectBoy', 'PrefectGirl', 'ClassRepM',
                                'ClassRepF', 'ClubLeaderM', 'ClubLeaderF', 'AthleteM', 'AthleteF',
                                'ScholarM', 'ScholarF', 'ArtistM', 'ArtistF', 'MusicianM',
                                'MusicianF', 'TechClubM', 'TechClubF', 'VolunteerM', 'VolunteerF'
                            ];
                            foreach($schoolSeeds as $seed){ 
                                // เพิ่ม background color แบบ gradient เพื่อความสวยงามและดูเป็นทางการขึ้น
                                $avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($seed) . "&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf&backgroundType=gradientLinear";
                                echo "<form action='admin_action.php' method='POST' style='display:inline;'>
                                        <input type='hidden' name='action' value='update_profile_pic'>
                                        <input type='hidden' name='preset_avatar' value='$avatarUrl'>
                                        <button type='submit' class='select-item' style='width:100%; aspect-ratio:1/1; padding:5px;'>
                                            <img src='$avatarUrl' style='width:100%; height:100%; object-fit:contain; border-radius:12px;'>
                                        </button>
                                      </form>"; 
                            } 
                            ?>
                        </div>
                        <div style="margin-top:15px; border-top:1px solid #e2e8f0; padding-top:15px;">
                            <form action="admin_action.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile_pic">
                                <input type="file" name="upload_avatar" class="form-control" required>
                                <button type="submit" class="btn-add" style="margin-top:10px; width:100%;">อัปโหลดรูปเอง</button>
                            </form>
                        </div>
                    </div>
                </div>
                 <div id="modal_frame" class="modal"><div class="modal-content"><div style="display:flex;justify-content:space-between;align-items:center;"><h3>เลือกกรอบรูป</h3><span class="close-modal" onclick="closeModal('modal_frame')">&times;</span></div><div class="selection-grid"><?php for($i=0;$i<=10;$i++){ echo "<form action='admin_action.php' method='POST' style='display:inline;'><input type='hidden' name='action' value='update_profile_pic'><input type='hidden' name='frame_style' value='frame-$i'><button type='submit' class='select-item'><div class='profile-img-nav frame-$i' style='width:50px; height:50px; margin:0 auto; background:#eee;'></div><small>แบบ $i</small></button></form>"; } ?></div></div></div>
                 <script>function openModal(id){document.getElementById(id).style.display='block';} function closeModal(id){document.getElementById(id).style.display='none';} window.onclick = function(e){ if(e.target.classList.contains('modal')) e.target.style.display='none'; }</script>
            <?php endif; ?>

        </main>
    </div>

    <script>
        const ALL_COURSES = <?php echo json_encode($all_courses); ?>;
        const AVAILABLE_LEVELS = <?php echo json_encode($class_levels); ?>; // ส่งค่าระดับชั้นไปให้ JS
        let teachingRowCount = 0;

        function addTeachingRow(data = null) {
            teachingRowCount++;
            const container = document.getElementById('teaching_container');
            const div = document.createElement('div');
            div.className = 'teaching-row';
            div.id = 't_row_' + teachingRowCount;
            
            const level = data ? data.room.split('/')[0] : '';
            const room = data ? data.room.split('/')[1] : '';
            const selectedSubjects = data ? data.subjects : [];

            // สร้างตัวเลือก Level แบบ Dynamic
            let levelOptions = `<option value="">-ระดับ-</option>`;
            AVAILABLE_LEVELS.forEach(l => {
                const selected = (l === level) ? 'selected' : '';
                levelOptions += `<option value="${l}" ${selected}>${l}</option>`;
            });

            let html = `
                <button type="button" class="btn-remove-row" onclick="removeTeachingRow(${teachingRowCount})"><i class="fa-solid fa-times"></i></button>
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <select name="teaching_level[]" class="form-control" style="flex:1" required>
                        ${levelOptions}
                    </select>
                    <select name="teaching_room[]" class="form-control" style="flex:1" required>
                        <option value="">-ห้อง-</option>
                        ${Array.from({length:10}, (_, i) => i + 1).map(i => `<option value="${i}" ${i==room?'selected':''}>${i}</option>`).join('')}
                    </select>
                </div>
                <label style="font-size:0.85rem; color:#64748b;">วิชาที่สอนในห้องนี้:</label>
                <div class="subject-checkbox-list">
            `;
            
            if (ALL_COURSES.length > 0) {
                ALL_COURSES.forEach(c => {
                    const label = `${c.course_name} (${c.course_code}) - ${c.class_level}`;
                    const isChecked = selectedSubjects.includes(label) ? 'checked' : '';
                    html += `
                        <div class="subject-checkbox-item">
                            <input type="checkbox" name="teaching_subjects[${teachingRowCount-1}][]" value="${label}" ${isChecked}>
                            <span>${label}</span>
                        </div>
                    `;
                });
            } else {
                html += `<div style="padding:5px; color:#94a3b8;">ยังไม่มีข้อมูลวิชาในระบบ</div>`;
            }

            html += `</div>`;
            div.innerHTML = html;
            container.appendChild(div);
        }

        function removeTeachingRow(id) { document.getElementById('t_row_' + id).remove(); }

        function editYear(id) { 
            fetch(`admin_action.php?action=get_year_data&id=${id}`).then(r=>r.json()).then(d=>{ 
                document.getElementById('year_action').value='update_year'; 
                document.getElementById('year_id').value=d.year_id; 
                document.getElementById('year_name').value=d.year_name; 
                document.getElementById('term').value=d.term; 
                document.getElementById('total_sessions').value = (d.total_sessions !== undefined && d.total_sessions !== null) ? d.total_sessions : 40;
                document.getElementById('active_year').checked=(d.is_active==1); 
                document.getElementById('year_form_title').innerHTML='<i class="fa-solid fa-pen-to-square"></i> แก้ไขปีการศึกษา'; 
                document.getElementById('btn_year_submit').innerHTML='<i class="fa-solid fa-save"></i> บันทึกแก้ไข'; 
                document.getElementById('btn_year_cancel').style.display='inline-flex'; 
                document.getElementById('yearForm').scrollIntoView({behavior:'smooth'}); 
            }); 
        }
        function resetYearForm() { 
            document.getElementById('yearForm').reset(); 
            document.getElementById('year_action').value='add_year'; 
            document.getElementById('year_id').value=''; 
            document.getElementById('total_sessions').value = '40';
            document.getElementById('year_form_title').innerHTML='<i class="fa-solid fa-plus-circle"></i> เพิ่มปีการศึกษาใหม่'; 
            document.getElementById('btn_year_submit').innerHTML='<i class="fa-solid fa-save"></i> บันทึก'; 
            document.getElementById('btn_year_cancel').style.display='none'; 
        }
        function editCourse(id) { fetch(`admin_action.php?action=get_course_data&id=${id}`).then(r=>r.json()).then(d=>{ document.getElementById('course_action').value='update_course'; document.getElementById('course_id').value=d.course_id; document.getElementById('course_code').value=d.course_code; document.getElementById('course_name').value=d.course_name; document.getElementById('course_level').value=d.class_level; document.getElementById('subject_group').value=d.subject_group; document.getElementById('course_form_title').innerHTML='<i class="fa-solid fa-pen-to-square"></i> แก้ไขรายวิชา'; document.getElementById('btn_course_submit').innerHTML='<i class="fa-solid fa-save"></i> บันทึกแก้ไข'; document.getElementById('btn_course_cancel').style.display='inline-flex'; document.getElementById('courseForm').scrollIntoView({behavior:'smooth'}); }); }
        function resetCourseForm() { document.getElementById('courseForm').reset(); document.getElementById('course_action').value='add_course'; document.getElementById('course_id').value=''; document.getElementById('course_form_title').innerHTML='<i class="fa-solid fa-plus-circle"></i> เพิ่มรายวิชาใหม่'; document.getElementById('btn_course_submit').innerHTML='<i class="fa-solid fa-save"></i> บันทึก'; document.getElementById('btn_course_cancel').style.display='none'; }
        
        function editTeacher(id) {
            fetch(`admin_action.php?action=get_user_data&id=${id}`)
                .then(r => r.json())
                .then(d => {
                    if(d.error) { alert('ไม่พบข้อมูล'); return; }
                    document.getElementById('teacher_action').value = 'update_user';
                    document.getElementById('teacher_user_id').value = d.user_id;
                    document.getElementById('teacher_username').value = d.username;
                    document.getElementById('teacher_fullname').value = d.full_name;
                    document.getElementById('teacher_password').required = false;

                    if(d.room_number && d.room_number.includes('/')){
                        const parts = d.room_number.split('/');
                        document.getElementById('advisory_level').value = parts[0];
                        document.getElementById('advisory_room_no').value = parts[1];
                    } else {
                        document.getElementById('advisory_level').value = '';
                        document.getElementById('advisory_room_no').value = '';
                    }

                    const container = document.getElementById('teaching_container');
                    container.innerHTML = '';
                    teachingRowCount = 0;

                    if(d.subjects_taught) {
                        try {
                            const teachingData = JSON.parse(d.subjects_taught);
                            if(Array.isArray(teachingData)){
                                teachingData.forEach(item => { addTeachingRow(item); });
                            } else { addTeachingRow(); }
                        } catch(e) { addTeachingRow(); }
                    } else { addTeachingRow(); }

                    document.getElementById('teacher_form_title').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลครู';
                    document.getElementById('btn_teacher_submit').innerHTML = '<i class="fa-solid fa-save"></i> บันทึกแก้ไข';
                    document.getElementById('btn_teacher_cancel').style.display = 'inline-flex';
                    document.getElementById('teacherForm').scrollIntoView({behavior: 'smooth'});
                });
        }
        function resetTeacherForm() { document.getElementById('teacherForm').reset(); document.getElementById('teacher_action').value = 'add_user'; document.getElementById('teacher_user_id').value = ''; document.getElementById('teacher_password').required = true; document.getElementById('teaching_container').innerHTML = ''; document.getElementById('teacher_form_title').innerHTML = '<i class="fa-solid fa-user-plus"></i> เพิ่มครู'; document.getElementById('btn_teacher_submit').innerHTML = '<i class="fa-solid fa-save"></i> บันทึก'; document.getElementById('btn_teacher_cancel').style.display = 'none'; }

        function editStudent(id) { fetch(`admin_action.php?action=get_user_data&id=${id}`).then(r=>r.json()).then(d=>{ document.getElementById('student_action').value='update_user'; document.getElementById('student_user_id').value=d.user_id; document.getElementById('student_number').value=d.student_number; document.getElementById('student_username').value=d.username; document.getElementById('hidden_student_code').value=d.student_code; document.getElementById('student_fullname').value=d.full_name; document.getElementById('student_password').required=false; document.getElementById('student_form_title').innerHTML='<i class="fa-solid fa-pen-to-square"></i> แก้ไขนักเรียน'; document.getElementById('btn_student_submit').innerHTML='<i class="fa-solid fa-save"></i> บันทึกแก้ไข'; document.getElementById('btn_student_cancel').style.display='inline-flex'; document.getElementById('studentForm').scrollIntoView({behavior:'smooth'}); }); }
        function resetStudentForm() { document.getElementById('studentForm').reset(); document.getElementById('student_action').value='add_user'; document.getElementById('student_user_id').value=''; document.getElementById('student_number').value=''; document.getElementById('student_password').required=true; document.getElementById('student_form_title').innerHTML='<i class="fa-solid fa-user-plus"></i> เพิ่มนักเรียน'; document.getElementById('btn_student_submit').innerHTML='<i class="fa-solid fa-save"></i> บันทึก'; document.getElementById('btn_student_cancel').style.display='none'; }
    </script>

    <div class="mobile-bottom-nav">
        <a href="?tab=overview" class="nav-item <?php echo $tab=='overview'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> ภาพรวม
        </a>
        <a href="?tab=years" class="nav-item <?php echo $tab=='years'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-regular fa-calendar-days"></i></span> ปี/วิชา
        </a>
        <a href="?tab=teachers" class="nav-item <?php echo $tab=='teachers'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-chalkboard-user"></i></span> ครู
        </a>
        <a href="?tab=students" class="nav-item <?php echo $tab=='students'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span> นร.
        </a>
        <a href="?tab=profile" class="nav-item <?php echo $tab=='profile'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-user"></i></span> ฉัน
        </a>
    </div>

    <script>
        let timeoutTimer;
        const timeoutDuration = 300000; // 5 นาที (300,000 milliseconds)

        function startTimer() {
            // เคลียร์ Timer เก่า และเริ่มนับถอยหลังใหม่
            clearTimeout(timeoutTimer);
            timeoutTimer = setTimeout(doLogout, timeoutDuration);
        }

        function doLogout() {
            // แจ้งเตือนและ Redirect ไปยัง Logout
            alert("คุณไม่ได้ใช้งานระบบเกิน 5 นาที ระบบจะทำการออกจากระบบอัตโนมัติเพื่อความปลอดภัย");
            window.location.href = 'logout.php?timeout=1';
        }

        // รีเซ็ต Timer เมื่อมีการเคลื่อนไหว (เมาส์, คีย์บอร์ด, คลิก, สัมผัส)
        window.onload = startTimer;
        document.onmousemove = startTimer;
        document.onkeypress = startTimer;
        document.onclick = startTimer;
        document.onscroll = startTimer;
        document.ontouchstart = startTimer; // สำหรับ Mobile
    </script>

</body>
</html>