<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบว่ามีการส่งข้อมูลมาหรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: design_queue_list.php");
    exit();
}

// ตรวจสอบข้อมูลที่จำเป็น
if (!isset($_POST['design_id']) || !isset($_POST['new_status'])) {
    $_SESSION['error_message'] = "ข้อมูลไม่ครบถ้วน กรุณาลองใหม่อีกครั้ง";
    header("Location: design_queue_list.php");
    exit();
}

$designId = intval($_POST['design_id']);
$newStatus = $conn->real_escape_string($_POST['new_status']);
$comment = isset($_POST['comment']) ? $conn->real_escape_string($_POST['comment']) : '';

// ตรวจสอบว่า design_id มีอยู่จริงหรือไม่
$checkQuery = "SELECT design_id, status FROM design_queue WHERE design_id = $designId";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows === 0) {
    $_SESSION['error_message'] = "ไม่พบคิวออกแบบที่ระบุ";
    header("Location: design_queue_list.php");
    exit();
}

$row = $checkResult->fetch_assoc();
$oldStatus = $row['status'];

// ตรวจสอบสถานะที่อนุญาต
$allowedStatuses = ['pending', 'in_progress', 'customer_review', 'revision', 'approved', 'production', 'completed'];
if (!in_array($newStatus, $allowedStatuses)) {
    $_SESSION['error_message'] = "สถานะไม่ถูกต้อง";
    header("Location: view_design_queue.php?id=$designId");
    exit();
}

// ตรวจสอบความถูกต้องของการเปลี่ยนสถานะ
$validTransition = false;

switch ($oldStatus) {
    case 'pending':
        $validTransition = in_array($newStatus, ['in_progress']);
        break;
    case 'in_progress':
        $validTransition = in_array($newStatus, ['customer_review']);
        break;
    case 'customer_review':
        $validTransition = in_array($newStatus, ['revision', 'approved']);
        break;
    case 'revision':
        $validTransition = in_array($newStatus, ['in_progress', 'customer_review']);
        break;
    case 'approved':
        $validTransition = in_array($newStatus, ['revision', 'production']);
        break;
    case 'production':
        $validTransition = in_array($newStatus, ['approved', 'completed']);
        break;
    case 'completed':
        $validTransition = in_array($newStatus, ['production']);
        break;
    default:
        $validTransition = true; // ถ้าเป็นสถานะที่ไม่รู้จัก ให้อนุญาตการเปลี่ยนสถานะ
}

if (!$validTransition) {
    $_SESSION['error_message'] = "ไม่สามารถเปลี่ยนสถานะจาก " . getStatusThai($oldStatus) . " เป็น " . getStatusThai($newStatus) . " ได้";
    header("Location: view_design_queue.php?id=$designId");
    exit();
}

// อัปเดตสถานะ
$updateSql = "UPDATE design_queue SET status = '$newStatus', updated_at = NOW() WHERE design_id = $designId";

if ($conn->query($updateSql) === TRUE) {
    // บันทึกประวัติการเปลี่ยนสถานะ
    $user_id = $_SESSION['user_id'];
    $historySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                  VALUES ($designId, '$oldStatus', '$newStatus', $user_id, '$comment')";
    
    if ($conn->query($historySql) === TRUE) {
        $_SESSION['success_message'] = "อัปเดตสถานะเป็น " . getStatusThai($newStatus) . " เรียบร้อยแล้ว";
    } else {
        $_SESSION['error_message'] = "อัปเดตสถานะสำเร็จ แต่ไม่สามารถบันทึกประวัติได้: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "ไม่สามารถอัปเดตสถานะได้: " . $conn->error;
}

// กลับไปยังหน้าก่อนหน้า
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'design_queue') !== false) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: view_design_queue.php?id=$designId");
}
exit();
?>