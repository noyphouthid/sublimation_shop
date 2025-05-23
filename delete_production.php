<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบว่ามีการส่ง POST request และมี production_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['production_id'])) {
    $productionId = intval($_POST['production_id']);
    
    // ตรวจสอบสิทธิ์ในการลบ (เฉพาะ admin)
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['error_message'] = "ຂໍອະໄພ, ທ່ານບໍ່ມີສິດທິໃນການລຶບລາຍການຜະລິດ";
        header("Location: production_list.php");
        exit();
    }
    
    // ตรวจสอบว่ามีรายการผลิตนี้อยู่จริงหรือไม่
    $checkQuery = "SELECT po.*, dq.queue_code 
                   FROM production_orders po 
                   LEFT JOIN design_queue dq ON po.design_id = dq.design_id 
                   WHERE po.production_id = $productionId";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult->num_rows === 0) {
        $_SESSION['error_message'] = "ບໍ່ພົບລາຍການຜະລິດທີ່ຕ້ອງການລຶບ";
        header("Location: production_list.php");
        exit();
    }
    
    $production = $checkResult->fetch_assoc();
    $designId = $production['design_id'];
    
    // ตรวจสอบสถานะ - ไม่อนุญาตให้ลบรายการที่ส่งมอบแล้ว
    if ($production['status'] === 'delivered') {
        $_SESSION['error_message'] = "ບໍ່ສາມາດລຶບລາຍການທີ່ສົ່ງມອບແລ້ວໄດ້";
        header("Location: production_list.php");
        exit();
    }
    
    // เริ่ม transaction เพื่อให้แน่ใจว่าการลบข้อมูลทั้งหมดเป็นไปอย่างถูกต้อง
    $conn->begin_transaction();
    
    try {
        // 1. ดึงข้อมูลรูปภาพที่เกี่ยวข้องเพื่อลบจากเซิร์ฟเวอร์
        $imagesQuery = "SELECT image_path FROM production_images WHERE production_id = $productionId";
        $imagesResult = $conn->query($imagesQuery);
        
        $imagesToDelete = [];
        while ($image = $imagesResult->fetch_assoc()) {
            $imagesToDelete[] = $image['image_path'];
        }
        
        // 2. ลบข้อมูลจากฐานข้อมูล
        
        // ลบรูปภาพผลิต
        $deleteImagesSql = "DELETE FROM production_images WHERE production_id = $productionId";
        $conn->query($deleteImagesSql);
        
        // ลบประวัติสถานะการผลิต
        $deleteHistorySql = "DELETE FROM production_status_history WHERE production_id = $productionId";
        $conn->query($deleteHistorySql);
        
        // ลบรายการผลิต
        $deleteProductionSql = "DELETE FROM production_orders WHERE production_id = $productionId";
        if (!$conn->query($deleteProductionSql)) {
            throw new Exception("ไม่สามารถลบรายการผลิตได้: " . $conn->error);
        }
        
        // 3. อัปเดตสถานะคิวออกแบบกลับเป็น 'approved'
        if ($designId) {
            $updateDesignSql = "UPDATE design_queue SET status = 'approved', updated_at = NOW() WHERE design_id = $designId";
            if (!$conn->query($updateDesignSql)) {
                throw new Exception("ไม่สามารถอัปเดตสถานะคิวออกแบบได้: " . $conn->error);
            }
            
            // บันทึกประวัติการเปลี่ยนสถานะในคิวออกแบบ
            $user_id = $_SESSION['user_id'];
            $comment = "ຍົກເລີກການສົ່ງຜະລິດ - ລາຍການຜະລິດຖືກລຶບ (Production ID: $productionId)";
            
            $designHistorySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                                VALUES ($designId, 'production', 'approved', $user_id, '$comment')";
            $conn->query($designHistorySql);
        }
        
        // ยืนยัน transaction
        $conn->commit();
        
        // 4. ลบไฟล์รูปภาพจากเซิร์ฟเวอร์ (ทำหลังจาก commit transaction)
        foreach ($imagesToDelete as $imagePath) {
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // 5. ลบโฟลเดอร์ของรายการผลิต (ถ้ามี)
        $uploadDir = "uploads/production/$productionId/";
        if (file_exists($uploadDir) && is_dir($uploadDir)) {
            // ลบไฟล์ทั้งหมดในโฟลเดอร์ (เผื่อมีไฟล์ตกค้าง)
            $files = glob($uploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            // ลบโฟลเดอร์
            rmdir($uploadDir);
        }
        
        $_SESSION['success_message'] = "ລຶບລາຍການຜະລິດສຳເລັດແລ້ວ (ລະຫັດຄິວ: {$production['queue_code']})";
        
    } catch (Exception $e) {
        // ถ้ามีข้อผิดพลาด ให้ยกเลิก transaction
        $conn->rollback();
        $_SESSION['error_message'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບລາຍການຜະລິດ: " . $e->getMessage();
    }
    
    // กลับไปหน้ารายการการผลิต
    header("Location: production_list.php");
    exit();
    
} else {
    // ถ้าไม่ใช่ POST request หรือไม่มี production_id
    $_SESSION['error_message'] = "ຄຳຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
    header("Location: production_list.php");
    exit();
}
?>