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
    header("Location: production_list.php");
    exit();
}

$productionId = intval($_GET['id']);

// ดึงข้อมูลการผลิต
$sql = "SELECT po.*, dq.queue_code, dq.customer_name, dq.customer_phone, 
               dq.customer_contact, dq.team_name, dq.design_details, dq.deadline,
               u.full_name as designer_name
        FROM production_orders po 
        LEFT JOIN design_queue dq ON po.design_id = dq.design_id
        LEFT JOIN users u ON dq.designer_id = u.user_id
        WHERE po.production_id = $productionId";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header("Location: production_list.php");
    exit();
}

$production = $result->fetch_assoc();

// ดึงประวัติการเปลี่ยนสถานะการผลิต
$historyQuery = "SELECT psh.*, u.full_name as user_name 
                FROM production_status_history psh
                JOIN users u ON psh.changed_by = u.user_id
                WHERE psh.production_id = $productionId 
                ORDER BY psh.change_date DESC";
$historyResult = $conn->query($historyQuery);

// ดึงรูปภาพสินค้า
$imagesQuery = "SELECT pi.*, u.full_name as uploader_name 
               FROM production_images pi
               JOIN users u ON pi.uploaded_by = u.user_id
               WHERE pi.production_id = $productionId 
               ORDER BY pi.upload_date DESC";
$imagesResult = $conn->query($imagesQuery);

// ดึงไฟล์ออกแบบสุดท้าย
$designFilesQuery = "SELECT df.*, u.full_name as uploader_name 
                    FROM design_files df
                    JOIN users u ON df.uploaded_by = u.user_id
                    WHERE df.design_id = {$production['design_id']} AND df.file_type = 'final'
                    ORDER BY df.upload_date DESC";
$designFilesResult = $conn->query($designFilesQuery);

// ข้อมูลโรงงาน (จำลอง)
$factories = [
    1 => ['name' => 'Life Football', 'contact' => 'ອ້າຍໜິງ +856 20 96 299 953', 'address' => 'ວຽງຈັນ'],
    2 => ['name' => 'ໂຮງງານ B', 'contact' => 'ຄຸນສົມຫຍິງ 089-876-5432', 'address' => 'ສະຫວັນນະເຂດ'], 
    3 => ['name' => 'ໂຮງງານ C', 'contact' => 'ຄຸນໃຈດີ 063-456-7890', 'address' => 'ຈຳປາສັກ']
];

// ฟังก์ชันแปลงสถานะ
function getProductionStatusThai($status) {
    $statusMap = [
        'pending' => 'ຖ້າສົ່ງໂຮງງານ',
        'sent' => 'ສົ່ງໄຟລ໌ແລ້ວ',
        'in_progress' => 'ໂຮງງານກຳລັງຜະລິດ',
        'ready_pickup' => 'ພ້ອມມາຮັບ',
        'received' => 'ຮັບຂອງຈາກໂຮງງານແລ້ວ',
        'delivered' => 'ສົ່ງໃຫ້ລູກຄ້າແລ້ວ',
        'cancelled' => 'ຍົກເລີກ'
    ];
    
    return isset($statusMap[$status]) ? $statusMap[$status] : $status;
}

function getProductionStatusColor($status) {
    $colorMap = [
        'pending' => 'secondary',
        'sent' => 'info',
        'in_progress' => 'primary',
        'ready_pickup' => 'success',
        'received' => 'success',
        'delivered' => 'dark',
        'cancelled' => 'danger'
    ];
    
    return isset($colorMap[$status]) ? $colorMap[$status] : 'secondary';
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ລາຍລະອຽດການຜະລິດ - <?php echo $production['queue_code']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        @font-face {
            font-family: 'Saysettha OT';
            src: url('assets/fonts/saysettha-ot.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body, h1, h2, h3, h4, h5, h6, p, a, button, input, textarea, select, option, label, span, div {
            font-family: 'Saysettha OT', sans-serif !important;
        }

        .progress-timeline {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            position: relative;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .progress-step::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            z-index: 1;
        }

        .progress-step:last-child::before {
            display: none;
        }

        .progress-step.active::before {
            background-color: #28a745;
        }

        .progress-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #dee2e6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
            color: white;
            font-size: 14px;
        }

        .progress-step.active .progress-icon {
            background-color: #28a745;
        }

        .progress-step.current .progress-icon {
            background-color: #007bff;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
        }

        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .image-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .image-item:hover img {
            transform: scale(1.05);
        }

        .overdue-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #e17055;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include_once 'navbar.php'; ?>

    <div class="container my-4">
        <!-- Header ด้านบน -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-2xl font-bold">ລາຍລະອຽດການຜະລິດ</h1>
                <h2 class="text-xl text-primary"><?php echo $production['queue_code']; ?> (ID: <?php echo $production['production_id']; ?>)</h2>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success update-production-status-btn" 
                        data-bs-toggle="modal" 
                        data-bs-target="#updateProductionStatusModal" 
                        data-id="<?php echo $productionId; ?>"
                        data-status="<?php echo $production['status']; ?>">
                    <i class="fas fa-arrow-right"></i> ອັບເດດສະຖານະ
                </button>
                <a href="view_design_queue.php?id=<?php echo $production['design_id']; ?>" class="btn btn-info">
                    <i class="fas fa-paint-brush"></i> ເບີ່ງຄິວອອກແບບ
                </a>
                <a href="production_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າລາຍການ
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

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

        <!-- สถานะปัจจุบันและ Timeline -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">ສະຖານະການຜະລິດປະຈຸບັນ</h3>
                    <span class="badge bg-<?php echo getProductionStatusColor($production['status']); ?> fs-6">
                        <?php echo getProductionStatusThai($production['status']); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <!-- Progress Timeline -->
                <div class="progress-timeline">
                    <?php
                    $statusOrder = ['pending', 'sent', 'in_progress', 'ready_pickup', 'received', 'delivered'];
                    $currentIndex = array_search($production['status'], $statusOrder);
                    
                    $statusIcons = [
                        'pending' => 'clock',
                        'sent' => 'paper-plane',
                        'in_progress' => 'cogs',
                        'ready_pickup' => 'box',
                        'received' => 'truck',
                        'delivered' => 'check'
                    ];
                    
                    $statusLabels = [
                        'pending' => 'ຖ້າສົ່ງ',
                        'sent' => 'ສົ່ງແລ້ວ',
                        'in_progress' => 'ກຳລັງຜະລິດ',
                        'ready_pickup' => 'ພ້ອມຮັບ',
                        'received' => 'ຮັບແລ້ວ',
                        'delivered' => 'ສົ່ງລູກຄ້າ'
                    ];
                    
                    foreach ($statusOrder as $index => $status):
                        $isActive = $index < $currentIndex;
                        $isCurrent = $index === $currentIndex;
                        $class = $isActive ? 'active' : ($isCurrent ? 'current' : '');
                    ?>
                        <div class="progress-step <?php echo $class; ?>">
                            <div class="progress-icon">
                                <i class="fas fa-<?php echo $statusIcons[$status]; ?>"></i>
                            </div>
                            <div><small><?php echo $statusLabels[$status]; ?></small></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php
                // ตรวจสอบการเกินกำหนด
                $today = new DateTime();
                $expectedDate = new DateTime($production['expected_completion_date']);
                $isOverdue = $today > $expectedDate && !in_array($production['status'], ['delivered']);
                ?>

                <?php if ($isOverdue): ?>
                    <div class="alert alert-warning overdue-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>ເກີນກຳນົດການຜະລິດ!</strong> 
                        ກຳນົດເສັດແມ່ນວັນທີ <?php echo date('d/m/Y', strtotime($production['expected_completion_date'])); ?>
                        (ກາຍມາແລ້ວ <?php echo $today->diff($expectedDate)->days; ?> ວັນ)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- คอลัมน์หลัก -->
            <div class="col-md-8">
                <!-- ข้อมูลการผลิต -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h3 class="mb-0">ຂໍ້ມູນການຜະລິດ</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">ຂໍ້ມູນພື້ນຖານ</h5>
                                <p><strong>ລະຫັດການຜະລິດ:</strong> <?php echo $production['production_id']; ?></p>
                                <p><strong>ລະຫັດຄິວອອກແບບ:</strong> 
                                    <a href="view_design_queue.php?id=<?php echo $production['design_id']; ?>" class="text-primary">
                                        <?php echo $production['queue_code']; ?>
                                    </a>
                                </p>
                                <p><strong>ລູກຄ້າ:</strong> <?php echo htmlspecialchars($production['customer_name']); ?></p>
                                <p><strong>ທີມ:</strong> <?php echo $production['team_name'] ? htmlspecialchars($production['team_name']) : '-'; ?></p>
                                <p><strong>ຜູ້ອອກແບບ:</strong> <?php echo $production['designer_name'] ? htmlspecialchars($production['designer_name']) : '-'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">ຂໍ້ມູນໂຮງງານ</h5>
                                <?php $factory = isset($factories[$production['factory_id']]) ? $factories[$production['factory_id']] : null; ?>
                                <?php if ($factory): ?>
                                    <p><strong>ໂຮງງານ:</strong> <?php echo $factory['name']; ?></p>
                                    <p><strong>ຕິດຕໍ່:</strong> <?php echo $factory['contact']; ?></p>
                                    <p><strong>ທີ່ຕັ້ງ:</strong> <?php echo $factory['address']; ?></p>
                                <?php else: ?>
                                    <p><strong>ໂຮງງານ:</strong> <span class="text-muted">ບໍ່ຮູ້ຈັກ (ID: <?php echo $production['factory_id']; ?>)</span></p>
                                <?php endif; ?>
                                <p><strong>ຄ່າຜະລິດ:</strong> <?php echo number_format($production['production_cost']); ?> ₭</p>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">ລາຍລະອຽດການຜະລິດ</h5>
                                <div class="bg-light p-3 rounded">
                                    <?php echo nl2br(htmlspecialchars($production['product_details'])); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($production['production_notes']): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2 mb-3">ໝາຍເຫດເພີ່ມເຕີມ</h5>
                                    <div class="bg-light p-3 rounded">
                                        <?php echo nl2br(htmlspecialchars($production['production_notes'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ไฟล์ออกแบบสุดท้าย -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h3 class="mb-0">ໄຟລ໌ອອກແບບສຸດທ້າຍ</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($designFilesResult->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($file = $designFilesResult->fetch_assoc()): ?>
                                    <?php
                                    $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                    $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']);
                                    ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card">
                                            <?php if ($isImage): ?>
                                                <img src="<?php echo $file['file_path']; ?>" class="card-img-top" alt="ໄຟລ໌ອອກແບບ" style="height: 150px; object-fit: contain;">
                                            <?php else: ?>
                                                <div class="card-img-top bg-light d-flex justify-content-center align-items-center" style="height: 150px;">
                                                    <i class="fas fa-file-alt fa-3x text-secondary"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h6 class="card-title text-truncate"><?php echo htmlspecialchars($file['file_name']); ?></h6>
                                                <small class="text-muted">ອັບໂຫລດເມື່ອ: <?php echo date('d/m/Y', strtotime($file['upload_date'])); ?></small>
                                                <div class="d-flex gap-1 mt-2">
                                                    <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download>
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ຍັງບໍ່ມີໄຟລ໌ອອກແບບສຸດທ້າຍ
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- รูปภาพสินค้าจริง -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">ຮູບພາບສິນຄ້າຈິງ</h3>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#uploadImageModal">
                            <i class="fas fa-camera"></i> ອັບໂຫລດຮູບ
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if ($imagesResult->num_rows > 0): ?>
                            <div class="image-gallery">
                                <?php while ($image = $imagesResult->fetch_assoc()): ?>
                                    <div class="image-item">
                                        <img src="<?php echo $image['image_path']; ?>" 
                                             alt="ຮູບສິນຄ້າ" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#imageModal" 
                                             data-image="<?php echo $image['image_path']; ?>"
                                             data-name="<?php echo htmlspecialchars($image['image_name']); ?>">
                                        <div class="p-2">
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($image['image_name']); ?></small>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($image['upload_date'])); ?></small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                ຍັງບໍ່ມີຮູບພາບສິນຄ້າ
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ไซด์บาร์ด้านขวา -->
            <div class="col-md-4">
                <!-- การดำเนินการ -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">ການດຳເນີນການ</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success update-production-status-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#updateProductionStatusModal" 
                                    data-id="<?php echo $productionId; ?>"
                                    data-status="<?php echo $production['status']; ?>">
                                <i class="fas fa-arrow-right"></i> ອັບເດດສະຖານະ
                            </button>
                            
                            <a href="view_design_queue.php?id=<?php echo $production['design_id']; ?>" class="btn btn-outline-info">
                                <i class="fas fa-paint-brush"></i> ເບີ່ງຄິວອອກແບບ
                            </a>
                            
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadImageModal">
                                <i class="fas fa-camera"></i> ອັບໂຫລດຮູບສິນຄ້າ
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลเวลา -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h3 class="mb-0">ຂໍ້ມູນເວລາ</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>ວັນທີສ້າງ:</strong><br><?php echo date('d/m/Y H:i', strtotime($production['created_at'])); ?></p>
                        <p><strong>ອັບເດດລ່າສຸດ:</strong><br><?php echo date('d/m/Y H:i', strtotime($production['updated_at'])); ?></p>
                        <p><strong>ກຳນົດເສັດ:</strong><br><?php echo date('d/m/Y', strtotime($production['expected_completion_date'])); ?></p>
                        
                        <?php if ($production['actual_completion_date']): ?>
                            <p><strong>ເສັດຈິງ:</strong><br><?php echo date('d/m/Y', strtotime($production['actual_completion_date'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($production['deadline']): ?>
                            <hr>
                            <p><strong>ກຳນົດສົ່ງລູກຄ້າ:</strong><br><?php echo date('d/m/Y', strtotime($production['deadline'])); ?></p>
                            
                            <?php
                            $customerDeadline = new DateTime($production['deadline']);
                            $interval = $today->diff($customerDeadline);
                            $isPastCustomerDeadline = $today > $customerDeadline;
                            ?>
                            
                            <?php if ($isPastCustomerDeadline): ?>
                                <div class="alert alert-danger p-2">
                                    <small><i class="fas fa-exclamation-triangle"></i> ກາຍກຳນົດລູກຄ້າ <?php echo $interval->days; ?> ວັນ</small>
                                </div>
                            <?php elseif ($interval->days <= 3): ?>
                                <div class="alert alert-warning p-2">
                                    <small><i class="fas fa-clock"></i> ອີກ <?php echo $interval->days; ?> ວັນຕ້ອງສົ່ງລູກຄ້າ</small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ประวัติการเปลี่ยนสถานะ -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h3 class="mb-0">ປະຫວັດການປ່ຽນສະຖານະ</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="timeline p-3">
                            <?php if ($historyResult->num_rows > 0): ?>
                                <?php while ($history = $historyResult->fetch_assoc()): ?>
                                    <div class="timeline-item pb-3 mb-3 border-bottom">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-primary fw-bold">
                                                <?php echo getProductionStatusThai($history['new_status']); ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($history['change_date'])); ?></small>
                                        </div>
                                        <div>
                                            <?php if ($history['notes']): ?>
                                                <p class="mb-1 small"><?php echo htmlspecialchars($history['notes']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($history['actual_completion_date']): ?>
                                                <p class="mb-1 small text-success">
                                                    <i class="fas fa-calendar-check"></i> ວັນທີເສັດຈິງ: <?php echo date('d/m/Y', strtotime($history['actual_completion_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            <small class="text-muted">ໂດຍ: <?php echo htmlspecialchars($history['user_name']); ?></small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-center py-3">ຍັງບໍ່ມີປະຫວັດການປ່ຽນສະຖານະ</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal อัปเดตสถานะการผลิต -->
    <div class="modal fade" id="updateProductionStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ອັບເດດສະຖານະການຜະລິດ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update_production_status.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="production_id" id="modal_production_id" value="<?php echo $productionId; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">ສະຖານະປະຈຸບັນ:</label>
                            <span class="badge bg-<?php echo getProductionStatusColor($production['status']); ?>">
                                <?php echo getProductionStatusThai($production['status']); ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ປ່ຽນສະຖານະເປັນ:</label>
                            <select name="new_status" id="new_production_status" class="form-select" required>
                                <!-- ตัวเลือกจะถูกเติมด้วย JavaScript -->
                            </select>
                        </div>
                        
                        <div class="mb-3" id="completionDateField" style="display: none;">
                            <label class="form-label">ວັນທີຮັບຂອງຈາກໂຮງງານ:</label>
                            <input type="date" class="form-control" name="actual_completion_date" id="actual_completion_date">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ອັບໂຫລດຮູບສິນຄ້າ (ຖ້າມີ):</label>
                            <input type="file" class="form-control" name="product_images[]" multiple accept="image/*">
                            <div class="form-text">ອັບໂຫລດຮູບພາບສິນຄ້າທີ່ສຳເລັດແລ້ວ</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ໝາຍເຫດ:</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="ບັນທຶກເພີ່ມເຕີມກ່ຽວກັບການອັບເດດສະຖານະ"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-primary">ບັນທຶກການອັບເດດ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal อัปโหลดรูปภาพ -->
    <div class="modal fade" id="uploadImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ອັບໂຫລດຮູບພາບສິນຄ້າ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="upload_production_images.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="production_id" value="<?php echo $productionId; ?>">
                        
                        <div class="mb-3">
                            <label for="product_images" class="form-label">ເລືອກຮູບພາບ:</label>
                            <input type="file" class="form-control" id="product_images" name="product_images[]" multiple accept="image/*" required>
                            <div class="form-text">ອັບໂຫລດຮູບພາບສິນຄ້າທີ່ສຳເລັດແລ້ວ (ສູງສຸດ 5MB ຕໍ່ຮູບ)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ໝາຍເຫດ (ຖ້າມີ):</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="ບັນທຶກເພີ່ມເຕີມກ່ຽວກັບຮູບພາບ"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> ອັບໂຫລດ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แสดงรูปภาพขนาดใหญ่ -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">ຮູບພາບສິນຄ້າ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="ຮູບພາບສິນຄ້າ" class="img-fluid">
                </div>
                <div class="modal-footer">
                    <a id="downloadImageBtn" href="" download class="btn btn-success">
                        <i class="fas fa-download"></i> ດາວໂຫລດ
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ປິດ</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // การทำงานของ Modal อัปเดตสถานะการผลิต
        const updateButtons = document.querySelectorAll('.update-production-status-btn');
        
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productionId = this.getAttribute('data-id');
                const currentStatus = this.getAttribute('data-status');
                
                document.getElementById('modal_production_id').value = productionId;
                
                // เคลียร์และเติมตัวเลือกสถานะ
                const statusSelect = document.getElementById('new_production_status');
                statusSelect.innerHTML = '';
                
                const nextStatuses = getNextProductionStatuses(currentStatus);
                
                nextStatuses.forEach(status => {
                    const option = document.createElement('option');
                    option.value = status.value;
                    option.textContent = status.label;
                    statusSelect.appendChild(option);
                });
                
                // แสดง/ซ่อนฟิลด์วันที่รับของ
                statusSelect.addEventListener('change', function() {
                    const dateField = document.getElementById('completionDateField');
                    const dateInput = document.getElementById('actual_completion_date');
                    
                    if (this.value === 'received') {
                        dateField.style.display = 'block';
                        dateInput.required = true;
                        dateInput.value = new Date().toISOString().split('T')[0]; // วันที่ปัจจุบัน
                    } else {
                        dateField.style.display = 'none';
                        dateInput.required = false;
                        dateInput.value = '';
                    }
                });
            });
        });
        
        // การทำงานของ Modal แสดงรูปภาพ
        const imageModal = document.getElementById('imageModal');
        imageModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const imageSrc = button.getAttribute('data-image');
            const imageName = button.getAttribute('data-name');
            
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('imageModalTitle');
            const downloadBtn = document.getElementById('downloadImageBtn');
            
            modalImage.src = imageSrc;
            modalTitle.textContent = imageName;
            downloadBtn.href = imageSrc;
            downloadBtn.download = imageName;
        });
        
        // ฟังก์ชันสำหรับกำหนดสถานะถัดไปที่เป็นไปได้
        function getNextProductionStatuses(currentStatus) {
            switch(currentStatus) {
                case 'pending':
                    return [
                        { value: 'sent', label: 'ສົ່ງໄຟລ໌ໄປໂຮງງານແລ້ວ' }
                    ];
                case 'sent':
                    return [
                        { value: 'in_progress', label: 'ໂຮງງານເລີ່ມຜະລິດ' },
                        { value: 'pending', label: 'ຍ້ອນກັບສະຖານະຖ້າສົ່ງ' }
                    ];
                case 'in_progress':
                    return [
                        { value: 'ready_pickup', label: 'ຜະລິດເສັດແລ້ວ ພ້ອມມາຮັບ' }
                    ];
                case 'ready_pickup':
                    return [
                        { value: 'received', label: 'ຮັບຂອງຈາກໂຮງງານແລ້ວ' }
                    ];
                case 'received':
                    return [
                        { value: 'delivered', label: 'ສົ່ງໃຫ້ລູກຄ້າແລ້ວ' }
                    ];
                case 'delivered':
                    return [
                        { value: 'received', label: 'ຍ້ອນກັບສະຖານະຮັບແລ້ວ' }
                    ];
                default:
                    return [
                        { value: 'pending', label: 'ຖ້າສົ່ງໂຮງງານ' },
                        { value: 'sent', label: 'ສົ່ງໄຟລ໌ແລ້ວ' },
                        { value: 'in_progress', label: 'ກຳລັງຜະລິດ' },
                        { value: 'ready_pickup', label: 'ພ້ອມມາຮັບ' },
                        { value: 'received', label: 'ຮັບຂອງແລ້ວ' },
                        { value: 'delivered', label: 'ສົ່ງລູກຄ້າແລ້ວ' }
                    ];
            }
        }
    });
    </script>
</body>
</html>