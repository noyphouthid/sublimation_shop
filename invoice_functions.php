<?php
// ต้อง include db_connect.php ก่อน
require_once 'db_connect.php';

// ฟังก์ชันสร้างรหัส invoice อัตโนมัติ
function generateInvoiceNumber($conn) {
    $year = date('y'); // 2 หลัก เช่น 25
    $month = date('n'); // 1-12
    
    // ดึงเลขลำดับสูงสุดในเดือนปัจจุบัน
    $sql = "SELECT MAX(invoice_no) as max_code FROM invoices 
            WHERE invoice_no LIKE 'INV$year-$month%'";
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
    
    // สร้างรหัสใหม่ (เช่น INV25-5001)
    $invoiceNo = sprintf("INV%s-%d%03d", $year, $month, $newSeq);
    return $invoiceNo;
}

// ฟังก์ชันดึงข้อมูลประเภทผ้าทั้งหมด
function getAllFabricTypes($conn) {
    $sql = "SELECT * FROM fabric_types ORDER BY fabric_name_lao";
    $result = $conn->query($sql);
    
    $fabricTypes = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $fabricTypes[] = $row;
        }
    }
    
    return $fabricTypes;
}

// ฟังก์ชันดึงข้อมูล invoice ตาม ID
function getInvoiceById($conn, $invoiceId) {
    $sql = "SELECT i.*, u.full_name as created_by_name 
            FROM invoices i 
            LEFT JOIN users u ON i.created_by = u.user_id 
            WHERE i.invoice_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// ฟังก์ชันดึงรายการสินค้าใน invoice
function getInvoiceItems($conn, $invoiceId) {
    $sql = "SELECT ii.*, ft.fabric_name_lao, ft.base_price 
            FROM invoice_items ii 
            LEFT JOIN fabric_types ft ON ii.fabric_id = ft.fabric_id 
            WHERE ii.invoice_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// ฟังก์ชันคำนวณราคาตามไซส์พิเศษ
function calculateSpecialSizes($sizes) {
    $additionalCost = 0;
    
    if (isset($sizes['3xl']) && $sizes['3xl'] > 0) {
        $additionalCost += $sizes['3xl'] * 20000;
    }
    
    if (isset($sizes['4xl']) && $sizes['4xl'] > 0) {
        $additionalCost += $sizes['4xl'] * 25000;
    }
    
    if (isset($sizes['5xl']) && $sizes['5xl'] > 0) {
        $additionalCost += $sizes['5xl'] * 35000;
    }
    
    if (isset($sizes['6xl']) && $sizes['6xl'] > 0) {
        $additionalCost += $sizes['6xl'] * 35000;
    }
    
    return $additionalCost;
}
// เพิ่มฟังก์ชันนี้ใน invoice_functions.php

// ฟังก์ชันบันทึกใบเสนอราคาอิสระ
function saveIndependentInvoice($conn, $invoiceData, $invoiceItems) {
    $conn->begin_transaction();
    
    try {
        // สร้างหรืออัปเดต invoice
        if (!empty($invoiceData['invoice_id'])) {
            // อัปเดต invoice ที่มีอยู่แล้ว (สำหรับแก้ไข)
            $sql = "UPDATE invoices SET 
                    order_id = ?, 
                    design_id = ?, 
                    custom_queue_code = ?,
                    customer_name = ?, 
                    customer_phone = ?, 
                    customer_contact = ?, 
                    team_name = ?, 
                    deposit_amount = ?, 
                    total_amount = ?, 
                    special_discount = ?,
                    notes = ?, 
                    status = ? 
                    WHERE invoice_id = ?";
            
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("การเตรียม SQL ล้มเหลว (อัปเดต): " . $conn->error);
            }
            
            $stmt->bind_param(
                "iisssssiidssi", 
                $invoiceData['order_id'], 
                $invoiceData['design_id'], 
                $invoiceData['custom_queue_code'],
                $invoiceData['customer_name'], 
                $invoiceData['customer_phone'], 
                $invoiceData['customer_contact'], 
                $invoiceData['team_name'], 
                $invoiceData['deposit_amount'], 
                $invoiceData['total_amount'], 
                $invoiceData['special_discount'],
                $invoiceData['notes'], 
                $invoiceData['status'], 
                $invoiceData['invoice_id']
            );
            $stmt->execute();
            
            $invoiceId = $invoiceData['invoice_id'];
            
            // ลบรายการสินค้าเดิม
            $sql = "DELETE FROM invoice_items WHERE invoice_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $invoiceId);
            $stmt->execute();
        } else {
            // สร้าง invoice ใหม่
            $invoiceNo = generateInvoiceNumber($conn);
            
            // เพิ่มตาราง invoices ให้รองรับ custom_queue_code
            $createTableSql = "CREATE TABLE IF NOT EXISTS invoices (
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
            
            $conn->query($createTableSql);
            
            $sql = "INSERT INTO invoices (
                    invoice_no, 
                    order_id, 
                    design_id, 
                    custom_queue_code,
                    customer_name, 
                    customer_phone, 
                    customer_contact, 
                    team_name, 
                    created_by, 
                    deposit_amount, 
                    total_amount, 
                    special_discount,
                    notes, 
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("การเตรียม SQL ล้มเหลว: " . $conn->error);
            }
            
            $stmt->bind_param(
                "siisssssiiddss", 
                $invoiceNo, 
                $invoiceData['order_id'], 
                $invoiceData['design_id'], 
                $invoiceData['custom_queue_code'],
                $invoiceData['customer_name'], 
                $invoiceData['customer_phone'], 
                $invoiceData['customer_contact'], 
                $invoiceData['team_name'], 
                $invoiceData['created_by'], 
                $invoiceData['deposit_amount'], 
                $invoiceData['total_amount'], 
                $invoiceData['special_discount'],
                $invoiceData['notes'], 
                $invoiceData['status']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("การบันทึกล้มเหลว: " . $stmt->error);
            }
            
            $invoiceId = $conn->insert_id;
        }
        
        // เพิ่มรายการสินค้า
        foreach ($invoiceItems as $item) {
            $sql = "INSERT INTO invoice_items (
                    invoice_id, 
                    fabric_id, 
                    quantity, 
                    has_long_sleeve, 
                    has_collar, 
                    size_s, 
                    size_m, 
                    size_l, 
                    size_xl, 
                    size_2xl, 
                    size_3xl, 
                    size_4xl, 
                    size_5xl, 
                    size_6xl, 
                    additional_costs, 
                    additional_notes, 
                    item_total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("การเตรียม SQL สำหรับรายการสินค้าล้มเหลว: " . $conn->error);
            }
            
            $longSleeve = isset($item['has_long_sleeve']) ? 1 : 0;
            $hasCollar = isset($item['has_collar']) ? 1 : 0;
            
            $stmt->bind_param(
                "iiiiiiiiiiiiiddsd", 
                $invoiceId, 
                $item['fabric_id'], 
                $item['quantity'], 
                $longSleeve, 
                $hasCollar, 
                $item['size_s'], 
                $item['size_m'], 
                $item['size_l'], 
                $item['size_xl'], 
                $item['size_2xl'], 
                $item['size_3xl'], 
                $item['size_4xl'], 
                $item['size_5xl'], 
                $item['size_6xl'], 
                $item['additional_costs'], 
                $item['additional_notes'], 
                $item['item_total']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("การบันทึกรายการสินค้าล้มเหลว: " . $stmt->error);
            }
        }
        
        $conn->commit();
        return ['success' => true, 'invoice_id' => $invoiceId];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ฟังก์ชันดึงข้อมูล invoice พร้อมรหัสคิวที่แสดงผล
function getInvoiceByIdWithQueue($conn, $invoiceId) {
    $sql = "SELECT i.*, u.full_name as created_by_name,
                   COALESCE(dq.queue_code, i.custom_queue_code) as display_queue_code,
                   dq.queue_code as design_queue_code,
                   i.custom_queue_code
            FROM invoices i 
            LEFT JOIN users u ON i.created_by = u.user_id 
            LEFT JOIN design_queue dq ON i.design_id = dq.design_id
            WHERE i.invoice_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}
// ฟังก์ชันดึงข้อมูลคิวออกแบบ
function getDesignById($conn, $designId) {
    $sql = "SELECT * FROM design_queue WHERE design_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $designId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// ฟังก์ชันดึงข้อมูลคำสั่งซื้อ (ถ้ายังไม่มี)
function getOrderById($conn, $orderId) {
    // สร้างข้อมูลจำลองก่อน เพราะยังไม่มีตาราง orders
    return null;
}
// ฟังก์ชันอัปเดต invoice_list.php ให้แสดงรหัสคิวที่ถูกต้อง
function getAllInvoicesWithQueue($conn) {
    $sql = "SELECT i.*, u.full_name as created_by_name,
                   COALESCE(dq.queue_code, i.custom_queue_code, 'ไม่มีรหัสคิว') as display_queue_code,
                   CASE 
                       WHEN i.design_id IS NOT NULL THEN 'design'
                       WHEN i.custom_queue_code IS NOT NULL THEN 'custom'
                       ELSE 'none'
                   END as queue_type
            FROM invoices i 
            LEFT JOIN users u ON i.created_by = u.user_id 
            LEFT JOIN design_queue dq ON i.design_id = dq.design_id
            ORDER BY i.created_at DESC";
    
    $result = $conn->query($sql);
    $invoices = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }
    }
    
    return $invoices;
}