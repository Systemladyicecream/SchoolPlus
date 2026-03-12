<?php
// ชื่อไฟล์: homework_student_action.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'submit_work') {
    $aid = $_POST['assignment_id'];
    $text = isset($_POST['text_content']) ? $_POST['text_content'] : '';
    $link = isset($_POST['link']) ? $_POST['link'] : '';
    
    // Check Assignment Config
    $assign = $conn->query("SELECT * FROM assignments WHERE assignment_id = $aid")->fetch_assoc();
    if (!$assign) die("Assignment not found");

    // Check Late
    $status = 'submitted';
    if (time() > strtotime($assign['due_date'])) {
        $status = 'late';
        if ($assign['allow_late'] == 0) {
            $_SESSION['error'] = "ไม่อนุญาตให้ส่งงานล่าช้า";
            header("Location: homework_student.php?tab=view&id=$aid");
            exit;
        }
    }

    // Handle File Uploads
    $file_paths = [];
    
    // Retrieve existing files if this is a re-submission update (optional logic, but here we overwrite for simplicity or merge)
    // For simplicity: New upload overwrites old files list in DB, but keeps files on server.
    // Ideally, we might want to append. Let's start fresh for resubmit.
    
    if (!empty($_FILES['files']['name'][0])) {
        $upload_dir = "uploads/assignments/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['error'][$key] == 0) {
                $tmp = $_FILES['files']['tmp_name'][$key];
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $new_name = "sub_" . $user_id . "_" . time() . "_" . $key . "." . $ext;
                
                if (move_uploaded_file($tmp, $upload_dir . $new_name)) {
                    $file_paths[] = ['name' => $name, 'path' => $new_name];
                }
            }
        }
    }
    
    $files_json = !empty($file_paths) ? json_encode($file_paths, JSON_UNESCAPED_UNICODE) : NULL;

    // Check if Submission Exists
    $chk = $conn->query("SELECT submission_id, files FROM submissions WHERE assignment_id = $aid AND student_id = $user_id");
    
    if ($chk->num_rows > 0) {
        // Update
        $row = $chk->fetch_assoc();
        
        // If new files uploaded, use new. If not, keep old (unless user wants to clear? assume overwrite if new files provided)
        if ($files_json == NULL && !empty($row['files'])) {
            $files_json = $row['files']; 
        } elseif ($files_json != NULL) {
            // New files uploaded, replace old.
        }

        $stmt = $conn->prepare("UPDATE submissions SET text_content=?, links=?, files=?, submitted_at=NOW(), status=? WHERE submission_id=?");
        $stmt->bind_param("ssssi", $text, $link, $files_json, $status, $row['submission_id']);
        
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, text_content, links, files, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iissss", $aid, $user_id, $text, $link, $files_json, $status);
    }

    if ($stmt->execute()) {
        $_SESSION['msg'] = "ส่งงานเรียบร้อยแล้ว" . ($status == 'late' ? " (ส่งช้า)" : "");
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }

    header("Location: homework_student.php?tab=view&id=$aid");
}
?>