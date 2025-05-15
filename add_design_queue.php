<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ดึงรายชื่อดีไซเนอร์
$designersQuery = "SELECT user_id, full_name FROM users WHERE role = 'designer'";
$designersResult = $conn->query($designersQuery);

// การจัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // สร้างรหัสคิวอัตโนมัติ
    $queueCode = generateQueueCode($conn);
    
    // รับข้อมูลจากฟอร์ม
    $customerName = $conn->real_escape_string($_POST['customer_name']);
    $customerPhone = $conn->real_escape_string($_POST['customer_phone']);
    $customerContact = $conn->real_escape_string($_POST['customer_contact']);
    $designDetails = $conn->real_escape_string($_POST['design_details']);
    $teamName = $conn->real_escape_string($_POST['team_name']);
    $shirtColor = $conn->real_escape_string($_POST['shirt_color']);
    $designType = $conn->real_escape_string($_POST['design_type']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $designerId = !empty($_POST['designer_id']) ? intval($_POST['designer_id']) : NULL;
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : NULL;
    
    // เพิ่มข้อมูลลงในฐานข้อมูล
    $sql = "INSERT INTO design_queue (queue_code, customer_name, customer_phone, customer_contact, 
            design_details, team_name, shirt_color, design_type, notes, designer_id, deadline) 
            VALUES ('$queueCode', '$customerName', '$customerPhone', '$customerContact', 
            '$designDetails', '$teamName', '$shirtColor', '$designType', '$notes', " . 
            ($designerId ? $designerId : "NULL") . ", " . 
            ($deadline ? "'$deadline'" : "NULL") . ")";
    
    if ($conn->query($sql) === TRUE) {
        $designId = $conn->insert_id;
        
        // บันทึกประวัติสถานะ
        $user_id = $_SESSION['user_id'];
        $statusSql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                     VALUES ($designId, NULL, 'pending', $user_id, 'สร้างคิวใหม่')";
        $conn->query($statusSql);
        
        // อัปโหลดไฟล์ (ถ้ามี)
        if (isset($_FILES['reference_files']) && $_FILES['reference_files']['error'][0] !== UPLOAD_ERR_NO_FILE) {
            $fileCount = count($_FILES['reference_files']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['reference_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = $_FILES['reference_files']['name'][$i];
                    $tmpName = $_FILES['reference_files']['tmp_name'][$i];
                    
                    // สร้างโฟลเดอร์ถ้ายังไม่มี
                    $uploadDir = "uploads/design_queue/$designId/";
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // เพิ่มเวลาปัจจุบันเข้าไปในชื่อไฟล์เพื่อป้องกันชื่อซ้ำ
                    $uniqueFileName = time() . '_' . $fileName;
                    $targetPath = $uploadDir . $uniqueFileName;
                    
                    // ย้ายไฟล์
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        // บันทึกข้อมูลไฟล์ลงฐานข้อมูล
                        $fileSql = "INSERT INTO design_files (design_id, file_name, file_path, file_type, uploaded_by) 
                                   VALUES ($designId, '$fileName', '$targetPath', 'reference', {$_SESSION['user_id']})";
                        $conn->query($fileSql);
                    }
                }
            }
        }
        
        // แสดงข้อความสำเร็จและเปลี่ยนเส้นทาง
        $_SESSION['success_message'] = "เพิ่มคิวออกแบบใหม่เรียบร้อยแล้ว (รหัสคิว: $queueCode)";
        header("Location: design_queue_list.php");
        exit();
    } else {
        $error_message = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มคิวออกแบบใหม่ - ระบบคิวออกแบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-tshirt me-2"></i> ระบบบริหารร้านเสื้อพิมพ์ลาย
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="design_queue_list.php">
                            <i class="fas fa-list-ul"></i> คิวออกแบบ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-shopping-cart"></i> คำสั่งซื้อ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-industry"></i> การผลิต
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-chart-line"></i> รายงาน
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['full_name']; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small"><?php echo ucfirst($_SESSION['role']); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog"></i> ตั้งค่าบัญชี</a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-2xl font-bold">เพิ่มคิวออกแบบใหม่</h1>
            <a href="design_queue_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="row">
                        <!-- ข้อมูลลูกค้า -->
                        <div class="col-md-6 mb-4">
                            <h3 class="text-lg font-semibold mb-3 border-bottom pb-2">ข้อมูลลูกค้า</h3>
                            
                            <div class="mb-3">
                                <label for="customer_name" class="form-label required">ชื่อลูกค้า/ทีม</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control" id="customer_phone" name="customer_phone">
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_contact" class="form-label">ช่องทางติดต่ออื่นๆ</label>
                                <input type="text" class="form-control" id="customer_contact" name="customer_contact" 
                                       placeholder="LINE ID, Facebook, Email, ฯลฯ">
                            </div>
                            
                            <div class="mb-3">
                                <label for="team_name" class="form-label">ชื่อทีม (ถ้ามี)</label>
                                <input type="text" class="form-control" id="team_name" name="team_name">
                            </div>
                        </div>

                        <!-- รายละเอียดการออกแบบ -->
                        <div class="col-md-6 mb-4">
                            <h3 class="text-lg font-semibold mb-3 border-bottom pb-2">รายละเอียดการออกแบบ</h3>
                            
                            <div class="mb-3">
                                <label for="design_type" class="form-label">ประเภทของการออกแบบ</label>
                                <select class="form-select" id="design_type" name="design_type">
                                    <option value="">-- เลือกประเภท --</option>
                                    <option value="เสื้อกีฬา">เสื้อกีฬา</option>
                                    <option value="เสื้อคลาส/รุ่น">เสื้อคลาส/รุ่น</option>
                                    <option value="เสื้อทีม">เสื้อทีม</option>
                                    <option value="เสื้อบริษัท/องค์กร">เสื้อบริษัท/องค์กร</option>
                                    <option value="เสื้อกิจกรรม">เสื้อกิจกรรม</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="shirt_color" class="form-label">สีเสื้อ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-palette"></i></span>
                                    <input type="text" class="form-control" id="shirt_color" name="shirt_color" 
                                           placeholder="เช่น ขาว, ดำ, น้ำเงินเข้ม">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deadline" class="form-label">วันที่ต้องการงาน</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" id="deadline" name="deadline" 
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="designer_id" class="form-label">มอบหมายให้ดีไซเนอร์</label>
                                <select class="form-select" id="designer_id" name="designer_id">
                                    <option value="">-- ยังไม่กำหนด --</option>
                                    <?php while ($designer = $designersResult->fetch_assoc()): ?>
                                        <option value="<?php echo $designer['user_id']; ?>">
                                            <?php echo $designer['full_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- รายละเอียดเพิ่มเติม -->
                        <div class="col-12 mb-4">
                            <h3 class="text-lg font-semibold mb-3 border-bottom pb-2">รายละเอียดเพิ่มเติม</h3>
                            
                            <div class="mb-3">
                                <label for="design_details" class="form-label required">รายละเอียดการออกแบบ</label>
                                <textarea class="form-control" id="design_details" name="design_details" rows="5" required
                                          placeholder="ระบุรายละเอียดการออกแบบ เช่น โลโก้, สี, ฟอนต์, ข้อความ, แนวคิดการออกแบบ ฯลฯ"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">บันทึกเพิ่มเติม</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                          placeholder="บันทึกเพิ่มเติมอื่นๆ ที่ต้องการแจ้งให้ทีมออกแบบทราบ"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reference_files" class="form-label">ไฟล์อ้างอิง (รูปภาพ, โลโก้, ฯลฯ)</label>
                                <input type="file" class="form-control" id="reference_files" name="reference_files[]" multiple>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> ไฟล์ที่อนุญาต: รูปภาพ (JPG, PNG, GIF), ไฟล์ PDF, AI, PSD, SVG, ไฟล์ ZIP ขนาดไม่เกิน 10MB ต่อไฟล์
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> บันทึกคิวออกแบบ
                        </button>
                        <a href="design_queue_list.php" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fas fa-times"></i> ยกเลิก
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ตรวจสอบการแสดงผลไฟล์ที่เลือก
        const fileInput = document.getElementById('reference_files');
        
        fileInput.addEventListener('change', function() {
            // ตรวจสอบว่ามีไฟล์ที่เลือกหรือไม่
            if (this.files.length > 0) {
                // แสดงจำนวนไฟล์ที่เลือก
                console.log(`เลือก ${this.files.length} ไฟล์`);
                
                // ตรวจสอบขนาดไฟล์
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const fileSize = file.size / 1024 / 1024; // แปลงเป็น MB
                    
                    if (fileSize > 10) {
                        alert(`ไฟล์ "${file.name}" มีขนาดใหญ่เกินไป (${fileSize.toFixed(2)} MB). ขนาดไฟล์สูงสุดที่อนุญาตคือ 10 MB.`);
                        this.value = ''; // ล้างการเลือกไฟล์
                        break;
                    }
                }
            }
        });
    });
    </script>
</body>
</html>