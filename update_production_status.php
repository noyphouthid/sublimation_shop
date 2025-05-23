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
    header("Location: production_list.php");
    exit();
}

// ตรวจสอบข้อมูลที่จำเป็น
if (!isset($_POST['production_id']) || !isset($_POST['new_status'])) {
    $_SESSION['error_message'] = "ຂໍ້ມູນບໍ່ຄົບຖ້ວນ ກະລຸນາລອງໃໝ່ອີກຄັ້ງ";
    header("Location: production_list.php");
    exit();
}

$productionId = intval($_POST['production_id']);
$newStatus = $conn->real_escape_string($_POST['new_status']);
$notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : '';
$actualCompletionDate = isset($_POST['actual_completion_date']) && !empty($_POST['actual_completion_date']) ? $_POST['actual_completion_date'] : null;

// ตรวจสอบว่า production_id มีอยู่จริงหรือไม่
$checkQuery = "SELECT po.*, dq.queue_code FROM production_orders po 
               LEFT JOIN design_queue dq ON po.design_id = dq.design_id 
               WHERE po.production_id = $productionId";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows === 0) {
    $_SESSION['error_message'] = "ບໍ່ພົບລາຍການຜະລິດທີ່ລະບຸ";
    header("Location: production_list.php");
    exit();
}

$production = $checkResult->fetch_assoc();
$oldStatus = $production['status'];

// ตรวจสอบสถานะที่อนุญาต
$allowedStatuses = ['pending', 'sent', 'in_progress', 'ready_pickup', 'received', 'delivered', 'cancelled'];
if (!in_array($newStatus, $allowedStatuses)) {
    $_SESSION['error_message'] = "ສະຖານະບໍ່ຖືກຕ້ອງ";
    header("Location: production_list.php");
    exit();
}

// สร้างตาราง production_status_history ถ้ายังไม่มี
$createHistoryTableSql = "CREATE TABLE IF NOT EXISTS production_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    production_id INT NOT NULL,
    old_status VARCHAR(50) NOT NULL,
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    actual_completion_date DATE NULL,
    FOREIGN KEY (production_id) REFERENCES production_orders(production_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id)
)";

$conn->query($createHistoryTableSql);

// สร้างตาราง production_images ถ้ายังไม่มี
$createImagesTableSql = "CREATE TABLE IF NOT EXISTS production_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    production_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_name VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_id) REFERENCES production_orders(production_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
)";

$conn->query($createImagesTableSql);

// เริ่ม transaction
$conn->begin_transaction();

try {
    // อัปเดตสถานะ
    $updateSql = "UPDATE production_orders SET 
                  status = '$newStatus', 
                  updated_at = NOW()";
    
    // เพิ่มวันที่รับของจริงถ้ามี
    if ($actualCompletionDate) {
        $updateSql .= ", actual_completion_date = '$actualCompletionDate'";
    }
    
    $updateSql .= " WHERE production_id = $productionId";
    
    if (!$conn->query($updateSql)) {
        throw new Exception("ไม่สามารถอัปเดตสถานะได้: " . $conn->error);
    }
    
    // บันทึกประวัติการเปลี่ยนสถานะ
    $user_id = $_SESSION['user_id'];
    $historySql = "INSERT INTO production_status_history 
                   (production_id, old_status, new_status, changed_by, notes, actual_completion_date) 
                   VALUES ($productionId, '$oldStatus', '$newStatus', $user_id, '$notes', " . 
                   ($actualCompletionDate ? "'$actualCompletionDate'" : "NULL") . ")";
    
    if (!$conn->query($historySql)) {
        throw new Exception("ไม่สามารถบันทึกประวัติได้: " . $conn->error);
    }
    
    // จัดการการอัปโหลดรูปภาพ
    if (isset($_FILES['product_images']) && $_FILES['product_images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
        $fileCount = count($_FILES['product_images']['name']);
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        $uploadDir = "uploads/production/$productionId/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['product_images']['name'][$i];
                $tmpName = $_FILES['product_images']['tmp_name'][$i];
                $fileSize = $_FILES['product_images']['size'][$i];
                
                // ตรวจสอบนามสกุลไฟล์
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($fileExt, $allowedExtensions)) {
                    continue; // ข้ามไฟล์ที่ไม่อนุญาต
                }
                
                // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
                if ($fileSize > 5 * 1024 * 1024) {
                    continue; // ข้ามไฟล์ที่ใหญ่เกินไป
                }
                
                // เพิ่มเวลาปัจจุบันเข้าไปในชื่อไฟล์เพื่อป้องกันชื่อซ้ำ
                $uniqueFileName = time() . '_' . $fileName;
                $targetPath = $uploadDir . $uniqueFileName;
                
                // ย้ายไฟล์
                if (move_uploaded_file($tmpName, $targetPath)) {
                    // บันทึกข้อมูลไฟล์ลงฐานข้อมูล
                    $imageSql = "INSERT INTO production_images 
                                (production_id, image_path, image_name, uploaded_by) 
                                VALUES ($productionId, '$targetPath', '$fileName', $user_id)";
                    
                    if (!$conn->query($imageSql)) {
                        throw new Exception("ไม่สามารถบันทึกข้อมูลรูปภาพได้: " . $conn->error);
                    }
                }
            }
        }
    }
    
    // อัปเดตสถานะคิวออกแบบถ้าจำเป็น
    if ($newStatus === 'delivered') {
        $updateDesignSql = "UPDATE design_queue SET status = 'completed', updated_at = NOW() 
                           WHERE design_id = {$production['design_id']}";
        $conn->query($updateDesignSql);
        
        // บันทึกประวัติในคิวออกแบบ
        $designHistorySql = "INSERT INTO status_history 
                            (design_id, old_status, new_status, changed_by, comment) 
                            VALUES ({$production['design_id']}, 'production', 'completed', $user_id, 
                            'งานผลิตเสร็จสิ้นสมบูรณ์ (Production ID: $productionId)')";
        $conn->query($designHistorySql);
    }
    
    $conn->commit();
    
    // ข้อความแจ้งเตือนตามสถานะ
    $statusMessages = [
        'sent' => "ສົ່ງໄຟລ໌ໄປໂຮງງານເຮີບຮ້ອຍແລ້ວ",
        'in_progress' => "ໂຮງງານເລີ່ມດຳເນີນການຜະລິດແລ້ວ",
        'ready_pickup' => "ສິນຄ້າພ້ອມສຳລັບການມາຮັບແລ້ວ",
        'received' => "ຮັບສິນຄ້າຈາກໂຮງງານເຮີບຮ້ອຍແລ້ວ",
        'delivered' => "ສົ່ງສິນຄ້າໃຫ້ລູກຄ້າເຮີບຮ້ອຍແລ້ວ - ສຳເລັດສົມບູນ"
    ];
    
    $message = isset($statusMessages[$newStatus]) ? $statusMessages[$newStatus] : "ອັບເດດສະຖານະເຮີບຮ້ອຍແລ້ວ";
    $_SESSION['success_message'] = $message . " (ລະຫັດຄິວ: {$production['queue_code']})";
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
}

// กลับไปยังหน้าก่อนหน้า
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'production') !== false) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: production_list.php");
}
exit();
?>