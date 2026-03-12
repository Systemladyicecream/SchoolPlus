<?php
// ชื่อไฟล์: homework_teacher.php
session_start();
require_once 'db_connect.php';

// --- SECURITY & SETUP ---
$timeout_duration = 300; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset(); session_destroy(); setcookie("user_login", "", time() - 3600, "/"); header("Location: index.php?timeout=1"); exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: index.php"); exit; }

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];
$school_row = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school_row['school_name'];

// --- AUTO MIGRATION: UPDATE DATABASE SCHEMA ---
// ตรวจสอบและเพิ่มคอลัมน์ที่จำเป็นหากยังไม่มี
$cols = [
    'assignments' => [
        'rubric_data' => "TEXT COMMENT 'JSON Rubric Criteria'"
    ],
    'submissions' => [
        'teacher_stamp' => "VARCHAR(50) COMMENT 'good, very_good, improve'",
        'feedback_file' => "VARCHAR(255)",
        'feedback_audio' => "VARCHAR(255)",
        'rubric_scores' => "TEXT COMMENT 'JSON Rubric Scores'"
    ]
];

foreach ($cols as $table => $columns) {
    foreach ($columns as $col => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
        if ($chk->num_rows == 0) {
            $conn->query("ALTER TABLE $table ADD COLUMN $col $def");
        }
    }
}
// -----------------------------------------------

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'manage';
$ay_q = $conn->query("SELECT year_id FROM academic_years WHERE school_id = $school_id AND is_active = 1");
$active_year_id = $ay_q->fetch_assoc()['year_id'] ?? 0;
$teacher = $conn->query("SELECT u.*, ud.subjects_taught, ud.room_number FROM users u LEFT JOIN user_year_data ud ON u.user_id = ud.user_id AND ud.year_id = $active_year_id WHERE u.user_id = $user_id")->fetch_assoc();

// Helper Image
$img_src = $teacher['profile_img'];
if(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png' && !file_exists("uploads/".$img_src)) $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix';
elseif(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png') $img_src = "uploads/" . $img_src;
elseif ($img_src == 'default_avatar.png') $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $teacher['username'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Homework System - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .hw-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 15px; position: relative; }
        .hw-status { position: absolute; top: 20px; right: 20px; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .status-active { background: #dcfce7; color: #166534; } .status-closed { background: #f1f5f9; color: #64748b; }
        
        /* Grid View for Grading */
        .grading-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .student-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; transition: 0.2s; position: relative; }
        .student-card:hover { border-color: var(--primary-color); box-shadow: var(--shadow-sm); }
        .st-status { font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; display: inline-block; margin-bottom: 5px; }
        .st-submit { background: #dcfce7; color: #166534; } 
        .st-late { background: #fef9c3; color: #854d0e; }
        .st-missing { background: #fee2e2; color: #991b1b; }
        .st-graded { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

        /* Stamp Styles */
        .stamp-option { cursor: pointer; display: inline-block; text-align: center; margin: 5px; opacity: 0.5; transition: 0.2s; }
        .stamp-option:hover, .stamp-option.selected { opacity: 1; transform: scale(1.1); }
        .stamp-img { width: 60px; height: 60px; border-radius: 50%; border: 3px solid #ccc; padding: 2px; }
        
        .rubric-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="brand"><i class="fa-solid fa-book-open"></i> <div>Homework System <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;">ระบบสั่งการบ้าน</span></div></div>
        <div class="user-info">
            <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $teacher['profile_frame']; ?>">
            <span>อ.<?php echo $teacher['full_name']; ?></span>
            <a href="dashboard_teacher.php" class="btn-action" style="background:#f1f5f9; color:#64748b; margin-left:10px;"><i class="fa-solid fa-arrow-left"></i> กลับ Dashboard</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $teacher['profile_frame']; ?>">
                <h4 style="margin-top:10px;"><?php echo $teacher['full_name']; ?></h4>
                <div style="background:#eff6ff; color:var(--primary-color); padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">Teacher</div>
            </div>
            <h3>เมนูการบ้าน</h3>
            <a href="?tab=manage" class="menu-item <?php echo $tab=='manage'?'active':''; ?>"><i class="fa-solid fa-list-check"></i> จัดการงาน (Management)</a>
            <a href="?tab=create" onclick="resetForm()" class="menu-item <?php echo $tab=='create'?'active':''; ?>"><i class="fa-solid fa-plus-circle"></i> สั่งงานใหม่</a>
            <a href="?tab=grading" class="menu-item <?php echo $tab=='grading'||$tab=='grading_view'?'active':''; ?>"><i class="fa-solid fa-marker"></i> ตรวจและให้คะแนน</a>
        </aside>

        <main class="content-area">
            <?php if(isset($_SESSION['msg'])): ?><div class="alert-box alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div><?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?><div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

            <?php if($tab == 'manage'): ?>
                <div class="page-header"><h2 class="page-title">คลังงานและการจัดการ</h2><a href="?tab=create" onclick="resetForm()" class="btn-add"><i class="fa-solid fa-plus"></i> สั่งงานเพิ่ม</a></div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>หัวข้อ</th><th>กลุ่มเรียน</th><th>กำหนดส่ง</th><th>ส่งแล้ว/ทั้งหมด</th><th>จัดการ</th></tr></thead>
                        <tbody>
                            <?php 
                            $res = $conn->query("SELECT * FROM assignments WHERE teacher_id=$user_id AND school_id=$school_id ORDER BY created_at DESC");
                            while($row = $res->fetch_assoc()):
                                // Stat
                                $aid = $row['assignment_id'];
                                $cnt_sub = $conn->query("SELECT COUNT(*) FROM submissions WHERE assignment_id=$aid AND status != 'returned'")->fetch_row()[0];
                                
                                // นับนักเรียนในห้อง
                                $cnt_all = 0;
                                $target_cond = "";
                                if($row['target_students'] && $row['target_students']!='null') {
                                    $target_ids = json_decode($row['target_students']);
                                    if(is_array($target_ids)) $cnt_all = count($target_ids);
                                } else {
                                    $cnt_all_q = $conn->query("SELECT COUNT(*) FROM user_year_data WHERE class_level='{$row['class_level']}' AND room_number='{$row['room_number']}' AND school_id=$school_id AND year_id=$active_year_id");
                                    $cnt_all = $cnt_all_q->fetch_row()[0];
                                }
                            ?>
                            <tr>
                                <td><?php echo $row['title']; ?></td>
                                <td><?php echo $row['class_level'].'/'.$row['room_number']; ?> (<?php echo $row['course_code']; ?>)</td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['due_date'])); ?></td>
                                <td><span style="font-weight:bold; color:var(--primary-color);"><?php echo $cnt_sub; ?></span> / <?php echo $cnt_all; ?></td>
                                <td style="display:flex; gap:5px;">
                                    <button class="btn-action btn-edit" onclick="editAssign(<?php echo $aid; ?>)" title="แก้ไข"><i class="fa-solid fa-pen"></i></button>
                                    <a href="homework_action.php?action=duplicate&id=<?php echo $aid; ?>" class="btn-action" style="background:#f3e8ff; color:#7e22ce;" title="ทำซ้ำ"><i class="fa-solid fa-copy"></i></a>
                                    <a href="homework_action.php?action=notify_missing&id=<?php echo $aid; ?>" class="btn-action" style="background:#ffedd5; color:#c2410c;" title="ทวงงาน" onclick="return confirm('ส่งแจ้งเตือนหานักเรียนที่ยังไม่ส่งงาน?');"><i class="fa-solid fa-bell"></i></a>
                                    <a href="homework_action.php?action=delete_assignment&id=<?php echo $aid; ?>" class="btn-action btn-delete" onclick="return confirm('ยืนยันการลบ?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if($tab == 'create'): ?>
                <div class="page-header"><h2 class="page-title" id="page_title">สั่งงานใหม่</h2></div>
                <form id="hw_form" action="homework_action.php" method="POST" enctype="multipart/form-data" class="card-form">
                    <input type="hidden" name="action" id="form_action" value="add_assignment">
                    <input type="hidden" name="assignment_id" id="edit_assignment_id">
                    
                    <h4 style="margin-bottom:15px; color:var(--primary-color);">ข้อมูลงาน</h4>
                    <input type="text" name="title" id="inp_title" class="form-control" required placeholder="หัวข้องาน" style="margin-bottom:10px;">
                    <textarea id="summernote" name="description"></textarea>
                    
                    <div style="display:flex; gap:15px; margin-top:15px; flex-wrap:wrap;">
                         <div style="flex:1; min-width:200px;">
                             <select name="target_class_key" id="target_class" class="form-control" required>
                                <option value="">-- เลือกวิชา/ห้อง --</option>
                                <?php 
                                if(isset($teacher['subjects_taught'])){
                                    $subs = json_decode($teacher['subjects_taught'], true);
                                    if(is_array($subs)){
                                        foreach($subs as $sub){
                                            $r = $sub['room'];
                                            foreach($sub['subjects'] as $sname) echo "<option value='$r|$sname'>ห้อง $r - $sname</option>";
                                        }
                                    }
                                }
                                ?>
                            </select>
                         </div>
                        <div style="flex:1; min-width:200px;">
                            <input type="datetime-local" name="due_date" id="inp_due_date" class="form-control" required>
                        </div>
                        <div style="flex:1; min-width:100px;">
                            <input type="number" name="max_score" id="inp_max_score" class="form-control" placeholder="คะแนนเต็ม" value="10" required>
                        </div>
                    </div>

                    <h4 style="margin:20px 0 10px 0; color:var(--primary-color);">เกณฑ์การให้คะแนน (Rubric) - (ไม่บังคับ)</h4>
                    <div id="rubric_container" style="background:#f8fafc; padding:15px; border-radius:8px;">
                        </div>
                    <button type="button" class="btn-action" onclick="addRubricRow()" style="background:#eff6ff; color:var(--primary-color); border:1px dashed var(--primary-color); width:100%; margin-top:10px;"><i class="fa-solid fa-plus"></i> เพิ่มเกณฑ์คะแนน</button>
                    
                    <button type="submit" id="btn_submit_hw" class="btn-add" style="margin-top:20px; width:100%; padding:12px;">บันทึกและสั่งงาน</button>
                    <button type="button" id="btn_cancel_edit" class="btn-cancel" onclick="resetForm()" style="margin-top:10px; width:100%; text-align:center;">ยกเลิกการแก้ไข</button>
                </form>
            <?php endif; ?>

            <?php if($tab == 'grading'): ?>
                <div class="page-header"><h2 class="page-title">เลือกงานเพื่อตรวจ</h2></div>
                <div class="menu-grid">
                    <?php 
                    $res = $conn->query("SELECT * FROM assignments WHERE teacher_id=$user_id ORDER BY created_at DESC");
                    while($row = $res->fetch_assoc()):
                        $aid = $row['assignment_id'];
                        $pending = $conn->query("SELECT COUNT(*) FROM submissions WHERE assignment_id=$aid AND status='submitted'")->fetch_row()[0];
                    ?>
                    <a href="?tab=grading_view&id=<?php echo $aid; ?>" class="menu-card" style="align-items:flex-start; aspect-ratio:auto; min-height:120px;">
                        <div style="font-weight:bold; font-size:1.1rem;"><?php echo $row['title']; ?></div>
                        <div style="color:#64748b; font-size:0.9rem; margin-top:5px;"><?php echo $row['class_level'].'/'.$row['room_number']; ?></div>
                        <div style="margin-top:10px;">
                            <?php if($pending > 0): ?>
                                <span style="background:#fee2e2; color:#b91c1c; padding:4px 10px; border-radius:20px; font-size:0.8rem; font-weight:bold;"><?php echo $pending; ?> รอตรวจ</span>
                            <?php else: ?>
                                <span style="background:#dcfce7; color:#166534; padding:4px 10px; border-radius:20px; font-size:0.8rem;">ตรวจครบแล้ว</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <?php if($tab == 'grading_view'): 
                $aid = $_GET['id'];
                $assign = $conn->query("SELECT * FROM assignments WHERE assignment_id=$aid")->fetch_assoc();
                $rubric = json_decode($assign['rubric_data'], true);
            ?>
                <div class="page-header">
                    <div>
                        <a href="?tab=grading" style="color:#64748b; text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> กลับ</a>
                        <h2 class="page-title" style="margin-top:5px;">ตรวจ: <?php echo $assign['title']; ?></h2>
                    </div>
                    <div>
                        <button class="btn-action" onclick="toggleView('grid')"><i class="fa-solid fa-grip"></i> Grid</button>
                        <button class="btn-action" onclick="toggleView('list')"><i class="fa-solid fa-list"></i> List</button>
                    </div>
                </div>

                <div class="filter-bar">
                    <strong>Filter:</strong>
                    <a href="#" class="btn-action" onclick="filterSt('all')">ทั้งหมด</a>
                    <a href="#" class="btn-action" onclick="filterSt('submitted')" style="background:#dcfce7; color:#166534;">ส่งแล้ว</a>
                    <a href="#" class="btn-action" onclick="filterSt('missing')" style="background:#fee2e2; color:#991b1b;">ยังไม่ส่ง</a>
                    <a href="#" class="btn-action" onclick="filterSt('graded')" style="background:#dbeafe; color:#1e40af;">ตรวจแล้ว</a>
                </div>

                <div id="student_container" class="grading-grid">
                    <?php 
                    $sql_std = "SELECT u.user_id, u.full_name, ud.student_number 
                                FROM users u JOIN user_year_data ud ON u.user_id = ud.user_id 
                                WHERE ud.class_level='{$assign['class_level']}' AND ud.room_number='{$assign['room_number']}' 
                                AND ud.year_id=$active_year_id AND u.role='student' ORDER BY ud.student_number ASC";
                    $res_std = $conn->query($sql_std);
                    
                    while($std = $res_std->fetch_assoc()):
                        $sid = $std['user_id'];
                        $sub = $conn->query("SELECT * FROM submissions WHERE assignment_id=$aid AND student_id=$sid")->fetch_assoc();
                        
                        $status = 'missing';
                        $status_txt = 'ยังไม่ส่ง';
                        $score_display = '-';
                        if($sub) {
                            $status = $sub['status']; 
                            if($status=='submitted') $status_txt='ส่งแล้ว';
                            elseif($status=='late') $status_txt='ส่งช้า';
                            elseif($status=='graded') { $status_txt='ตรวจแล้ว'; $score_display = $sub['score']; }
                        }
                    ?>
                    <div class="student-card st-item" data-status="<?php echo $status; ?>">
                        <div class="st-status st-<?php echo $status; ?>"><?php echo $status_txt; ?></div>
                        <div style="font-weight:bold; margin-bottom:5px;">เลขที่ <?php echo $std['student_number']; ?> <?php echo $std['full_name']; ?></div>
                        <div style="color:#64748b; font-size:0.9rem; margin-bottom:10px;">คะแนน: <strong style="color:var(--primary-color);"><?php echo $score_display; ?></strong> / <?php echo $assign['max_score']; ?></div>
                        
                        <?php if($sub): ?>
                            <button type="button" class="btn-action" style="width:100%; background:var(--primary-color); color:white;" onclick='openGradeModal(<?php echo json_encode($sub); ?>, <?php echo json_encode($std); ?>, <?php echo json_encode($rubric); ?>)'>
                                <i class="fa-solid fa-pen-to-square"></i> ตรวจงาน
                            </button>
                        <?php else: ?>
                             <button disabled class="btn-action" style="width:100%; background:#eee; color:#aaa; cursor:not-allowed;">ยังไม่มีงาน</button>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>

                <div id="modal_grading" class="modal">
                    <div class="modal-content" style="max-width:800px; max-height:90vh;">
                        <span class="close-modal" onclick="closeModal('modal_grading')">&times;</span>
                        <h3 id="g_student_name" style="border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px;"></h3>
                        <form action="homework_action.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="save_grade">
                            <input type="hidden" name="submission_id" id="g_submission_id">
                            <input type="hidden" name="assignment_id" value="<?php echo $aid; ?>">
                            
                            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                                <div style="flex:1; min-width:300px; border-right:1px solid #eee; padding-right:15px;">
                                    <h4 style="color:var(--primary-color);">งานที่ส่ง</h4>
                                    <div id="g_work_display" style="margin:10px 0; padding:10px; background:#f8fafc; border-radius:8px;"></div>
                                    <h4 style="color:var(--primary-color); margin-top:15px;">ประทับตรา (Stamp)</h4>
                                    <div style="display:flex; justify-content:center;">
                                        <label class="stamp-option"><input type="radio" name="stamp" value="excellent" hidden><div class="stamp-img" style="background:#dcfce7; border-color:#166534; color:#166534; display:flex; align-items:center; justify-content:center; font-weight:bold;">ดีมาก</div></label>
                                        <label class="stamp-option"><input type="radio" name="stamp" value="good" hidden><div class="stamp-img" style="background:#dbeafe; border-color:#1e40af; color:#1e40af; display:flex; align-items:center; justify-content:center; font-weight:bold;">ดี</div></label>
                                        <label class="stamp-option"><input type="radio" name="stamp" value="improve" hidden><div class="stamp-img" style="background:#fee2e2; border-color:#991b1b; color:#991b1b; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.8rem;">ปรับปรุง</div></label>
                                    </div>
                                </div>
                                <div style="flex:1; min-width:300px;">
                                    <h4 style="color:var(--primary-color);">ให้คะแนน & ความเห็น</h4>
                                    <div id="g_rubric_area"></div>
                                    <div class="form-group"><label>คะแนนรวม (เต็ม <?php echo $assign['max_score']; ?>)</label><input type="number" name="total_score" id="g_total_score" class="form-control" step="0.5" required></div>
                                    <div class="form-group"><label>ความเห็น</label><textarea name="feedback" class="form-control" rows="3"></textarea></div>
                                    <div class="form-group">
                                        <label>แนบไฟล์ Feedback / บันทึกเสียง</label>
                                        <input type="file" name="feedback_file" class="form-control" style="margin-bottom:5px;">
                                        <button type="button" class="btn-action" id="btn_record" onclick="toggleRecord()"><i class="fa-solid fa-microphone"></i> อัดเสียง Feedback</button>
                                        <span id="record_status" style="color:red; display:none;"> กำลังอัดเสียง...</span>
                                        <input type="hidden" name="audio_data" id="audio_data_input">
                                    </div>
                                    <button type="submit" class="btn-add" style="width:100%;">บันทึกผลการตรวจ</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $('#summernote').summernote({ height: 150 });

        function addRubricRow(name='', max='') {
            const html = `<div class="rubric-row">
                <input type="text" name="rubric_name[]" class="form-control" placeholder="หัวข้อเกณฑ์ (เช่น ความถูกต้อง)" value="${name}" required>
                <input type="number" name="rubric_max[]" class="form-control" placeholder="คะแนนเต็ม" value="${max}" style="width:100px;" required>
                <button type="button" class="btn-action btn-delete" onclick="this.parentElement.remove()">X</button>
            </div>`;
            $('#rubric_container').append(html);
        }

        // --- EDIT ASSIGNMENT LOGIC ---
        function editAssign(id) {
            // เรียกข้อมูลจาก Server
            fetch(`homework_action.php?action=get_assignment_data&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if(data.error) { alert('ไม่พบข้อมูลงาน'); return; }
                    
                    // เปลี่ยน Tab ไปหน้า Create
                    window.history.pushState(null, '', '?tab=create');
                    // Reload เฉพาะส่วน content หรือบังคับ redirect ไป tab create แล้วค่อย fill data ก็ได้ 
                    // แต่วิธีที่ง่ายคือใช้ JS ควบคุม DOM ในหน้าเดียวกัน ถ้าระบบเป็น SPA ย่อมๆ
                    // ในที่นี้ระบบเป็น PHP Render Tab ดังนั้นเราต้อง Redirect ไป tab=create พร้อมแนบ id หรือใช้ AJAX
                    // เพื่อความสมบูรณ์และง่าย: เราใช้ JS Populate Form ในหน้านี้เลย (ถ้า tab=manage อยู่)
                    // แต่เนื่องจาก element ของ tab=create ไม่ถูก render ถ้าอยู่ tab=manage 
                    // ดังนั้นเราต้อง Redirect ไป ?tab=create&edit_id=...
                    window.location.href = `homework_teacher.php?tab=create&edit_id=${id}`;
                });
        }

        // ตรวจสอบ Query Param เมื่อโหลดหน้า (สำหรับการแก้ไข)
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit_id');
            if(editId && document.getElementById('hw_form')) {
                loadEditData(editId);
            }
        });

        function loadEditData(id) {
            fetch(`homework_action.php?action=get_assignment_data&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    // Fill Data
                    document.getElementById('page_title').innerText = "แก้ไขงาน: " + data.title;
                    document.getElementById('form_action').value = "update_assignment";
                    document.getElementById('edit_assignment_id').value = data.assignment_id;
                    document.getElementById('inp_title').value = data.title;
                    $('#summernote').summernote('code', data.description);
                    
                    // Date Format: YYYY-MM-DDTHH:MM
                    const d = new Date(data.due_date);
                    // Adjust timezone offset manually or use simple string manipulation if format is standard
                    // ง่ายสุดคือตัด string เอา
                    const dueStr = data.due_date.replace(' ', 'T').substring(0, 16);
                    document.getElementById('inp_due_date').value = dueStr;
                    
                    document.getElementById('inp_max_score').value = data.max_score;
                    
                    // Select Class
                    const key = data.room_number + '|' + data.course_code; // Format in Value
                    // ต้องหา option ที่ตรงกัน (บางที course_code อาจมีวงเล็บใน value ต้องเช็ค)
                    // ใน PHP เราสร้าง value='$r|$sname'
                    // ดังนั้นต้องลอง set value ตรงๆ
                    const select = document.getElementById('target_class');
                    for(let i=0; i<select.options.length; i++){
                        if(select.options[i].value.includes(data.room_number) && select.options[i].value.includes(data.course_code)){
                            select.selectedIndex = i;
                            break;
                        }
                    }

                    // Rubric
                    document.getElementById('rubric_container').innerHTML = '';
                    if(data.rubric_data) {
                        try {
                            const r = JSON.parse(data.rubric_data);
                            r.forEach(item => addRubricRow(item.name, item.max));
                        } catch(e) {}
                    }

                    // Change Button Text
                    document.getElementById('btn_submit_hw').innerHTML = '<i class="fa-solid fa-save"></i> บันทึกการแก้ไข';
                    document.getElementById('btn_cancel_edit').style.display = 'block';
                });
        }

        function resetForm() {
            document.getElementById('hw_form').reset();
            document.getElementById('page_title').innerText = "สั่งงานใหม่";
            document.getElementById('form_action').value = "add_assignment";
            document.getElementById('edit_assignment_id').value = "";
            $('#summernote').summernote('code', '');
            document.getElementById('rubric_container').innerHTML = '';
            document.getElementById('btn_submit_hw').innerHTML = 'บันทึกและสั่งงาน';
            document.getElementById('btn_cancel_edit').style.display = 'none';
            // Clear URL param
            window.history.pushState(null, '', 'homework_teacher.php?tab=create');
        }

        // --- GRADING & VIEW ---
        function toggleView(type) {
            const c = document.getElementById('student_container');
            if(type==='list') c.style.display='block'; 
            else { c.style.display='grid'; c.style.gridTemplateColumns='repeat(auto-fill, minmax(250px, 1fr))'; }
        }
        function filterSt(status) {
            const items = document.querySelectorAll('.st-item');
            items.forEach(el => {
                if(status==='all' || el.dataset.status === status) el.style.display = 'block';
                else el.style.display = 'none';
            });
        }

        let mediaRecorder; let audioChunks = [];
        function openGradeModal(sub, std, rubric) {
            document.getElementById('modal_grading').style.display='block';
            document.getElementById('g_student_name').innerText = std.full_name;
            document.getElementById('g_submission_id').value = sub.submission_id;
            document.getElementById('g_total_score').value = sub.score || '';
            
            let workHtml = '';
            if(sub.text_content) workHtml += `<p>${sub.text_content}</p>`;
            if(sub.files) {
                const files = JSON.parse(sub.files);
                files.forEach(f => workHtml += `<a href="uploads/assignments/${f.path}" target="_blank" style="display:block; margin:5px 0;">📄 ${f.name}</a>`);
            }
            document.getElementById('g_work_display').innerHTML = workHtml || '<span style="color:#aaa;">ไม่มีไฟล์แนบ</span>';

            let rHtml = '';
            if(rubric) {
                rHtml += '<table style="width:100%; margin-bottom:10px;">';
                rubric.forEach((r, idx) => {
                    rHtml += `<tr><td>${r.name} (${r.max})</td><td><input type="number" name="rubric_score[${idx}]" class="form-control" max="${r.max}" style="width:70px;" onchange="calcTotal()"></td></tr>`;
                });
                rHtml += '</table>';
            }
            document.getElementById('g_rubric_area').innerHTML = rHtml;
        }
        function closeModal(id) { document.getElementById(id).style.display='none'; }
        function calcTotal() {
            let total = 0;
            document.querySelectorAll('input[name^="rubric_score"]').forEach(i => total += parseFloat(i.value || 0));
            document.getElementById('g_total_score').value = total;
        }
        async function toggleRecord() {
            const btn = document.getElementById('btn_record');
            const status = document.getElementById('record_status');
            if(!mediaRecorder || mediaRecorder.state === "inactive") {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                mediaRecorder.onstop = () => {
                    const blob = new Blob(audioChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.readAsDataURL(blob);
                    reader.onloadend = () => {
                        document.getElementById('audio_data_input').value = reader.result;
                        status.innerText = " บันทึกแล้ว"; status.style.color="green";
                    };
                };
                mediaRecorder.start();
                btn.innerHTML = '<i class="fa-solid fa-stop"></i> หยุดอัด';
                status.style.display = 'inline';
            } else {
                mediaRecorder.stop();
                btn.innerHTML = '<i class="fa-solid fa-microphone"></i> อัดใหม่';
            }
        }
    </script>
</body>
</html>