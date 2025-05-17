
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

// ดึงຂໍ້ມູນຄິວອອກແບບ
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

// ดึงไฟล์แนบทั้งหมด
$filesQuery = "SELECT df.*, u.full_name as uploader_name 
               FROM design_files df
               JOIN users u ON df.uploaded_by = u.user_id
               WHERE df.design_id = $designId 
               ORDER BY df.file_type, df.upload_date DESC";
$filesResult = $conn->query($filesQuery);

// ดึงประวัติการเปลี่ยนสถานะ
$historyQuery = "SELECT sh.*, u.full_name as user_name 
                FROM status_history sh
                JOIN users u ON sh.changed_by = u.user_id
                WHERE sh.design_id = $designId 
                ORDER BY sh.change_date DESC";
$historyResult = $conn->query($historyQuery);

// จัดกลุ่มไฟล์ตามประเภท
$files = [
    'reference' => [],
    'design' => [],
    'feedback' => [],
    'final' => []
];

while ($file = $filesResult->fetch_assoc()) {
    $files[$file['file_type']][] = $file;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ລາຍລະອຽດຄິວອອກແບບ - <?php echo $queue['queue_code']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <!-- Header ด้านบน -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">ລາຍລະອຽດຄິວອອກແບບ</h1>
                <h2 class="text-xl text-blue-600"><?php echo $queue['queue_code']; ?></h2>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn btn-success status-update-btn" 
                        data-bs-toggle="modal" 
                        data-bs-target="#updateStatusModal" 
                        data-id="<?php echo $designId; ?>"
                        data-status="<?php echo $queue['status']; ?>">
                    <i class="fas fa-arrow-right"></i> ອັບເດດສະຖານະ
                </button>
                <a href="edit_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> ແກ້ໄຂ
                </a>
                <a href="design_queue_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າລາຍການ
                </a>
                <!-- เพิ่มปุ่มนี้ในส่วนของเมนูดำเนินการ -->
                 

            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success mb-4">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger mb-4">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- ສະຖານະປະຈຸບັນ - แสดงแบบเด่นชัด -->
        <div class="card mb-4">
            <div class="card-body p-4 flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-sm mb-1">ສະຖານະປະຈຸບັນ</div>
                    <span class="badge bg-<?php echo getStatusColor($queue['status']); ?> p-2 text-lg">
                        <?php echo getStatusThai($queue['status']); ?>
                    </span>
                </div>
                <button type="button" class="btn btn-primary status-update-btn" 
                        data-bs-toggle="modal" 
                        data-bs-target="#updateStatusModal" 
                        data-id="<?php echo $designId; ?>"
                        data-status="<?php echo $queue['status']; ?>">
                    <i class="fas fa-arrow-right"></i> ອັບເດດສະຖານະ
                </button>
            </div>
        </div>
        
        <!-- การ์ดหลักและไซด์บาร์ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- คอลัมน์หลัก -->
            <div class="col-span-2">
                <!-- ข้อมูลทั่วไป -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold">ຂໍ້ມູນຄິວອອກແບບ</h2>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="mb-2"><span class="font-medium text-gray-600">ລະຫັດຄິວ:</span> <?php echo $queue['queue_code']; ?></p>
                                <p class="mb-2"><span class="font-medium text-gray-600">ຊື່ທີມລູກຄ້າ:</span> <?php echo htmlspecialchars($queue['customer_name']); ?></p>
                                <p class="mb-2"><span class="font-medium text-gray-600">ຕິດຕໍ່:</span> 
                                    <?php 
                                    echo $queue['customer_phone'] ? htmlspecialchars($queue['customer_phone']) : '-'; 
                                    echo $queue['customer_contact'] ? ' / ' . htmlspecialchars($queue['customer_contact']) : '';
                                    ?>
                                </p>
                                <p class="mb-2"><span class="font-medium text-gray-600">ທີມ:</span> <?php echo $queue['team_name'] ? htmlspecialchars($queue['team_name']) : '-'; ?></p>
                            </div>
                            <div>
                                <p class="mb-2"><span class="font-medium text-gray-600">ປະເພດເສື້ອ:</span> <?php echo $queue['design_type'] ? htmlspecialchars($queue['design_type']) : '-'; ?></p>
                                <p class="mb-2"><span class="font-medium text-gray-600">ສີເສື້ອ:</span> <?php echo $queue['shirt_color'] ? htmlspecialchars($queue['shirt_color']) : '-'; ?></p>
                                <p class="mb-2"><span class="font-medium text-gray-600">ວັນທີທີ່ຕ້ອງການ:</span> <?php echo $queue['deadline'] ? date('d/m/Y', strtotime($queue['deadline'])) : 'ไม่ระบุ'; ?></p>
                                <p class="mb-2"><span class="font-medium text-gray-600">ຜູ້ຮັບຜິດຊອບຄິວ:</span> <?php echo $queue['designer_name'] ? htmlspecialchars($queue['designer_name']) : 'ยังไม่กำหนด'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- รายละเอียดการออกแบบ -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold">ລາຍລະອຽດການອອກແບບ</h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h3 class="text-lg font-medium mb-2">ລາຍລະອຽດ:</h3>
                            <div class="bg-gray-50 p-3 rounded border">
                                <?php echo nl2br(htmlspecialchars($queue['design_details'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($queue['notes']): ?>
                        <div>
                            <h3 class="text-lg font-medium mb-2">ໝາຍເຫດເພີ່ມເຕີມ:</h3>
                            <div class="bg-gray-50 p-3 rounded border">
                                <?php echo nl2br(htmlspecialchars($queue['notes'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ไฟล์ทั้งหมด -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold">ໄຟລ໌ທັງມົດ</h2>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="fileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="reference-tab" data-bs-toggle="tab" data-bs-target="#reference" type="button" role="tab">
                                    <i class="fas fa-file-image text-success"></i> ໄຟລ໌ອ້າງອີກ <span class="badge bg-secondary rounded-pill"><?php echo count($files['reference']); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="design-tab" data-bs-toggle="tab" data-bs-target="#design" type="button" role="tab">
                                    <i class="fas fa-paint-brush text-primary"></i> ໄຟລ໌ອອກແບບ <span class="badge bg-secondary rounded-pill"><?php echo count($files['design']); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="feedback-tab" data-bs-toggle="tab" data-bs-target="#feedback" type="button" role="tab">
                                    <i class="fas fa-comments text-warning"></i> ຟີດແບັກຈາກລູກຄ້າ <span class="badge bg-secondary rounded-pill"><?php echo count($files['feedback']); ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="final-tab" data-bs-toggle="tab" data-bs-target="#final" type="button" role="tab">
                                    <i class="fas fa-check-circle text-success"></i> ໄຟລ໌ສຸດທ້າຍ <span class="badge bg-secondary rounded-pill"><?php echo count($files['final']); ?></span>
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="fileTabsContent">
                            <!-- แท็บไฟล์อ้างอิง -->
                            <div class="tab-pane fade show active" id="reference" role="tabpanel">
                                <?php if (count($files['reference']) > 0): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <?php foreach ($files['reference'] as $file): ?>
                                            <?php
                                            $fileExt = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                                            $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif']);
                                            ?>
                                            <div class="card">
                                                <?php if ($isImage): ?>
                                                    <img src="<?php echo $file['file_path']; ?>" class="card-img-top" alt="ຮູບຕົວຢ່າງ" style="height: 150px; object-fit: contain;">
                                                <?php else: ?>
                                                    <div class="card-img-top bg-light d-flex justify-content-center align-items-center" style="height: 150px;">
                                                        <i class="fas fa-file-alt fa-3x text-secondary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h5 class="card-title text-truncate"><?php echo htmlspecialchars($file['file_name']); ?></h5>
                                                    <p class="card-text small text-muted">ອັບໂຫລດສື່: <?php echo date('d/m/Y', strtotime($file['upload_date'])); ?></p>
                                                    <p class="card-text small text-muted">ໂດຍ: <?php echo htmlspecialchars($file['uploader_name']); ?></p>
                                                    <div class="d-flex gap-1">
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                            <i class="fas fa-eye"></i> ເບີ່ງໄຟລ໌
                                                        </a>
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="delete_file.php?id=<?php echo $file['file_id']; ?>&design_id=<?php echo $designId; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบไฟล์นี้?');">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> ຍັງບໍ່ມີໄຟລ໌ຕົວຢ່າງ
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadReferenceModal">
                                        <i class="fas fa-upload"></i> ອັບໂຫລດໄຟລ໌ຕົວຢ່າງ
                                    </button>
                                </div>
                            </div>
                            
                            <!-- แท็บไฟล์ออกแบบ -->
                            <div class="tab-pane fade" id="design" role="tabpanel">
                                <?php if (count($files['design']) > 0): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <?php foreach ($files['design'] as $file): ?>
                                            <?php
                                            $fileExt = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                                            $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif']);
                                            ?>
                                            <div class="card">
                                                <?php if ($isImage): ?>
                                                    <img src="<?php echo $file['file_path']; ?>" class="card-img-top" alt="ຮູບອອກແບບ" style="height: 150px; object-fit: contain;">
                                                <?php else: ?>
                                                    <div class="card-img-top bg-light d-flex justify-content-center align-items-center" style="height: 150px;">
                                                        <i class="fas fa-file-alt fa-3x text-primary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h5 class="card-title text-truncate"><?php echo htmlspecialchars($file['file_name']); ?></h5>
                                                    <p class="card-text small text-muted">ອັບໂຫລດເມື່ອ: <?php echo date('d/m/Y', strtotime($file['upload_date'])); ?></p>
                                                    <p class="card-text small text-muted">ໂດຍ: <?php echo htmlspecialchars($file['uploader_name']); ?></p>
                                                    <div class="d-flex gap-1">
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                            <i class="fas fa-eye"></i> ເບີ່ງໄຟລ໌
                                                        </a>
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="delete_file.php?id=<?php echo $file['file_id']; ?>&design_id=<?php echo $designId; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('ຕ້ອງການລົບໄຟລ໌ນີ້ ແທ້ບໍ່?');">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> ຍັງບໍ່ມີໄຟລ໌ແບບ
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDesignModal">
                                        <i class="fas fa-upload"></i> ອັບໂຫລດໄຟລ໌ອອກແບບໃໝ່
                                    </button>
                                </div>
                            </div>
                            
                            <!-- แท็บฟีดแบ็คຊື່ທີມລູກຄ້າ -->
                            <div class="tab-pane fade" id="feedback" role="tabpanel">
                                <?php if (count($files['feedback']) > 0): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <?php foreach ($files['feedback'] as $file): ?>
                                            <?php
                                            $fileExt = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                                            $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif']);
                                            ?>
                                            <div class="card">
                                                <?php if ($isImage): ?>
                                                    <img src="<?php echo $file['file_path']; ?>" class="card-img-top" alt="ฟีดแบ็คຊື່ທີມລູກຄ້າ" style="height: 150px; object-fit: contain;">
                                                <?php else: ?>
                                                    <div class="card-img-top bg-light d-flex justify-content-center align-items-center" style="height: 150px;">
                                                        <i class="fas fa-comment-alt fa-3x text-warning"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h5 class="card-title text-truncate"><?php echo htmlspecialchars($file['file_name']); ?></h5>
                                                    <p class="card-text small text-muted">ອັບໂຫລດເມື່ອ: <?php echo date('d/m/Y', strtotime($file['upload_date'])); ?></p>
                                                    <p class="card-text small text-muted">ໂດຍ: <?php echo htmlspecialchars($file['uploader_name']); ?></p>
                                                    <div class="d-flex gap-1">
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                            <i class="fas fa-eye"></i> ເບີ່ງໄຟລ໌
                                                        </a>
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="delete_file.php?id=<?php echo $file['file_id']; ?>&design_id=<?php echo $designId; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('ຕ້ອງການລົບໄຟລ໌ນີ້ ແທ້ບໍ່?');">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> ຍັງບໍ່ມີຟີດແບັກຈາກລູກຄ້າ
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#uploadFeedbackModal">
                                        <i class="fas fa-upload"></i> ອັບໂຫລດໄຟລ໌ຟີດແບັກຈາກລູກຄ້າ
                                    </button>
                                </div>
                            </div>
                            
                            <!-- แท็บไฟล์สุดท้าย -->
                            <div class="tab-pane fade" id="final" role="tabpanel">
                                <?php if (count($files['final']) > 0): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <?php foreach ($files['final'] as $file): ?>
                                            <?php
                                            $fileExt = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                                            $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif']);
                                            ?>
                                            <div class="card">
                                                <?php if ($isImage): ?>
                                                    <img src="<?php echo $file['file_path']; ?>" class="card-img-top" alt="ไฟล์สุดท้าย" style="height: 150px; object-fit: contain;">
                                                <?php else: ?>
                                                    <div class="card-img-top bg-light d-flex justify-content-center align-items-center" style="height: 150px;">
                                                        <i class="fas fa-file-archive fa-3x text-success"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h5 class="card-title text-truncate"><?php echo htmlspecialchars($file['file_name']); ?></h5>
                                                    <p class="card-text small text-muted">ອັບໂຫລດເມື່ອ: <?php echo date('d/m/Y', strtotime($file['upload_date'])); ?></p>
                                                    <p class="card-text small text-muted">ໂດຍ: <?php echo htmlspecialchars($file['uploader_name']); ?></p>
                                                    <div class="d-flex gap-1">
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                            <i class="fas fa-eye"></i> ເບີ່ງໄຟລ໌
                                                        </a>
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="delete_file.php?id=<?php echo $file['file_id']; ?>&design_id=<?php echo $designId; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('ຕ້ອງການລົບໄຟລ໌ນີ້ ແທ້ບໍ່?');">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> ຍັງບໍ່ມີໄຟລ໌ສຸດທ້າຍ
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadFinalModal">
                                        <i class="fas fa-upload"></i> ອັບໂຫລດໄຟລ໌ສຸດທ້າຍ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ไซด์บาร์ด้านขวา -->
            <div class="col-span-1">
                <!-- การดำเนินการ -->
               <!-- ในส่วนของ card "การดำเนินการ" -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h2 class="text-xl font-semibold">ການດຳເນີນການ</h2>
    </div>
    <div class="card-body">
        <div class="d-grid gap-2">
            <!-- ปุ่มที่มีอยู่แล้ว -->
            <a href="edit_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> ແກ້ໄຂລາຍລະອຽດ
            </a>
            
            <!-- เพิ่มปุ่มนี้สำหรับสร้างใบเสนอราคา -->
            <a href="add_invoice.php?design_id=<?php echo $designId; ?>" class="btn btn-info">
                <i class="fas fa-file-invoice"></i> ສ້າງໃບສະເໜີລາຄາ
            </a>
            
            <?php if ($queue['status'] === 'approved'): ?>
                <a href="prepare_production.php?id=<?php echo $designId; ?>" class="btn btn-success">
                    <i class="fas fa-industry"></i> ກຽມສົ່ງຜະລິດ
                </a>
            <?php endif; ?>
            
            <!-- การดำเนินการทั่วไป -->
            <button type="button" class="btn btn-outline-primary status-update-btn" 
                    data-bs-toggle="modal" 
                    data-bs-target="#updateStatusModal" 
                    data-id="<?php echo $designId; ?>"
                    data-status="<?php echo $queue['status']; ?>">
                <i class="fas fa-arrow-right"></i> ອັບເດດສະຖານະ
            </button>
        </div>
    </div>
</div>

                <!-- ประวัติการเปลี่ยนสถานะ -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold">ປະຫວັດປ່ຽນສະຖານະ</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="timeline p-3">
                            <?php if ($historyResult->num_rows > 0): ?>
                                <?php while ($history = $historyResult->fetch_assoc()): ?>
                                    <div class="timeline-item pb-3 mb-3 border-bottom">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-primary fw-bold">
                                                <?php 
                                                if ($history['old_status']) {
                                                    echo getStatusThai($history['old_status']) . ' <i class="fas fa-arrow-right"></i> ' . getStatusThai($history['new_status']);
                                                } else {
                                                    echo getStatusThai($history['new_status']);
                                                }
                                                ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($history['change_date'])); ?></small>
                                        </div>
                                        <div>
                                            <?php if ($history['comment']): ?>
                                                <p class="mb-1"><?php echo htmlspecialchars($history['comment']); ?></p>
                                            <?php endif; ?>
                                            <small class="text-muted">ໂດຍ: <?php echo htmlspecialchars($history['user_name']); ?></small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-center py-3">ຍັງບໍ່ມີປະຫວັດປ່ຽນສະຖານະ</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลเพิ่มเติม -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold">ຂໍ້ມູນເພີ່ມເຕີມ</h2>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><span class="font-medium text-gray-600">ວັນທີສ້າງ:</span> <?php echo date('d/m/Y H:i', strtotime($queue['created_at'])); ?></p>
                        <p class="mb-2"><span class="font-medium text-gray-600">ອັບເດດລ່າສຸດ:</span> <?php echo date('d/m/Y H:i', strtotime($queue['updated_at'])); ?></p>
                        
                        <?php if ($queue['deadline']): ?>
                            <?php
                            $deadline = new DateTime($queue['deadline']);
                            $today = new DateTime();
                            $interval = $today->diff($deadline);
                            $isPast = $today > $deadline;
                            $daysRemaining = $interval->days;
                            ?>
                            
                            <div class="mt-3 p-3 rounded 
                                <?php echo $isPast ? 'bg-danger text-white' : ($daysRemaining <= 3 ? 'bg-warning' : 'bg-info text-white'); ?>">
                                <h4 class="mb-2">
                                    <?php if ($isPast): ?>
                                        <i class="fas fa-exclamation-triangle"></i> ກາຍກຳນົດສົ່ງ
                                    <?php else: ?>
                                        <i class="fas fa-clock"></i> ກຳນົດສົ່ງ
                                    <?php endif; ?>
                                </h4>
                                
                                <p class="mb-0">
                                    <?php echo date('d/m/Y', strtotime($queue['deadline'])); ?>
                                    <br>
                                    <?php if ($isPast): ?>
                                        (ກາຍມາ <?php echo $daysRemaining; ?> ວັນ)
                                    <?php else: ?>
                                        (ອີກ <?php echo $daysRemaining; ?> ວັນ)
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ອັບເດດສະຖານະ -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ອັບເດດສະຖານະຄິວອອກແບບ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update_status.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="design_id" id="modal_design_id" value="<?php echo $designId; ?>">
                        <div class="mb-3">
                            <label class="form-label">ສະຖານະປະຈຸບັນ:</label>
                            <span class="badge bg-<?php echo getStatusColor($queue['status']); ?>">
                                <?php echo getStatusThai($queue['status']); ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ປ່ຽນສະຖານະເປັນ:</label>
                            <select name="new_status" id="new_status" class="form-select" required>
                                <!-- ตัวเลือกจะถูกเติมด้วย JavaScript -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ໝາຍເຫດ (ຖ້າມີ):</label>
                            <textarea name="comment" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-primary">ບັນທຶກ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal อัปโหลดไฟล์ประเภทต่างๆ -->
    <?php
    $fileTypes = [
        'reference' => ['title' => 'ໄຟລ໌ຕົວຢ່າງ', 'color' => 'success'],
        'design' => ['title' => 'ໄຟລ໌ອອກແບບ', 'color' => 'primary'],
        'feedback' => ['title' => 'ຟີດແບັກຈາກລູກຄ້າ', 'color' => 'warning'],
        'final' => ['title' => 'ໄຟລ໌ສຸດທ້າຍ', 'color' => 'success']
    ];
    
    foreach ($fileTypes as $type => $info): 
    ?>
    <div class="modal fade" id="upload<?php echo ucfirst($type); ?>Modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ອັບໂຫລດ<?php echo $info['title']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="upload_handler.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="design_id" value="<?php echo $designId; ?>">
                        <input type="hidden" name="file_type" value="<?php echo $type; ?>">
                        
                        <div class="mb-3">
                            <label for="<?php echo $type; ?>_files" class="form-label">ເລືອກໄຟລ໌:</label>
                            <input type="file" class="form-control" id="<?php echo $type; ?>_files" name="files[]" multiple required>
                            <div class="form-text">ໄຟລ໌ທີ່ອະນຸຍາດ: ຮູບພາບ (JPG, PNG, GIF), ໄຟລ໌ PDF, ໄຟລ໌ ZIP ສູງສຸດ 10MB ຕໍ່ໄຟລ໌.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ໝາຍເຫດ (ຖ້າມີ):</label>
                            <textarea name="note" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-<?php echo $info['color']; ?>">ອັບໂຫລດ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // การทำงานของ Modal ອັບເດດສະຖານະ
        const statusButtons = document.querySelectorAll('.status-update-btn');
        
        statusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const designId = this.getAttribute('data-id');
                const currentStatus = this.getAttribute('data-status');
                
                document.getElementById('modal_design_id').value = designId;
                
                // เคลียร์ตัวเลือกเก่า
                const statusSelect = document.getElementById('new_status');
                statusSelect.innerHTML = '';
                
                // กำหนดตัวเลือกสถานะตามลำดับขั้นตอน
                const nextStatuses = getNextStatuses(currentStatus);
                
                nextStatuses.forEach(status => {
                    const option = document.createElement('option');
                    option.value = status.value;
                    option.textContent = status.label;
                    statusSelect.appendChild(option);
                });
            });
        });
        
        // ฟังก์ชันสำหรับกำหนดสถานะถัดไปที่เป็นไปได้
        function getNextStatuses(currentStatus) {
            switch(currentStatus) {
                case 'pending':
                    return [
                        { value: 'in_progress', label: 'ກຳລັງອອກແບບ' }
                    ];
                case 'in_progress':
                    return [
                        { value: 'customer_review', label: 'ສົ່ງໃຫ້ລູກຄ້າກວດແບບ' }
                    ];
                case 'customer_review':
                    return [
                        { value: 'revision', label: 'ລູກຄ້າຂໍແກ້ໄຂ' },
                        { value: 'approved', label: 'ລູກຄ້າຄອນເຟີມແບບແລ້ວ' }
                    ];
                case 'revision':
                    return [
                        { value: 'in_progress', label: 'ກຳລັງແກ້ແບບ (ແກ້ໄຂ)' },
                        { value: 'customer_review', label: 'ສົ່ງໃຫ້ລູກຄ້າກວດແບບຄືນ' }
                    ];
                case 'approved':
                    return [
                        { value: 'revision', label: 'ລູກຄ້າຂໍແກ້ໄຂໄຫມ່' },
                        { value: 'production', label: 'ສົ່ງໄປຍັງຂັ້ນຕອນຜະລິດ' }
                    ];
                case 'production':
                    return [
                        { value: 'approved', label: 'ຍ້ອນກັບໄປສະຖານະອະນຸມັດແລ້ວ' },
                        { value: 'completed', label: 'ສຳເລັດ' }
                    ];
                default:
                    return [
                        { value: 'pending', label: 'ຖ້າອອກແບບ' },
                        { value: 'in_progress', label: 'ກຳລັງອອກແບບ' },
                        { value: 'customer_review', label: 'ສົ່ງໃຫ້ລູກຄ້າກວດແບບ' },
                        { value: 'revision', label: 'ລູກຄ້າຂໍແກ້ໄຂ' },
                        { value: 'approved', label: 'ລູກຄ້າຄອນເຟີມແບບແລ້ວ' },
                        { value: 'production', label: 'ສົ່ງໄປຍັງຂັ້ນຕອນຜະລິດ' }
                    ];
            }
        }
    });
    </script>
</body>
</html>