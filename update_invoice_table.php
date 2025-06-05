<?php
require_once 'db_connect.php';

$htmlOutput = '<div style="margin: 50px auto; max-width: 800px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f8f9fa;">';
$htmlOutput .= '<h1 style="color: #28a745;">🔄 อัปเดตตารางใบเสนอราคาสำหรับระบบอิสระ</h1>';
$htmlOutput .= '<p>กำลังอัปเดตโครงสร้างตารางใบเสนอราคาให้รองรับรหัสคิวอิสระ...</p>';

$success = true;
$errors = [];

try {
    // ตรวจสอบว่าตาราง invoices มีอยู่หรือไม่
    $checkTableSQL = "SHOW TABLES LIKE 'invoices'";
    $result = $conn->query($checkTableSQL);
    
    if ($result->num_rows === 0) {
        // สร้างตาราง invoices ใหม่
        $createTableSQL = "CREATE TABLE invoices (
            invoice_id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_no VARCHAR(50) NOT NULL UNIQUE,
            order_id INT NULL,
            design_id INT NULL,
            custom_queue_code VARCHAR(100) NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NULL,
            customer_contact VARCHAR(255) NULL,
            team_name VARCHAR(255) NOT NULL,
            created_by INT NOT NULL,
            deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            special_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            notes TEXT NULL,
            status ENUM('draft', 'sent', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(user_id),
            INDEX idx_invoice_no (invoice_no),
            INDEX idx_status (status),
            INDEX idx_design_id (design_id),
            INDEX idx_custom_queue (custom_queue_code)
        )";
        
        if ($conn->query($createTableSQL) === TRUE) {
            $htmlOutput .= '<p style="color: green;">✅ สร้างตาราง invoices ใหม่สำเร็จ (รองรับรหัสคิวอิสระ)</p>';
        } else {
            throw new Exception("ไม่สามารถสร้างตาราง invoices ได้: " . $conn->error);
        }
    } else {
        // ตรวจสอบว่ามีฟิลด์ custom_queue_code หรือไม่
        $checkColumnSQL = "SHOW COLUMNS FROM invoices LIKE 'custom_queue_code'";
        $result = $conn->query($checkColumnSQL);
        
        if ($result->num_rows === 0) {
            // เพิ่มฟิลด์ custom_queue_code
            $addColumnSQL = "ALTER TABLE invoices ADD COLUMN custom_queue_code VARCHAR(100) NULL AFTER design_id";
            
            if ($conn->query($addColumnSQL) === TRUE) {
                $htmlOutput .= '<p style="color: green;">✅ เพิ่มฟิลด์ custom_queue_code ในตาราง invoices สำเร็จ</p>';
                
                // เพิ่ม index สำหรับ custom_queue_code
                $addIndexSQL = "ALTER TABLE invoices ADD INDEX idx_custom_queue (custom_queue_code)";
                if ($conn->query($addIndexSQL) === TRUE) {
                    $htmlOutput .= '<p style="color: green;">✅ เพิ่ม index สำหรับ custom_queue_code สำเร็จ</p>';
                } else {
                    $htmlOutput .= '<p style="color: orange;">⚠️ ไม่สามารถเพิ่ม index ได้: ' . $conn->error . '</p>';
                }
            } else {
                throw new Exception("ไม่สามารถเพิ่มฟิลด์ custom_queue_code ได้: " . $conn->error);
            }
        } else {
            $htmlOutput .= '<p style="color: blue;">ℹ️ ตาราง invoices มีฟิลด์ custom_queue_code อยู่แล้ว</p>';
        }
    }
    
    // ตรวจสอบและสร้างตาราง invoice_items ถ้ายังไม่มี
    $checkItemsTableSQL = "SHOW TABLES LIKE 'invoice_items'";
    $result = $conn->query($checkItemsTableSQL);
    
    if ($result->num_rows === 0) {
        $createItemsTableSQL = "CREATE TABLE invoice_items (
            item_id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            fabric_id INT NOT NULL,
            quantity INT NOT NULL,
            has_long_sleeve TINYINT(1) DEFAULT 0,
            has_collar TINYINT(1) DEFAULT 0,
            size_s INT DEFAULT 0,
            size_m INT DEFAULT 0,
            size_l INT DEFAULT 0,
            size_xl INT DEFAULT 0,
            size_2xl INT DEFAULT 0,
            size_3xl INT DEFAULT 0,
            size_4xl INT DEFAULT 0,
            size_5xl INT DEFAULT 0,
            size_6xl INT DEFAULT 0,
            additional_costs DECIMAL(10,2) DEFAULT 0.00,
            additional_notes TEXT NULL,
            item_total DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
            FOREIGN KEY (fabric_id) REFERENCES fabric_types(fabric_id),
            INDEX idx_invoice_id (invoice_id)
        )";
        
        if ($conn->query($createItemsTableSQL) === TRUE) {
            $htmlOutput .= '<p style="color: green;">✅ สร้างตาราง invoice_items สำเร็จ</p>';
        } else {
            throw new Exception("ไม่สามารถสร้างตาราง invoice_items ได้: " . $conn->error);
        }
    } else {
        $htmlOutput .= '<p style="color: blue;">ℹ️ ตาราง invoice_items มีอยู่แล้ว</p>';
    }
    
    // แสดงโครงสร้างตารางที่อัปเดตแล้ว
    $showStructureSQL = "DESCRIBE invoices";
    $result = $conn->query($showStructureSQL);
    
    $htmlOutput .= '<h3>📋 โครงสร้างตาราง invoices ปัจจุบัน:</h3>';
    $htmlOutput .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
    $htmlOutput .= '<tr style="background-color: #f2f2f2; border: 1px solid #ddd;">';
    $htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd;">Field</th>';
    $htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd;">Type</th>';
    $htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd;">Null</th>';
    $htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd;">Key</th>';
    $htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd;">Default</th>';
    $htmlOutput .= '</tr>';
    
    while ($row = $result->fetch_assoc()) {
        $htmlOutput .= '<tr style="border: 1px solid #ddd;">';
        $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $row['Field'] . '</td>';
        $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $row['Type'] . '</td>';
        $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $row['Null'] . '</td>';
        $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $row['Key'] . '</td>';
        $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . ($row['Default'] ?: 'NULL') . '</td>';
        $htmlOutput .= '</tr>';
    }
    $htmlOutput .= '</table>';
    
} catch (Exception $e) {
    $success = false;
    $errors[] = $e->getMessage();
    $htmlOutput .= '<p style="color: red;">❌ เกิดข้อผิดพลาด: ' . $e->getMessage() . '</p>';
}

// สรุปผลการอัปเดต
$htmlOutput .= '<hr>';
if ($success) {
    $htmlOutput .= '<div style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    $htmlOutput .= '<h3>🎉 อัปเดตระบบใบเสนอราคาสำเร็จ!</h3>';
    $htmlOutput .= '<p>ตอนนี้คุณสามารถ:</p>';
    $htmlOutput .= '<ul>';
    $htmlOutput .= '<li>✅ สร้างใบเสนอราคาจากคิวออกแบบที่มีอยู่</li>';
    $htmlOutput .= '<li>✅ สร้างใบเสนอราคาแบบอิสระโดยกำหนดรหัสคิวเอง</li>';
    $htmlOutput .= '<li>✅ ระบบจะแสดงประเภทรหัสคิวในรายการและหน้าแสดงผล</li>';
    $htmlOutput .= '</ul>';
    $htmlOutput .= '<div style="margin-top: 15px;">';
    $htmlOutput .= '<a href="add_invoice_independent.php" style="background-color: #28a745; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px;">📝 สร้างใบเสนอราคาอิสระ</a>';
    $htmlOutput .= '<a href="invoice_list.php" style="background-color: #007bff; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px;">📋 ดูรายการใบเสนอราคา</a>';
    $htmlOutput .= '<a href="add_invoice.php" style="background-color: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px;">🎨 สร้างจากคิวออกแบบ</a>';
    $htmlOutput .= '</div>';
    $htmlOutput .= '</div>';
} else {
    $htmlOutput .= '<div style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    $htmlOutput .= '<h3>❌ การอัปเดตมีปัญหา</h3>';
    $htmlOutput .= '<p>มีข้อผิดพลาดในการอัปเดตตาราง:</p>';
    $htmlOutput .= '<ul>';
    foreach ($errors as $error) {
        $htmlOutput .= '<li>' . htmlspecialchars($error) . '</li>';
    }
    $htmlOutput .= '</ul>';
    $htmlOutput .= '<p>กรุณาตรวจสอบและลองใหม่อีกครั้ง</p>';
    $htmlOutput .= '</div>';
}

$htmlOutput .= '<div style="margin-top: 30px; padding: 15px; background-color: #e9ecef; border-radius: 5px;">';
$htmlOutput .= '<h4>📝 คุณสมบัติใหม่ที่เพิ่มเข้ามา:</h4>';
$htmlOutput .= '<ol>';
$htmlOutput .= '<li><strong>รหัสคิวอิสระ (custom_queue_code)</strong> - สามารถกำหนดรหัสคิวเองได้</li>';
$htmlOutput .= '<li><strong>ระบบ Autocomplete</strong> - ค้นหาคิวออกแบบที่มีอยู่แล้วได้</li>';
$htmlOutput .= '<li><strong>แสดงประเภทรหัสคิว</strong> - แยกแสดงว่าเป็นคิวออกแบบหรืออิสระ</li>';
$htmlOutput .= '<li><strong>ลิงก์ไปคิวออกแบบ</strong> - ถ้าผูกกับคิวออกแบบจะมีปุ่มดูคิว</li>';
$htmlOutput .= '</ol>';
$htmlOutput .= '</div>';

$htmlOutput .= '</div>';

echo $htmlOutput;

$conn->close();
?>