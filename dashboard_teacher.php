<?php
// ชื่อไฟล์: dashboard_teacher.php
session_start();
require_once 'db_connect.php';

if (!function_exists('timeAgo')) {
    function timeAgo($time_ago) {
        $time_ago = strtotime($time_ago);
        $cur_time = time();
        $time_elapsed = $cur_time - $time_ago;
        $seconds = $time_elapsed ;
        $minutes = round($time_elapsed / 60 );
        $hours = round($time_elapsed / 3600);
        $days = round($time_elapsed / 86400 );
        $weeks = round($time_elapsed / 604800);
        $months = round($time_elapsed / 2600640 );
        $years = round($time_elapsed / 31207680 );
        if($seconds <= 60){ return "เมื่อสักครู่"; }
        else if($minutes <=60){ return "$minutes นาทีที่แล้ว"; }
        else if($hours <=24){ return "$hours ชั่วโมงที่แล้ว"; }
        else if($days <= 7){ return "$days วันที่แล้ว"; }
        else if($weeks <= 4.3){ return "$weeks สัปดาห์ที่แล้ว"; }
        else if($months <=12){ return "$months เดือนที่แล้ว"; }
        else{ return "$years ปีที่แล้ว"; }
    }
}

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
$school_id = isset($_SESSION['school_id']) ? intval($_SESSION['school_id']) : 0;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';

// --- NOTIFICATION SYSTEM (Phase 3: Settings & Auto-cleanup) ---
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, title VARCHAR(255), message TEXT, link VARCHAR(255), is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT PRIMARY KEY, line_token VARCHAR(255) DEFAULT NULL, notify_line TINYINT(1) DEFAULT 0
)");

// ลบการแจ้งเตือนที่เก่าเกิน 30 วันอัตโนมัติ (Pseudo-cron)
$conn->query("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$user_id");
    $redirect = strtok($_SERVER["REQUEST_URI"], '?');
    if(isset($_GET['tab'])) $redirect .= "?tab=" . $_GET['tab'];
    header("Location: $redirect");
    exit;
}

if (isset($_GET['read_notif']) && isset($_GET['link'])) {
    $n_id = intval($_GET['read_notif']);
    $url = $_GET['link'];
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$n_id AND user_id=$user_id");
    header("Location: $url");
    exit;
}

$notif_q = $conn->query("SELECT * FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 15");
$notifications = []; $unread_count = 0;
if($notif_q) { while($n = $notif_q->fetch_assoc()) { $notifications[] = $n; if($n['is_read'] == 0) $unread_count++; } }

// --- SAFE DB QUERIES ---
$school_q = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id");
$school_name = ($school_q && $school_q->num_rows > 0) ? $school_q->fetch_assoc()['school_name'] : 'ไม่ระบุโรงเรียน';

$ay_q = $conn->query("SELECT year_id, year_name, term FROM academic_years WHERE school_id = $school_id AND is_active = 1");
$active_year_row = $ay_q ? $ay_q->fetch_assoc() : null;
$active_year_id = $active_year_row ? $active_year_row['year_id'] : 0;
$current_term_text = $active_year_row ? "ปี {$active_year_row['year_name']}/{$active_year_row['term']}" : "ยังไม่ตั้งค่าปีการศึกษา";

$sql_teacher = "SELECT u.*, ud.subjects_taught, ud.class_level, ud.room_number 
                FROM users u LEFT JOIN user_year_data ud ON u.user_id = ud.user_id AND ud.year_id = $active_year_id 
                WHERE u.user_id = $user_id";
$res_teacher = $conn->query($sql_teacher);
$teacher = ($res_teacher && $res_teacher->num_rows > 0) ? $res_teacher->fetch_assoc() : [
    'full_name' => 'ไม่พบข้อมูล', 'username' => 'Teacher', 'profile_img' => 'default_avatar.png', 
    'profile_frame' => '', 'class_level' => '', 'room_number' => '-', 'subjects_taught' => '[]'
];

$raw_img = !empty($teacher['profile_img']) ? $teacher['profile_img'] : 'default_avatar.png';
if(strpos($raw_img, 'http') === false && $raw_img != 'default_avatar.png' && !file_exists("uploads/".$raw_img)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'; 
} elseif(strpos($raw_img, 'http') === false && $raw_img != 'default_avatar.png') {
   $img_src = "uploads/" . $raw_img;
} elseif ($raw_img == 'default_avatar.png') {
   $username_seed = !empty($teacher['username']) ? $teacher['username'] : 'Teacher';
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($username_seed);
} else { $img_src = $raw_img; }

$t_fullname = htmlspecialchars($teacher['full_name'] ?? '');
$t_frame = htmlspecialchars($teacher['profile_frame'] ?? '');
$t_username = htmlspecialchars($teacher['username'] ?? '');

$t_class_raw = $teacher['class_level'] ?? '';
$t_room_raw = $teacher['room_number'] ?? '';
$t_class = $t_class_raw; $t_room = $t_room_raw;
if (strpos($t_room_raw, '/') !== false) {
    $parts = explode('/', $t_room_raw);
    $t_class = trim($parts[0]); $t_room = trim($parts[1]);
}
$room_display = (!empty($t_class) && !empty($t_room) && $t_room !== '-') ? "$t_class/$t_room" : 'ไม่มี';

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Teacher Dashboard - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-bar { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .radio-present:checked + span { font-weight: bold; text-decoration: underline; color:#166534; }
        .radio-absent:checked + span { font-weight: bold; text-decoration: underline; color:#b91c1c; }
        .radio-late:checked + span { font-weight: bold; text-decoration: underline; color:#ca8a04; }
        .radio-leave:checked + span { font-weight: bold; text-decoration: underline; color:#0f172a; }
        .btn-delete-icon { color: #ef4444; background: none; border: none; cursor: pointer; padding: 5px 8px; border-radius: 6px; transition: 0.2s; }
        .btn-delete-icon:hover { background: #fee2e2; }
        .history-time { font-size: 0.75rem; color: #64748b; display: block; margin-top: 2px; }
        #modal_summary .modal-content { width: 95%; max-width: 1200px; height: 90vh; display: flex; flex-direction: column; }
        #summary_table_container { flex: 1; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <i class="fa-solid fa-chalkboard-user"></i>
            <div>Smart School Plus <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;"><?php echo htmlspecialchars($school_name); ?></span></div>
        </div>
        <div class="user-info">
            
            <div class="notification-wrapper">
                <div class="bell-icon" onclick="toggleNotif()" id="bellIcon">
                    <i class="fa-solid fa-bell"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="badge-count"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </div>
                <div id="notifDropdown" class="notification-dropdown">
                    <div class="notif-header">
                        <span><i class="fa-solid fa-bell" style="color:var(--primary-color);"></i> การแจ้งเตือน</span>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php if($unread_count > 0): ?>
                                <a href="?mark_all_read=1&tab=<?php echo $tab; ?>" style="font-size:0.75rem; color:var(--primary-color); text-decoration:none;"><i class="fa-solid fa-check-double"></i> อ่านทั้งหมด</a>
                            <?php endif; ?>
                            <span style="font-size:0.8rem; font-weight:normal; background:var(--bg-app); padding:2px 8px; border-radius:10px;"><?php echo $unread_count; ?> ใหม่</span>
                        </div>
                    </div>
                    <div class="notif-body">
                        <?php if(empty($notifications)): ?>
                            <div class="notif-empty"><i class="fa-regular fa-bell-slash" style="font-size:2rem; margin-bottom:10px; color:#cbd5e1;"></i><br>ไม่มีการแจ้งเตือนใหม่</div>
                        <?php else: foreach($notifications as $n): 
                                $is_unread = $n['is_read'] == 0 ? 'unread' : '';
                        ?>
                            <a href="?read_notif=<?php echo $n['id']; ?>&link=<?php echo urlencode($n['link']); ?>" class="notif-item <?php echo $is_unread; ?>">
                                <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>
                                <div class="notif-content">
                                    <h4><?php echo htmlspecialchars($n['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($n['message']); ?></p>
                                    <span class="notif-time"><i class="fa-regular fa-clock"></i> <?php echo timeAgo($n['created_at']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $t_frame; ?>">
                <span class="desktop-only">อ.<?php echo $t_fullname; ?></span>
            </div>
            <a href="logout.php" class="btn-logout desktop-only"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $t_frame; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo $t_fullname; ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    ที่ปรึกษา: <?php echo htmlspecialchars($room_display); ?>
                </div>
                <div style="margin-top:10px; font-size:0.85rem; color:var(--primary-color); font-weight:600;">
                    <i class="fa-regular fa-calendar-check"></i> <?php echo $current_term_text; ?>
                </div>
            </div>

            <h3>เมนูครู</h3>
            <a href="?tab=home" class="menu-item <?php echo $tab=='home'?'active':''; ?>"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <a href="?tab=attendance" class="menu-item <?php echo $tab=='attendance'?'active':''; ?>"><i class="fa-solid fa-clipboard-user"></i> เช็คชื่อรายวิชา</a>
            <a href="homework_teacher.php" class="menu-item"><i class="fa-solid fa-book-open"></i> ระบบสั่งงาน</a>
            <a href="grade_teacher.php" class="menu-item"><i class="fa-solid fa-chart-simple"></i> ตัดเกรด</a>
            <a href="media_teacher.php" class="menu-item"><i class="fa-solid fa-folder-open"></i> สื่อการสอน</a>
            <a href="advisory_teacher.php" class="menu-item"><i class="fa-solid fa-people-roof"></i> ห้องที่ปรึกษา</a>
            <div style="height:1px; background:var(--border-color); margin:10px 0;"></div>
            <a href="?tab=profile" class="menu-item <?php echo $tab=='profile'?'active':''; ?>"><i class="fa-solid fa-user-gear"></i> ข้อมูลส่วนตัว</a>
        </aside>

        <main class="content-area">
            <?php if(isset($_SESSION['msg'])): ?>
                <div class="alert-box alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if ($tab == 'home'): ?>
                <div class="page-header"><h2 class="page-title">ยินดีต้อนรับ, คุณครู<?php echo $t_fullname; ?></h2></div>
                <div class="menu-grid">
                    <a href="?tab=attendance" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-clipboard-user"></i></div><h3>เช็คชื่อ</h3></a>
                    <a href="homework_teacher.php" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-book-open"></i></div><h3>สั่งการบ้าน</h3></a>
                    <a href="grade_teacher.php" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-chart-simple"></i></div><h3>คะแนน/เกรด</h3></a>
                    <a href="media_teacher.php" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-folder-open"></i></div><h3>สื่อการสอน</h3></a>
                    <a href="advisory_teacher.php" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-people-roof"></i></div><h3>ห้องที่ปรึกษา</h3></a>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'attendance'): ?>
                <div class="page-header"><h2 class="page-title">ระบบเช็คชื่อรายวิชา (Course Attendance)</h2></div>
                
                <?php 
                $my_classes = [];
                if(!empty($teacher['subjects_taught'])) {
                    $json = json_decode($teacher['subjects_taught'], true);
                    if(is_array($json)) {
                        foreach($json as $j) {
                            $r = isset($j['room']) ? $j['room'] : '';
                            if(isset($j['subjects']) && is_array($j['subjects'])) {
                                foreach($j['subjects'] as $s) { $my_classes["$r|$s"] = "ห้อง $r - $s"; }
                            }
                        }
                    }
                }
                $select_class_key = isset($_GET['select_class_key']) ? $_GET['select_class_key'] : '';
                ?>

                <div class="filter-bar">
                    <form method="GET" action="dashboard_teacher.php" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">
                        <input type="hidden" name="tab" value="attendance">
                        <select name="select_class_key" class="form-control" onchange="this.form.submit()" style="flex:1; min-width:250px;">
                            <option value="">-- เลือกห้องเรียนและวิชาที่สอน --</option>
                            <?php foreach($my_classes as $k => $label) { $sel = ($select_class_key == $k) ? 'selected' : ''; echo "<option value='".htmlspecialchars($k)."' $sel>".htmlspecialchars($label)."</option>"; } ?>
                        </select>
                    </form>
                </div>

                <?php if($select_class_key && strpos($select_class_key, '|') !== false): 
                    $parts = explode('|', $select_class_key);
                    $room_str = $parts[0]; $subject_str = isset($parts[1]) ? $parts[1] : '';
                    $room_parts = explode('/', $room_str);
                    $class_lvl = isset($room_parts[0]) ? $room_parts[0] : ''; $room_no = isset($room_parts[1]) ? $room_parts[1] : '';
                    $course_name_raw = $subject_str; $course_code_raw = $subject_str;
                    if(strpos($subject_str, '(') !== false) {
                        $s_parts = explode('(', $subject_str); $course_name_raw = trim($s_parts[0]);
                        $c_parts = explode(')', $s_parts[1] ?? ''); $course_code_raw = trim($c_parts[0] ?? '');
                    }
                ?>
                <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                    <div class="card-form" style="flex:2; min-width:350px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h4><i class="fa-solid fa-clipboard-user"></i> บันทึกเวลาเรียน</h4>
                            <a href="teacher_action.php?action=export_csv&class_level=<?php echo urlencode($class_lvl); ?>&room_number=<?php echo urlencode($room_no); ?>&course_code=<?php echo urlencode($course_code_raw); ?>" target="_blank" class="btn-action" style="background:#10b981; color:white; text-decoration:none;"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
                        </div>
                        <form action="teacher_action.php" method="POST">
                            <input type="hidden" name="action" value="save_attendance"><input type="hidden" name="session_id" id="form_session_id" value="0">
                            <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($course_code_raw); ?>"><input type="hidden" name="course_name" value="<?php echo htmlspecialchars($course_name_raw); ?>">
                            <input type="hidden" name="class_level" value="<?php echo htmlspecialchars($class_lvl); ?>"><input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room_no); ?>">
                            <div style="background:#f1f5f9; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:15px; align-items:center;">
                                <div><label style="font-size:0.85rem; color:#64748b;">วันที่เรียน</label><input type="date" name="attendance_date" id="form_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                                <div style="flex:1; text-align:right;"><button type="button" class="btn-action" onclick="checkAll('present')" style="background:#dcfce7; color:#166534; border:1px solid #86efac;"><i class="fa-solid fa-check-double"></i> เช็ค "มา" ทั้งห้อง</button></div>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead><tr><th width="50">เลขที่</th><th>ชื่อ-นามสกุล</th><th>สถานะการมาเรียน</th></tr></thead>
                                    <tbody>
                                        <?php 
                                        $sql_std = "SELECT u.user_id, u.full_name, ud.student_number FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id WHERE u.school_id=$school_id AND u.role='student' AND ud.year_id=$active_year_id AND ud.class_level='$class_lvl' AND ud.room_number='$room_no' ORDER BY ud.student_number ASC";
                                        $res_std = $conn->query($sql_std);
                                        if($res_std && $res_std->num_rows == 0) { echo "<tr><td colspan='3' style='text-align:center; padding:20px; color:#94a3b8;'>ไม่พบรายชื่อนักเรียนในห้อง $class_lvl/$room_no</td></tr>"; } 
                                        elseif($res_std) { while($std = $res_std->fetch_assoc()): $uid = $std['user_id'];
                                        ?>
                                        <tr>
                                            <td style="text-align:center;"><?php echo htmlspecialchars($std['student_number']); ?></td><td><?php echo htmlspecialchars($std['full_name']); ?></td>
                                            <td>
                                                <div style="display:flex; gap:15px;">
                                                    <label><input type="radio" name="attendance[<?php echo $uid; ?>]" value="present" class="radio-present" required> <span>มา</span></label>
                                                    <label><input type="radio" name="attendance[<?php echo $uid; ?>]" value="absent" class="radio-absent"> <span>ขาด</span></label>
                                                    <label><input type="radio" name="attendance[<?php echo $uid; ?>]" value="late" class="radio-late"> <span>สาย</span></label>
                                                    <label><input type="radio" name="attendance[<?php echo $uid; ?>]" value="leave" class="radio-leave"> <span>ลา</span></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; } ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn-add" style="margin-top:20px; width:100%; font-size:1.1rem;"><i class="fa-solid fa-save"></i> บันทึกข้อมูล</button>
                        </form>
                    </div>

                    <div class="card-form" style="flex:1; min-width:300px; height:fit-content;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <h4 style="margin:0;"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติ</h4>
                            <button type="button" class="btn-action" style="font-size:0.8rem; padding:5px 10px; background:#eff6ff; color:var(--primary-color);" onclick="viewSummary()"><i class="fa-solid fa-table"></i> ดูสรุป</button>
                        </div>
                        <div style="max-height:500px; overflow-y:auto;">
                            <?php 
                            $sql_hist = "SELECT * FROM attendance_sessions WHERE school_id=$school_id AND teacher_id=$user_id AND class_level='$class_lvl' AND room_number='$room_no' AND course_code='$course_code_raw' ORDER BY attendance_date DESC, created_at DESC LIMIT 20";
                            $res_hist = $conn->query($sql_hist);
                            if($res_hist && $res_hist->num_rows == 0) echo "<p style='color:#94a3b8; text-align:center;'>ยังไม่มีประวัติ</p>";
                            elseif($res_hist) { while($h = $res_hist->fetch_assoc()): $time_str = date('H:i น.', strtotime($h['created_at']));
                            ?>
                            <div style="padding:12px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                <div><div style="font-weight:600; font-size:0.95rem;"><?php echo date('d/m/Y', strtotime($h['attendance_date'])); ?></div><span class="history-time"><i class="fa-regular fa-clock"></i> บันทึกเมื่อ <?php echo $time_str; ?></span></div>
                                <div style="display:flex; gap:5px;">
                                    <button type="button" class="btn-action btn-edit" onclick="loadAttendance(<?php echo $h['id']; ?>)" title="แก้ไข"><i class="fa-solid fa-pen"></i></button>
                                    <a href="teacher_action.php?action=delete_attendance&id=<?php echo $h['id']; ?>" class="btn-delete-icon" onclick="return confirm('ลบข้อมูลหรือไม่?');" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>
                                </div>
                            </div>
                            <?php endwhile; } ?>
                        </div>
                    </div>
                </div>

                <div id="modal_summary" class="modal">
                    <div class="modal-content">
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-bottom:15px; border-bottom:1px solid #e2e8f0; margin-bottom:15px;">
                            <h3><i class="fa-solid fa-table"></i> สรุปผลการเช็คชื่อ (<?php echo htmlspecialchars("$course_code_raw $class_lvl/$room_no"); ?>)</h3>
                            <span class="close-modal" onclick="closeModal('modal_summary')" style="font-size:2rem; cursor:pointer;">&times;</span>
                        </div>
                        <div id="summary_table_container"><div style="text-align:center; padding:50px; color:#94a3b8;"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:2rem;"></i> กำลังโหลดข้อมูล...</div></div>
                    </div>
                </div>

                <script>
                    function checkAll(status) { document.querySelectorAll(`.radio-${status}`).forEach(r => r.checked = true); }
                    function loadAttendance(sessionId) {
                        fetch(`teacher_action.php?action=get_session_data&id=${sessionId}`).then(res => res.json()).then(data => {
                            if(data.error) { alert('Error: ' + data.error); return; }
                            document.getElementById('form_session_id').value = data.session.id; document.getElementById('form_date').value = data.session.attendance_date;
                            for (const [studentId, status] of Object.entries(data.records)) {
                                const radio = document.querySelector(`input[name="attendance[${studentId}]"][value="${status}"]`); if(radio) radio.checked = true;
                            } window.scrollTo({ top: 0, behavior: 'smooth' });
                        });
                    }
                    function viewSummary() {
                        document.getElementById('modal_summary').style.display = 'block';
                        fetch(`teacher_action.php?action=view_summary_table&class_level=${encodeURIComponent('<?php echo $class_lvl; ?>')}&room_number=${encodeURIComponent('<?php echo $room_no; ?>')}&course_code=${encodeURIComponent('<?php echo $course_code_raw; ?>')}`).then(res => res.text()).then(html => document.getElementById('summary_table_container').innerHTML = html);
                    }
                </script>
                <?php else: ?>
                    <div style="text-align:center; padding:50px; border:2px dashed #e2e8f0; border-radius:12px; color:#94a3b8;"><i class="fa-solid fa-chalkboard" style="font-size:3rem; margin-bottom:15px;"></i><h3>กรุณาเลือกวิชาและห้องเรียน</h3></div>
                <?php endif; ?>

            <?php endif; ?>

            <?php if ($tab == 'profile'): 
                $settings_q = $conn->query("SELECT * FROM user_settings WHERE user_id=$user_id");
                $user_settings = ($settings_q && $settings_q->num_rows > 0) ? $settings_q->fetch_assoc() : ['line_token' => '', 'notify_line' => 0];
            ?>
                <div class="page-header"><h2 class="page-title">ข้อมูลส่วนตัว</h2></div>
                <div style="display:flex; gap:25px; flex-wrap:wrap; align-items:stretch;">
                    <div class="card-form" style="flex:1; min-width:300px; text-align:center;">
                        <h3 style="margin-bottom:20px;">รูปโปรไฟล์</h3>
                        <div style="margin:20px 0;"><img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $t_frame; ?>" id="preview_img"></div>
                        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                            <button type="button" class="btn-action btn-add" onclick="openModal('modal_avatar')"><i class="fa-solid fa-camera"></i> เปลี่ยนรูป</button>
                            <button type="button" class="btn-action btn-edit" onclick="openModal('modal_frame')"><i class="fa-solid fa-crop-simple"></i> เปลี่ยนกรอบ</button>
                        </div>
                    </div>

                    <div class="card-form" style="flex:1; min-width:300px;">
                        <h3 style="margin-bottom:20px;">แก้ไขข้อมูล</h3>
                        <form action="user_action.php" method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-group"><label>โรงเรียน</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($school_name); ?>" readonly style="background:#f1f5f9; color:#94a3b8;"></div>
                            <div class="form-group"><label>ชื่อ-นามสกุล</label><input type="text" name="full_name" class="form-control" value="<?php echo $t_fullname; ?>" required></div>
                            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo $t_username; ?>" required></div>
                            <div class="form-group"><label>รหัสผ่านใหม่</label><input type="password" name="password" class="form-control" placeholder="เว้นว่างไว้ถ้าไม่เปลี่ยน"></div>
                            <button type="submit" class="btn-add" style="width:100%; margin-top:10px;">บันทึกข้อมูล</button>
                        </form>
                    </div>

                    <div class="card-form" style="flex:1; min-width:300px;">
                        <h3 style="margin-bottom:20px; color:#10b981;"><i class="fa-brands fa-line"></i> แจ้งเตือนผ่าน LINE</h3>
                        <form action="user_action.php" method="POST">
                            <input type="hidden" name="action" value="update_settings">
                            <div class="form-group">
                                <label>LINE Notify Token</label>
                                <input type="text" name="line_token" class="form-control" value="<?php echo htmlspecialchars($user_settings['line_token']); ?>" placeholder="ใส่ Token จาก LINE Notify">
                                <small style="color:var(--text-secondary); display:block; margin-top:8px;">สามารถขอรับ Token ได้ฟรีที่ <a href="https://notify-bot.line.me/my/" target="_blank" style="color:var(--primary-color);">LINE Notify API</a></small>
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:15px; background:#f8fafc; padding:15px; border-radius:12px; border:1px solid #e2e8f0;">
                                <input type="checkbox" name="notify_active" id="notify_active" value="1" <?php echo ($user_settings['notify_line'] == 1) ? 'checked' : ''; ?> style="width:20px; height:20px; cursor:pointer;">
                                <label for="notify_active" style="margin:0; cursor:pointer; font-weight:bold; color:var(--text-main);">เปิดใช้งานการแจ้งเตือน</label>
                            </div>
                            <button type="submit" class="btn-add" style="width:100%; margin-top:20px; background:#10b981;"><i class="fa-solid fa-save"></i> บันทึกการตั้งค่า</button>
                        </form>
                    </div>
                </div>

                <div id="modal_avatar" class="modal">
                    <div class="modal-content">
                        <div style="display:flex;justify-content:space-between;align-items:center; margin-bottom:15px;"><h3>เลือกรูปโปรไฟล์ (20 แบบ)</h3><span class="close-modal" onclick="closeModal('modal_avatar')">&times;</span></div>
                        <div class="selection-grid" style="grid-template-columns: repeat(5, 1fr); gap: 10px;">
                            <?php 
                            $schoolSeeds = ['StudentM1','StudentF1','PrefectBoy','PrefectGirl','ClassRepM','ClassRepF','ClubLeaderM','ClubLeaderF','AthleteM','AthleteF','ScholarM','ScholarF','ArtistM','ArtistF','MusicianM','MusicianF','TechClubM','TechClubF','VolunteerM','VolunteerF'];
                            foreach($schoolSeeds as $seed){ 
                                $avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($seed) . "&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf&backgroundType=gradientLinear";
                                echo "<form action='user_action.php' method='POST' style='display:inline;'><input type='hidden' name='action' value='update_profile_pic'><input type='hidden' name='preset_avatar' value='$avatarUrl'><button type='submit' class='select-item' style='width:100%; aspect-ratio:1/1; padding:5px;'><img src='$avatarUrl' style='width:100%; height:100%; object-fit:contain; border-radius:12px;'></button></form>"; 
                            } 
                            ?>
                        </div>
                        <div style="margin-top:15px; border-top:1px solid #e2e8f0; padding-top:15px;">
                            <form action="user_action.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile_pic"><input type="file" name="upload_avatar" class="form-control" required><button type="submit" class="btn-add" style="margin-top:10px; width:100%;">อัปโหลดรูปเอง</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div id="modal_frame" class="modal">
                    <div class="modal-content">
                         <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;"><h3>เลือกกรอบรูป</h3><span class="close-modal" onclick="closeModal('modal_frame')">&times;</span></div>
                        <div class="selection-grid">
                            <?php for($i=0; $i<=10; $i++) echo "<form action='user_action.php' method='POST' style='display:inline;'><input type='hidden' name='action' value='update_profile_pic'><input type='hidden' name='frame_style' value='frame-$i'><button type='submit' class='select-item'><div class='profile-img-nav frame-$i' style='width:50px; height:50px; margin:0 auto; background:#eee;'></div><small>แบบ $i</small></button></form>"; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function toggleNotif() {
            var drop = document.getElementById('notifDropdown');
            drop.style.display = (drop.style.display === 'none' || drop.style.display === '') ? 'flex' : 'none';
        }
        window.onclick = function(e) { 
            if (e.target.classList.contains('modal')) e.target.style.display = 'none'; 
            var notifWrapper = document.querySelector('.notification-wrapper');
            if (notifWrapper && !notifWrapper.contains(e.target)) {
                var drop = document.getElementById('notifDropdown');
                if(drop) drop.style.display = 'none';
            }
        }
    </script>

    <div class="mobile-bottom-nav">
        <a href="?tab=home" class="nav-item <?php echo $tab=='home'?'active':''; ?>"><span class="nav-icon"><i class="fa-solid fa-house"></i></span> หน้าหลัก</a>
        <a href="?tab=attendance" class="nav-item <?php echo $tab=='attendance'?'active':''; ?>"><span class="nav-icon"><i class="fa-solid fa-clipboard-user"></i></span> เช็คชื่อ</a>
        <a href="homework_teacher.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-book-open"></i></span> สั่งงาน</a>
        <a href="grade_teacher.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span> เกรด</a>
        <a href="advisory_teacher.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-people-roof"></i></span> ที่ปรึกษา</a>
    </div>

    <script>
        let timeoutTimer; const timeoutDuration = 300000;
        function startTimer() { clearTimeout(timeoutTimer); timeoutTimer = setTimeout(doLogout, timeoutDuration); }
        function doLogout() { alert("คุณไม่ได้ใช้งานระบบเกิน 5 นาที ระบบจะทำการออกจากระบบอัตโนมัติ"); window.location.href = 'logout.php?timeout=1'; }
        window.onload = startTimer; document.onmousemove = startTimer; document.onkeypress = startTimer; document.onclick = startTimer; document.onscroll = startTimer; document.ontouchstart = startTimer;
    </script>

</body>
</html>