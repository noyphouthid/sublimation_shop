<?php
session_start();

// ลบข้อมูล session ทั้งหมด
session_unset();
session_destroy();

// กลับไปยังหน้าเข้าสู่ระบบ
header("Location: login.php");
exit();
?>