<?php
// ชื่อไฟล์: index.php
session_start();
if(isset($_COOKIE['user_login']) && !isset($_SESSION['user_id'])) {
    require_once 'db_connect.php';
    $username = $_COOKIE['user_login'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if($row = $result->fetch_assoc()){
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['full_name'] = $row['full_name'];
        $_SESSION['school_id'] = $row['school_id'];
        $_SESSION['last_activity'] = time(); // เพิ่ม: กำหนดเวลาเริ่มต้นเมื่อ Auto Login
        header("Location: dashboard_".$row['role'].".php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#ffffff">
    <title>เข้าสู่ระบบ - Smart School Plus</title>
    <link rel="stylesheet" href="style_theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="login-container">
        <div class="logo-area">
            <i class="fa-solid fa-graduation-cap"></i>
            <h2>Smart School Plus</h2>
            <p>ระบบบริหารจัดการสถานศึกษา</p>
        </div>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert-box alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> 
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['timeout'])): ?>
            <div class="alert-box alert-error" style="background-color:#fff7ed; color:#c2410c; border-color:#ffedd5;">
                <i class="fa-solid fa-clock"></i> หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบใหม่
            </div>
        <?php endif; ?>

        <form action="auth_login.php" method="POST">
            <div class="form-group">
                <label for="username">บัญชีผู้ใช้</label>
                <input type="text" name="username" id="username" class="form-control" required 
                       value="<?php echo isset($_COOKIE['user_login']) ? $_COOKIE['user_login'] : ''; ?>"
                       placeholder="Username / Student ID" autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" name="password" id="password" class="form-control" required 
                       placeholder="Password" autocomplete="current-password">
            </div>

            <label class="remember-me">
                <input type="checkbox" name="remember" id="remember" 
                       <?php echo isset($_COOKIE['user_login']) ? 'checked' : ''; ?>>
                จำการเข้าระบบ
            </label>

            <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
        </form>

        <div class="creator-credit">
            © 2026 Smart School Plus
        </div>
    </div>

</body>
</html>