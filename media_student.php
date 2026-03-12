<?php
// ชื่อไฟล์: media_student.php
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];
$filter_course = isset($_GET['course']) ? $_GET['course'] : 'all';

// --- SYSTEM TRACKING (ดักจับการคลิกเปิดดูสื่อการสอน) ---
if (isset($_GET['track_id'])) {
    $track_id = intval($_GET['track_id']);
    $conn->query("CREATE TABLE IF NOT EXISTS media_views (
        id INT AUTO_INCREMENT PRIMARY KEY, media_id INT, student_id INT, viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_view (media_id, student_id)
    )");
    $q = $conn->query("SELECT file_path FROM teaching_media WHERE id = $track_id");
    if ($q && $q->num_rows > 0) {
        $file_path = $q->fetch_assoc()['file_path'];
        $stmt = $conn->prepare("INSERT IGNORE INTO media_views (media_id, student_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $track_id, $user_id);
        $stmt->execute();
        header("Location: " . $file_path);
        exit;
    } else {
        die("ไม่พบสื่อการสอน หรือถูกลบไปแล้ว");
    }
}

// --- NOTIFICATION SYSTEM (Phase 2) ---
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, title VARCHAR(255), message TEXT, link VARCHAR(255), is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$user_id");
    $redirect = strtok($_SERVER["REQUEST_URI"], '?');
    if(isset($_GET['course'])) $redirect .= "?course=" . $_GET['course'];
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

$ay_q = $conn->query("SELECT year_id, year_name, term FROM academic_years WHERE school_id = $school_id AND is_active = 1");
$active_year_row = $ay_q->fetch_assoc();
$active_year_id = $active_year_row ? $active_year_row['year_id'] : 0;

$student = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();
$st_y_q = $conn->query("SELECT class_level, room_number FROM user_year_data WHERE user_id=$user_id AND year_id=$active_year_id");
$st_y = $st_y_q->fetch_assoc();
$my_class = $st_y ? $st_y['class_level'] : '';
$my_room = $st_y ? $st_y['room_number'] : '';

$raw_img = !empty($student['profile_img']) ? $student['profile_img'] : 'default_avatar.png';
if(strpos($raw_img, 'http') === false && $raw_img != 'default_avatar.png' && !file_exists("uploads/".$raw_img)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'; 
} elseif(strpos($raw_img, 'http') === false && $raw_img != 'default_avatar.png') {
   $img_src = "uploads/" . $raw_img;
} elseif ($raw_img == 'default_avatar.png') {
   $username_seed = !empty($student['username']) ? $student['username'] : 'Student';
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($username_seed);
} else {
   $img_src = $raw_img;
}

$st_fullname = htmlspecialchars($student['full_name'] ?? 'ไม่พบข้อมูล');
$st_code = htmlspecialchars($student['student_code'] ?? '-');
$st_frame = htmlspecialchars($student['profile_frame'] ?? '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>คลังสื่อการสอน - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-bar { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
        .media-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease; position: relative; height: 100%; }
        .media-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary-light); }
        .media-icon { font-size: 2.5rem; margin-bottom: 15px; }
        .icon-pdf { color: #ef4444; } .icon-word { color: #2563eb; } .icon-ppt { color: #f97316; } .icon-video { color: #8b5cf6; } .icon-link { color: #10b981; } .icon-image { color: #06b6d4; } .icon-other { color: #64748b; }
        .btn-media-action { display: block; text-align: center; padding: 12px; border-radius: 12px; font-weight: bold; text-decoration: none; transition: all 0.2s; }
        .btn-download { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; } .btn-download:hover { background: #dbeafe; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(37,99,235,0.15); }
        .btn-link { background: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0; } .btn-link:hover { background: #d1fae5; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(16,185,129,0.15); }
    </style>
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
                                <a href="?mark_all_read=1&course=<?php echo urlencode($filter_course); ?>" style="font-size:0.75rem; color:var(--primary-color); text-decoration:none;"><i class="fa-solid fa-check-double"></i> อ่านทั้งหมด</a>
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
                <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $st_frame; ?>">
                <span class="desktop-only"><?php echo $st_fullname; ?></span>
            </div>
            <a href="logout.php" class="btn-logout desktop-only"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $st_frame; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo $st_fullname; ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    รหัส: <?php echo $st_code; ?>
                </div>
            </div>
            
            <h3>เมนูหลัก</h3>
            <a href="dashboard_student.php?tab=home" class="menu-item"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <a href="dashboard_student.php?tab=attendance" class="menu-item"><i class="fa-solid fa-calendar-check"></i> เวลาเรียน</a>
            <a href="homework_student.php" class="menu-item"><i class="fa-solid fa-book"></i> การบ้าน</a>
            <a href="grade_student.php" class="menu-item"><i class="fa-solid fa-trophy"></i> ผลการเรียน</a>
            <a href="media_student.php" class="menu-item active"><i class="fa-solid fa-layer-group"></i> คลังความรู้</a>
            <div style="height:1px; background:var(--border-color); margin:10px 0;"></div>
            <a href="dashboard_student.php?tab=profile" class="menu-item"><i class="fa-solid fa-id-card"></i> ข้อมูลส่วนตัว</a>
        </aside>

        <main class="content-area">
            <div class="page-header">
                <h2 class="page-title">คลังสื่อการสอน (Teaching Media)</h2>
            </div>
            
            <?php
            $sql_media = "SELECT tm.*, u.full_name as teacher_name 
                          FROM teaching_media tm JOIN users u ON tm.teacher_id = u.user_id
                          WHERE tm.school_id=$school_id AND tm.year_id=$active_year_id
                          AND tm.class_level='$my_class' AND tm.room_number='$my_room' AND tm.is_visible=1
                          ORDER BY tm.created_at DESC";
            $res_media = $conn->query($sql_media);
            $medias = []; $courses = [];
            if($res_media) {
                while($m = $res_media->fetch_assoc()) {
                    $medias[] = $m;
                    $courses[$m['course_code']] = $m['course_code']; 
                }
            }
            ?>

            <?php if(!empty($courses)): ?>
            <div class="filter-bar">
                <a href="?course=all" class="btn-action <?php echo $filter_course=='all'?'btn-add':''; ?>" style="<?php echo $filter_course!='all'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>">ดูทั้งหมด</a>
                <?php foreach($courses as $c): ?>
                    <a href="?course=<?php echo urlencode($c); ?>" class="btn-action <?php echo $filter_course==$c?'btn-add':''; ?>" style="<?php echo $filter_course!=$c?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>">
                        <?php echo htmlspecialchars($c); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="menu-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); align-items: stretch;">
                <?php 
                $count = 0;
                foreach($medias as $m): 
                    if($filter_course != 'all' && $m['course_code'] != $filter_course) continue;
                    $count++;

                    $icon_class = 'fa-file icon-other';
                    $ext = strtolower($m['file_extension']);
                    if($m['media_type'] == 'link') {
                        if(strpos(strtolower($m['file_path']), 'youtube') !== false || strpos(strtolower($m['file_path']), 'youtu.be') !== false) {
                            $icon_class = 'fa-youtube icon-video';
                        } else { $icon_class = 'fa-link icon-link'; }
                    } else {
                        if(in_array($ext, ['pdf'])) $icon_class = 'fa-file-pdf icon-pdf';
                        elseif(in_array($ext, ['doc','docx'])) $icon_class = 'fa-file-word icon-word';
                        elseif(in_array($ext, ['ppt','pptx'])) $icon_class = 'fa-file-powerpoint icon-ppt';
                        elseif(in_array($ext, ['jpg','jpeg','png','gif'])) $icon_class = 'fa-file-image icon-image';
                        elseif(in_array($ext, ['mp4','avi','mov'])) $icon_class = 'fa-file-video icon-video';
                    }
                ?>
                    <div class="media-card">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <i class="fa-brands <?php echo $icon_class; ?> media-icon"></i>
                                <span style="font-size:0.75rem; background:linear-gradient(135deg, var(--primary-light), #fff); padding:4px 10px; border-radius:20px; color:var(--primary-color); font-weight:600; border:1px solid #bfdbfe;">
                                    วิชา <?php echo htmlspecialchars($m['course_code']); ?>
                                </span>
                            </div>
                            <h4 style="margin:10px 0 5px 0; font-size:1.15rem; color:var(--text-main); font-family:'Prompt', sans-serif; line-height:1.4;">
                                <?php echo htmlspecialchars($m['title']); ?>
                            </h4>
                            <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                                <i class="fa-solid fa-chalkboard-user"></i> อ.<?php echo htmlspecialchars($m['teacher_name']); ?>
                            </p>
                            <?php if($m['description']): ?>
                                <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:15px; line-height:1.5; background:#f8fafc; padding:10px; border-radius:8px;">
                                    <?php echo nl2br(htmlspecialchars($m['description'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                            <?php if($m['media_type'] == 'file'): ?>
                                <a href="?track_id=<?php echo $m['id']; ?>&course=<?php echo urlencode($filter_course); ?>" target="_blank" class="btn-media-action btn-download">
                                    <i class="fa-solid fa-download" style="margin-right:5px;"></i> ดาวน์โหลด / เปิดไฟล์
                                </a>
                            <?php else: ?>
                                <a href="?track_id=<?php echo $m['id']; ?>&course=<?php echo urlencode($filter_course); ?>" target="_blank" class="btn-media-action btn-link">
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="margin-right:5px;"></i> เปิดลิงก์สื่อการสอน
                                </a>
                            <?php endif; ?>
                            
                            <div style="text-align:center; font-size:0.7rem; color:#94a3b8; margin-top:10px;">
                                อัปโหลดเมื่อ: <?php echo date('d/m/Y', strtotime($m['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if($count == 0): ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:80px 20px; border:2px dashed #cbd5e1; border-radius:24px; color:#94a3b8; background:white; box-shadow:var(--shadow-xs);">
                        <i class="fa-solid fa-folder-open" style="font-size:4rem; margin-bottom:20px; color:#e2e8f0;"></i>
                        <h3 style="font-family:'Prompt', sans-serif; color:var(--text-main); margin-bottom:10px;">ยังไม่มีสื่อการสอน</h3>
                        <p style="font-size:0.95rem;">ครูผู้สอนอาจยังไม่ได้อัปโหลด หรือกำลังซ่อนสื่อไว้เพื่อเตรียมความพร้อม<br>กรุณารอการอัปเดตจากคุณครูประจำวิชาครับ</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleNotif() {
            var drop = document.getElementById('notifDropdown');
            drop.style.display = (drop.style.display === 'none' || drop.style.display === '') ? 'flex' : 'none';
        }
        window.onclick = function(e) { 
            var notifWrapper = document.querySelector('.notification-wrapper');
            if (notifWrapper && !notifWrapper.contains(e.target)) {
                var drop = document.getElementById('notifDropdown');
                if(drop) drop.style.display = 'none';
            }
        }
    </script>

    <div class="mobile-bottom-nav">
        <a href="dashboard_student.php?tab=home" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-house"></i></span> หน้าหลัก</a>
        <a href="dashboard_student.php?tab=attendance" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span> เรียน</a>
        <a href="homework_student.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-book-open"></i></span> งาน</a>
        <a href="grade_student.php" class="nav-item"><span class="nav-icon"><i class="fa-solid fa-trophy"></i></span> ผล</a>
        <a href="media_student.php" class="nav-item active"><span class="nav-icon"><i class="fa-solid fa-layer-group"></i></span> คลัง</a>
    </div>

    <script>
        let timeoutTimer; const timeoutDuration = 300000; 
        function startTimer() { clearTimeout(timeoutTimer); timeoutTimer = setTimeout(doLogout, timeoutDuration); }
        function doLogout() { alert("หมดเวลาการใช้งานอัตโนมัติ"); window.location.href = 'logout.php?timeout=1'; }
        window.onload = startTimer; document.onmousemove = startTimer; document.onkeypress = startTimer;
    </script>
</body>
</html>