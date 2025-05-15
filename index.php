<?php
// ไฟล์เริ่มต้นของระบบ
// จะ redirect ไปยังหน้า login หรือหน้ารายการคิวออกแบบ ตามสถานะการเข้าสู่ระบบ

session_start();

// ถ้าเข้าสู่ระบบแล้ว ให้ไปหน้ารายการคิวออกแบบ
if (isset($_SESSION['user_id'])) {
    header("Location: design_queue_list.php");
    exit();
} else {
    // ถ้ายังไม่ได้เข้าสู่ระบบ ให้ไปหน้าเข้าสู่ระบบ
    header("Location: login.php");
    exit();
}
?>