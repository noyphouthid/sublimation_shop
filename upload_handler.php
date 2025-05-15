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
if (!isset($_POST['design_id']) || !isset($_POST['file_type']) || !isset($_FILES['files'])) {
    $_SESSION['error_message'] = "ข้อมูลไม่ครบถ้วน";
    header("Location: design_queue_list.php");
    exit();
}

$designId = intval($_POST['design_id']);
$fileType = $conn->real_escape_string($_POST['file_type']);
$note = isset($_POST['note']) ? $conn->real_escape_string($_POST['note']) : '';

// ตรวจสอบว่า design_id มีอยู่จริงหรือไม่
$checkQuery = "SELECT design_id, status FROM design_queue WHERE design_id = $designId";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows === 0) {
    $_SESSION['error_message'] = "ไม่พบคิวออกแบบที่ระบุ";
    header("Location: design_queue_list.php");
    exit();
}

$queue = $checkResult->fetch_assoc();
$currentStatus = $queue['status'];

// ตรวจสอบประเภทไฟล์ที่อนุญาต
$allowedTypes = ['reference', 'design', 'feedback', 'final'];
if (!in_array($fileType, $allowedTypes)) {
    $_SESSION['error_message'] = "ประเภทไฟล์ไม่ถูกต้อง";
    header("Location: view_design_queue.php?id=$designId");
    exit();
}

// สร้างโฟลเดอร์ถ้ายังไม่มี
$uploadDir = "uploads/design_queue/$designId/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ไฟล์ที่อัปโหลดเสร็จ
$successFiles = [];
$errorFiles = [];

// อัปโหลดหลายไฟล์
$fileCount = count($_FILES['files']['name']);

for ($i = 0; $i < $fileCount; $i++) {
    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
        $fileName = $_FILES['files']['name'][$i];
        $tmpName = $_FILES['files']['tmp_name'][$i];
        $fileSize = $_FILES['files']['size'][$i];
        
        // ตรวจสอบนามสกุลไฟล์
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip', 'ai', 'psd', 'svg'];
        
        if (!in_array($fileExt, $allowedExtensions)) {
            $errorFiles[] = "$fileName (นามสกุลไฟล์ไม่อนุญาต)";
            continue;
        }
        
        // ตรวจสอบขนาดไฟล์ (ไม่เกิน 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            $errorFiles[] = "$fileName (ขนาดไฟล์เกิน 10MB)";
            continue;
        }
        
        // เพิ่มเวลาปัจจุบันเข้าไปในชื่อไฟล์เพื่อป้องกันชื่อซ้ำ
        $uniqueFileName = time() . '_' . $fileName;
        $targetPath = $uploadDir . $uniqueFileName;
        
        // ย้ายไฟล์
        if (move_uploaded_file($tmpName, $targetPath)) {
            // บันทึกข้อมูลไฟล์ลงฐานข้อมูล
            $fileSql = "INSERT INTO design_files (design_id, file_name, file_path, file_type, uploaded_by) 
                       VALUES ($designId, '$fileName', '$targetPath', '$fileType', {$_SESSION['user_id']})";
            
            if ($conn->query($fileSql) === TRUE) {
                $successFiles[] = $fileName;
            } else {
                $errorFiles[] = "$fileName (ไม่สามารถบันทึกข้อมูลลงฐานข้อมูล: " . $conn->error . ")";
            }
        } else {
            $errorFiles[] = "$fileName (ไม่สามารถย้ายไฟล์)";
        }
    } else {
        $errorCode = $_FILES['files']['error'][$i];
        $errorMessage = '';
        
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage = "ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดในไฟล์ php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดในฟอร์ม";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "ไฟล์ถูกอัปโหลดเพียงบางส่วน";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = "ไม่มีไฟล์ถูกอัปโหลด";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = "ไม่พบโฟลเดอร์ชั่วคราว";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = "ไม่สามารถเขียนไฟล์ลงดิสก์";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = "การอัปโหลดถูกหยุดโดยส่วนขยาย";
                break;
            default:
                $errorMessage = "เกิดข้อผิดพลาดที่ไม่รู้จัก";
        }
        
        $errorFiles[] = "ไฟล์ที่ " . ($i + 1) . " (" . $errorMessage . ")";
    }
}

// อัปเดตสถานะตามประเภทไฟล์ (ถ้าต้องการ)
if (count($successFiles) > 0) {
    $newStatus = null;
    $comment = "อัปโหลดไฟล์ประเภท " . $fileType;
    
    if ($note) {
        $comment .= ": " . $note;
    }
    
    switch ($fileType) {
        case 'design':
            // เมื่ออัปโหลดไฟล์ออกแบบ ให้เปลี่ยนสถานะเป็น "ส่งให้ลูกค้าตรวจสอบ" เฉพาะเมื่อสถานะปัจจุบันคือ "กำลังออกแบบ"
            if ($currentStatus === 'in_progress') {
                $newStatus = 'customer_review';
            }
            break;
        case 'feedback':
            // เมื่ออัปโหลดฟีดแบ็คลูกค้า ให้เปลี่ยนสถานะเป็น "ลูกค้าขอแก้ไข" เฉพาะเมื่อสถานะปัจจุบันคือ "ส่งให้ลูกค้าตรวจสอบ"
            if ($currentStatus === 'customer_review') {
                $newStatus = 'revision';
            }
            break;
        case 'final':
            // เมื่ออัปโหลดไฟล์สุดท้าย ให้เปลี่ยนสถานะเป็น "ลูกค้าอนุมัติแล้ว" เฉพาะเมื่อสถานะปัจจุบันคือ "ส่งให้ลูกค้าตรวจสอบ"
            if ($currentStatus === 'customer_review') {
                $newStatus = 'approved';
            }
            break;
    }
    
    // อัปเดตสถานะถ้ามีการกำหนด
    if ($newStatus) {
        // อัปเดตสถานะคิว
        $updateSql = "UPDATE design_queue SET status = '$newStatus', updated_at = NOW() WHERE design_id = $designId";
        
        if ($conn->query($updateSql) === TRUE) {
            // บันทึกประวัติการเปลี่ยนสถานะ
            $historySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                           VALUES ($designId, '$currentStatus', '$newStatus', {$_SESSION['user_id']}, '$comment')";
            $conn->query($historySql);
            
            $_SESSION['success_message'] = "อัปโหลดไฟล์สำเร็จและอัปเดตสถานะเป็น " . getStatusThai($newStatus);
        }
    } else {
        // บันทึกประวัติการอัปโหลดไฟล์
        $historySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                       VALUES ($designId, '$currentStatus', '$currentStatus', {$_SESSION['user_id']}, '$comment')";
        $conn->query($historySql);
        
        $_SESSION['success_message'] = "อัปโหลดไฟล์สำเร็จ " . count($successFiles) . " ไฟล์";
    }
    
    if (count($errorFiles) > 0) {
        $_SESSION['warning_message'] = "ไม่สามารถอัปโหลดได้ " . count($errorFiles) . " ไฟล์: " . implode(", ", $errorFiles);
    }
} else {
    $_SESSION['error_message'] = "ไม่สามารถอัปโหลดไฟล์ได้: " . implode(", ", $errorFiles);
}

// กลับไปยังหน้ารายละเอียดคิว
header("Location: view_design_queue.php?id=$designId");
exit();
?>