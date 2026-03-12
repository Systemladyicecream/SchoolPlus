<?php
// ชื่อไฟล์: advisory_teacher.php
session_start();
require_once 'db_connect.php';

if (!function_exists('timeAgo')) {
    function timeAgo($time_ago) {
        $time_ago = strtotime($time_ago);
        $cur_time   = time();
        $time_elapsed   = $cur_time - $time_ago;
        $seconds    = $time_elapsed ;
        $minutes    = round($time_elapsed / 60 );
        $hours      = round($time_elapsed / 3600);
        $days       = round($time_elapsed / 86400 );
        $weeks      = round($time_elapsed / 604800);
        $months     = round($time_elapsed / 2600640 );
        $years      = round($time_elapsed / 31207680 );
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school_id = isset($_SESSION['school_id']) ? intval($_SESSION['school_id']) : 0;
$view = isset($_GET['view']) ? $_GET['view'] : 'roster';

// --- NOTIFICATION SYSTEM (Phase 2) ---
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, title VARCHAR(255), message TEXT, link VARCHAR(255), is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$user_id");
    $redirect = strtok($_SERVER["REQUEST_URI"], '?');
    if(isset($_GET['view'])) $redirect .= "?view=" . $_GET['view'];
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
$teacher = ($res_teacher && $res_teacher->num_rows > 0) ? $res_teacher->fetch_assoc() : ['full_name' => 'ไม่พบข้อมูล', 'username' => 'Teacher', 'profile_img' => 'default_avatar.png', 'profile_frame' => '', 'class_level' => '', 'room_number' => '-'];

$raw_img = !empty($teacher['profile_img']) ? $teacher['profile_img'] : 'default_avatar.png';
if(strpos($raw_img, 'http') === false && $raw_img != 'default_avatar.png' && !file_exists("uploads/".$raw_img)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'; 
} elseif(strpos($raw_img, 'http') === false && $raw_img != 'default_avatar.png') {
   $img_src = "uploads/" . $raw_img;
} elseif ($raw_img == 'default_avatar.png') {
   $username_seed = !empty($teacher['username']) ? $teacher['username'] : 'Teacher';
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($username_seed);
} else {
   $img_src = $raw_img;
}

$t_fullname = htmlspecialchars($teacher['full_name'] ?? '');
$t_frame = htmlspecialchars($teacher['profile_frame'] ?? '');

$t_class_raw = $teacher['class_level'] ?? '';
$t_room_raw = $teacher['room_number'] ?? '';
$t_class = $t_class_raw; $t_room = $t_room_raw; $has_advisory_room = false;

if (strpos($t_room_raw, '/') !== false) {
    $parts = explode('/', $t_room_raw);
    $t_class = trim($parts[0]); 
    $t_room = trim($parts[1]);  
}

if (!empty($t_class) && !empty($t_room) && $t_room !== '-') { $has_advisory_room = true; }
$room_display = $has_advisory_room ? "$t_class/$t_room" : 'ยังไม่มีข้อมูล';

// Auto Migration 
$conn->query("CREATE TABLE IF NOT EXISTS homeroom_sessions (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, teacher_id INT, year_id INT, class_level VARCHAR(50), room_number VARCHAR(50), check_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS homeroom_records (id INT AUTO_INCREMENT PRIMARY KEY, session_id INT, student_id INT, status VARCHAR(20), score DECIMAL(5,2) DEFAULT 0)");
$conn->query("CREATE TABLE IF NOT EXISTS student_behavior_logs (id INT AUTO_INCREMENT PRIMARY KEY, school_id INT, teacher_id INT, student_id INT, year_id INT, log_type VARCHAR(50), description TEXT, photo_path TEXT, log_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$b_stats = [];
if ($has_advisory_room) {
    $b_q = $conn->query("SELECT student_id, log_type, COUNT(*) as c FROM student_behavior_logs WHERE year_id=$active_year_id AND school_id=$school_id GROUP BY student_id, log_type");
    if($b_q) { while($br = $b_q->fetch_assoc()) $b_stats[$br['student_id']][$br['log_type']] = $br['c']; }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ห้องที่ปรึกษา - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-bar { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .student-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.3s ease; position: relative; }
        .student-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary-light); }
        .student-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 3px solid #e2e8f0; }
        .student-number-badge { position: absolute; top: 15px; left: 15px; background: var(--primary-color); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .behavior-badges { position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 5px; }
        .badge-small { font-size: 0.7rem; padding: 2px 6px; border-radius: 8px; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .bg-positive { background: #dcfce7; color: #166534; border: 1px solid #86efac; } .bg-negative { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; } .bg-homevisit { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .radio-present:checked + span { font-weight: bold; text-decoration: underline; color: #166534; } .radio-absent:checked + span { font-weight: bold; text-decoration: underline; color: #b91c1c; } .radio-late:checked + span { font-weight: bold; text-decoration: underline; color: #ca8a04; } .radio-leave:checked + span { font-weight: bold; text-decoration: underline; color: #0f172a; }
        .btn-delete-icon { color: #ef4444; background: #fee2e2; border: none; cursor: pointer; padding: 5px 8px; border-radius: 6px; transition: 0.2s; } .btn-delete-icon:hover { background: #fecaca; }
        .history-time { font-size: 0.75rem; color: #64748b; display: block; margin-top: 2px; }
        .modal-large .modal-content { width: 95%; max-width: 900px; }
        .bhv-history-item { background: #f8fafc; border-left: 4px solid #cbd5e1; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; }
        .bhv-positive { border-left-color: #10b981; } .bhv-negative { border-left-color: #ef4444; } .bhv-counseling { border-left-color: #f59e0b; } .bhv-home_visit { border-left-color: #3b82f6; }
        .stat-card { flex: 1; min-width: 200px; background: #fff; padding: 20px; border-radius: 16px; border: 1px solid var(--border-color); text-align: center; box-shadow: var(--shadow-sm); }
        .stat-card h3 { font-size: 2.2rem; margin: 0; font-family: 'Prompt', sans-serif; }
        .stat-card p { margin: 5px 0 0 0; color: var(--text-secondary); font-size: 0.9rem; }
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
                                <a href="?mark_all_read=1&view=<?php echo urlencode($view); ?>" style="font-size:0.75rem; color:var(--primary-color); text-decoration:none;"><i class="fa-solid fa-check-double"></i> อ่านทั้งหมด</a>
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
            <a href="dashboard_teacher.php?tab=home" class="menu-item"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <a href="dashboard_teacher.php?tab=attendance" class="menu-item"><i class="fa-solid fa-clipboard-user"></i> เช็คชื่อรายวิชา</a>
            <a href="homework_teacher.php" class="menu-item"><i class="fa-solid fa-book-open"></i> ระบบสั่งงาน</a>
            <a href="grade_teacher.php" class="menu-item"><i class="fa-solid fa-chart-simple"></i> ตัดเกรด</a>
            <a href="media_teacher.php" class="menu-item"><i class="fa-solid fa-folder-open"></i> สื่อการสอน</a>
            <a href="advisory_teacher.php" class="menu-item active"><i class="fa-solid fa-people-roof"></i> ห้องที่ปรึกษา</a>
            <div style="height:1px; background:var(--border-color); margin:10px 0;"></div>
            <a href="dashboard_teacher.php?tab=profile" class="menu-item"><i class="fa-solid fa-user-gear"></i> ข้อมูลส่วนตัว</a>
        </aside>

        <main class="content-area">
            <?php if(isset($_SESSION['msg'])): ?>
                <div class="alert-box alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="page-header">
                <h2 class="page-title">ระบบห้องที่ปรึกษา (Advisory Room)</h2>
            </div>

            <?php if(!$has_advisory_room): ?>
                <div style="text-align:center; padding:80px 20px; background:white; border-radius:24px; border:2px dashed #cbd5e1; box-shadow:var(--shadow-sm);">
                    <i class="fa-solid fa-chalkboard-user" style="font-size:4rem; color:#cbd5e1; margin-bottom:20px;"></i>
                    <h3 style="color:var(--text-main); font-family:'Prompt', sans-serif;">ยังไม่ได้รับมอบหมายห้องที่ปรึกษา</h3>
                    <p style="color:var(--text-secondary);">คุณยังไม่มีข้อมูลห้องที่ปรึกษาในปีการศึกษานี้<br>กรุณาติดต่อผู้ดูแลระบบ (Admin) เพื่อตั้งค่าข้อมูลประจำตัว</p>
                </div>
            <?php else: ?>
            
                <div class="filter-bar" style="margin-bottom:24px;">
                    <span style="background:var(--primary-light); color:var(--primary-color); padding:8px 16px; border-radius:12px; font-weight:bold; margin-right:10px;">
                        <i class="fa-solid fa-chalkboard"></i> ห้อง <?php echo htmlspecialchars($room_display); ?>
                    </span>
                    <a href="?view=roster" class="btn-action <?php echo $view=='roster'?'btn-add':''; ?>" style="<?php echo $view!='roster'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-address-book"></i> ทำเนียบนักเรียน</a>
                    <a href="?view=homeroom" class="btn-action <?php echo $view=='homeroom'?'btn-add':''; ?>" style="<?php echo $view!='homeroom'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-flag"></i> เช็คชื่อโฮมรูม</a>
                    <a href="?view=analytics" class="btn-action <?php echo $view=='analytics'?'btn-add':''; ?>" style="<?php echo $view!='analytics'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-chart-pie"></i> แดชบอร์ดเฝ้าระวัง</a>
                </div>

                <?php if($view == 'roster'): ?>
                    <div class="menu-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:20px;">
                        <?php 
                        $sql_std = "SELECT u.*, ud.student_number 
                                    FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id 
                                    WHERE u.school_id=$school_id AND u.role='student' 
                                    AND ud.year_id=$active_year_id AND ud.class_level='$t_class' AND ud.room_number='$t_room'
                                    ORDER BY ud.student_number ASC";
                        $res_std = $conn->query($sql_std);
                        
                        if($res_std && $res_std->num_rows > 0):
                            while($std = $res_std->fetch_assoc()):
                                $uid = $std['user_id'];
                                $s_img_raw = !empty($std['profile_img']) ? $std['profile_img'] : 'default_avatar.png';
                                if(strpos($s_img_raw, 'http') === false && $s_img_raw != 'default_avatar.png' && !file_exists("uploads/".$s_img_raw)){
                                   $s_img = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'; 
                                } elseif(strpos($s_img_raw, 'http') === false && $s_img_raw != 'default_avatar.png') {
                                   $s_img = "uploads/" . $s_img_raw;
                                } elseif ($s_img_raw == 'default_avatar.png') {
                                   $s_img = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($std['username'] ?? 'Student');
                                } else { $s_img = $s_img_raw; }

                                $cnt_pos = $b_stats[$uid]['positive'] ?? 0;
                                $cnt_neg = $b_stats[$uid]['negative'] ?? 0;
                                $cnt_hv  = $b_stats[$uid]['home_visit'] ?? 0;
                        ?>
                            <div class="student-card">
                                <div class="student-number-badge"><?php echo htmlspecialchars($std['student_number']); ?></div>
                                <div class="behavior-badges">
                                    <?php if($cnt_pos > 0): ?><div class="badge-small bg-positive"><i class="fa-solid fa-star"></i> ดี x<?php echo $cnt_pos; ?></div><?php endif; ?>
                                    <?php if($cnt_neg > 0): ?><div class="badge-small bg-negative"><i class="fa-solid fa-triangle-exclamation"></i> เตือน x<?php echo $cnt_neg; ?></div><?php endif; ?>
                                    <?php if($cnt_hv > 0): ?><div class="badge-small bg-homevisit"><i class="fa-solid fa-house-chimney-user"></i> เยี่ยมบ้าน</div><?php endif; ?>
                                </div>
                                <img src="<?php echo $s_img; ?>" class="student-img <?php echo htmlspecialchars($std['profile_frame']); ?>">
                                <h4 style="margin:0 0 5px 0; font-size:1.05rem; color:var(--text-main);"><?php echo htmlspecialchars($std['full_name']); ?></h4>
                                <div style="color:var(--text-secondary); font-size:0.85rem; margin-bottom:15px;">รหัส: <?php echo htmlspecialchars($std['student_code']); ?></div>
                                
                                <div style="display:flex; gap:8px; width:100%; justify-content:center; flex-wrap:wrap;">
                                    <button class="btn-action" style="background:#eff6ff; color:#2563eb; flex:1; border-radius:8px; padding:8px 5px; font-size:0.8rem;" onclick="openModal('modal_bhv_<?php echo $uid; ?>')"><i class="fa-solid fa-book-journal-whills"></i> ประวัติ</button>
                                    <button class="btn-action" style="background:#fef3c7; color:#b45309; flex:1; border-radius:8px; padding:8px 5px; font-size:0.8rem;" onclick="openModal('modal_hw_<?php echo $uid; ?>')"><i class="fa-solid fa-list-check"></i> งานค้าง</button>
                                </div>
                            </div>

                            <div id="modal_bhv_<?php echo $uid; ?>" class="modal modal-large">
                                <div class="modal-content">
                                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:15px; margin-bottom:20px;">
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <img src="<?php echo $s_img; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
                                            <div>
                                                <h3 style="margin:0; font-family:'Prompt', sans-serif;"><i class="fa-solid fa-address-card" style="color:var(--primary-color);"></i> สมุดประวัติ: <?php echo htmlspecialchars($std['full_name']); ?></h3>
                                                <p style="margin:0; color:var(--text-secondary); font-size:0.85rem;">เลขที่ <?php echo htmlspecialchars($std['student_number']); ?> | รหัส: <?php echo htmlspecialchars($std['student_code']); ?></p>
                                            </div>
                                        </div>
                                        <span class="close-modal" onclick="closeModal('modal_bhv_<?php echo $uid; ?>')">&times;</span>
                                    </div>
                                    <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:stretch;">
                                        <div style="flex:1; min-width:300px; background:#f8fafc; padding:20px; border-radius:16px; border:1px solid #e2e8f0;">
                                            <h4 style="margin-top:0; color:var(--primary-color);"><i class="fa-solid fa-plus-circle"></i> เพิ่มบันทึกใหม่</h4>
                                            <form action="teacher_action.php" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="save_behavior_log">
                                                <input type="hidden" name="student_id" value="<?php echo $uid; ?>">
                                                <input type="hidden" name="year_id" value="<?php echo $active_year_id; ?>">
                                                <div class="form-group" style="margin-bottom:12px;">
                                                    <label style="font-size:0.85rem; font-weight:bold;">ประเภท</label>
                                                    <select name="log_type" class="form-control" required style="padding:10px;">
                                                        <option value="positive">⭐ ความดี / เชิงบวก</option>
                                                        <option value="negative">⚠️ พฤติกรรมที่ต้องปรับปรุง</option>
                                                        <option value="counseling">💬 ให้คำปรึกษา</option>
                                                        <option value="home_visit">🏡 การเยี่ยมบ้าน</option>
                                                    </select>
                                                </div>
                                                <div class="form-group" style="margin-bottom:12px;">
                                                    <label style="font-size:0.85rem; font-weight:bold;">วันที่</label>
                                                    <input type="date" name="log_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="padding:10px;">
                                                </div>
                                                <div class="form-group" style="margin-bottom:12px;">
                                                    <label style="font-size:0.85rem; font-weight:bold;">รายละเอียด</label>
                                                    <textarea name="description" class="form-control" rows="3" required style="padding:10px;"></textarea>
                                                </div>
                                                <div class="form-group" style="margin-bottom:15px;">
                                                    <label style="font-size:0.85rem; font-weight:bold;">แนบรูป (ถ้ามี)</label>
                                                    <input type="file" name="behavior_photo" class="form-control" accept="image/*" style="padding:10px; background:white;">
                                                </div>
                                                <button type="submit" class="btn-add" style="width:100%;"><i class="fa-solid fa-save"></i> บันทึกข้อมูล</button>
                                            </form>
                                        </div>
                                        <div style="flex:1.5; min-width:300px; display:flex; flex-direction:column;">
                                            <h4 style="margin:top:0; color:var(--text-main);"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติที่ผ่านมา</h4>
                                            <div style="flex:1; overflow-y:auto; padding-right:5px; max-height:400px;">
                                                <?php 
                                                $bhv_q = $conn->query("SELECT * FROM student_behavior_logs WHERE student_id=$uid AND year_id=$active_year_id ORDER BY log_date DESC, created_at DESC");
                                                if($bhv_q && $bhv_q->num_rows > 0):
                                                    while($bhv = $bhv_q->fetch_assoc()):
                                                        $b_class = 'bhv-counseling'; $b_icon = '<i class="fa-solid fa-comment-dots" style="color:#f59e0b;"></i>'; $b_title = 'ให้คำปรึกษา';
                                                        if($bhv['log_type'] == 'positive') { $b_class='bhv-positive'; $b_icon='<i class="fa-solid fa-star" style="color:#10b981;"></i>'; $b_title='ความดี'; }
                                                        elseif($bhv['log_type'] == 'negative') { $b_class='bhv-negative'; $b_icon='<i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i>'; $b_title='ต้องปรับปรุง'; }
                                                        elseif($bhv['log_type'] == 'home_visit') { $b_class='bhv-home_visit'; $b_icon='<i class="fa-solid fa-house-chimney-user" style="color:#3b82f6;"></i>'; $b_title='เยี่ยมบ้าน'; }
                                                ?>
                                                    <div class="bhv-history-item <?php echo $b_class; ?>">
                                                        <div style="flex:1;">
                                                            <div style="font-size:0.8rem; color:#64748b; margin-bottom:3px;"><?php echo date('d/m/Y', strtotime($bhv['log_date'])); ?></div>
                                                            <div style="font-weight:bold; margin-bottom:5px;"><?php echo $b_icon . " " . $b_title; ?></div>
                                                            <div style="font-size:0.9rem; color:var(--text-main); line-height:1.4;"><?php echo nl2br(htmlspecialchars($bhv['description'])); ?></div>
                                                            <?php if(!empty($bhv['photo_path'])): ?>
                                                                <div style="margin-top:10px;"><a href="<?php echo htmlspecialchars($bhv['photo_path']); ?>" target="_blank" style="font-size:0.8rem; background:#e2e8f0; padding:4px 10px; border-radius:8px; color:var(--text-main); text-decoration:none;"><i class="fa-solid fa-image"></i> ดูรูปภาพแนบ</a></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div style="margin-left:10px;">
                                                            <form action="teacher_action.php" method="POST" onsubmit="return confirm('ยืนยันลบประวัตินี้หรือไม่?');">
                                                                <input type="hidden" name="action" value="delete_behavior_log">
                                                                <input type="hidden" name="log_id" value="<?php echo $bhv['id']; ?>">
                                                                <button type="submit" class="btn-delete-icon"><i class="fa-solid fa-trash-can"></i></button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php endwhile; else: ?>
                                                    <div style="text-align:center; padding:40px; color:#94a3b8; border:2px dashed #e2e8f0; border-radius:12px;">ไม่พบประวัติ</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="modal_hw_<?php echo $uid; ?>" class="modal modal-large">
                                <div class="modal-content">
                                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:15px; margin-bottom:20px;">
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <img src="<?php echo $s_img; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
                                            <div>
                                                <h3 style="margin:0; font-family:'Prompt', sans-serif;"><i class="fa-solid fa-clipboard-list" style="color:#b45309;"></i> สรุปงานค้าง: <?php echo htmlspecialchars($std['full_name']); ?></h3>
                                                <p style="margin:0; color:var(--text-secondary); font-size:0.85rem;">เลขที่ <?php echo htmlspecialchars($std['student_number']); ?> | รหัส: <?php echo htmlspecialchars($std['student_code']); ?></p>
                                            </div>
                                        </div>
                                        <span class="close-modal" onclick="closeModal('modal_hw_<?php echo $uid; ?>')">&times;</span>
                                    </div>
                                    <div style="max-height: 400px; overflow-y: auto;">
                                        <?php
                                        $sql_missing_hw = "SELECT a.assignment_id, a.title, a.course_code, a.due_date, u.full_name as teacher_name 
                                                           FROM assignments a LEFT JOIN users u ON a.teacher_id = u.user_id
                                                           WHERE a.school_id = $school_id AND a.class_level = '$t_class' AND a.room_number = '$t_room'
                                                             AND a.assignment_id NOT IN (SELECT assignment_id FROM submissions WHERE student_id = $uid)
                                                           ORDER BY a.due_date ASC";
                                        $res_missing_hw = $conn->query($sql_missing_hw);
                                        if($res_missing_hw && $res_missing_hw->num_rows > 0):
                                        ?>
                                            <div class="alert-box alert-error" style="background:#fee2e2; color:#b91c1c;">
                                                <i class="fa-solid fa-triangle-exclamation"></i> พบงานค้างทั้งหมด <?php echo $res_missing_hw->num_rows; ?> ชิ้น
                                            </div>
                                            <table class="data-table" style="width:100%; font-size:0.9rem;">
                                                <thead style="background:#f8fafc;"><tr><th>วิชา</th><th>ชื่องาน</th><th>ผู้สอน</th><th>กำหนดส่ง</th><th>สถานะ</th></tr></thead>
                                                <tbody>
                                                    <?php while($hw = $res_missing_hw->fetch_assoc()): 
                                                        $due_time = strtotime($hw['due_date']);
                                                        $is_overdue = ($due_time < time());
                                                        $status_badge = $is_overdue ? '<span class="badge-small bg-negative">เลยกำหนด</span>' : '<span class="badge-small" style="background:#fef3c7; color:#b45309;">ยังไม่ถึงกำหนด</span>';
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($hw['course_code']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($hw['title']); ?></td>
                                                        <td>อ.<?php echo htmlspecialchars($hw['teacher_name']); ?></td>
                                                        <td style="<?php echo $is_overdue ? 'color:#ef4444; font-weight:bold;' : ''; ?>"><?php echo date('d/m/Y', $due_time); ?></td>
                                                        <td><?php echo $status_badge; ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div style="text-align:center; padding:40px; border:2px dashed #10b981; border-radius:12px; background:#ecfdf5; color:#166534;">
                                                <i class="fa-solid fa-face-smile-beam" style="font-size:3rem; margin-bottom:15px;"></i>
                                                <h3 style="margin:0;">ยอดเยี่ยม! ไม่มีงานค้าง</h3><p style="margin-top:5px; font-size:0.9rem;">นักเรียนคนนี้ส่งงานครบทุกวิชาแล้ว</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        <?php endwhile; else: ?>
                            <div style="grid-column: 1 / -1; text-align:center; padding:40px; border:2px dashed #cbd5e1; border-radius:16px; color:#94a3b8;">
                                <i class="fa-solid fa-users-slash" style="font-size:3rem; margin-bottom:10px;"></i><p>ยังไม่มีข้อมูลนักเรียนในห้องนี้</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if($view == 'homeroom'): ?>
                    <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                        <div class="card-form" style="flex:2; min-width:350px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;"><h4 style="color:var(--primary-color);"><i class="fa-solid fa-flag"></i> บันทึกโฮมรูม</h4></div>
                            <form action="teacher_action.php" method="POST">
                                <input type="hidden" name="action" value="save_homeroom"><input type="hidden" name="session_id" id="form_session_id" value="0">
                                <input type="hidden" name="class_level" value="<?php echo htmlspecialchars($t_class); ?>"><input type="hidden" name="room_number" value="<?php echo htmlspecialchars($t_room); ?>">
                                <div style="background:#f1f5f9; padding:15px; border-radius:12px; margin-bottom:20px; display:flex; gap:15px; align-items:center;">
                                    <div><label style="font-size:0.85rem; color:#64748b;">วันที่ทำกิจกรรม</label><input type="date" name="check_date" id="form_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                                    <div style="flex:1; text-align:right;"><button type="button" class="btn-action" onclick="checkAll('present')" style="background:#dcfce7; color:#166534; border:1px solid #86efac;"><i class="fa-solid fa-check-double"></i> มาครบทุกคน</button></div>
                                </div>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead><tr><th width="50">เลขที่</th><th>ชื่อ-นามสกุล</th><th>สถานะ</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $sql_std_hr = "SELECT u.user_id, u.full_name, ud.student_number FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id WHERE u.school_id=$school_id AND u.role='student' AND ud.year_id=$active_year_id AND ud.class_level='$t_class' AND ud.room_number='$t_room' ORDER BY ud.student_number ASC";
                                            $res_std_hr = $conn->query($sql_std_hr);
                                            if($res_std_hr && $res_std_hr->num_rows > 0) {
                                                while($std = $res_std_hr->fetch_assoc()): $uid = $std['user_id'];
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
                                <button type="submit" class="btn-add" style="margin-top:20px; width:100%; font-size:1.1rem;"><i class="fa-solid fa-save"></i> บันทึกโฮมรูม</button>
                            </form>
                        </div>
                        <div class="card-form" style="flex:1; min-width:300px; height:fit-content;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;"><h4 style="margin:0;"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติล่าสุด</h4></div>
                            <div style="max-height:500px; overflow-y:auto;">
                                <?php 
                                $sql_hist = "SELECT * FROM homeroom_sessions WHERE school_id=$school_id AND teacher_id=$user_id AND class_level='$t_class' AND room_number='$t_room' ORDER BY check_date DESC LIMIT 15";
                                $res_hist = $conn->query($sql_hist);
                                if($res_hist && $res_hist->num_rows > 0) {
                                    while($h = $res_hist->fetch_assoc()):
                                ?>
                                <div style="padding:12px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                    <div><div style="font-weight:600;"><?php echo date('d/m/Y', strtotime($h['check_date'])); ?></div><span class="history-time">บันทึกเมื่อ <?php echo date('H:i น.', strtotime($h['created_at'])); ?></span></div>
                                    <div style="display:flex; gap:5px;">
                                        <button type="button" class="btn-action btn-edit" onclick="loadHomeroom(<?php echo $h['id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                        <a href="teacher_action.php?action=delete_homeroom&id=<?php echo $h['id']; ?>" class="btn-delete-icon" onclick="return confirm('ลบข้อมูลหรือไม่?');"><i class="fa-solid fa-trash-can"></i></a>
                                    </div>
                                </div>
                                <?php endwhile; } ?>
                            </div>
                        </div>
                    </div>
                    <script>
                        function checkAll(status) { document.querySelectorAll(`.radio-${status}`).forEach(r => r.checked = true); }
                        function loadHomeroom(sessionId) {
                            fetch(`teacher_action.php?action=get_homeroom_data&id=${sessionId}`).then(res => res.json()).then(data => {
                                document.getElementById('form_session_id').value = data.session.id; document.getElementById('form_date').value = data.session.check_date;
                                for (const [studentId, status] of Object.entries(data.records)) {
                                    const radio = document.querySelector(`input[name="attendance[${studentId}]"][value="${status}"]`); if(radio) radio.checked = true;
                                }
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            });
                        }
                    </script>
                <?php endif; ?>

                <?php if($view == 'analytics'): 
                    $sql_std_an = "SELECT u.user_id, u.full_name, ud.student_number FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id WHERE u.school_id=$school_id AND u.role='student' AND ud.year_id=$active_year_id AND ud.class_level='$t_class' AND ud.room_number='$t_room' ORDER BY ud.student_number ASC";
                    $res_std_an = $conn->query($sql_std_an);
                    $students_an = []; if($res_std_an) while($s = $res_std_an->fetch_assoc()) $students_an[$s['user_id']] = $s;
                    $sid_list = !empty($students_an) ? implode(',', array_keys($students_an)) : '0';

                    $hr_total = ($q = $conn->query("SELECT COUNT(*) as c FROM homeroom_sessions WHERE school_id=$school_id AND year_id=$active_year_id AND class_level='$t_class' AND room_number='$t_room'")) ? intval($q->fetch_assoc()['c']) : 0;
                    $hr_rec = [];
                    if($hr_total > 0 && $sid_list !== '0' && ($q = $conn->query("SELECT student_id, SUM(score) as t_score FROM homeroom_records hr JOIN homeroom_sessions hs ON hr.session_id = hs.id WHERE hs.school_id=$school_id AND hs.year_id=$active_year_id AND hs.class_level='$t_class' AND hs.room_number='$t_room' GROUP BY student_id"))) while($r = $q->fetch_assoc()) $hr_rec[$r['student_id']] = floatval($r['t_score']);

                    $bhv_totals = ['positive'=>0, 'negative'=>0, 'counseling'=>0, 'home_visit'=>0]; $bhv_rec = [];
                    if($sid_list !== '0' && ($q = $conn->query("SELECT student_id, log_type, COUNT(*) as c FROM student_behavior_logs WHERE student_id IN ($sid_list) AND year_id=$active_year_id GROUP BY student_id, log_type"))) while($b = $q->fetch_assoc()) { $bhv_rec[$b['student_id']][$b['log_type']] = intval($b['c']); $bhv_totals[$b['log_type']] += intval($b['c']); }

                    $grade_rec = [];
                    if($sid_list !== '0' && ($q = $conn->query("SELECT gs.student_id, gs.grade FROM grade_scores gs JOIN grade_criteria gc ON gs.criteria_id = gc.id WHERE gs.student_id IN ($sid_list) AND gc.year_id=$active_year_id AND gc.is_published=1"))) while($g = $q->fetch_assoc()) {
                        $sid = $g['student_id']; if(!isset($grade_rec[$sid])) $grade_rec[$sid] = ['total_pts'=>0, 'count'=>0, 'fail'=>0];
                        $g_val = trim($g['grade']);
                        if(is_numeric($g_val)) { $grade_rec[$sid]['total_pts'] += floatval($g_val); $grade_rec[$sid]['count']++; if(floatval($g_val) == 0) $grade_rec[$sid]['fail']++; } else { $grade_rec[$sid]['fail']++; }
                    }

                    $risk_academic = 0; $risk_attendance = 0; $risk_behavior = 0;
                    foreach($students_an as $sid => &$sdata) {
                        $sdata['is_risk'] = false; $sdata['gpa'] = 0; $sdata['fail'] = 0; $sdata['att_percent'] = 100; $sdata['neg_bhv'] = $bhv_rec[$sid]['negative'] ?? 0;
                        if(isset($grade_rec[$sid]) && $grade_rec[$sid]['count'] > 0) { $sdata['gpa'] = number_format($grade_rec[$sid]['total_pts'] / $grade_rec[$sid]['count'], 2); $sdata['fail'] = $grade_rec[$sid]['fail']; if($sdata['fail'] > 0 || $sdata['gpa'] < 2.0) { $risk_academic++; $sdata['is_risk'] = true; } }
                        if($hr_total > 0) { $sdata['att_percent'] = (($hr_rec[$sid] ?? 0) / $hr_total) * 100; if($sdata['att_percent'] < 80) { $risk_attendance++; $sdata['is_risk'] = true; } }
                        if($sdata['neg_bhv'] > 0) { $risk_behavior++; $sdata['is_risk'] = true; }
                    } unset($sdata);
                ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;"><h3 style="margin:0; font-family:'Prompt', sans-serif;"><i class="fa-solid fa-chart-pie"></i> ภาพรวมและการเฝ้าระวัง</h3><a href="teacher_action.php?action=export_advisory_risk_csv" target="_blank" class="btn-action" style="background:#10b981; color:white; text-decoration:none;"><i class="fa-solid fa-file-csv"></i> ดาวน์โหลดรายงาน</a></div>
                    <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:24px;">
                        <div class="stat-card" style="border-bottom: 4px solid #ef4444;"><h3 style="color:#ef4444;"><?php echo $risk_academic; ?></h3><p>เสี่ยงผลการเรียน</p></div>
                        <div class="stat-card" style="border-bottom: 4px solid #f59e0b;"><h3 style="color:#f59e0b;"><?php echo $risk_attendance; ?></h3><p>เสี่ยงเวลาเรียน</p></div>
                        <div class="stat-card" style="border-bottom: 4px solid #3b82f6;"><h3 style="color:#3b82f6;"><?php echo $risk_behavior; ?></h3><p>พฤติกรรมเสี่ยง</p></div>
                    </div>
                    <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:24px;">
                        <div class="card-form" style="flex:1; min-width:300px; margin-bottom:0;"><h4 style="margin-top:0;">สัดส่วนพฤติกรรมรวม</h4><div style="height: 250px; width: 100%;"><canvas id="behaviorChart"></canvas></div></div>
                        <div class="card-form" style="flex:2; min-width:400px; margin-bottom:0;"><h4 style="margin-top:0;">ตารางวิเคราะห์ความเสี่ยง</h4>
                            <div class="table-responsive" style="max-height: 250px;">
                                <table class="data-table" style="font-size:0.85rem;"><thead style="position: sticky; top: 0; background: var(--bg-app); z-index: 1;"><tr><th>เลขที่</th><th>ชื่อ</th><th style="text-align:center;">GPA</th><th style="text-align:center;">ตก</th><th style="text-align:center;">โฮมรูม</th><th style="text-align:center;">สถานะ</th></tr></thead>
                                    <tbody><?php foreach($students_an as $sid => $s): ?><tr><td style="text-align:center;"><?php echo $s['student_number']; ?></td><td><?php echo htmlspecialchars($s['full_name']); ?></td><td style="text-align:center;"><?php echo $s['gpa']; ?></td><td style="text-align:center;"><?php echo $s['fail']; ?></td><td style="text-align:center;"><?php echo number_format($s['att_percent'],1); ?>%</td><td style="text-align:center;"><?php echo $s['is_risk'] ? '<span class="badge-small bg-negative">เฝ้าระวัง</span>' : '<span class="badge-small bg-positive">ปกติ</span>'; ?></td></tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <script>
                        new Chart(document.getElementById('behaviorChart').getContext('2d'), { type: 'doughnut', data: { labels: ['ทำความดี', 'ต้องปรับปรุง', 'ให้คำปรึกษา', 'เยี่ยมบ้าน'], datasets: [{ data: [<?php echo $bhv_totals['positive']; ?>, <?php echo $bhv_totals['negative']; ?>, <?php echo $bhv_totals['counseling']; ?>, <?php echo $bhv_totals['home_visit']; ?>], backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } }, cutout: '60%' } });
                    </script>
                <?php endif; ?>
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
        <a href="dashboard_teacher.php?tab=home" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house"></i></span> หน้าหลัก</a>
        <a href="dashboard_teacher.php?tab=attendance" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-clipboard-user"></i></span> เช็คชื่อ</a>
        <a href="homework_teacher.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-book-open"></i></span> สั่งงาน</a>
        <a href="grade_teacher.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span> เกรด</a>
        <a href="advisory_teacher.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-people-roof"></i></span> ที่ปรึกษา</a>
    </div>
</body>
</html>