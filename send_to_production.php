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
$required_fields = ['design_id', 'factory_id', 'product_details', 'expected_completion_date', 'production_cost', 'confirm_files'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $_SESSION['error_message'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
        header("Location: prepare_production.php?id=" . $_POST['design_id']);
        exit();
    }
}

$designId = intval($_POST['design_id']);
$factoryId = intval($_POST['factory_id']);
$productDetails = $conn->real_escape_string($_POST['product_details']);
$expectedCompletionDate = $_POST['expected_completion_date'];
$productionCost = floatval($_POST['production_cost']);
$productionNotes = isset($_POST['production_notes']) ? $conn->real_escape_string($_POST['production_notes']) : '';

// ตรวจสอบว่า design_id มีอยู่จริงหรือไม่
$checkQuery = "SELECT * FROM design_queue WHERE design_id = $designId AND status = 'approved'";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows === 0) {
    $_SESSION['error_message'] = "ไม่พบคิวออกแบบที่อนุมัติแล้ว";
    header("Location: design_queue_list.php");
    exit();
}

$queue = $checkResult->fetch_assoc();

// ตรวจสอบว่ามีไฟล์สุดท้ายหรือไม่
$fileQuery = "SELECT COUNT(*) as file_count FROM design_files WHERE design_id = $designId AND file_type = 'final'";
$fileResult = $conn->query($fileQuery);
$fileRow = $fileResult->fetch_assoc();

if ($fileRow['file_count'] === '0') {
    $_SESSION['error_message'] = "ไม่พบไฟล์สุดท้ายสำหรับส่งผลิต กรุณาอัปโหลดไฟล์ก่อน";
    header("Location: prepare_production.php?id=$designId");
    exit();
}

// เตรียมข้อมูลสำหรับเชื่อมต่อกับระบบผลิตในอนาคต
// ในตัวอย่างนี้เราจะสร้างตารางมาใหม่เพื่อใช้ในระบบผลิต (ถ้ายังไม่มี)
$createProductionTableSql = "CREATE TABLE IF NOT EXISTS production_orders (
    production_id INT AUTO_INCREMENT PRIMARY KEY,
    design_id INT NOT NULL,
    factory_id INT NOT NULL,
    product_details TEXT NOT NULL,
    production_cost DECIMAL(10,2) NOT NULL,
    expected_completion_date DATE NOT NULL,
    production_notes TEXT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (design_id) REFERENCES design_queue(design_id) ON DELETE CASCADE
)";

if ($conn->query($createProductionTableSql) !== TRUE) {
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการสร้างตารางข้อมูลการผลิต: " . $conn->error;
    header("Location: prepare_production.php?id=$designId");
    exit();
}

// เพิ่มข้อมูลลงในตารางการผลิต
$insertProductionSql = "INSERT INTO production_orders (
    design_id, factory_id, product_details, production_cost, 
    expected_completion_date, production_notes
) VALUES (
    $designId, $factoryId, '$productDetails', $productionCost, 
    '$expectedCompletionDate', '$productionNotes'
)";

if ($conn->query($insertProductionSql) === TRUE) {
    $productionId = $conn->insert_id;
    
    // บันทึกประวัติการส่งผลิต
    $oldStatus = 'approved';
    $newStatus = 'production';
    $user_id = $_SESSION['user_id'];
    $comment = "ส่งไปยังระบบผลิตเรียบร้อยแล้ว (หมายเลขการผลิต: $productionId)";
    
    $historySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                   VALUES ($designId, '$oldStatus', '$newStatus', $user_id, '$comment')";
    $conn->query($historySql);
    
    // อัปเดตสถานะคิว
    $updateSql = "UPDATE design_queue SET status = 'production', updated_at = NOW() WHERE design_id = $designId";
    if ($conn->query($updateSql) === TRUE) {
        $_SESSION['success_message'] = "ส่งคิวออกแบบไปยังระบบผลิตเรียบร้อยแล้ว (หมายเลขการผลิต: $productionId)";
        header("Location: design_queue_list.php");
        exit();
    } else {
        $_SESSION['error_message'] = "ไม่สามารถอัปเดตสถานะคิวได้: " . $conn->error;
        header("Location: prepare_production.php?id=$designId");
        exit();
    }
} else {
    $_SESSION['error_message'] = "ไม่สามารถสร้างรายการผลิตได้: " . $conn->error;
    header("Location: prepare_production.php?id=$designId");
    exit();
}
?>