<?php
// ไฟล์เชื่อมต่อฐานข้อมูลและฟังก์ชันทั่วไป
$host = "localhost";
$username = "root"; // เปลี่ยนตามการตั้งค่าของคุณ
$password = ""; // เปลี่ยนตามการตั้งค่าของคุณ
$database = "sublimation_shop";

// สร้างการเชื่อมต่อ
$conn = new mysqli($host, $username, $password, $database);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// ตั้งค่า charset เป็น utf8 สำหรับรองรับภาษาไทย
$conn->set_charset("utf8");

// ฟังก์ชันสำหรับสร้างรหัสคิวอัตโนมัติ
function generateQueueCode($conn, $userCode = 'PKLF') {
    $year = date('y'); // 2 หลัก เช่น 25
    $month = date('n'); // 1-12
    
    // ดึงเลขลำดับสูงสุดในเดือนปัจจุบัน
    $sql = "SELECT MAX(queue_code) as max_code FROM design_queue 
            WHERE queue_code LIKE '$userCode$year-$month%'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    if ($row['max_code']) {
        // แยกส่วนของเลขลำดับ
        $parts = explode('-', $row['max_code']);
        $lastSeq = intval(substr($parts[1], 1)); // ตัดเดือนออก เอาแค่เลขลำดับ
        $newSeq = $lastSeq + 1;
    } else {
        $newSeq = 1; // เริ่มต้นที่ 1 หากไม่มีรหัสในเดือนนี้
    }
    
    // สร้างรหัสใหม่ (เช่น PKLF25-5001)
    $queueCode = sprintf("%s%s-%d%03d", $userCode, $year, $month, $newSeq);
    return $queueCode;
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status) {
    $statusMap = [
               'pending' => 'ລໍຖ້າອອກແບບ',
        'in_progress' => 'ກຳລັງອອກແບບ',
        'customer_review' => 'ສົ່ງໃຫ້ລູກຄ້າກວດສອບ',
        'revision' => 'ລູກຄ້າຂໍແກ້ໄຂ',
        'approved' => 'ລູກຄ້າອະນຸມັດແລ້ວ',
        'production' => 'ສົ່ງໄປຍັງລະບົບຜະລິດ',
        'completed' => 'ສຳເລັດສົມບູນ'

    ];
    
    return isset($statusMap[$status]) ? $statusMap[$status] : $status;
}

// ฟังก์ชันแปลงสถานะเป็นสี Bootstrap
function getStatusColor($status) {
    $colorMap = [
        'pending' => 'secondary',
        'in_progress' => 'primary',
        'customer_review' => 'info',
        'revision' => 'warning',
        'approved' => 'success',
        'production' => 'dark',
        'completed' => 'success'
    ];
    
    return isset($colorMap[$status]) ? $colorMap[$status] : 'secondary';
}

// ฟังก์ชันตรวจสอบไฟล์ที่อัปโหลด
function validateFile($file) {
    // ตรวจสอบค่าความผิดพลาดในการอัปโหลด
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'message' => 'เกิดข้อผิดพลาดในการอัปโหลด รหัส: ' . $file['error']
        ];
    }
    
    // ตรวจสอบขนาดไฟล์ (ไม่เกิน 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return [
            'valid' => false,
            'message' => 'ຂະໜາດໄຟລ໌ເກິນ 10MB ບໍ່ສາມາດອະນຸມັດໃຫ້ເອົາໃຊ້'
        ];
    }
    
    // ตรวจสอบนามสกุลไฟล์
    $fileName = $file['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip', 'ai', 'psd', 'svg'];
    
    if (!in_array($fileExt, $allowedExts)) {
        return [
            'valid' => false,
            'message' => 'ບໍ່ອະນຸຍາດປະເພດໄຟລ໌: ສະເພາະ JPG, PNG, GIF, PDF, ZIP, AI, PSD, ແລະ SVG.'
        ];
    }
    
    return [
        'valid' => true,
        'message' => 'ไฟล์ถูกต้อง'
    ];
}

// ฟังก์ชันสำหรับจัดรูปแบบวันที่แบบไทย
function formatThaiDate($date) {
    if (empty($date)) return '-';
    
    $thaiMonths = [
        1 => 'ມັງກອນ', 'ກຸມພາ', 'ມີນາ', 'ເມສາ', 'ພຶດສະພາ', 'ມິຖຸນາ',
        'ກໍລະກົດ', 'ສິງຫາ', 'ກັນຍາ', 'ຕຸລາ', 'ພະຈິກ', 'ທັນວາ',
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp) + 543; // แปลงเป็น พ.ศ.
    
    return "$day {$thaiMonths[$month]} $year";
}

// ฟังก์ชันตรวจสอบสิทธิ์ผู้ใช้
function checkUserRole($requiredRole, $userRole) {
    // role hierarchy: admin > designer > staff
    if ($userRole === 'admin') {
        return true; // admin สามารถเข้าถึงได้ทุกส่วน
    }
    
    if ($userRole === 'designer' && ($requiredRole === 'designer' || $requiredRole === 'staff')) {
        return true;
    }
    
    if ($userRole === 'staff' && $requiredRole === 'staff') {
        return true;
    }
    
    return false;
}
?>