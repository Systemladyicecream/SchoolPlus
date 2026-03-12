<?php
// ชื่อไฟล์: media_teacher.php
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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];
$school_row = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school_row['school_name'];

// 1. ดึงปีการศึกษาปัจจุบัน
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

// Auto Migration 
$conn->query("CREATE TABLE IF NOT EXISTS teaching_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT,
    teacher_id INT,
    year_id INT,
    course_code VARCHAR(50),
    class_level VARCHAR(50),
    room_number VARCHAR(50),
    media_type VARCHAR(20),
    file_extension VARCHAR(20),
    title VARCHAR(255),
    description TEXT,
    file_path TEXT,
    is_visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS media_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id INT,
    student_id INT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_view (media_id, student_id)
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
    <title>ระบบสื่อการสอน - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-bar { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        
        .media-card {
            background: #fff; border-radius: 16px; padding: 20px;
            box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);
            display: flex; flex-direction: column; justify-content: space-between;
            transition: all 0.3s ease; position: relative;
        }
        .media-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary-light); }
        .media-icon { font-size: 2.5rem; margin-bottom: 15px; }
        .icon-pdf { color: #ef4444; } .icon-word { color: #2563eb; } .icon-ppt { color: #f97316; }
        .icon-video { color: #8b5cf6; } .icon-link { color: #10b981; } .icon-image { color: #06b6d4; } .icon-other { color: #64748b; }
        
        .media-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 15px; }
        .btn-delete-icon { color: #ef4444; background: #fee2e2; border: none; cursor: pointer; padding: 8px 12px; border-radius: 8px; transition: 0.2s; font-size: 0.9rem; }
        .btn-delete-icon:hover { background: #fecaca; transform: scale(1.05); }
        
        /* สไตล์สำหรับ Progress Bar (Analytics) */
        .stat-progress-bg { background: #e2e8f0; height: 12px; border-radius: 10px; width: 100%; overflow: hidden; margin-top: 8px; }
        .stat-progress-fill { height: 100%; border-radius: 10px; transition: width 1s ease; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <i class="fa-solid fa-chalkboard-user"></i>
            <div>Smart School Plus <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;"><?php echo htmlspecialchars($school_name); ?></span></div>
        </div>
        <div class="user-info">
            <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $teacher['profile_frame']; ?>">
            <span>อ.<?php echo htmlspecialchars($teacher['full_name']); ?></span>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $teacher['profile_frame']; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    ที่ปรึกษา: <?php echo htmlspecialchars($teacher['room_number'] ?? '-'); ?>
                </div>
            </div>

            <h3>เมนูครู</h3>
            <a href="dashboard_teacher.php?tab=home" class="menu-item"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <a href="dashboard_teacher.php?tab=attendance" class="menu-item"><i class="fa-solid fa-clipboard-user"></i> เช็คชื่อ</a>
            <a href="homework_teacher.php" class="menu-item"><i class="fa-solid fa-book-open"></i> ระบบสั่งงาน</a>
            <a href="grade_teacher.php" class="menu-item"><i class="fa-solid fa-chart-simple"></i> ตัดเกรด</a>
            <a href="media_teacher.php" class="menu-item active"><i class="fa-solid fa-folder-open"></i> สื่อการสอน</a>
            <a href="dashboard_teacher.php?tab=advisory" class="menu-item"><i class="fa-solid fa-people-roof"></i> ห้องที่ปรึกษา</a>
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
                <h2 class="page-title">ระบบสื่อการสอน (Teaching Media)</h2>
            </div>
            
            <div class="filter-bar" style="margin-bottom:20px;">
                <a href="?view=overview" class="btn-action <?php echo $view=='overview'?'btn-add':''; ?>" style="<?php echo $view!='overview'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-layer-group"></i> ภาพรวมรายวิชา</a>
                <?php if($select_class_key): ?>
                    <a href="?view=manage&select_class_key=<?php echo urlencode($select_class_key); ?>" class="btn-action <?php echo $view=='manage'?'btn-add':''; ?>" style="<?php echo $view!='manage'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-cloud-arrow-up"></i> จัดการคลังสื่อ</a>
                    <a href="?view=analytics&select_class_key=<?php echo urlencode($select_class_key); ?>" class="btn-action <?php echo $view=='analytics'?'btn-add':''; ?>" style="<?php echo $view!='analytics'?'background:#fff; color:#64748b; border:1px solid #cbd5e1;':''; ?>"><i class="fa-solid fa-chart-line"></i> สถิติการเข้าชม</a>
                <?php endif; ?>
            </div>

            <?php if($view == 'overview'): ?>
                <div class="menu-grid">
                    <?php foreach($my_classes as $k => $label): ?>
                    <a href="?view=manage&select_class_key=<?php echo urlencode($k); ?>" class="menu-card" style="align-items:flex-start; aspect-ratio:auto; min-height:120px;">
                        <div style="font-weight:bold; font-size:1.1rem; color:var(--primary-color);"><i class="fa-solid fa-book"></i> <?php echo htmlspecialchars($label); ?></div>
                        <div style="margin-top:15px;">
                            <span class="btn-action" style="background:#eff6ff; color:var(--primary-color);"><i class="fa-solid fa-folder-plus"></i> เพิ่มสื่อการสอน</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php 
            if($view == 'manage' && $select_class_key): 
                list($room_str, $subject_str) = explode('|', $select_class_key);
                list($class_lvl, $room_no) = explode('/', $room_str);
                $course_code_raw = (strpos($subject_str, '(') !== false) ? str_replace(')', '', explode('(', $subject_str)[1]) : $subject_str;
            ?>
                <div class="card-form" style="background: linear-gradient(to right, #ffffff, #f8fafc);">
                    <h3 style="margin-bottom:15px; color:var(--primary-color);"><i class="fa-solid fa-cloud-arrow-up"></i> อัปโหลดสื่อการสอน (<?php echo htmlspecialchars("$course_code_raw $class_lvl/$room_no"); ?>)</h3>
                    
                    <form action="teacher_action.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_media">
                        <input type="hidden" name="select_class_key" value="<?php echo htmlspecialchars($select_class_key); ?>">
                        <input type="hidden" name="course_code" value="<?php echo htmlspecialchars($course_code_raw); ?>">
                        <input type="hidden" name="class_level" value="<?php echo htmlspecialchars($class_lvl); ?>">
                        <input type="hidden" name="room_number" value="<?php echo htmlspecialchars($room_no); ?>">
                        <input type="hidden" name="year_id" value="<?php echo $active_year_id; ?>">

                        <div class="form-group">
                            <label>ชื่อเรื่อง / หัวข้อสื่อการสอน <span style="color:red;">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="เช่น สไลด์บทที่ 1 หรือ ลิงก์วิดีโออธิบายเรื่องเซต" required>
                        </div>
                        
                        <div class="form-group">
                            <label>คำอธิบายเพิ่มเติม (ถ้ามี)</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="อธิบายให้นักเรียนเข้าใจว่าสื่อนี้เกี่ยวกับอะไร..."></textarea>
                        </div>

                        <div class="form-group" style="margin-top:20px;">
                            <label>ประเภทของสื่อ</label>
                            <div style="display:flex; gap:20px; margin-bottom:15px;">
                                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                    <input type="radio" name="media_type" value="file" checked onchange="toggleMediaType()"> 
                                    <span><i class="fa-solid fa-file-arrow-up"></i> อัปโหลดไฟล์ (PDF, Word, PPT, รูปภาพ)</span>
                                </label>
                                <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                    <input type="radio" name="media_type" value="link" onchange="toggleMediaType()"> 
                                    <span><i class="fa-solid fa-link"></i> แนบลิงก์ (YouTube, Google Drive)</span>
                                </label>
                            </div>
                        </div>

                        <div id="file_input_area" class="form-group" style="background:#f1f5f9; padding:20px; border-radius:12px; border:2px dashed #cbd5e1;">
                            <label>เลือกไฟล์จากเครื่องของคุณ <span style="color:red;">*</span></label>
                            <input type="file" name="upload_file" class="form-control" style="background:white;">
                            <small style="color:var(--text-secondary); display:block; margin-top:8px;">รองรับไฟล์: .pdf, .doc, .docx, .ppt, .pptx, .xls, .xlsx, .jpg, .png, .mp4 (ขนาดไม่เกิน 50MB)</small>
                        </div>

                        <div id="link_input_area" class="form-group" style="display:none; background:#f1f5f9; padding:20px; border-radius:12px; border:2px dashed #cbd5e1;">
                            <label>วาง URL หรือลิงก์ที่นี่ <span style="color:red;">*</span></label>
                            <input type="url" name="media_link" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                        </div>

                        <button type="submit" class="btn-add" style="width:100%; margin-top:20px; font-size:1.1rem;">
                            <i class="fa-solid fa-save"></i> บันทึกและเพิ่มสื่อ
                        </button>
                    </form>
                </div>

                <script>
                    function toggleMediaType() {
                        const isFile = document.querySelector('input[name="media_type"]:checked').value === 'file';
                        document.getElementById('file_input_area').style.display = isFile ? 'block' : 'none';
                        document.getElementById('link_input_area').style.display = isFile ? 'none' : 'block';
                        document.querySelector('input[name="upload_file"]').required = isFile;
                        document.querySelector('input[name="media_link"]').required = !isFile;
                    }
                    toggleMediaType();
                </script>

                <h3 style="margin:30px 0 15px 0; color:var(--text-main); font-family:'Prompt', sans-serif;"><i class="fa-solid fa-boxes-stacked"></i> คลังสื่อการสอนที่อัปโหลดแล้ว</h3>
                <div class="menu-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                    <?php 
                    $sql_media = "SELECT * FROM teaching_media 
                                  WHERE school_id=$school_id AND teacher_id=$user_id AND year_id=$active_year_id AND course_code='$course_code_raw' AND class_level='$class_lvl' AND room_number='$room_no'
                                  ORDER BY created_at DESC";
                    $res_media = $conn->query($sql_media);
                    
                    if($res_media->num_rows == 0):
                    ?>
                        <div style="grid-column: 1 / -1; text-align:center; padding:40px; border:2px dashed #cbd5e1; border-radius:16px; color:#94a3b8;">
                            <i class="fa-solid fa-box-open" style="font-size:3rem; margin-bottom:10px;"></i><p>ยังไม่มีสื่อการสอนในห้องเรียนนี้</p>
                        </div>
                    <?php else: 
                        while($m = $res_media->fetch_assoc()):
                            $icon_class = 'fa-file icon-other';
                            $ext = strtolower($m['file_extension']);
                            if($m['media_type'] == 'link') {
                                $icon_class = (strpos(strtolower($m['file_path']), 'youtube') !== false || strpos(strtolower($m['file_path']), 'youtu.be') !== false) ? 'fa-youtube icon-video' : 'fa-link icon-link';
                            } else {
                                if(in_array($ext, ['pdf'])) $icon_class = 'fa-file-pdf icon-pdf';
                                elseif(in_array($ext, ['doc','docx'])) $icon_class = 'fa-file-word icon-word';
                                elseif(in_array($ext, ['ppt','pptx'])) $icon_class = 'fa-file-powerpoint icon-ppt';
                                elseif(in_array($ext, ['jpg','jpeg','png','gif'])) $icon_class = 'fa-file-image icon-image';
                                elseif(in_array($ext, ['mp4','avi','mov'])) $icon_class = 'fa-file-video icon-video';
                            }
                            $is_visible = $m['is_visible'];
                            $vis_color = $is_visible ? '#10b981' : '#94a3b8';
                            $vis_bg = $is_visible ? '#ecfdf5' : '#f1f5f9';
                            $vis_icon = $is_visible ? 'fa-eye' : 'fa-eye-slash';
                            $vis_title = $is_visible ? 'ปิดการมองเห็นจากนักเรียน' : 'เปิดให้นักเรียนดูได้';
                    ?>
                        <div class="media-card" style="<?php echo !$is_visible ? 'opacity:0.75;' : ''; ?>">
                            <?php if(!$is_visible): ?>
                                <div style="position:absolute; top:15px; right:15px; background:#f1f5f9; color:#64748b; padding:4px 8px; border-radius:8px; font-size:0.75rem; font-weight:bold;"><i class="fa-solid fa-eye-slash"></i> ซ่อนอยู่</div>
                            <?php endif; ?>
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <i class="fa-brands <?php echo $icon_class; ?> media-icon"></i>
                                    <?php if($is_visible): ?>
                                    <span style="font-size:0.75rem; background:#f1f5f9; padding:4px 8px; border-radius:6px; color:var(--text-secondary);"><?php echo date('d/m/Y', strtotime($m['created_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h4 style="margin:10px 0 5px 0; font-size:1.1rem; color:var(--text-main); font-family:'Prompt', sans-serif; line-height:1.4;"><?php echo htmlspecialchars($m['title']); ?></h4>
                            </div>
                            <div class="media-actions">
                                <div style="display:flex; gap:8px; flex:1;">
                                    <form action="teacher_action.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="toggle_media_visibility">
                                        <input type="hidden" name="media_id" value="<?php echo $m['id']; ?>">
                                        <input type="hidden" name="select_class_key" value="<?php echo htmlspecialchars($select_class_key); ?>">
                                        <button type="submit" class="btn-action" style="background:<?php echo $vis_bg; ?>; color:<?php echo $vis_color; ?>; padding:8px 12px; border-radius:8px; border:none; font-size:1rem;" title="<?php echo $vis_title; ?>"><i class="fa-solid <?php echo $vis_icon; ?>"></i></button>
                                    </form>
                                    <a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank" class="btn-action" style="background:#eff6ff; color:#2563eb; text-decoration:none; flex:1; text-align:center; padding:8px; border-radius:8px;">
                                        <i class="fa-solid <?php echo $m['media_type']=='file' ? 'fa-download' : 'fa-link'; ?>"></i> เปิดดู
                                    </a>
                                </div>
                                <form action="teacher_action.php" method="POST" onsubmit="return confirm('คุณต้องการลบสื่อการสอนนี้ใช่หรือไม่?');" style="margin:0; margin-left:8px;">
                                    <input type="hidden" name="action" value="delete_media">
                                    <input type="hidden" name="media_id" value="<?php echo $m['id']; ?>">
                                    <input type="hidden" name="select_class_key" value="<?php echo htmlspecialchars($select_class_key); ?>">
                                    <button type="submit" class="btn-delete-icon" title="ลบข้อมูล"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; endif; ?>
                </div>
            <?php endif; ?>

            <?php 
            if($view == 'analytics' && $select_class_key): 
                list($room_str, $subject_str) = explode('|', $select_class_key);
                list($class_lvl, $room_no) = explode('/', $room_str);
                $course_code_raw = (strpos($subject_str, '(') !== false) ? str_replace(')', '', explode('(', $subject_str)[1]) : $subject_str;
                
                // หาจำนวนนักเรียนทั้งหมดในห้องนี้
                $sql_std = "SELECT u.user_id, u.full_name, ud.student_number 
                            FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id 
                            WHERE u.school_id=$school_id AND u.role='student' 
                            AND ud.year_id=$active_year_id AND ud.class_level='$class_lvl' AND ud.room_number='$room_no'
                            ORDER BY ud.student_number ASC";
                $res_std = $conn->query($sql_std);
                $class_students = [];
                $total_students = $res_std->num_rows;
                if($res_std) {
                    while($s = $res_std->fetch_assoc()) $class_students[$s['user_id']] = $s;
                }

                // ดึงสื่อการสอนทั้งหมด
                $sql_media = "SELECT * FROM teaching_media 
                              WHERE school_id=$school_id AND teacher_id=$user_id AND year_id=$active_year_id AND course_code='$course_code_raw' AND class_level='$class_lvl' AND room_number='$room_no'
                              ORDER BY created_at DESC";
                $res_media = $conn->query($sql_media);
            ?>
                <div class="card-form">
                    <h3 style="margin-bottom:15px; color:var(--primary-color); font-family:'Prompt', sans-serif;"><i class="fa-solid fa-chart-line"></i> สถิติการเข้าชมสื่อ (<?php echo htmlspecialchars("$course_code_raw $class_lvl/$room_no"); ?>)</h3>
                    <p style="color:var(--text-secondary); margin-bottom:20px;">มีนักเรียนในห้องทั้งหมด <strong><?php echo $total_students; ?></strong> คน</p>

                    <?php if($res_media->num_rows == 0): ?>
                        <div style="text-align:center; padding:40px; border:2px dashed #cbd5e1; border-radius:16px; color:#94a3b8;">ไม่พบสื่อการสอน</div>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:15px;">
                            <?php 
                            while($m = $res_media->fetch_assoc()): 
                                $mid = $m['id'];
                                // ดึงคนที่ดูแล้ว
                                $v_q = $conn->query("SELECT student_id, viewed_at FROM media_views WHERE media_id = $mid");
                                $viewed_arr = [];
                                if($v_q) { while($v = $v_q->fetch_assoc()) $viewed_arr[$v['student_id']] = $v['viewed_at']; }
                                
                                $view_count = count($viewed_arr);
                                $percent = $total_students > 0 ? ($view_count / $total_students) * 100 : 0;
                                
                                $p_color = '#ef4444'; // Red
                                if($percent >= 80) $p_color = '#10b981'; // Green
                                elseif($percent >= 40) $p_color = '#f59e0b'; // Yellow
                                
                                // จัดกลุ่มรายชื่อสำหรับ Modal
                                $viewed_html = '';
                                $not_viewed_html = '';
                                foreach($class_students as $sid => $sdata) {
                                    if(isset($viewed_arr[$sid])) {
                                        $v_time = date('d/m/Y H:i', strtotime($viewed_arr[$sid]));
                                        $viewed_html .= "<div style='padding:8px 0; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between;'><span style='color:#166534;'><i class='fa-solid fa-check'></i> เลขที่ {$sdata['student_number']} {$sdata['full_name']}</span> <small style='color:#94a3b8;'>$v_time</small></div>";
                                    } else {
                                        $not_viewed_html .= "<div style='padding:8px 0; border-bottom:1px solid #e2e8f0; color:#b91c1c;'><i class='fa-solid fa-xmark'></i> เลขที่ {$sdata['student_number']} {$sdata['full_name']}</div>";
                                    }
                                }
                                if($viewed_html == '') $viewed_html = "<div style='color:#94a3b8; font-style:italic;'>ยังไม่มีนักเรียนเข้าดู</div>";
                                if($not_viewed_html == '') $not_viewed_html = "<div style='color:#166534; font-style:italic;'>เข้าดูครบทุกคนแล้ว เยี่ยมมาก!</div>";
                            ?>
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:15px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                        <div style="flex:2; min-width:200px;">
                                            <h4 style="margin:0; color:var(--text-main); font-size:1.05rem;"><i class="fa-regular <?php echo $m['media_type']=='file'?'fa-file':'fa-link'; ?>" style="color:#64748b; margin-right:5px;"></i> <?php echo htmlspecialchars($m['title']); ?></h4>
                                            <div class="stat-progress-bg">
                                                <div class="stat-progress-fill" style="width:<?php echo $percent; ?>%; background:<?php echo $p_color; ?>;"></div>
                                            </div>
                                        </div>
                                        <div style="flex:1; min-width:150px; text-align:right;">
                                            <div style="font-size:1.1rem; font-weight:bold; color:<?php echo $p_color; ?>;"><?php echo $view_count; ?> <span style="font-size:0.8rem; color:#64748b;">/ <?php echo $total_students; ?> คน (<?php echo round($percent); ?>%)</span></div>
                                            <button onclick="openModal('modal_stats_<?php echo $mid; ?>')" class="btn-action" style="background:#fff; color:var(--primary-color); border:1px solid #cbd5e1; padding:4px 10px; font-size:0.8rem; margin-top:5px;"><i class="fa-solid fa-users-viewfinder"></i> ดูรายชื่อ</button>
                                        </div>
                                    </div>
                                </div>

                                <div id="modal_stats_<?php echo $mid; ?>" class="modal">
                                    <div class="modal-content">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding-bottom:10px; border-bottom:1px solid #e2e8f0;">
                                            <h3 style="margin:0; font-size:1.1rem;">สถิติ: <?php echo htmlspecialchars($m['title']); ?></h3>
                                            <span class="close-modal" onclick="closeModal('modal_stats_<?php echo $mid; ?>')" style="font-size:1.5rem;">&times;</span>
                                        </div>
                                        <div style="display:flex; gap:15px; flex-wrap:wrap;">
                                            <div style="flex:1; min-width:200px; background:#ecfdf5; padding:15px; border-radius:12px; border:1px solid #a7f3d0; max-height:300px; overflow-y:auto;">
                                                <h4 style="color:#166534; margin-top:0;"><i class="fa-solid fa-eye"></i> ดูแล้ว (<?php echo $view_count; ?>)</h4>
                                                <?php echo $viewed_html; ?>
                                            </div>
                                            <div style="flex:1; min-width:200px; background:#fee2e2; padding:15px; border-radius:12px; border:1px solid #fecaca; max-height:300px; overflow-y:auto;">
                                                <h4 style="color:#b91c1c; margin-top:0;"><i class="fa-solid fa-eye-slash"></i> ยังไม่ดู (<?php echo $total_students - $view_count; ?>)</h4>
                                                <?php echo $not_viewed_html; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.style.display = 'none'; }
    </script>

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
        <a href="grade_teacher.php" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span> เกรด
        </a>
        <a href="media_teacher.php" class="nav-item active">
            <span class="nav-icon"><i class="fa-solid fa-folder-open"></i></span> สื่อการสอน
        </a>
    </div>

    <script>
        let timeoutTimer;
        const timeoutDuration = 300000;
        function startTimer() { clearTimeout(timeoutTimer); timeoutTimer = setTimeout(doLogout, timeoutDuration); }
        function doLogout() { alert("หมดเวลาการใช้งานอัตโนมัติ"); window.location.href = 'logout.php?timeout=1'; }
        window.onload = startTimer; document.onmousemove = startTimer; document.onkeypress = startTimer;
    </script>
</body>
</html>