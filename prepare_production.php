<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: design_queue_list.php");
    exit();
}

$designId = intval($_GET['id']);

// ดึงข้อมูลคิวออกแบบ
$sql = "SELECT dq.*, u.full_name as designer_name 
        FROM design_queue dq 
        LEFT JOIN users u ON dq.designer_id = u.user_id 
        WHERE dq.design_id = $designId";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header("Location: design_queue_list.php");
    exit();
}

$queue = $result->fetch_assoc();

// ตรวจสอบว่าสถานะเป็น approved หรือไม่
if ($queue['status'] !== 'approved') {
    $_SESSION['error_message'] = "ไม่สามารถส่งผลิตได้เนื่องจากยังไม่ได้รับการอนุมัติจากลูกค้า";
    header("Location: view_design_queue.php?id=$designId");
    exit();
}

// ดึงไฟล์แนบสุดท้าย
$filesQuery = "SELECT * FROM design_files WHERE design_id = $designId AND file_type = 'final' ORDER BY upload_date DESC";
$filesResult = $conn->query($filesQuery);

// ดึงรายชื่อโรงงานคู่ค้า (สำหรับเชื่อมต่อกับระบบอื่นในอนาคต)
// ในตัวอย่างนี้เราจะสร้างข้อมูลจำลอง
$factories = [
    ['id' => 1, 'name' => 'โรงงาน A', 'contact' => 'คุณสมชาย 081-234-5678'],
    ['id' => 2, 'name' => 'โรงงาน B', 'contact' => 'คุณสมหญิง 089-876-5432'],
    ['id' => 3, 'name' => 'โรงงาน C', 'contact' => 'คุณใจดี 063-456-7890']
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เตรียมส่งผลิต - <?php echo $queue['queue_code']; ?></title>
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
        <div class="d-flex justify-between align-items-center mb-4">
            <div>
                <h1 class="text-2xl font-bold mb-1">เตรียมส่งผลิต</h1>
                <h2 class="text-xl text-primary"><?php echo $queue['queue_code']; ?></h2>
            </div>
            <div>
                <a href="view_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้ารายละเอียด
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> 
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white">
                <h2 class="text-xl font-semibold mb-0">
                    <i class="fas fa-info-circle me-2"></i> ข้อมูลคิวออกแบบ
                </h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <h5 class="text-muted mb-2">ข้อมูลลูกค้า</h5>
                        <p><strong>รหัสคิว:</strong> <?php echo $queue['queue_code']; ?></p>
                        <p><strong>ลูกค้า:</strong> <?php echo htmlspecialchars($queue['customer_name']); ?></p>
                        <p><strong>ทีม:</strong> <?php echo $queue['team_name'] ? htmlspecialchars($queue['team_name']) : '-'; ?></p>
                        <p><strong>ติดต่อ:</strong> 
                            <?php
                            if ($queue['customer_phone']) {
                                echo '<i class="fas fa-phone-alt me-1"></i> ' . htmlspecialchars($queue['customer_phone']);
                            }
                            if ($queue['customer_contact']) {
                                echo ' / ' . htmlspecialchars($queue['customer_contact']);
                            }
                            if (!$queue['customer_phone'] && !$queue['customer_contact']) {
                                echo '-';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h5 class="text-muted mb-2">รายละเอียดงาน</h5>
                        <p><strong>ประเภท:</strong> <?php echo $queue['design_type'] ? htmlspecialchars($queue['design_type']) : '-'; ?></p>
                        <p><strong>สีเสื้อ:</strong> <?php echo $queue['shirt_color'] ? htmlspecialchars($queue['shirt_color']) : '-'; ?></p>
                        <p><strong>ดีไซเนอร์:</strong> <?php echo $queue['designer_name'] ? htmlspecialchars($queue['designer_name']) : '-'; ?></p>
                        <p><strong>สถานะ:</strong> <span class="badge bg-success">ลูกค้าอนุมัติแล้ว</span></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h5 class="text-muted mb-2">กำหนดเวลา</h5>
                        <p><strong>วันที่สร้าง:</strong> <?php echo date('d/m/Y', strtotime($queue['created_at'])); ?></p>
                        <p><strong>อัปเดตล่าสุด:</strong> <?php echo date('d/m/Y H:i', strtotime($queue['updated_at'])); ?></p>
                        
                        <?php if ($queue['deadline']): ?>
                            <?php
                            $deadline = new DateTime($queue['deadline']);
                            $today = new DateTime();
                            $interval = $today->diff($deadline);
                            $isPast = $today > $deadline;
                            $daysRemaining = $interval->days;
                            ?>
                            
                            <div class="mt-2 p-2 rounded 
                                <?php echo $isPast ? 'bg-danger text-white' : ($daysRemaining <= 3 ? 'bg-warning' : 'bg-info text-white'); ?>">
                                <strong>
                                    <?php if ($isPast): ?>
                                        <i class="fas fa-exclamation-triangle me-1"></i> เลยกำหนดส่งมอบ:
                                    <?php else: ?>
                                        <i class="fas fa-clock me-1"></i> กำหนดส่งมอบ:
                                    <?php endif; ?>
                                </strong>
                                
                                <?php echo date('d/m/Y', strtotime($queue['deadline'])); ?>
                                <br>
                                <?php if ($isPast): ?>
                                    (เลยมา <?php echo $daysRemaining; ?> วัน)
                                <?php else: ?>
                                    (อีก <?php echo $daysRemaining; ?> วัน)
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p><strong>วันที่ต้องการ:</strong> <span class="text-muted">ไม่ระบุ</span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- ไฟล์สุดท้ายสำหรับส่งผลิต -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-file-archive me-2 text-success"></i> ไฟล์สุดท้ายสำหรับส่งผลิต
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if ($filesResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ชื่อไฟล์</th>
                                            <th class="text-center">ประเภท</th>
                                            <th>วันที่อัปโหลด</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($file = $filesResult->fetch_assoc()): ?>
                                            <?php
                                            $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                            $fileIcon = 'file-alt';
                                            $fileType = 'ไฟล์';
                                            
                                            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                $fileIcon = 'file-image';
                                                $fileType = 'รูปภาพ';
                                            } elseif ($fileExt === 'pdf') {
                                                $fileIcon = 'file-pdf';
                                                $fileType = 'PDF';
                                            } elseif (in_array($fileExt, ['ai', 'psd'])) {
                                                $fileIcon = 'file-image';
                                                $fileType = strtoupper($fileExt);
                                            } elseif ($fileExt === 'svg') {
                                                $fileIcon = 'file-code';
                                                $fileType = 'SVG';
                                            } elseif ($fileExt === 'zip') {
                                                $fileIcon = 'file-archive';
                                                $fileType = 'ZIP';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-<?php echo $fileIcon; ?> text-secondary me-2"></i>
                                                    <?php echo htmlspecialchars($file['file_name']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?php echo $fileType; ?></span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($file['upload_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-info" target="_blank" title="ดูไฟล์">
                                                            <i class="fas fa-eye"></i> ดู
                                                        </a>
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download title="ดาวน์โหลด">
                                                            <i class="fas fa-download"></i> ดาวน์โหลด
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> 
                                <strong>ยังไม่มีไฟล์สุดท้ายสำหรับส่งผลิต</strong> กรุณาอัปโหลดไฟล์ก่อนส่งผลิต
                            </div>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadFinalModal">
                                <i class="fas fa-upload me-1"></i> อัปโหลดไฟล์สุดท้าย
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ฟอร์มส่งผลิต -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-industry me-2"></i> ส่งงานไปยังโรงงานผลิต
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if ($filesResult->num_rows === 0): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> 
                                <strong>ไม่สามารถส่งผลิตได้</strong> กรุณาอัปโหลดไฟล์สุดท้ายก่อนส่งผลิต
                            </div>
                        <?php else: ?>
                            <form action="send_to_production.php" method="post">
                                <input type="hidden" name="design_id" value="<?php echo $designId; ?>">
                                
                                <div class="mb-3">
                                    <label for="factory_id" class="form-label required">เลือกโรงงานผลิต</label>
                                    <select class="form-select" id="factory_id" name="factory_id" required>
                                        <option value="">-- เลือกโรงงาน --</option>
                                        <?php foreach ($factories as $factory): ?>
                                            <option value="<?php echo $factory['id']; ?>">
                                                <?php echo htmlspecialchars($factory['name']); ?> 
                                                (<?php echo htmlspecialchars($factory['contact']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="product_details" class="form-label required">รายละเอียดการผลิต</label>
                                    <textarea class="form-control" id="product_details" name="product_details" rows="4" required
                                            placeholder="ระบุจำนวน, ไซซ์, รายละเอียดการผลิตอื่นๆ เช่น S-20 ตัว, M-30 ตัว, L-20 ตัว"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="expected_completion_date" class="form-label required">วันที่คาดว่าจะเสร็จ</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                            <input type="date" class="form-control" id="expected_completion_date" 
                                                name="expected_completion_date" min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="production_cost" class="form-label required">ต้นทุนการผลิต (บาท)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-money-bill-alt"></i></span>
                                            <input type="number" class="form-control" id="production_cost" 
                                                name="production_cost" min="0" step="0.01" required>
                                            <span class="input-group-text">บาท</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="production_notes" class="form-label">หมายเหตุเพิ่มเติม</label>
                                    <textarea class="form-control" id="production_notes" name="production_notes" rows="3"
                                            placeholder="ข้อมูลเพิ่มเติมที่ต้องการแจ้งให้โรงงานทราบ"></textarea>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="confirm_files" name="confirm_files" required>
                                    <label class="form-check-label" for="confirm_files">
                                        <strong>ยืนยันว่าได้ตรวจสอบไฟล์สุดท้ายและพร้อมสำหรับการผลิตแล้ว</strong>
                                    </label>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-lg btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> ส่งไปยังโรงงานผลิต
                                    </button>
                                    <a href="view_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-lg btn-outline-secondary ms-2">
                                        <i class="fas fa-times me-1"></i> ยกเลิก
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> หมายเหตุ: เมื่อส่งงานไปยังโรงงานผลิตแล้ว ระบบจะสร้างรายการในระบบติดตามการผลิตโดยอัตโนมัติ
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- รายละเอียดงาน -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-tasks me-2 text-primary"></i> รายละเอียดงาน
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-2">รายละเอียดการออกแบบ</h5>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($queue['design_details'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($queue['notes']): ?>
                            <div>
                                <h5 class="border-bottom pb-2 mb-2">บันทึกเพิ่มเติม</h5>
                                <div class="bg-light p-3 rounded">
                                    <?php echo nl2br(htmlspecialchars($queue['notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ข้อมูลโรงงาน -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-building me-2 text-secondary"></i> ข้อมูลโรงงานคู่ค้า
                        </h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($factories as $factory): ?>
                                <div class="list-group-item">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($factory['name']); ?></h5>
                                    <p class="mb-1"><i class="fas fa-phone-alt me-1 text-primary"></i> <?php echo htmlspecialchars($factory['contact']); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i> เวลาผลิตเฉลี่ย: 5-7 วัน
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal อัปโหลดไฟล์สุดท้าย -->
    <div class="modal fade" id="uploadFinalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">อัปโหลดไฟล์สุดท้าย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="upload_handler.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="design_id" value="<?php echo $designId; ?>">
                        <input type="hidden" name="file_type" value="final">
                        
                        <div class="mb-3">
                            <label for="final_files" class="form-label">เลือกไฟล์:</label>
                            <input type="file" class="form-control" id="final_files" name="files[]" multiple required>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> ไฟล์ที่อนุญาต: รูปภาพ (JPG, PNG, GIF), ไฟล์ PDF, AI, PSD, SVG, ไฟล์ ZIP ขนาดไม่เกิน 10MB ต่อไฟล์
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ (ถ้ามี):</label>
                            <textarea name="note" class="form-control" rows="3" 
                                      placeholder="บันทึกเพิ่มเติมเกี่ยวกับไฟล์ที่อัปโหลด"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-1"></i> อัปโหลด
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>