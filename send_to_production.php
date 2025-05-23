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
        $_SESSION['error_message'] = "ກະລຸນາກຸ່ມຂໍ້ມູນໃຫ້ຄົບຖ້ວນ";
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
    $_SESSION['error_message'] = "ບໍ່ພົບຄິວອອກແບບທີ່ອະນຸມັດແລ້ວ";
    header("Location: design_queue_list.php");
    exit();
}

$queue = $checkResult->fetch_assoc();

// ตรวจสอบว่ามีไฟล์สุดท้ายหรือไม่
$fileQuery = "SELECT COUNT(*) as file_count FROM design_files WHERE design_id = $designId AND file_type = 'final'";
$fileResult = $conn->query($fileQuery);
$fileRow = $fileResult->fetch_assoc();

if ($fileRow['file_count'] === '0') {
    $_SESSION['error_message'] = "ບໍ່ພົບໄຟລ໌ສຸດທ້າຍສຳລັບການຜະລິດ ກະລຸນາອັບໂຫລດໄຟລ໌ກ່ອນ";
    header("Location: prepare_production.php?id=$designId");
    exit();
}

// สร้างตาราง production_orders ถ้ายังไม่มี
$createProductionTableSql = "CREATE TABLE IF NOT EXISTS production_orders (
    production_id INT AUTO_INCREMENT PRIMARY KEY,
    design_id INT NOT NULL,
    factory_id INT NOT NULL,
    product_details TEXT NOT NULL,
    production_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expected_completion_date DATE NOT NULL,
    actual_completion_date DATE NULL,
    production_notes TEXT NULL,
    status ENUM('pending', 'sent', 'in_progress', 'ready_pickup', 'received', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (design_id) REFERENCES design_queue(design_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_factory (factory_id),
    INDEX idx_completion_date (expected_completion_date)
)";

if ($conn->query($createProductionTableSql) !== TRUE) {
    $_SESSION['error_message'] = "ເກີດຂໍ້ຜິດພາດໃນການສ້າງຕາລາງຂໍ້ມູນການຜະລິດ: " . $conn->error;
    header("Location: prepare_production.php?id=$designId");
    exit();
}

// เริ่ม transaction
$conn->begin_transaction();

try {
    // เพิ่มข้อมูลลงในตารางการผลิต
    $insertProductionSql = "INSERT INTO production_orders (
        design_id, factory_id, product_details, production_cost, 
        expected_completion_date, production_notes
    ) VALUES (
        $designId, $factoryId, '$productDetails', $productionCost, 
        '$expectedCompletionDate', '$productionNotes'
    )";

    if (!$conn->query($insertProductionSql)) {
        throw new Exception("ไม่สามารถสร้างรายการผลิตได้: " . $conn->error);
    }
    
    $productionId = $conn->insert_id;
    
    // อัปเดตสถานะคิวออกแบบเป็น 'production'
    $updateDesignSql = "UPDATE design_queue SET status = 'production', updated_at = NOW() WHERE design_id = $designId";
    if (!$conn->query($updateDesignSql)) {
        throw new Exception("ไม่สามารถอัปเดตสถานะคิวได้: " . $conn->error);
    }
    
    // บันทึกประวัติการส่งผลิตในคิวออกแบบ
    $oldStatus = 'approved';
    $newStatus = 'production';
    $user_id = $_SESSION['user_id'];
    $comment = "ສົ່ງໄປຍັງລະບົບການຜະລິດເຮີບຮ້ອຍແລ້ວ (ໝາຍເລກການຜະລິດ: $productionId)";
    
    $designHistorySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                        VALUES ($designId, '$oldStatus', '$newStatus', $user_id, '$comment')";
    
    if (!$conn->query($designHistorySql)) {
        throw new Exception("ไม่สามารถบันทึกประวัติคิวออกแบบได้: " . $conn->error);
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
        FOREIGN KEY (changed_by) REFERENCES users(user_id),
        INDEX idx_production_id (production_id),
        INDEX idx_change_date (change_date)
    )";
    
    $conn->query($createHistoryTableSql);
    
    // บันทึกประวัติการสร้างรายการผลิต
    $productionHistorySql = "INSERT INTO production_status_history 
                            (production_id, old_status, new_status, changed_by, notes) 
                            VALUES ($productionId, '', 'pending', $user_id, 'ສ້າງລາຍການຜະລິດໃໝ່ຈາກຄິວອອກແບບ')";
    
    $conn->query($productionHistorySql);
    
    $conn->commit();
    
    $_SESSION['success_message'] = "ສົ່ງຄິວອອກແບບໄປຍັງລະບົບການຜະລິດເຮີບຮ້ອຍແລ້ວ (ໝາຍເລກການຜະລິດ: $productionId)";
    header("Location: view_production.php?id=$productionId");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
    header("Location: prepare_production.php?id=$designId");
    exit();
}
?>