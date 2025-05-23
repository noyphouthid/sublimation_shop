<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบว่ามีการส่ง POST request และมี design_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['design_id'])) {
    $designId = intval($_POST['design_id']);
    
    // เริ่ม transaction เพื่อให้แน่ใจว่าการลบข้อมูลทั้งหมดเป็นไปอย่างถูกต้อง
    $conn->begin_transaction();
    
    try {
        // 1. ดึงข้อมูลไฟล์ที่เกี่ยวข้องเพื่อลบจากเซิร์ฟเวอร์
        $filesQuery = "SELECT file_path FROM design_files WHERE design_id = $designId";
        $filesResult = $conn->query($filesQuery);
        
        $filesToDelete = [];
        while ($file = $filesResult->fetch_assoc()) {
            $filesToDelete[] = $file['file_path'];
        }
        
        // 2. ลบข้อมูลจากฐานข้อมูล (ใช้ foreign key constraints จะช่วยลบข้อมูลที่เกี่ยวข้องให้อัตโนมัติ)
        
        // ลบประวัติสถานะ
        $deleteHistoryQuery = "DELETE FROM status_history WHERE design_id = $designId";
        $conn->query($deleteHistoryQuery);
        
        // ลบไฟล์แนบ
        $deleteFilesQuery = "DELETE FROM design_files WHERE design_id = $designId";
        $conn->query($deleteFilesQuery);
        
        // ลบคิวออกแบบ
        $deleteQueueQuery = "DELETE FROM design_queue WHERE design_id = $designId";
        $conn->query($deleteQueueQuery);
        
        // ยืนยัน transaction
        $conn->commit();
        
        // 3. ลบไฟล์จากเซิร์ฟเวอร์ (ทำหลังจาก commit transaction เพื่อให้แน่ใจว่าข้อมูลในฐานข้อมูลถูกลบเรียบร้อยแล้ว)
        foreach ($filesToDelete as $filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // 4. ลบโฟลเดอร์ของคิวออกแบบ (ถ้ามี)
        $uploadDir = "uploads/design_queue/$designId/";
        if (file_exists($uploadDir) && is_dir($uploadDir)) {
            // ลบไฟล์ทั้งหมดในโฟลเดอร์ (เผื่อมีไฟล์ตกค้าง)
            $files = glob($uploadDir . '*');
            foreach ($files as $file) {
                unlink($file);
            }
            // ลบโฟลเดอร์
            rmdir($uploadDir);
        }
        
        $_SESSION['success_message'] = "ລຶບຄິວອອກແບບສຳເລັດແລ້ວ";
    } catch (Exception $e) {
        // ถ้ามีข้อผิดพลาด ให้ยกเลิก transaction
        $conn->rollback();
        $_SESSION['error_message'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຄິວ: " . $e->getMessage();
    }
    
    // กลับไปหน้ารายการคิว
    header("Location: design_queue_list.php");
    exit();
} else {
    // ถ้าไม่ใช่ POST request หรือไม่มี design_id
    $_SESSION['error_message'] = "ຄຳຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
    header("Location: design_queue_list.php");
    exit();
}
?>