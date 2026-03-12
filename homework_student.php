<?php
// ชื่อไฟล์: homework_student.php
session_start();
require_once 'db_connect.php';

// --- SECURITY ---
$timeout_duration = 300; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset(); session_destroy(); setcookie("user_login", "", time() - 3600, "/"); header("Location: index.php?timeout=1"); exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header("Location: index.php"); exit; }

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];
$school_row = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school_row['school_name'];

// Get Student Info & Class
$ay_q = $conn->query("SELECT year_id FROM academic_years WHERE school_id = $school_id AND is_active = 1");
$active_year_id = $ay_q->fetch_assoc()['year_id'] ?? 0;
$student = $conn->query("SELECT u.*, ud.class_level, ud.room_number FROM users u LEFT JOIN user_year_data ud ON u.user_id = ud.user_id AND ud.year_id = $active_year_id WHERE u.user_id = $user_id")->fetch_assoc();

$my_class = $student['class_level'];
$my_room = $student['room_number'];

// Helper Image
$img_src = $student['profile_img'];
if(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png' && !file_exists("uploads/".$img_src)) $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix';
elseif(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png') $img_src = "uploads/" . $img_src;
elseif ($img_src == 'default_avatar.png') $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $student['username'];

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'list';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Homework - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hw-item { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 15px; position: relative; transition: 0.2s; display: flex; flex-direction: column; gap: 10px; }
        .hw-item:hover { border-color: var(--primary-color); box-shadow: var(--shadow-sm); }
        .hw-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .hw-tag { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .status-pending { background: #fefce8; color: #854d0e; }
        .status-submitted { background: #dcfce7; color: #166534; }
        .status-late { background: #fee2e2; color: #991b1b; }
        .status-graded { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .upload-area { border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; background: #f8fafc; transition: 0.2s; }
        .upload-area:hover { border-color: var(--primary-color); background: #eff6ff; }
        
        .stamp-box { position: absolute; top: 10px; right: 10px; border: 3px solid; padding: 10px; border-radius: 50%; width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; font-weight: bold; transform: rotate(-15deg); opacity: 0.8; font-size: 0.8rem; z-index: 10; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="brand"><i class="fa-solid fa-book-open"></i> <div>My Homework <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;">การบ้านของฉัน</span></div></div>
        <div class="user-info">
            <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $student['profile_frame']; ?>">
            <span><?php echo $student['full_name']; ?></span>
            <a href="dashboard_student.php" class="btn-action" style="background:#f1f5f9; color:#64748b; margin-left:10px;"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $student['profile_frame']; ?>">
                <h4 style="margin-top:10px;"><?php echo $student['full_name']; ?></h4>
                <div style="background:#eff6ff; color:var(--primary-color); padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    <?php echo $my_class.'/'.$my_room; ?>
                </div>
            </div>
            <h3>เมนู</h3>
            <a href="?tab=list" class="menu-item <?php echo $tab=='list'?'active':''; ?>"><i class="fa-solid fa-list-ul"></i> รายการงานทั้งหมด</a>
            <a href="dashboard_student.php" class="menu-item"><i class="fa-solid fa-house"></i> กลับหน้าหลัก</a>
        </aside>

        <main class="content-area">
            <?php if(isset($_SESSION['msg'])): ?><div class="alert-box alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div><?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?><div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

            <?php if($tab == 'list'): ?>
                <div class="page-header"><h2 class="page-title">งานที่ได้รับมอบหมาย</h2></div>
                <div style="display:flex; flex-direction:column; gap:15px;">
                    <?php 
                    // Query งานที่ตรงกับห้อง หรือ ระบุตัวบุคคล (Target JSON)
                    $sql = "SELECT a.*, s.submission_id, s.status, s.score, s.teacher_stamp 
                            FROM assignments a 
                            LEFT JOIN submissions s ON a.assignment_id = s.assignment_id AND s.student_id = $user_id
                            WHERE a.school_id = $school_id AND a.is_hidden = 0 
                            AND (
                                (a.class_level = '$my_class' AND a.room_number = '$my_room' AND (a.target_students IS NULL OR a.target_students = 'null'))
                                OR 
                                (a.target_students LIKE '%\"$user_id\"%')
                            )
                            ORDER BY a.due_date ASC, a.created_at DESC";
                    $res = $conn->query($sql);
                    
                    if($res->num_rows == 0) echo "<div style='text-align:center; padding:50px; color:#94a3b8;'>ไม่มีการบ้านในขณะนี้</div>";

                    while($row = $res->fetch_assoc()):
                        // Status Logic
                        $status_tag = '<span class="hw-tag status-pending">รอส่ง</span>';
                        $is_submitted = false;
                        if($row['status']) {
                            $is_submitted = true;
                            if($row['status'] == 'submitted') $status_tag = '<span class="hw-tag status-submitted">ส่งแล้ว</span>';
                            elseif($row['status'] == 'late') $status_tag = '<span class="hw-tag status-late">ส่งช้า</span>';
                            elseif($row['status'] == 'graded') $status_tag = '<span class="hw-tag status-graded">ตรวจแล้ว</span>';
                        } else {
                            if(strtotime($row['due_date']) < time()) $status_tag = '<span class="hw-tag status-late">เลยกำหนดส่ง</span>';
                        }
                    ?>
                    <div class="hw-item">
                        <div class="hw-header">
                            <div>
                                <div style="font-weight:bold; font-size:1.1rem; margin-bottom:5px;"><?php echo $row['title']; ?></div>
                                <div style="font-size:0.9rem; color:#64748b;">วิชา: <?php echo $row['course_code']; ?> | กำหนดส่ง: <?php echo date('d/m/Y H:i', strtotime($row['due_date'])); ?></div>
                            </div>
                            <?php echo $status_tag; ?>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                            <div>
                                <?php if($row['status'] == 'graded'): ?>
                                    <span style="color:var(--primary-color); font-weight:bold;">ได้คะแนน: <?php echo $row['score']; ?> / <?php echo $row['max_score']; ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="?tab=view&id=<?php echo $row['assignment_id']; ?>" class="btn-action" style="background:var(--primary-color); color:white; padding:8px 20px;">
                                <?php echo $is_submitted ? '<i class="fa-solid fa-eye"></i> ดูงาน/คะแนน' : '<i class="fa-solid fa-upload"></i> ส่งงาน'; ?>
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <?php if($tab == 'view'): 
                $aid = $_GET['id'];
                $assign = $conn->query("SELECT a.*, u.full_name as teacher_name FROM assignments a JOIN users u ON a.teacher_id = u.user_id WHERE a.assignment_id=$aid")->fetch_assoc();
                $sub = $conn->query("SELECT * FROM submissions WHERE assignment_id=$aid AND student_id=$user_id")->fetch_assoc();
                
                $is_late = (time() > strtotime($assign['due_date']));
                $can_submit = true;
                if($sub && $assign['allow_resubmit'] == 0) $can_submit = false; // ส่งแล้วห้ามส่งซ้ำ
                if($is_late && $assign['allow_late'] == 0 && !$sub) $can_submit = false; // เลยกำหนดห้ามส่ง
            ?>
                <div class="page-header">
                    <h2 class="page-title" style="font-size:1.3rem;"><?php echo $assign['title']; ?></h2>
                </div>

                <div class="card-form" style="position:relative;">
                    <div style="color:#64748b; font-size:0.9rem; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid #eee;">
                        <i class="fa-solid fa-user-tie"></i> ครูผู้สอน: <?php echo $assign['teacher_name']; ?> | 
                        <i class="fa-regular fa-clock"></i> กำหนดส่ง: <?php echo date('d/m/Y H:i', strtotime($assign['due_date'])); ?>
                        <?php if($is_late && !$sub) echo '<span style="color:red; font-weight:bold;"> (เลยกำหนดส่ง)</span>'; ?>
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <?php echo $assign['description']; ?>
                    </div>

                    <?php 
                    if($assign['attachments'] && $assign['attachments'] != '[]'): 
                        $files = json_decode($assign['attachments'], true);
                    ?>
                        <h4 style="margin-bottom:10px;">ไฟล์แนบจากครู:</h4>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <?php foreach($files as $f): ?>
                            <a href="uploads/assignments/<?php echo $f['path']; ?>" target="_blank" class="btn-action" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1;">
                                <i class="fa-solid fa-paperclip"></i> <?php echo $f['name']; ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if($sub && $sub['status'] == 'graded'): ?>
                <div class="card-form" style="background:#f0fdf4; border-color:#bbf7d0; position:relative;">
                    <h3 style="color:#166534; margin-bottom:15px;"><i class="fa-solid fa-check-circle"></i> ผลการตรวจ</h3>
                    
                    <?php if($sub['teacher_stamp']): 
                        $stamp = $sub['teacher_stamp'];
                        $s_color = ($stamp=='excellent')?'#166534':(($stamp=='good')?'#1e40af':'#991b1b');
                        $s_bg = ($stamp=='excellent')?'#dcfce7':(($stamp=='good')?'#dbeafe':'#fee2e2');
                        $s_text = ($stamp=='excellent')?'ดีมาก':(($stamp=='good')?'ดี':'ปรับปรุง');
                    ?>
                        <div class="stamp-box" style="color:<?php echo $s_color; ?>; background:<?php echo $s_bg; ?>; border-color:<?php echo $s_color; ?>;">
                            <?php echo $s_text; ?>
                        </div>
                    <?php endif; ?>

                    <div style="font-size:1.2rem; font-weight:bold; margin-bottom:10px;">
                        คะแนน: <?php echo $sub['score']; ?> / <?php echo $assign['max_score']; ?>
                    </div>
                    
                    <?php if($sub['feedback']): ?>
                        <div style="background:white; padding:15px; border-radius:8px; border:1px solid #dcfce7;">
                            <strong>ความเห็นครู:</strong><br>
                            <?php echo nl2br($sub['feedback']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if($sub['feedback_audio']): ?>
                        <div style="margin-top:10px;">
                            <strong>เสียงตอบกลับ:</strong><br>
                            <audio controls src="<?php echo $sub['feedback_audio']; ?>"></audio>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if($can_submit): ?>
                    <form action="homework_student_action.php" method="POST" enctype="multipart/form-data" class="card-form">
                        <h3 style="margin-bottom:15px; color:var(--primary-color);">
                            <i class="fa-solid fa-upload"></i> <?php echo $sub ? 'ส่งงานใหม่ / แก้ไข' : 'ส่งงาน'; ?>
                        </h3>
                        <input type="hidden" name="action" value="submit_work">
                        <input type="hidden" name="assignment_id" value="<?php echo $aid; ?>">
                        
                        <?php 
                        $types = json_decode($assign['submission_types'] ?? '[]', true);
                        if(empty($types)) $types = ['file', 'text']; // Default
                        ?>

                        <?php if(in_array('file', $types) || in_array('photo', $types)): ?>
                            <div class="form-group">
                                <label>อัปโหลดไฟล์ / รูปภาพ</label>
                                <div class="upload-area" onclick="document.getElementById('file_input').click()">
                                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:2rem; color:#94a3b8;"></i>
                                    <p style="margin-top:5px; color:#64748b;">คลิกเพื่อเลือกไฟล์ หรือ ถ่ายรูป</p>
                                </div>
                                <input type="file" name="files[]" id="file_input" multiple style="display:none;" onchange="showFileNames(this)">
                                <div id="file_list" style="margin-top:10px; font-size:0.9rem; color:#64748b;"></div>
                            </div>
                        <?php endif; ?>

                        <?php if(in_array('text', $types)): ?>
                            <div class="form-group">
                                <label>พิมพ์คำตอบ / ข้อความ</label>
                                <textarea name="text_content" class="form-control" rows="5" placeholder="พิมพ์คำตอบที่นี่..."><?php echo $sub['text_content'] ?? ''; ?></textarea>
                            </div>
                        <?php endif; ?>

                        <?php if(in_array('link', $types)): ?>
                            <div class="form-group">
                                <label>ลิงก์งาน (Google Drive, Canva, etc.)</label>
                                <input type="text" name="link" class="form-control" placeholder="https://..." value="<?php echo $sub['links'] ?? ''; ?>">
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-add" style="width:100%; padding:12px; font-size:1.1rem;">ส่งงาน</button>
                    </form>
                <?php elseif(!$sub): ?>
                    <div class="alert-box alert-error" style="text-align:center; display:block;">
                        <h3><i class="fa-solid fa-lock"></i> ปิดรับการส่งงานแล้ว</h3>
                        <p>งานนี้เลยกำหนดส่งและไม่อนุญาตให้ส่งช้า</p>
                    </div>
                <?php endif; ?>

                <?php if($sub): ?>
                    <div class="card-form">
                        <h4 style="margin-bottom:15px; color:#64748b;">ประวัติการส่งงาน</h4>
                        <div style="font-size:0.9rem;">
                            ส่งเมื่อ: <?php echo date('d/m/Y H:i', strtotime($sub['submitted_at'])); ?> 
                            <?php if($sub['status']=='late') echo '<span class="hw-tag status-late">ส่งช้า</span>'; ?>
                        </div>
                        <?php if($sub['text_content']): ?>
                            <div style="background:#f8fafc; padding:10px; margin-top:10px; border-radius:8px;"><?php echo nl2br($sub['text_content']); ?></div>
                        <?php endif; ?>
                        <?php 
                        if($sub['files']): 
                            $s_files = json_decode($sub['files'], true);
                            if(is_array($s_files)):
                        ?>
                            <div style="margin-top:10px;">
                                <?php foreach($s_files as $f): ?>
                                    <a href="uploads/assignments/<?php echo $f['path']; ?>" target="_blank" style="display:block; margin-bottom:5px; color:var(--primary-color);">
                                        <i class="fa-solid fa-file"></i> <?php echo $f['name']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

    <script>
        function showFileNames(input) {
            const list = document.getElementById('file_list');
            list.innerHTML = '';
            if(input.files.length > 0) {
                for(let i=0; i<input.files.length; i++) {
                    list.innerHTML += `<div><i class="fa-solid fa-paperclip"></i> ${input.files[i].name}</div>`;
                }
            }
        }
    </script>
</body>
</html>