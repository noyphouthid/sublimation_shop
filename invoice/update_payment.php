<?php
require_once '../db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ตรวจสอบว่ามีการส่งข้อมูลมาหรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: invoice_list.php");
    exit();
}

$invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
$payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : '';
$payment_note = isset($_POST['payment_note']) ? $_POST['payment_note'] : '';

// ตรวจสอบข้อมูล
if ($invoice_id <= 0 || empty($payment_status)) {
    $_SESSION['error_message'] = "ข้อมูลไม่ถูกต้อง";
    header("Location: view_invoice.php?id=$invoice_id");
    exit();
}

// ดึงข้อมูลใบกำกับภาษี
$sql = "SELECT * FROM invoices WHERE invoice_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "ไม่พบข้อมูลใบกำกับภาษี";
    header("Location: invoice_list.php");
    exit();
}

$invoice = $result->fetch_assoc();

// อัปเดตสถานะการชำระเงิน
$sql = "UPDATE invoices SET payment_status = ? WHERE invoice_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $payment_status, $invoice_id);

if ($stmt->execute()) {
    // บันทึกประวัติการเปลี่ยนสถานะ
    $sql = "INSERT INTO payment_history (invoice_id, old_status, new_status, changed_by, change_date, comment) 
            VALUES (?, ?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $invoice_id, $invoice['payment_status'], $payment_status, $_SESSION['user_id'], $payment_note);
    $stmt->execute();
    
    $_SESSION['success_message'] = "อัปเดตสถานะการชำระเงินเรียบร้อยแล้ว";
} else {
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะการชำระเงิน";
}

header("Location: view_invoice.php?id=$invoice_id");
exit();