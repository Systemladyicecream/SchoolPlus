<?php
// ชื่อไฟล์: dashboard_superadmin.php
session_start();
require_once 'db_connect.php';

// --- SECURITY: AUTO LOGOUT SYSTEM (PHP Check) ---
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

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$me = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();

// จัดการรูปโปรไฟล์
$img_src = $me['profile_img'];
if(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png' && !file_exists("uploads/".$img_src)){
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=SuperAdmin'; 
} elseif(strpos($img_src, 'http') === false && $img_src != 'default_avatar.png') {
   $img_src = "uploads/" . $img_src;
} elseif ($img_src == 'default_avatar.png') {
   $img_src = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $me['username'];
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'schools';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#ffffff">
    <title>Superadmin Dashboard - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="brand">
            <i class="fa-solid fa-screwdriver-wrench"></i> 
            <div>Smart School Plus <span style="font-size:0.8rem; opacity:0.6; display:block; line-height:1;">(Superadmin)</span></div>
        </div>
        <div class="user-info">
            <div class="profile-container">
                <img src="<?php echo $img_src; ?>" class="profile-img-nav <?php echo $me['profile_frame']; ?>" alt="Profile">
            </div>
            <span><?php echo $me['full_name']; ?></span>
            <a href="logout.php" class="btn-logout" onclick="return confirm('ยืนยันการออกจากระบบ?');"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <div class="dashboard-container">
        
        <aside class="sidebar" id="sidebar">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid var(--border-color);">
                <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $me['profile_frame']; ?>">
                <h4 style="margin-top:10px; font-size:1.1rem;"><?php echo $me['full_name']; ?></h4>
                <div style="background:#f1f5f9; color:#64748b; padding:4px 12px; border-radius:20px; display:inline-block; font-size:0.8rem; margin-top:5px;">
                    ผู้ดูแลระบบสูงสุด
                </div>
            </div>

            <h3>เมนูหลัก</h3>
            <a href="?tab=schools" class="menu-item <?php echo $tab=='schools'?'active':''; ?>"><i class="fa-solid fa-school"></i> จัดการโรงเรียน</a>
            <a href="?tab=admins" class="menu-item <?php echo $tab=='admins'?'active':''; ?>"><i class="fa-solid fa-users-gear"></i> จัดการ Admin</a>
            <a href="?tab=logs" class="menu-item <?php echo $tab=='logs'?'active':''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติการใช้งาน</a>
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

            <?php if ($tab == 'schools'): ?>
                <div class="page-header">
                    <h2 class="page-title">รายชื่อโรงเรียนในระบบ</h2>
                </div>
                
                <div class="card-form">
                    <h4 id="school_form_title" style="margin-bottom:15px;"><i class="fa-solid fa-plus-circle"></i> เพิ่มโรงเรียนใหม่</h4>
                    <form id="schoolForm" action="superadmin_action.php" method="POST" style="display:flex; gap:15px; flex-wrap:wrap;">
                        <input type="hidden" name="action" id="school_action" value="add_school">
                        <input type="hidden" name="school_id" id="school_id">
                        
                        <div style="flex:2; min-width:200px;">
                            <label>ชื่อโรงเรียน</label>
                            <input type="text" name="school_name" id="school_name" class="form-control" placeholder="ระบุชื่อโรงเรียน" required>
                        </div>
                        <div style="flex:1; min-width:150px;">
                            <label>ระดับการศึกษา</label>
                            <select name="education_level" id="education_level" class="form-control">
                                <option value="ประถมศึกษา">ประถมศึกษา</option>
                                <option value="ขยายโอกาส">ขยายโอกาส</option>
                                <option value="มัธยมศึกษา">มัธยมศึกษา</option>
                            </select>
                        </div>
                        <div style="width:100%;">
                            <button type="submit" class="btn-add" id="btn_school_submit"><i class="fa-solid fa-save"></i> บันทึก</button>
                            <button type="button" class="btn-cancel" id="btn_school_cancel" onclick="resetSchoolForm()"><i class="fa-solid fa-xmark"></i> ยกเลิก</button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ชื่อโรงเรียน</th>
                                <th>ระดับการศึกษา</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $result = $conn->query("SELECT * FROM schools ORDER BY school_id ASC");
                            while($row = $result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td data-label="ID"><?php echo $row['school_id']; ?></td>
                                <td data-label="โรงเรียน"><?php echo $row['school_name']; ?></td>
                                <td data-label="ระดับ"><span style="padding:4px 12px; background:#eff6ff; color:var(--primary-color); border-radius:8px; font-size:0.85rem; font-weight:600;"><?php echo $row['education_level']; ?></span></td>
                                <td data-label="จัดการ">
                                    <button type="button" class="btn-action btn-edit" onclick="editSchool(<?php echo $row['school_id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                    <a href="superadmin_action.php?action=delete_school&id=<?php echo $row['school_id']; ?>" class="btn-action btn-delete" onclick="return confirm('การลบโรงเรียนจะลบ User ทั้งหมดในโรงเรียนนั้นด้วย ยืนยัน?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'admins'): ?>
                <div class="page-header">
                    <h2 class="page-title">จัดการ Admin</h2>
                </div>

                <div class="card-form">
                    <h4 id="admin_form_title" style="margin-bottom:15px;"><i class="fa-solid fa-user-plus"></i> สร้างบัญชี Admin ใหม่</h4>
                    <form id="adminForm" action="superadmin_action.php" method="POST">
                        <input type="hidden" name="action" id="admin_action" value="add_admin">
                        <input type="hidden" name="user_id" id="admin_user_id">
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div>
                                <label>เลือกโรงเรียน</label>
                                <select name="school_id" id="admin_school_id" class="form-control" required>
                                    <option value="">-- กรุณาเลือก --</option>
                                    <?php 
                                    $schools = $conn->query("SELECT * FROM schools");
                                    while($s = $schools->fetch_assoc()){
                                        echo "<option value='".$s['school_id']."'>".$s['school_name']."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label>ชื่อ-นามสกุล Admin</label>
                                <input type="text" name="full_name" id="admin_fullname" class="form-control" required>
                            </div>
                            <div>
                                <label>Username</label>
                                <input type="text" name="username" id="admin_username" class="form-control" required>
                            </div>
                            <div>
                                <label>Password</label>
                                <input type="text" name="password" id="admin_password" class="form-control" required value="Admin1234" placeholder="Password">
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="btn-add" id="btn_admin_submit"><i class="fa-solid fa-save"></i> บันทึก</button>
                            <button type="button" class="btn-cancel" id="btn_admin_cancel" onclick="resetAdminForm()"><i class="fa-solid fa-xmark"></i> ยกเลิก</button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>โรงเรียนสังกัด</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sql = "SELECT u.*, s.school_name FROM users u LEFT JOIN schools s ON u.school_id = s.school_id WHERE u.role = 'admin' ORDER BY u.created_at DESC";
                            $result = $conn->query($sql);
                            if($result->num_rows == 0) echo "<tr><td colspan='4' style='text-align:center;'>ยังไม่มีข้อมูล Admin</td></tr>";
                            while($row = $result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td data-label="Username"><?php echo $row['username']; ?></td>
                                <td data-label="ชื่อ-สกุล"><?php echo $row['full_name']; ?></td>
                                <td data-label="โรงเรียน"><?php echo $row['school_name'] ? $row['school_name'] : '-'; ?></td>
                                <td data-label="จัดการ">
                                    <button type="button" class="btn-action btn-edit" onclick="editAdmin(<?php echo $row['user_id']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                    <a href="superadmin_action.php?action=delete_user&id=<?php echo $row['user_id']; ?>" class="btn-action btn-delete" onclick="return confirm('ยืนยันการลบ?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'logs'): ?>
                <div class="page-header"><h2 class="page-title">ประวัติการเข้าใช้งาน</h2></div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>เวลา</th>
                                <th>ผู้ใช้งาน</th>
                                <th>Role</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sql_log = "SELECT l.*, u.username, u.role FROM login_logs l JOIN users u ON l.user_id = u.user_id ORDER BY l.login_time DESC LIMIT 50";
                            $result = $conn->query($sql_log);
                            while($row = $result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td data-label="เวลา"><?php echo date("d/m/Y H:i", strtotime($row['login_time'])); ?></td>
                                <td data-label="User"><?php echo $row['username']; ?></td>
                                <td data-label="Role"><span style="text-transform:capitalize; color:var(--text-secondary);"><?php echo $row['role']; ?></span></td>
                                <td data-label="IP"><?php echo $row['ip_address']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'profile'): ?>
                <div class="page-header"><h2 class="page-title">ข้อมูลส่วนตัว</h2></div>
                <div style="display:flex; gap:25px; flex-wrap:wrap;">
                    <div class="card-form" style="flex:1; min-width:300px; text-align:center;">
                        <h3 style="margin-bottom:20px;">รูปโปรไฟล์</h3>
                        <div style="margin:25px 0;">
                            <img src="<?php echo $img_src; ?>" class="profile-img-large <?php echo $me['profile_frame']; ?>" id="preview_img">
                        </div>
                        <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                            <button type="button" class="btn-action btn-add" onclick="openModal('modal_avatar')"><i class="fa-solid fa-camera"></i> เปลี่ยนรูป</button>
                            <button type="button" class="btn-action btn-edit" onclick="openModal('modal_frame')"><i class="fa-solid fa-crop-simple"></i> เปลี่ยนกรอบ</button>
                        </div>
                    </div>
                    <div class="card-form" style="flex:1; min-width:300px;">
                        <h3 style="margin-bottom:20px;">ข้อมูลบัญชี</h3>
                        <form action="superadmin_action.php" method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-group">
                                <label>ชื่อผู้สร้างระบบ</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo $me['full_name']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo $me['username']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Password ใหม่</label>
                                <input type="password" name="password" class="form-control" placeholder="เว้นว่างไว้ถ้าไม่เปลี่ยน">
                            </div>
                            <button type="submit" class="btn-add" style="margin-top:20px; width:100%;"><i class="fa-solid fa-save"></i> บันทึก</button>
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
                                echo "<form action='superadmin_action.php' method='POST' style='display:inline;'>
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
                            <form action="superadmin_action.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile_pic">
                                <input type="file" name="upload_avatar" class="form-control" required>
                                <button type="submit" class="btn-add" style="margin-top:10px; width:100%;">อัปโหลดรูปเอง</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="modal_frame" class="modal">
                    <div class="modal-content">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <h3>เลือกกรอบรูป</h3>
                            <span class="close-modal" onclick="closeModal('modal_frame')">&times;</span>
                        </div>
                        <div class="selection-grid">
                            <?php 
                            for($i=0; $i<=10; $i++) {
                                echo "<form action='superadmin_action.php' method='POST' style='display:inline;'><input type='hidden' name='action' value='update_profile_pic'><input type='hidden' name='frame_style' value='frame-$i'><button type='submit' class='select-item'><div class='profile-img-nav frame-$i' style='width:50px; height:50px; margin:0 auto; background:#eee;'></div><small>แบบ $i</small></button></form>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <script>
                    function openModal(id) { document.getElementById(id).style.display = 'block'; }
                    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
                    window.onclick = function(event) { if (event.target.classList.contains('modal')) { event.target.style.display = 'none'; } }
                </script>
            <?php endif; ?>

        </main>
    </div>

    <div class="mobile-bottom-nav">
        <a href="?tab=schools" class="nav-item <?php echo $tab=='schools'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-school"></i></span> โรงเรียน
        </a>
        <a href="?tab=admins" class="nav-item <?php echo $tab=='admins'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-users-gear"></i></span> Admin
        </a>
        <a href="?tab=logs" class="nav-item <?php echo $tab=='logs'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left"></i></span> ประวัติ
        </a>
        <a href="?tab=profile" class="nav-item <?php echo $tab=='profile'?'active':''; ?>">
            <span class="nav-icon"><i class="fa-solid fa-user"></i></span> ฉัน
        </a>
    </div>

    <script>
        // Edit School Logic
        function editSchool(id) {
            fetch(`superadmin_action.php?action=get_school_data&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('school_action').value = 'update_school';
                    document.getElementById('school_id').value = data.school_id;
                    document.getElementById('school_name').value = data.school_name;
                    document.getElementById('education_level').value = data.education_level;
                    document.getElementById('school_form_title').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลโรงเรียน';
                    document.getElementById('btn_school_submit').innerHTML = '<i class="fa-solid fa-save"></i> บันทึกแก้ไข';
                    document.getElementById('btn_school_cancel').style.display = 'inline-flex';
                    document.getElementById('schoolForm').scrollIntoView({behavior: 'smooth'});
                });
        }
        function resetSchoolForm() {
            document.getElementById('schoolForm').reset();
            document.getElementById('school_action').value = 'add_school';
            document.getElementById('school_id').value = '';
            document.getElementById('school_form_title').innerHTML = '<i class="fa-solid fa-plus-circle"></i> เพิ่มโรงเรียนใหม่';
            document.getElementById('btn_school_submit').innerHTML = '<i class="fa-solid fa-save"></i> บันทึก';
            document.getElementById('btn_school_cancel').style.display = 'none';
        }

        // Edit Admin Logic
        function editAdmin(id) {
            fetch(`superadmin_action.php?action=get_user_data&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('admin_action').value = 'update_admin';
                    document.getElementById('admin_user_id').value = data.user_id;
                    document.getElementById('admin_school_id').value = data.school_id;
                    document.getElementById('admin_fullname').value = data.full_name;
                    document.getElementById('admin_username').value = data.username;
                    document.getElementById('admin_password').required = false;
                    document.getElementById('admin_password').placeholder = "กรอกใหม่ถ้าต้องการเปลี่ยน";
                    document.getElementById('admin_password').value = "";
                    document.getElementById('admin_form_title').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูล Admin';
                    document.getElementById('btn_admin_submit').innerHTML = '<i class="fa-solid fa-save"></i> บันทึกแก้ไข';
                    document.getElementById('btn_admin_cancel').style.display = 'inline-flex';
                    document.getElementById('adminForm').scrollIntoView({behavior: 'smooth'});
                });
        }
        function resetAdminForm() {
            document.getElementById('adminForm').reset();
            document.getElementById('admin_action').value = 'add_admin';
            document.getElementById('admin_user_id').value = '';
            document.getElementById('admin_password').required = true;
            document.getElementById('admin_password').placeholder = "Password";
            document.getElementById('admin_form_title').innerHTML = '<i class="fa-solid fa-user-plus"></i> สร้างบัญชี Admin ใหม่';
            document.getElementById('btn_admin_submit').innerHTML = '<i class="fa-solid fa-save"></i> สร้างบัญชี Admin';
            document.getElementById('btn_admin_cancel').style.display = 'none';
        }
    </script>

    <script>
        let timeoutTimer;
        const timeoutDuration = 300000; // 5 นาที (300,000 milliseconds)

        function startTimer() {
            clearTimeout(timeoutTimer);
            timeoutTimer = setTimeout(doLogout, timeoutDuration);
        }

        function doLogout() {
            alert("คุณไม่ได้ใช้งานระบบเกิน 5 นาที ระบบจะทำการออกจากระบบอัตโนมัติเพื่อความปลอดภัย");
            window.location.href = 'logout.php?timeout=1';
        }

        window.onload = startTimer;
        document.onmousemove = startTimer;
        document.onkeypress = startTimer;
        document.onclick = startTimer;
        document.onscroll = startTimer;
        document.ontouchstart = startTimer;
    </script>
</body>
</html>