<?php
// ชื่อไฟล์: grade_student.php
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
// ------------------------------------------------

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$school_id = $_SESSION['school_id'];
$school_row = $conn->query("SELECT school_name FROM schools WHERE school_id = $school_id")->fetch_assoc();
$school_name = $school_row['school_name'];

$student = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();

$img_src = $student['profile_img'];
if(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png' && !file_exists("uploads/".$img_src)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix'; 
} elseif(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png') {
   $img_src = "uploads/" . $img_src;
} elseif ($img_src == 'default_avatar.png') {
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $student['username'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ผลการเรียน - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        @media screen {
            .print-only { display: none !important; }
        }
        @media print {
            /* ซ่อนทุกอย่างบนหน้าจอที่ไม่จำเป็นสำหรับการพิมพ์ */
            body * { display: none !important; }
            
            /* แสดงเฉพาะส่วนที่ให้ Print */
            .print-only, .print-only * { 
                display: block !important; 
                visibility: visible !important; 
            }
            
            /* กำหนดโครงสร้างตารางตอน Print ให้ถูกต้อง */
            .print-only table { display: table !important; width: 100% !important; border-collapse: collapse !important; margin-bottom: 20px !important; }
            .print-only thead { display: table-header-group !important; }
            .print-only tbody { display: table-row-group !important; }
            .print-only tr { display: table-row !important; }
            .print-only th, .print-only td { display: table-cell !important; border: 1px solid #000 !important; padding: 10px !important; font-size: 14px !important; }
            .print-only strong, .print-only span { display: inline !important; }
            .print-only h2, .print-only h3, .print-only p { display: block !important; color: #000 !important; }
            
            .print-only {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                padding: 10px;
                color: #000;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <i class="fa-solid fa-user-graduate"></i> 
            <div>Smart School Plus <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;"><?php echo $school_name; ?></span></div>
        </div>
        <div class="user-info">
            <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $student['profile_frame']; ?>">
            <span><?php echo $student['full_name']; ?></span>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $student['profile_frame']; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo $student['full_name']; ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    รหัส: <?php echo $student['student_code']; ?>
                </div>
            </div>
            
            <h3>เมนูหลัก</h3>
            <a href="dashboard_student.php?tab=home" class="menu-item"><i class="fa-solid fa-house"></i> หน้าหลัก</a>
            <a href="dashboard_student.php?tab=attendance" class="menu-item"><i class="fa-solid fa-calendar-check"></i> เวลาเรียน</a>
            <a href="homework_student.php" class="menu-item"><i class="fa-solid fa-book"></i> การบ้าน</a>
            <a href="grade_student.php" class="menu-item active"><i class="fa-solid fa-trophy"></i> ผลการเรียน</a>
            <a href="dashboard_student.php?tab=media" class="menu-item"><i class="fa-solid fa-layer-group"></i> คลังความรู้</a>
            <div style="height:1px; background:var(--border-color); margin:10px 0;"></div>
            <a href="dashboard_student.php?tab=profile" class="menu-item"><i class="fa-solid fa-id-card"></i> ข้อมูลส่วนตัว</a>
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

            <div class="page-header"><h2 class="page-title">ผลการเรียน (Grades)</h2></div>

            <?php
            // ดึงปีการศึกษาปัจจุบัน
            $ay_q = $conn->query("SELECT year_id, year_name, term FROM academic_years WHERE school_id = $school_id AND is_active = 1");
            $active_year = $ay_q->fetch_assoc();
            $active_year_id = $active_year ? $active_year['year_id'] : 0;
            $term_text = $active_year ? "ภาคเรียนที่ {$active_year['term']}/{$active_year['year_name']}" : "ยังไม่ตั้งค่าปีการศึกษา";

            // ดึงข้อมูลเกรดเฉพาะวิชาที่ครูกด 'ส่งเกรดแล้ว' (is_published = 1)
            $sql_grades = "SELECT gc.id as criteria_id, gc.course_code, gc.criteria_json, gs.scores_json, gs.total_score, gs.grade
                           FROM grade_scores gs
                           JOIN grade_criteria gc ON gs.criteria_id = gc.id
                           WHERE gs.student_id = $user_id AND gc.is_published = 1 AND gc.year_id = $active_year_id";
            $res_grades = $conn->query($sql_grades);

            $total_grade_points = 0;
            $subject_count = 0;
            $grades_data = [];
            
            // ชุดข้อมูลสำหรับกราฟ Phase 3
            $grade_stats = ['4'=>0, '3.5'=>0, '3'=>0, '2.5'=>0, '2'=>0, '1.5'=>0, '1'=>0, '0'=>0];
            $other_stats = 0; // สำหรับ ร., มส.

            while ($row = $res_grades->fetch_assoc()) {
                $grades_data[] = $row;
                $g_val = trim($row['grade']);
                
                if (is_numeric($g_val)) {
                    $total_grade_points += floatval($g_val);
                    $subject_count++;
                    
                    // จัดเก็บสถิติเพื่อนำไปทำกราฟ
                    $g_key = (strpos($g_val, '.') !== false) ? rtrim(rtrim($g_val, '0'), '.') : $g_val;
                    if (isset($grade_stats[$g_key])) {
                        $grade_stats[$g_key]++;
                    } else {
                        $other_stats++;
                    }
                } else {
                    $other_stats++;
                }
            }

            // คำนวณ GPA เฉลี่ยอย่างง่าย
            $gpa = $subject_count > 0 ? number_format($total_grade_points / $subject_count, 2) : '0.00';
            ?>

            <div class="card-form" style="background: linear-gradient(135deg, #4f46e5, #818cf8); color: white; margin-bottom: 24px; padding: 24px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);">
                <div>
                    <h3 style="margin: 0; font-size: 1.2rem; color: #e0e7ff; font-family: 'Prompt', sans-serif;"><i class="fa-solid fa-graduation-cap"></i> สรุปผลการเรียน</h3>
                    <p style="margin: 5px 0 15px 0; font-size: 0.95rem; opacity: 0.9;"><?php echo $term_text; ?></p>
                    <?php if (!empty($grades_data)): ?>
                        <button class="btn-action" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4); border-radius: 8px; font-weight: 500; font-size: 0.85rem;" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> พิมพ์รายงานผลการเรียน
                        </button>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.9rem; opacity: 0.9;">เกรดเฉลี่ยประจำภาคเรียน (GPA)</div>
                    <div style="font-size: 2.8rem; font-weight: bold; line-height: 1; font-family: 'Prompt', sans-serif;"><?php echo $gpa; ?></div>
                </div>
            </div>

            <?php if (empty($grades_data)): ?>
                <div style="text-align:center; padding:60px 20px; background:white; border-radius:20px; border:2px dashed #cbd5e1;">
                    <i class="fa-solid fa-folder-open" style="font-size:4rem; color:#cbd5e1; margin-bottom:15px;"></i>
                    <h3 style="color:#64748b;">ยังไม่มีผลการเรียน</h3>
                    <p style="color:#94a3b8;">ครูผู้สอนยังไม่ได้ประกาศผลการเรียนสำหรับภาคเรียนนี้ หรือยังไม่มีการประเมินผล</p>
                </div>
            <?php else: ?>
            
                <div class="card-form" style="margin-bottom: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin: 0; color: var(--text-main); font-size: 1.1rem; font-family: 'Prompt', sans-serif;"><i class="fa-solid fa-chart-pie" style="color:var(--primary-color);"></i> สถิติระดับผลการเรียน</h3>
                        <div style="background: var(--bg-app); padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary);">รวมทั้งหมด <?php echo count($grades_data); ?> วิชา</div>
                    </div>
                    <div style="height: 250px; width: 100%;">
                        <canvas id="gradeChart"></canvas>
                    </div>
                </div>
                
                <script>
                    const ctx = document.getElementById('gradeChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'อื่นๆ (ร/มส)'],
                            datasets: [{
                                label: 'จำนวนวิชา (วิชา)',
                                data: [
                                    <?php echo $grade_stats['4']; ?>, <?php echo $grade_stats['3.5']; ?>, <?php echo $grade_stats['3']; ?>, 
                                    <?php echo $grade_stats['2.5']; ?>, <?php echo $grade_stats['2']; ?>, <?php echo $grade_stats['1.5']; ?>, 
                                    <?php echo $grade_stats['1']; ?>, <?php echo $grade_stats['0']; ?>, <?php echo $other_stats; ?>
                                ],
                                backgroundColor: [
                                    'rgba(16, 185, 129, 0.7)', 'rgba(16, 185, 129, 0.5)', 'rgba(59, 130, 246, 0.7)',
                                    'rgba(59, 130, 246, 0.5)', 'rgba(245, 158, 11, 0.7)', 'rgba(245, 158, 11, 0.5)',
                                    'rgba(234, 88, 12, 0.7)', 'rgba(239, 68, 68, 0.7)', 'rgba(100, 116, 139, 0.7)'
                                ],
                                borderColor: [
                                    '#10b981', '#10b981', '#3b82f6', '#3b82f6', '#f59e0b', '#f59e0b', '#ea580c', '#ef4444', '#64748b'
                                ],
                                borderWidth: 1,
                                borderRadius: 6
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

                <h3 style="margin: 30px 0 15px 0; font-family: 'Prompt', sans-serif; font-size: 1.1rem; color: var(--text-main);"><i class="fa-solid fa-list-check"></i> รายวิชาของคุณ</h3>
                
                <div class="menu-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($grades_data as $g): 
                        $grade_val = $g['grade'];
                        $color = '#64748b'; // default gray
                        $bg_color = '#f1f5f9';
                        
                        // จัดการสีตามระดับเกรด
                        if (is_numeric($grade_val)) {
                            $gv = floatval($grade_val);
                            if ($gv >= 3.5) { $color = '#10b981'; $bg_color = '#d1fae5'; } // สีเขียว
                            elseif ($gv >= 2.5) { $color = '#3b82f6'; $bg_color = '#dbeafe'; } // สีฟ้า
                            elseif ($gv >= 1.5) { $color = '#f59e0b'; $bg_color = '#fef3c7'; } // สีเหลืองทอง
                            elseif ($gv >= 1.0) { $color = '#ea580c'; $bg_color = '#ffedd5'; } // สีส้ม
                            else { $color = '#ef4444'; $bg_color = '#fee2e2'; } // สีแดง
                        } else {
                            $color = '#ef4444'; $bg_color = '#fee2e2'; // ร, มส (สีแดง)
                        }
                    ?>
                        <div class="card-form" style="padding: 24px; border-radius: 20px; margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between; border-top: 5px solid <?php echo $color; ?>; transition: transform 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 600;">รหัสวิชา</div>
                                    <div style="font-size: 1.2rem; font-weight: bold; color: var(--text-main); font-family: 'Prompt', sans-serif;"><?php echo htmlspecialchars($g['course_code']); ?></div>
                                </div>
                                <div style="background: <?php echo $bg_color; ?>; color: <?php echo $color; ?>; padding: 10px 15px; border-radius: 14px; text-align: center; min-width: 80px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">เกรด</div>
                                    <div style="font-size: 1.8rem; font-weight: bold; line-height: 1; font-family: 'Prompt', sans-serif;"><?php echo $grade_val; ?></div>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 24px;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 8px;">
                                    <span style="color: var(--text-secondary);">คะแนนรวม</span>
                                    <span style="font-weight: bold; color: var(--text-main);"><?php echo $g['total_score']; ?> / 100</span>
                                </div>
                                <div style="background: #e2e8f0; border-radius: 10px; height: 8px; width: 100%; overflow: hidden;">
                                    <div style="background: <?php echo $color; ?>; height: 100%; width: <?php echo floatval($g['total_score']); ?>%; transition: width 1s ease-in-out;"></div>
                                </div>
                            </div>

                            <button type="button" class="btn-action" style="width: 100%; background: #f8fafc; color: var(--primary-color); border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#eff6ff'; this.style.borderColor='#bfdbfe';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';" onclick="openModal('modal_grade_<?php echo $g['criteria_id']; ?>')">
                                <i class="fa-solid fa-chart-pie"></i> ดูรายละเอียดคะแนน
                            </button>
                        </div>

                        <div id="modal_grade_<?php echo $g['criteria_id']; ?>" class="modal">
                            <div class="modal-content" style="max-width: 450px;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
                                    <div>
                                        <h3 style="margin:0; color:var(--primary-color); font-family: 'Prompt', sans-serif;"><i class="fa-solid fa-square-poll-horizontal"></i> เจาะลึกคะแนน</h3>
                                        <p style="margin:5px 0 0 0; font-size:0.9rem; color:var(--text-secondary);">รหัสวิชา: <strong><?php echo htmlspecialchars($g['course_code']); ?></strong></p>
                                    </div>
                                    <span class="close-modal" onclick="closeModal('modal_grade_<?php echo $g['criteria_id']; ?>')" style="font-size:2rem; line-height:1; cursor:pointer;">&times;</span>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 18px;">
                                    <?php 
                                    $criteria = json_decode($g['criteria_json'], true);
                                    $scores = json_decode($g['scores_json'], true);
                                    
                                    // ป้องกัน Error กรณีไม่มีคะแนนหรือ JSON พัง
                                    if (!is_array($scores)) $scores = [];
                                    
                                    if (is_array($criteria)) {
                                        foreach ($criteria as $idx => $c) {
                                            $max = floatval($c['max']);
                                            $score = isset($scores[$idx]) ? floatval($scores[$idx]) : 0;
                                            $percent = $max > 0 ? ($score / $max) * 100 : 0;
                                            
                                            // สีของหลอด Progress Bar ย่อย
                                            $bar_color = '#3b82f6'; // สีฟ้าเริ่มต้น
                                            if ($percent >= 80) $bar_color = '#10b981'; // ดีมาก (เขียว)
                                            elseif ($percent >= 50) $bar_color = '#f59e0b'; // ปานกลาง (เหลือง)
                                            else $bar_color = '#ef4444'; // ควรปรับปรุง (แดง)
                                    ?>
                                        <div>
                                            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 6px;">
                                                <span style="font-weight: 500; color: var(--text-main);"><i class="fa-solid fa-check" style="color: <?php echo $bar_color; ?>; font-size: 0.8rem; margin-right: 5px;"></i> <?php echo htmlspecialchars($c['name']); ?></span>
                                                <span style="font-weight: bold; color: <?php echo $bar_color; ?>;"><?php echo $score; ?> <span style="color: var(--text-secondary); font-weight: normal; font-size:0.85rem;">/ <?php echo $max; ?></span></span>
                                            </div>
                                            <div style="background: #f1f5f9; border-radius: 10px; height: 10px; width: 100%; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                                <div style="background: <?php echo $bar_color; ?>; height: 100%; width: <?php echo $percent; ?>%; border-radius: 10px; transition: width 0.8s ease;"></div>
                                            </div>
                                        </div>
                                    <?php 
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <div style="margin-top: 25px; padding-top: 20px; border-top: 2px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 15px; border-radius: 12px;">
                                    <span style="font-weight: bold; color: var(--text-main); font-size: 1.1rem;">คะแนนรวมสุทธิ</span>
                                    <span style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color); font-family: 'Prompt', sans-serif;"><?php echo $g['total_score']; ?> <small style="font-size: 0.9rem; color: #64748b;">/ 100</small></span>
                                </div>

                                <button type="button" class="btn-cancel" style="width: 100%; margin-top: 15px; display: block;" onclick="closeModal('modal_grade_<?php echo $g['criteria_id']; ?>')">ปิดหน้าต่าง</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div id="print-area" class="print-only">
                    <div style="text-align:center; margin-bottom: 20px;">
                        <h2 style="font-family: 'Prompt', sans-serif; font-size: 24px; margin-bottom: 5px;">รายงานผลการเรียน (เบื้องต้น)</h2>
                        <p style="font-size: 16px; margin: 0;"><?php echo $school_name; ?></p>
                        <p style="font-size: 16px; margin: 0;"><strong><?php echo $term_text; ?></strong></p>
                    </div>
                    
                    <div style="margin-bottom: 20px; border: 1px solid #000; padding: 15px; border-radius: 8px;">
                        <p style="margin: 0 0 5px 0;"><strong>ชื่อ-นามสกุล:</strong> <?php echo $student['full_name']; ?></p>
                        <p style="margin: 0;"><strong>รหัสประจำตัวนักเรียน:</strong> <?php echo $student['student_code']; ?></p>
                    </div>

                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;" border="1">
                        <thead>
                            <tr style="background-color: #f1f5f9;">
                                <th style="padding: 10px; text-align: center; width: 15%;">รหัสวิชา</th>
                                <th style="padding: 10px; text-align: left;">ชื่อรายวิชา</th>
                                <th style="padding: 10px; text-align: center; width: 20%;">คะแนนรวม (100)</th>
                                <th style="padding: 10px; text-align: center; width: 20%;">ระดับผลการเรียน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades_data as $g): ?>
                            <tr>
                                <td style="padding: 10px; text-align: center;"><?php echo htmlspecialchars($g['course_code']); ?></td>
                                <td style="padding: 10px; text-align: left;">รายวิชา <?php echo htmlspecialchars($g['course_code']); ?></td>
                                <td style="padding: 10px; text-align: center;"><?php echo $g['total_score']; ?></td>
                                <td style="padding: 10px; text-align: center; font-size: 16px;"><strong><?php echo $g['grade']; ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="text-align: right; border: 2px solid #000; padding: 15px; border-radius: 8px; width: 300px; float: right;">
                        <p style="margin: 0; font-size: 16px;"><strong>เกรดเฉลี่ยประจำภาคเรียน (GPA)</strong></p>
                        <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: bold; font-family: 'Prompt', sans-serif;"><?php echo $gpa; ?></p>
                    </div>
                    <div style="clear:both;"></div>
                    
                    <div style="margin-top: 50px; text-align: center; color: #64748b; font-size: 12px;">
                        <p>เอกสารฉบับนี้พิมพ์จากระบบ Smart School Plus ใช้สำหรับตรวจสอบข้อมูลเบื้องต้นเท่านั้น</p>
                        <p>พิมพ์เมื่อ: <?php echo date('d/m/Y H:i'); ?></p>
                    </div>
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
        <a href="dashboard_student.php?tab=home" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-house"></i></span> หน้าหลัก
        </a>
        <a href="dashboard_student.php?tab=attendance" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span> เรียน
        </a>
        <a href="homework_student.php" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span> งาน
        </a>
        <a href="grade_student.php" class="nav-item active">
            <span class="nav-icon"><i class="fa-solid fa-trophy"></i></span> ผล
        </a>
        <a href="dashboard_student.php?tab=profile" class="nav-item">
            <span class="nav-icon"><i class="fa-solid fa-user"></i></span> ฉัน
        </a>
    </div>

    <script>
        let timeoutTimer;
        const timeoutDuration = 300000; 
        function startTimer() { clearTimeout(timeoutTimer); timeoutTimer = setTimeout(doLogout, timeoutDuration); }
        function doLogout() { alert("คุณไม่ได้ใช้งานระบบเกิน 5 นาที ระบบจะทำการออกจากระบบอัตโนมัติเพื่อความปลอดภัย"); window.location.href = 'logout.php?timeout=1'; }
        window.onload = startTimer; document.onmousemove = startTimer; document.onkeypress = startTimer; document.onclick = startTimer; document.onscroll = startTimer; document.ontouchstart = startTimer;
    </script>

</body>
</html>