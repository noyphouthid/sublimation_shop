<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบข้อมูลที่จำเป็น
if (!isset($_GET['id']) || !isset($_GET['design_id'])) {
    $_SESSION['error_message'] = "ข้อมูลไม่ครบถ้วน";
    header("Location: design_queue_list.php");
    exit();
}

$fileId = intval($_GET['id']);
$designId = intval($_GET['design_id']);

// ตรวจสอบว่าไฟล์มีอยู่จริงและเป็นของคิวออกแบบที่ระบุหรือไม่
$query = "SELECT * FROM design_files WHERE file_id = $fileId AND design_id = $designId";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "ไม่พบไฟล์ที่ระบุ";
    header("Location: view_design_queue.php?id=$designId");
    exit();
}

$file = $result->fetch_assoc();

// ตรวจสอบสิทธิ์ในการลบไฟล์ (ผู้ดูแลระบบหรือผู้อัปโหลดเท่านั้น)
$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

if ($userRole !== 'admin' && $file['uploaded_by'] !== $userId) {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์ในการลบไฟล์นี้";
    header("Location: view_design_queue.php?id=$designId");
    exit();
}

// ลบไฟล์จากเซิร์ฟเวอร์
$deleted = false;
if (file_exists($file['file_path'])) {
    $deleted = unlink($file['file_path']);
}

// ถ้าลบไฟล์สำเร็จหรือไม่พบไฟล์ ให้ลบข้อมูลจากฐานข้อมูล
if ($deleted || !file_exists($file['file_path'])) {
    // ลบข้อมูลไฟล์จากฐานข้อมูล
    $deleteSql = "DELETE FROM design_files WHERE file_id = $fileId";
    
    if ($conn->query($deleteSql) === TRUE) {
        // บันทึกประวัติการลบไฟล์
        $comment = "ลบไฟล์ " . $file['file_name'] . " (ประเภท: " . $file['file_type'] . ")";
        
        // ดึงสถานะปัจจุบันของคิวออกแบบ
        $statusQuery = "SELECT status FROM design_queue WHERE design_id = $designId";
        $statusResult = $conn->query($statusQuery);
        $statusRow = $statusResult->fetch_assoc();
        $currentStatus = $statusRow['status'];
        
        $historySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                      VALUES ($designId, '$currentStatus', '$currentStatus', $userId, '$comment')";
        $conn->query($historySql);
        
        $_SESSION['success_message'] = "ลบไฟล์เรียบร้อยแล้ว";
    } else {
        $_SESSION['error_message'] = "ไม่สามารถลบข้อมูลไฟล์จากฐานข้อมูลได้: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "ไม่สามารถลบไฟล์จากเซิร์ฟเวอร์ได้";
}

// กลับไปยังหน้ารายละเอียดคิว
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'design_queue') !== false) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: view_design_queue.php?id=$designId");
}
exit();
?>