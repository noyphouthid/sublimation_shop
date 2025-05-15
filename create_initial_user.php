<?php
require_once 'db_connect.php';

// ตรวจสอบว่ามีผู้ใช้ในระบบหรือยัง
$checkQuery = "SELECT COUNT(*) as user_count FROM users";
$result = $conn->query($checkQuery);
$row = $result->fetch_assoc();

// ถ้ามีผู้ใช้ในระบบแล้ว ให้ตรวจสอบว่าเข้าถึงไฟล์นี้จากเบราว์เซอร์หรือไม่
if ($row['user_count'] > 0 && !isset($_GET['force']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div style="margin: 50px auto; max-width: 800px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f8f9fa;">';
    echo '<h1 style="color: #dc3545;">⚠️ คำเตือน: มีผู้ใช้ในระบบแล้ว</h1>';
    echo '<p>การรันสคริปต์นี้จะไม่สร้างผู้ใช้เพิ่มเติม เนื่องจากมีผู้ใช้ในระบบอยู่แล้ว (' . $row['user_count'] . ' คน)</p>';
    echo '<p>หากคุณต้องการรีเซ็ตระบบและสร้างผู้ใช้เริ่มต้นใหม่ กรุณาตรวจสอบให้แน่ใจว่าคุณต้องการดำเนินการนี้จริงๆ เพราะข้อมูลเดิมอาจสูญหายได้</p>';
    
    echo '<form method="post" style="margin-top: 20px;">';
    echo '<div style="margin-bottom: 10px;"><label><input type="checkbox" name="confirm_reset" value="1" required> ยืนยันว่าฉันต้องการรีเซ็ตระบบ</label></div>';
    echo '<button type="submit" style="background-color: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">รีเซ็ตระบบและสร้างผู้ใช้เริ่มต้น</button>';
    echo ' <a href="index.php" style="background-color: #6c757d; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; margin-left: 10px;">ยกเลิก</a>';
    echo '</form>';
    
    echo '</div>';
    exit();
}

// ถ้ามีการยืนยันการรีเซ็ต ให้ลบข้อมูลเดิมและสร้างผู้ใช้ใหม่
if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] == 1) {
    // ลบข้อมูลเดิม
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("TRUNCATE TABLE status_history");
    $conn->query("TRUNCATE TABLE design_files");
    $conn->query("TRUNCATE TABLE design_queue");
    $conn->query("TRUNCATE TABLE users");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo '<div style="margin: 50px auto; max-width: 800px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f8f9fa;">';
    echo '<h1 style="color: #28a745;">✅ รีเซ็ตข้อมูลเรียบร้อยแล้ว</h1>';
    echo '<p>ลบข้อมูลเดิมในระบบเรียบร้อยแล้ว กำลังสร้างผู้ใช้เริ่มต้น...</p>';
    echo '</div>';
}

// เริ่มการสร้างผู้ใช้เริ่มต้น
$htmlOutput = '<div style="margin: 50px auto; max-width: 800px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: #f8f9fa;">';

// ถ้ามีผู้ใช้ในระบบแล้วและไม่ใช่การรีเซ็ต ให้ออก
if ($row['user_count'] > 0 && !isset($_POST['confirm_reset'])) {
    $htmlOutput .= '<h1 style="color: #dc3545;">⚠️ คำเตือน: มีผู้ใช้ในระบบแล้ว</h1>';
    $htmlOutput .= '<p>ไม่สามารถสร้างผู้ใช้เริ่มต้นได้ เนื่องจากมีผู้ใช้ในระบบอยู่แล้ว (' . $row['user_count'] . ' คน)</p>';
    $htmlOutput .= '<p><a href="login.php" style="background-color: #007bff; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px;">ไปยังหน้าเข้าสู่ระบบ</a></p>';
    $htmlOutput .= '</div>';
    echo $htmlOutput;
    exit();
}

// ข้อมูลผู้ใช้เริ่มต้น
$users = [
    [
        'username' => 'admin',
        'password' => 'admin123', // ในการใช้งานจริงควรใช้รหัสผ่านที่ซับซ้อนกว่านี้
        'full_name' => 'ผู้ดูแลระบบ',
        'email' => 'admin@example.com',
        'role' => 'admin'
    ],
    [
        'username' => 'designer1',
        'password' => 'design123',
        'full_name' => 'ดีไซเนอร์ 1',
        'email' => 'designer1@example.com',
        'role' => 'designer'
    ],
    [
        'username' => 'designer2',
        'password' => 'design123',
        'full_name' => 'ดีไซเนอร์ 2',
        'email' => 'designer2@example.com',
        'role' => 'designer'
    ],
    [
        'username' => 'staff1',
        'password' => 'staff123',
        'full_name' => 'เจ้าหน้าที่ 1',
        'email' => 'staff1@example.com',
        'role' => 'staff'
    ]
];

$htmlOutput .= '<h1 style="color: #28a745;">🚀 สร้างผู้ใช้เริ่มต้น</h1>';
$htmlOutput .= '<p>กำลังสร้างผู้ใช้เริ่มต้นสำหรับระบบบริหารร้านเสื้อพิมพ์ลาย...</p>';
$htmlOutput .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
$htmlOutput .= '<tr style="background-color: #f2f2f2;">';
$htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">ชื่อผู้ใช้</th>';
$htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">รหัสผ่าน</th>';
$htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">ชื่อ-นามสกุล</th>';
$htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">อีเมล</th>';
$htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">บทบาท</th>';
$htmlOutput .= '<th style="padding: 8px; border: 1px solid #ddd; text-align: left;">สถานะ</th>';
$htmlOutput .= '</tr>';

foreach ($users as $user) {
    $username = $user['username'];
    $password = $user['password'];
    $fullName = $user['full_name'];
    $email = $user['email'];
    $role = $user['role'];
    
    // เข้ารหัสรหัสผ่าน
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // เพิ่มผู้ใช้ลงในฐานข้อมูล
    $sql = "INSERT INTO users (username, password, full_name, email, role) 
            VALUES ('$username', '$hashedPassword', '$fullName', '$email', '$role')";
    
    $success = $conn->query($sql);
    
    $htmlOutput .= '<tr>';
    $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $username . '</td>';
    $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $password . '</td>';
    $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $fullName . '</td>';
    $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $email . '</td>';
    $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . ucfirst($role) . '</td>';
    $htmlOutput .= '<td style="padding: 8px; border: 1px solid #ddd;">' . ($success ? '<span style="color: green;">✓ สำเร็จ</span>' : '<span style="color: red;">✗ ล้มเหลว: ' . $conn->error . '</span>') . '</td>';
    $htmlOutput .= '</tr>';
}

$htmlOutput .= '</table>';
$htmlOutput .= '<div style="margin-top: 20px;">';
$htmlOutput .= '<p><a href="login.php" style="background-color: #007bff; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; display: inline-block; margin-top: 10px;">ไปยังหน้าเข้าสู่ระบบ</a></p>';
$htmlOutput .= '</div>';
$htmlOutput .= '</div>';

echo $htmlOutput;

// สร้างโฟลเดอร์อัปโหลด
$uploadDir = 'uploads/design_queue';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$conn->close();
?>