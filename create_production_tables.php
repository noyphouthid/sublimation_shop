<?php
require_once 'db_connect.php';

$htmlOutput = '<div style="margin: 50px auto; max-width: 800px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f8f9fa;">';
$htmlOutput .= '<h1 style="color: #28a745;">🏭 สร้างตารางระบบการผลิต</h1>';
$htmlOutput .= '<p>กำลังสร้างตารางสำหรับระบบติดตามการผลิต...</p>';

$success = true;
$errors = [];

// ตาราง production_orders (ตารางหลัก)
$productionOrdersSQL = "CREATE TABLE IF NOT EXISTS production_orders (
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

if ($conn->query($productionOrdersSQL) === TRUE) {
    $htmlOutput .= '<p style="color: green;">✅ สร้างตาราง production_orders สำเร็จ</p>';
} else {
    $success = false;
    $errors[] = "production_orders: " . $conn->error;
    $htmlOutput .= '<p style="color: red;">❌ ไม่สามารถสร้างตาราง production_orders ได้: ' . $conn->error . '</p>';
}

// ตาราง production_status_history (ประวัติการเปลี่ยนสถานะ)
$statusHistorySQL = "CREATE TABLE IF NOT EXISTS production_status_history (
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

if ($conn->query($statusHistorySQL) === TRUE) {
    $htmlOutput .= '<p style="color: green;">✅ สร้างตาราง production_status_history สำเร็จ</p>';
} else {
    $success = false;
    $errors[] = "production_status_history: " . $conn->error;
    $htmlOutput .= '<p style="color: red;">❌ ไม่สามารถสร้างตาราง production_status_history ได้: ' . $conn->error . '</p>';
}

// ตาราง production_images (รูปภาพสินค้า)
$productionImagesSQL = "CREATE TABLE IF NOT EXISTS production_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    production_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_name VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    FOREIGN KEY (production_id) REFERENCES production_orders(production_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id),
    INDEX idx_production_id (production_id)
)";

if ($conn->query($productionImagesSQL) === TRUE) {
    $htmlOutput .= '<p style="color: green;">✅ สร้างตาราง production_images สำเร็จ</p>';
} else {
    $success = false;
    $errors[] = "production_images: " . $conn->error;
    $htmlOutput .= '<p style="color: red;">❌ ไม่สามารถสร้างตาราง production_images ได้: ' . $conn->error . '</p>';
}

// ตาราง fabric_types (ประเภทผ้า - สำหรับระบบใบเสนอราคา)
$fabricTypesSQL = "CREATE TABLE IF NOT EXISTS fabric_types (
    fabric_id INT AUTO_INCREMENT PRIMARY KEY,
    fabric_name_lao VARCHAR(255) NOT NULL,
    fabric_name_thai VARCHAR(255) NULL,
    fabric_name_english VARCHAR(255) NULL,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($fabricTypesSQL) === TRUE) {
    $htmlOutput .= '<p style="color: green;">✅ สร้างตาราง fabric_types สำเร็จ</p>';
    
    // เพิ่มข้อมูลตัวอย่างประเภทผ้า
    $sampleFabrics = [
        ['ຜ້າຝ້າຍ 100%', 'ผ้าฝ้าย 100%', 'Cotton 100%', 85000],
        ['ຜ້າ Polyester', 'ผ้า Polyester', 'Polyester', 75000],
        ['ຜ້າ Cotton Blend', 'ผ้า Cotton Blend', 'Cotton Blend', 80000],
        ['ຜ້າ Dri-Fit', 'ผ้า Dri-Fit', 'Dri-Fit', 95000],
        ['ຜ້າ Mesh', 'ผ้า Mesh', 'Mesh', 90000]
    ];
    
    foreach ($sampleFabrics as $fabric) {
        $insertSQL = "INSERT IGNORE INTO fabric_types (fabric_name_lao, fabric_name_thai, fabric_name_english, base_price) 
                     VALUES ('{$fabric[0]}', '{$fabric[1]}', '{$fabric[2]}', {$fabric[3]})";
        $conn->query($insertSQL);
    }
    $htmlOutput .= '<p style="color: blue;">📝 เพิ่มข้อมูลตัวอย่างประเภทผ้า 5 รายการ</p>';
} else {
    $htmlOutput .= '<p style="color: orange;">⚠️ ตาราง fabric_types มีอยู่แล้วหรือไม่สามารถสร้างได้: ' . $conn->error . '</p>';
}

// สร้างโฟลเดอร์อัปโหลด
$uploadDirs = [
    'uploads/production',
    'uploads/invoices'
];

foreach ($uploadDirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            $htmlOutput .= '<p style="color: green;">📁 สร้างโฟลเดอร์ ' . $dir . ' สำเร็จ</p>';
        } else {
            $htmlOutput .= '<p style="color: red;">❌ ไม่สามารถสร้างโฟลเดอร์ ' . $dir . ' ได้</p>';
        }
    } else {
        $htmlOutput .= '<p style="color: blue;">📁 โฟลเดอร์ ' . $dir . ' มีอยู่แล้ว</p>';
    }
}

// สรุปผลการติดตั้ง
$htmlOutput .= '<hr>';
if ($success) {
    $htmlOutput .= '<div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    $htmlOutput .= '<h3>🎉 ติดตั้งระบบการผลิตสำเร็จ!</h3>';
    $htmlOutput .= '<p>ตารางทั้งหมดถูกสร้างเรียบร้อยแล้ว คุณสามารถเริ่มใช้งานระบบการผลิตได้</p>';
    $htmlOutput .= '<div style="margin-top: 15px;">';
    $htmlOutput .= '<a href="production_dashboard.php" style="background-color: #007bff; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px;">📊 ไปยังแดชบอร์ดการผลิต</a>';
    $htmlOutput .= '<a href="production_list.php" style="background-color: #28a745; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px;">📋 ไปยังรายการการผลิต</a>';
    $htmlOutput .= '<a href="design_queue_list.php" style="background-color: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px;">🎨 กลับไปคิวออกแบบ</a>';
    $htmlOutput .= '</div>';
    $htmlOutput .= '</div>';
} else {
    $htmlOutput .= '<div style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    $htmlOutput .= '<h3>❌ การติดตั้งมีปัญหา</h3>';
    $htmlOutput .= '<p>มีข้อผิดพลาดในการสร้างตารางบางตาราง:</p>';
    $htmlOutput .= '<ul>';
    foreach ($errors as $error) {
        $htmlOutput .= '<li>' . $error . '</li>';
    }
    $htmlOutput .= '</ul>';
    $htmlOutput .= '<p>กรุณาตรวจสอบและลองใหม่อีกครั้ง</p>';
    $htmlOutput .= '</div>';
}

$htmlOutput .= '<div style="margin-top: 30px; padding: 15px; background-color: #e9ecef; border-radius: 5px;">';
$htmlOutput .= '<h4>📋 ตารางที่ถูกสร้าง:</h4>';
$htmlOutput .= '<ol>';
$htmlOutput .= '<li><strong>production_orders</strong> - ตารางหลักการผลิต</li>';
$htmlOutput .= '<li><strong>production_status_history</strong> - ประวัติการเปลี่ยนสถานะ</li>';
$htmlOutput .= '<li><strong>production_images</strong> - รูปภาพสินค้า</li>';
$htmlOutput .= '<li><strong>fabric_types</strong> - ประเภทผ้า (สำหรับใบเสนอราคา)</li>';
$htmlOutput .= '</ol>';
$htmlOutput .= '</div>';

$htmlOutput .= '</div>';

echo $htmlOutput;

$conn->close();
?>