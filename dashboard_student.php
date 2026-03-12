<?php
// ชื่อไฟล์: dashboard_student.php
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];
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

$school_row = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school_row ? $school_row['school_name'] : 'ไม่ระบุโรงเรียน';
$student = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();

$img_src = $student['profile_img'];
if(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png' && !file_exists("uploads/".$img_src)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'; 
} elseif(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png') {
   $img_src = "uploads/" . $img_src;
} elseif ($img_src == 'default_avatar.png') {
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $student['username'];
} else { $img_src = $img_src; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Student Dashboard - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <i class="fa-solid fa-user-graduate"></i> 
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
                                $icon = strpos($n['title'], 'ผลการเรียน') !== false ? 'fa-trophy' : (strpos($n['title'], 'คลังความรู้') !== false ? 'fa-folder-open' : (strpos($n['title'], 'พฤติกรรม') !== false ? 'fa-book-journal-whills' : 'fa-bell'));
                        ?>
                            <a href="?read_notif=<?php echo $n['id']; ?>&link=<?php echo urlencode($n['link']); ?>" class="notif-item <?php echo $is_unread; ?>">
                                <div class="notif-icon"><i class="fa-solid <?php echo $icon; ?>"></i></div>
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
                <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $student['profile_frame']; ?>">
                <span class="desktop-only"><?php echo htmlspecialchars($student['full_name']); ?></span>
            </div>
            <a href="logout.php" class="btn-logout desktop-only"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $student['profile_frame']; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    รหัส: <?php echo htmlspecialchars($student['student_code']); ?>
                </div>
            </div>
            
            <h3>เมนูหลัก</h3>
            <a href="?tab=home" class="menu-item <?php echo $tab=='home'?'active':''; ?>"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <a href="?tab=attendance" class="menu-item <?php echo $tab=='attendance'?'active':''; ?>"><i class="fa-solid fa-calendar-check"></i> เวลาเรียน</a>
            <a href="homework_student.php" class="menu-item"><i class="fa-solid fa-book"></i> การบ้าน</a>
            <a href="grade_student.php" class="menu-item"><i class="fa-solid fa-trophy"></i> ผลการเรียน</a>
            <a href="media_student.php" class="menu-item"><i class="fa-solid fa-layer-group"></i> คลังความรู้</a>
            <div style="height:1px; background:var(--border-color); margin:10px 0;"></div>
            <a href="?tab=profile" class="menu-item <?php echo $tab=='profile'?'active':''; ?>"><i class="fa-solid fa-id-card"></i> ข้อมูลส่วนตัว</a>
        </aside>

        <main class="content-area">
             <?php if(isset($_SESSION['msg'])): ?>
                <div class="alert-box alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if ($tab == 'home'): ?>
                <div class="page-header"><h2 class="page-title">สวัสดี, <?php echo htmlspecialchars($student['full_name']); ?> 👋</h2></div>
                <div class="menu-grid">
                    <a href="?tab=attendance" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-calendar-check"></i></div><h3>เวลาเรียน</h3></a>
                    <a href="homework_student.php" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-book"></i></div><h3>การบ้าน</h3></a>
                    <a href="grade_student.php" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-trophy"></i></div><h3>ผลการเรียน</h3></a>
                    <a href="media_student.php" class="menu-card"><div class="icon-circle"><i class="fa-solid fa-layer-group"></i></div><h3>คลังความรู้</h3></a>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'attendance'): ?>
                <div class="page-header"><h2 class="page-title">ประวัติการเข้าเรียน</h2></div>
                
                <?php
                $sql_att = "SELECT ar.status, ar.score, asess.attendance_date, asess.course_code, asess.course_name 
                            FROM attendance_records ar JOIN attendance_sessions asess ON ar.session_id = asess.id 
                            WHERE ar.student_id = $user_id ORDER BY asess.attendance_date DESC";
                $res_att = $conn->query($sql_att);
                $history = [];
                $stats = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'score_sum' => 0];
                $course_stats = [];

                if($res_att) {
                    while($row = $res_att->fetch_assoc()){
                        $history[] = $row;
                        $stats['total']++;
                        if(isset($stats[$row['status']])) $stats[$row['status']]++;
                        $stats['score_sum'] += $row['score'];

                        $k = $row['course_code'];
                        if(!isset($course_stats[$k])) {
                            $course_stats[$k] = ['name' => $row['course_name'], 'total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'score' => 0];
                        }
                        $course_stats[$k]['total']++;
                        if(isset($course_stats[$k][$row['status']])) $course_stats[$k][$row['status']]++;
                        $course_stats[$k]['score'] += $row['score'];
                    }
                }
                $global_percent = $stats['total'] > 0 ? ($stats['score_sum'] / $stats['total']) * 100 : 0;
                ?>
                
                <div class="menu-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 24px;">
                    <div class="menu-card" style="aspect-ratio: auto; padding: 20px; align-items: flex-start; border-left: 5px solid #166534;"><h3 style="font-size:0.9rem; color:#64748b; margin-bottom:5px;">เข้าเรียน (ปกติ)</h3><div style="font-size:1.8rem; font-weight:bold; color:#166534;"><?php echo $stats['present']; ?></div><div style="font-size:0.8rem; color:#166534;">ครั้ง</div></div>
                    <div class="menu-card" style="aspect-ratio: auto; padding: 20px; align-items: flex-start; border-left: 5px solid #ca8a04;"><h3 style="font-size:0.9rem; color:#64748b; margin-bottom:5px;">สาย</h3><div style="font-size:1.8rem; font-weight:bold; color:#ca8a04;"><?php echo $stats['late']; ?></div><div style="font-size:0.8rem; color:#ca8a04;">ครั้ง</div></div>
                    <div class="menu-card" style="aspect-ratio: auto; padding: 20px; align-items: flex-start; border-left: 5px solid #ef4444;"><h3 style="font-size:0.9rem; color:#64748b; margin-bottom:5px;">ขาดเรียน</h3><div style="font-size:1.8rem; font-weight:bold; color:#ef4444;"><?php echo $stats['absent']; ?></div><div style="font-size:0.8rem; color:#ef4444;">ครั้ง</div></div>
                    <div class="menu-card" style="aspect-ratio: auto; padding: 20px; align-items: flex-start; border-left: 5px solid #2563eb;"><h3 style="font-size:0.9rem; color:#64748b; margin-bottom:5px;">% การมาเรียน</h3><div style="font-size:1.8rem; font-weight:bold; color:#2563eb;"><?php echo number_format($global_percent, 2); ?>%</div><div style="font-size:0.8rem; color:#2563eb;">จากทั้งหมด <?php echo $stats['total']; ?> คาบ</div></div>
                </div>

                <div class="card-form">
                    <h3 style="margin-bottom: 15px;"><i class="fa-solid fa-list-check"></i> สรุปรายวิชา</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>รหัสวิชา</th><th>ชื่อวิชา</th><th style="text-align:center;">เข้าเรียน/คาบรวม</th><th style="text-align:center;">% เช็คชื่อ</th></tr></thead>
                            <tbody>
                                <?php if(empty($course_stats)): ?>
                                    <tr><td colspan="4" style="text-align:center; padding:20px; color:#94a3b8;">ยังไม่มีข้อมูล</td></tr>
                                <?php else: foreach($course_stats as $code => $d): 
                                        $pct = $d['total'] > 0 ? ($d['score'] / $d['total']) * 100 : 0;
                                        $color = $pct < 80 ? '#ef4444' : '#166534';
                                    ?>
                                    <tr><td data-label="รหัส"><?php echo htmlspecialchars($code); ?></td><td data-label="วิชา"><?php echo htmlspecialchars($d['name']); ?></td><td data-label="สถิติ" style="text-align:center;"><span style="color:#166534; font-weight:bold;"><?php echo $d['present']+$d['late']; ?></span> / <?php echo $d['total']; ?></td><td data-label="%" style="text-align:center; font-weight:bold; color:<?php echo $color; ?>;"><?php echo number_format($pct, 2); ?>%</td></tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-form" style="margin-top: 20px;">
                    <h3 style="margin-bottom: 15px;"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติล่าสุด</h3>
                     <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>วันที่</th><th>วิชา</th><th>สถานะ</th></tr></thead>
                             <tbody>
                                <?php if(empty($history)): ?>
                                    <tr><td colspan="3" style="text-align:center;">ยังไม่มีประวัติ</td></tr>
                                <?php else: foreach(array_slice($history, 0, 10) as $row): 
                                         $st_color = 'black'; $st_txt = '';
                                         switch($row['status']){ case 'present': $st_color='#166534'; $st_txt='มาเรียน'; break; case 'late': $st_color='#ca8a04'; $st_txt='มาสาย'; break; case 'absent': $st_color='#ef4444'; $st_txt='ขาดเรียน'; break; case 'leave': $st_color='#3b82f6'; $st_txt='ลา'; break; }
                                    ?>
                                    <tr><td data-label="วันที่"><?php echo date('d/m/Y', strtotime($row['attendance_date'])); ?></td><td data-label="วิชา"><?php echo htmlspecialchars($row['course_name']); ?> <small style="color:#94a3b8;">(<?php echo htmlspecialchars($row['course_code']); ?>)</small></td><td data-label="สถานะ"><span style="padding:4px 12px; border-radius:12px; background:<?php echo $st_color.'15'; ?>; color:<?php echo $st_color; ?>; font-weight:600;"><?php echo $st_txt; ?></span></td></tr>
                                    <?php endforeach; endif; ?>
                             </tbody>
                        </table>
                     </div>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'profile'): 
                // โหลดการตั้งค่า LINE Notify
                $settings_q = $conn->query("SELECT * FROM user_settings WHERE user_id=$user_id");
                $user_settings = ($settings_q && $settings_q->num_rows > 0) ? $settings_q->fetch_assoc() : ['line_token' => '', 'notify_line' => 0];
            ?>
                <div class="page-header"><h2 class="page-title">ข้อมูลส่วนตัว</h2></div>
                <div style="display:flex; gap:25px; flex-wrap:wrap; align-items:stretch;">
                    <div class="card-form" style="flex:1; min-width:300px; text-align:center;">
                        <h3 style="margin-bottom:20px;">รูปโปรไฟล์</h3>
                        <div style="margin:20px 0;"><img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $student['profile_frame']; ?>" id="preview_img"></div>
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
                            <div class="form-group"><label>ชื่อ-นามสกุล</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" required></div>
                            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($student['username']); ?>" readonly style="background:#f1f5f9; color:#94a3b8;"></div>
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
                            $schoolSeeds = ['StudentM1', 'StudentF1', 'PrefectBoy', 'PrefectGirl', 'ClassRepM', 'ClassRepF', 'ClubLeaderM', 'ClubLeaderF', 'AthleteM', 'AthleteF', 'ScholarM', 'ScholarF', 'ArtistM', 'ArtistF', 'MusicianM', 'MusicianF', 'TechClubM', 'TechClubF', 'VolunteerM', 'VolunteerF'];
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
                            <?php for($i=0; $i<=10; $i++) { echo "<form action='user_action.php' method='POST' style='display:inline;'><input type='hidden' name='action' value='update_profile_pic'><input type='hidden' name='frame_style' value='frame-$i'><button type='submit' class='select-item'><div class='profile-img-nav frame-$i' style='width:50px; height:50px; margin:0 auto; background:#eee;'></div><small>แบบ $i</small></button></form>"; } ?>
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
        <a href="?tab=attendance" class="nav-item <?php echo $tab=='attendance'?'active':''; ?>"><span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span> เรียน</a>
        <a href="homework_student.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-book-open"></i></span> งาน</a>
        <a href="grade_student.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-trophy"></i></span> ผล</a>
        <a href="?tab=profile" class="nav-item <?php echo $tab=='profile'?'active':''; ?>"><span class="nav-icon"><i class="fa-solid fa-user"></i></span> ฉัน</a>
    </div>

    <script>
        let timeoutTimer; const timeoutDuration = 300000; 
        function startTimer() { clearTimeout(timeoutTimer); timeoutTimer = setTimeout(doLogout, timeoutDuration); }
        function doLogout() { alert("หมดเวลาการใช้งานอัตโนมัติ"); window.location.href = 'logout.php?timeout=1'; }
        window.onload = startTimer; document.onmousemove = startTimer; document.onkeypress = startTimer;
    </script>
</body>
</html>