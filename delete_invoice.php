<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบว่ามี ID ที่ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: invoice_list.php");
    exit;
}

$invoiceId = intval($_GET['id']);

// ตรวจสอบว่ามี invoice นี้อยู่จริงหรือไม่
$checkSql = "SELECT invoice_id FROM invoices WHERE invoice_id = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "ບໍ່ພົບໃບສະເໜີລາຄາທີ່ຕ້ອງການລຶບ";
    header("Location: invoice_list.php");
    exit;
}

// ลบข้อมูล (ใช้ transaction เพื่อความปลอดภัย)
$conn->begin_transaction();

try {
    // ลบรายการสินค้าก่อน
    $deleteItemsSql = "DELETE FROM invoice_items WHERE invoice_id = ?";
    $stmt = $conn->prepare($deleteItemsSql);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    
    // ลบ invoice
    $deleteInvoiceSql = "DELETE FROM invoices WHERE invoice_id = ?";
    $stmt = $conn->prepare($deleteInvoiceSql);
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    
    $conn->commit();
    
    $_SESSION['success_message'] = "ລຶບໃບສະເໜີລາຄາສຳເລັດແລ້ວ";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບ: " . $e->getMessage();
}

header("Location: invoice_list.php");
exit;
?>