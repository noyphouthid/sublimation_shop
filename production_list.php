<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ตัวกรองตามสถานะ
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$factoryFilter = isset($_GET['factory']) ? intval($_GET['factory']) : 0;
$searchKeyword = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// สร้าง query พื้นฐาน
$query = "SELECT po.*, dq.queue_code, dq.customer_name, dq.team_name, dq.deadline,
                 u.full_name as created_by_name
          FROM production_orders po 
          LEFT JOIN design_queue dq ON po.design_id = dq.design_id
          LEFT JOIN users u ON dq.designer_id = u.user_id
          WHERE 1=1";

// เพิ่มเงื่อนไขการกรอง
if ($statusFilter) {
    $query .= " AND po.status = '$statusFilter'";
}

if ($factoryFilter > 0) {
    $query .= " AND po.factory_id = $factoryFilter";
}

if ($searchKeyword) {
    $query .= " AND (dq.queue_code LIKE '%$searchKeyword%' 
                  OR dq.customer_name LIKE '%$searchKeyword%'
                  OR dq.team_name LIKE '%$searchKeyword%'
                  OR po.product_details LIKE '%$searchKeyword%')";
}

// เรียงตามการอัปเดตล่าสุด
$query .= " ORDER BY po.updated_at DESC";

$result = $conn->query($query);

// ข้อมูลโรงงาน (จำลอง)
$factories = [
    1 => 'Life Football',
    2 => 'ໂຮງງານ B', 
    3 => 'ໂຮງງານ C'
];

// คำนวณสถิติ
$pendingQuery = "SELECT COUNT(*) as count FROM production_orders WHERE status = 'pending'";
$inProgressQuery = "SELECT COUNT(*) as count FROM production_orders WHERE status IN ('sent', 'in_progress')";
$completedQuery = "SELECT COUNT(*) as count FROM production_orders WHERE status IN ('delivered')";
$overdueQuery = "SELECT COUNT(*) as count FROM production_orders WHERE status IN ('pending', 'sent', 'in_progress', 'ready_pickup') AND expected_completion_date < CURDATE()";

$pendingResult = $conn->query($pendingQuery)->fetch_assoc();
$inProgressResult = $conn->query($inProgressQuery)->fetch_assoc();
$completedResult = $conn->query($completedQuery)->fetch_assoc();
$overdueResult = $conn->query($overdueQuery)->fetch_assoc();

// ฟังก์ชันแปลงสถานะเป็นภาษาลาว
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

// ฟังก์ชันแปลงสถานะเป็นสี Bootstrap
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
    <title>ລະບົບຕິດຕາມການຜະລິດ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
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

        .overdue {
            background-color: #ffe6e6 !important;
            border-left: 4px solid #dc3545 !important;
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-tshirt me-2"></i> ລະບົບບໍລິຫານຮ້ານເສື້ອພິມລາຍ
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="design_queue_list.php">
                            <i class="fas fa-list-ul"></i> ຄິວອອກແບບ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="invoice_list.php">
                            <i class="fas fa-file-invoice"></i> ໃບສະເໜີລາຄາ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-shopping-cart"></i> ຄຳສັ່ງຊື້
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="production_list.php">
                            <i class="fas fa-industry"></i> ການຜະລິດ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-chart-line"></i> ລາຍງານ
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
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> ອອກຈາກລະບົບ</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- สรุปสถิติ -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">ຖ້າສົ່ງໂຮງງານ</h6>
                            <h2 class="my-2"><?php echo $pendingResult['count']; ?></h2>
                            <p class="card-text mb-0">ງານທີ່ຍັງບໍ່ສົ່ງ</p>
                        </div>
                        <i class="fas fa-hourglass-start fa-3x text-white-50"></i>
                    </div>
                    <a href="?status=pending" class="card-footer bg-secondary text-white text-decoration-none d-block small">
                        <i class="fas fa-search me-1"></i> ເບີ່ງງານທີ່ຖ້າສົ່ງ
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">ກຳລັງຜະລິດ</h6>
                            <h2 class="my-2"><?php echo $inProgressResult['count']; ?></h2>
                            <p class="card-text mb-0">ງານທີ່ໂຮງງານກຳລັງເຮັດ</p>
                        </div>
                        <i class="fas fa-cogs fa-3x text-white-50"></i>
                    </div>
                    <a href="?status=in_progress" class="card-footer bg-primary text-white text-decoration-none d-block small">
                        <i class="fas fa-search me-1"></i> ເບີ່ງງານທີ່ກຳລັງຜະລິດ
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">ສຳເລັດແລ້ວ</h6>
                            <h2 class="my-2"><?php echo $completedResult['count']; ?></h2>
                            <p class="card-text mb-0">ງານທີ່ສຳເລັດ</p>
                        </div>
                        <i class="fas fa-check-circle fa-3x text-white-50"></i>
                    </div>
                    <a href="?status=completed" class="card-footer bg-success text-white text-decoration-none d-block small">
                        <i class="fas fa-search me-1"></i> ເບີ່ງງານທີ່ສຳເລັດ
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">ເກີນກຳນົດ</h6>
                            <h2 class="my-2"><?php echo $overdueResult['count']; ?></h2>
                            <p class="card-text mb-0">ງານທີ່ກາຍກຳນົດ</p>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-3x text-white-50"></i>
                    </div>
                    <div class="card-footer bg-danger text-white small">
                        <i class="fas fa-clock me-1"></i> ຕ້ອງຕິດຕາມດ່ວນ
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-2xl font-bold">ລາຍການຕິດຕາມການຜະລິດ</h1>
        </div>

        <!-- ตัวกรอง -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">ສະຖານະ:</label>
                        <select name="status" class="form-select">
                            <option value="">ທັງໝົດ</option>
                            <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>ຖ້າສົ່ງໂຮງງານ</option>
                            <option value="sent" <?php echo $statusFilter == 'sent' ? 'selected' : ''; ?>>ສົ່ງໄຟລ໌ແລ້ວ</option>
                            <option value="in_progress" <?php echo $statusFilter == 'in_progress' ? 'selected' : ''; ?>>ກຳລັງຜະລິດ</option>
                            <option value="ready_pickup" <?php echo $statusFilter == 'ready_pickup' ? 'selected' : ''; ?>>ພ້ອມມາຮັບ</option>
                            <option value="received" <?php echo $statusFilter == 'received' ? 'selected' : ''; ?>>ຮັບຂອງແລ້ວ</option>
                            <option value="delivered" <?php echo $statusFilter == 'delivered' ? 'selected' : ''; ?>>ສົ່ງລູກຄ້າແລ້ວ</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ໂຮງງານ:</label>
                        <select name="factory" class="form-select">
                            <option value="0">ທັງໝົດ</option>
                            <?php foreach ($factories as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $factoryFilter == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ຄົ້ນຫາ:</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchKeyword); ?>" 
                               placeholder="ລະຫັດຄິວ, ຊື່ລູກຄ້າ, ທີມ...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ຄົ້ນຫາ
                            </button>
                            <a href="production_list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync"></i> ລ້າງ
                            </a>
                        </div>
                    </div>
                </form>
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

        <!-- ตารางแสดงการผลิต -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-3 py-3">ລະຫັດຄິວ</th>
                                <th class="px-3 py-3">ລູກຄ້າ/ທີມ</th>
                                <th class="px-3 py-3">ໂຮງງານ</th>
                                <th class="px-3 py-3">ລາຍລະອຽດຜະລິດ</th>
                                <th class="px-3 py-3">ສະຖານະ</th>
                                <th class="px-3 py-3">ກຳນົດເຊ່ັດ</th>
                                <th class="px-3 py-3">ຄ່າຜະລິດ</th>
                                <th class="px-3 py-3">ຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                    $today = new DateTime();
                                    $expectedDate = new DateTime($row['expected_completion_date']);
                                    $isOverdue = $today > $expectedDate && !in_array($row['status'], ['delivered']);
                                    ?>
                                    <tr class="<?php echo $isOverdue ? 'overdue' : ''; ?>">
                                        <td class="px-3 py-3">
                                            <div><?php echo $row['queue_code']; ?></div>
                                            <small class="text-muted">ID: <?php echo $row['production_id']; ?></small>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                            <?php if ($row['team_name']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['team_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="badge bg-light text-dark">
                                                <?php echo isset($factories[$row['factory_id']]) ? $factories[$row['factory_id']] : 'ບໍ່ຮູ້ຈັກ'; ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <?php echo mb_substr(htmlspecialchars($row['product_details']), 0, 50, 'UTF-8') . (mb_strlen($row['product_details'], 'UTF-8') > 50 ? '...' : ''); ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="badge bg-<?php echo getProductionStatusColor($row['status']); ?>">
                                                <?php echo getProductionStatusThai($row['status']); ?>
                                            </span>
                                            <?php if ($isOverdue): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> ເກີນກຳນົດ</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <?php
                                            echo date('d/m/Y', strtotime($row['expected_completion_date']));
                                            
                                            $interval = $today->diff($expectedDate);
                                            $daysRemaining = $interval->days;
                                            
                                            if ($isOverdue) {
                                                echo '<br><span class="badge bg-danger">ກາຍ ' . $daysRemaining . ' ວັນ</span>';
                                            } elseif ($daysRemaining <= 3 && !in_array($row['status'], ['delivered'])) {
                                                echo '<br><span class="badge bg-warning text-dark">ອີກ ' . $daysRemaining . ' ວັນ</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <?php echo number_format($row['production_cost']); ?> ₭
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="btn-group">
                                                <a href="view_production.php?id=<?php echo $row['production_id']; ?>" class="btn btn-sm btn-primary" title="ເບີ່ງລາຍລະອຽດ">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-success update-status-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#updateProductionStatusModal" 
                                                        data-id="<?php echo $row['production_id']; ?>"
                                                        data-status="<?php echo $row['status']; ?>"
                                                        title="ອັບເດດສະຖານະ">
                                                    <i class="fas fa-arrow-right"></i>
                                                </button>
                                                <?php if ($_SESSION['role'] === 'admin' && $row['status'] !== 'delivered'): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-production-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteProductionModal" 
                                                        data-id="<?php echo $row['production_id']; ?>"
                                                        data-code="<?php echo $row['queue_code']; ?>"
                                                        data-status="<?php echo getProductionStatusThai($row['status']); ?>"
                                                        title="ລຶບລາຍການ">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>ບໍ່ພົບຂໍ້ມູນການຜະລິດ</p>
                                            <?php if ($statusFilter || $factoryFilter || $searchKeyword): ?>
                                                <a href="production_list.php" class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="fas fa-sync"></i> ລ້າງຕົວກັ່ນ
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                <form action="update_production_status.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="production_id" id="modal_production_id">
                        
                        <!-- Progress Timeline -->
                        <div class="progress-timeline mb-4">
                            <div class="progress-step" data-status="pending">
                                <div class="progress-icon"><i class="fas fa-clock"></i></div>
                                <div><small>ຖ້າສົ່ງ</small></div>
                            </div>
                            <div class="progress-step" data-status="sent">
                                <div class="progress-icon"><i class="fas fa-paper-plane"></i></div>
                                <div><small>ສົ່ງແລ້ວ</small></div>
                            </div>
                            <div class="progress-step" data-status="in_progress">
                                <div class="progress-icon"><i class="fas fa-cogs"></i></div>
                                <div><small>ກຳລັງຜະລິດ</small></div>
                            </div>
                            <div class="progress-step" data-status="ready_pickup">
                                <div class="progress-icon"><i class="fas fa-box"></i></div>
                                <div><small>ພ້ອມຮັບ</small></div>
                            </div>
                            <div class="progress-step" data-status="received">
                                <div class="progress-icon"><i class="fas fa-truck"></i></div>
                                <div><small>ຮັບແລ້ວ</small></div>
                            </div>
                            <div class="progress-step" data-status="delivered">
                                <div class="progress-icon"><i class="fas fa-check"></i></div>
                                <div><small>ສົ່ງລູກຄ້າ</small></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ປ່ຽນສະຖານະເປັນ:</label>
                            <select name="new_status" id="new_production_status" class="form-select" required>
                                <!-- ตัวเลือกจะถูกเติมด้วย JavaScript -->
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ວັນທີຮັບຂອງຈາກໂຮງງານ (ສຳລັບສະຖານະ "ຮັບຂອງແລ້ວ"):</label>
                            <input type="date" class="form-control" name="actual_completion_date" id="actual_completion_date">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ອັບໂຫລດຮູບສິນຄ້າຈຸງ (ຖ້າມີ):</label>
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

    <!-- Modal ลบรายการผลิต -->
    <div class="modal fade" id="deleteProductionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">ຢືນຢັນການລຶບລາຍການຜະລິດ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                    </div>
                    
                    <p class="text-center">ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບລາຍການຜະລິດນີ້?</p>
                    
                    <div class="alert alert-info">
                        <strong>ລະຫັດຄິວ:</strong> <span id="deleteQueueCode"></span><br>
                        <strong>ສະຖານະປະຈຸບັນ:</strong> <span id="deleteCurrentStatus"></span>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> ຄຳເຕືອນ:</h6>
                        <ul class="mb-0">
                            <li>ການດຳເນີນການນີ້ບໍ່ສາມາດຍົກເລີກໄດ້</li>
                            <li>ຂໍ້ມູນລາຍການຜະລິດທັງໝົດຈະຖືກລຶບ</li>
                            <li>ຮູບພາບສິນຄ້າທັງໝົດຈະຖືກລຶບ</li>
                            <li>ປະຫວັດການເປລີ່ຍນສະຖານະຈະຖືກລຶບ</li>
                            <li>ຄິວອອກແບບຈະກັບໄປສະຖານະ "ອະນຸມັດແລ້ວ"</li>
                        </ul>
                    </div>
                    
                    <p class="text-danger text-center mb-0">
                        <strong>ໝາຍເຫດ:</strong> ບໍ່ສາມາດລຶບລາຍການທີ່ສົ່ງມອບແລ້ວໄດ້
                    </p>
                </div>
                <form action="delete_production.php" method="post">
                    <input type="hidden" name="production_id" id="production_id_to_delete">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> ຢືນຢັນການລຶບ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // การทำงานของ Modal อัปเดตสถานะการผลิต
        const updateButtons = document.querySelectorAll('.update-status-btn');
        
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productionId = this.getAttribute('data-id');
                const currentStatus = this.getAttribute('data-status');
                
                document.getElementById('modal_production_id').value = productionId;
                
                // อัปเดต Timeline
                updateProgressTimeline(currentStatus);
                
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
                    const dateField = document.getElementById('actual_completion_date');
                    if (this.value === 'received') {
                        dateField.style.display = 'block';
                        dateField.required = true;
                    } else {
                        dateField.style.display = 'none';
                        dateField.required = false;
                    }
                });
            });
        });
        
        // การทำงานของ Modal ลบรายการผลิต
        const deleteButtons = document.querySelectorAll('.delete-production-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productionId = this.getAttribute('data-id');
                const queueCode = this.getAttribute('data-code');
                const currentStatus = this.getAttribute('data-status');
                
                document.getElementById('production_id_to_delete').value = productionId;
                document.getElementById('deleteQueueCode').textContent = queueCode;
                document.getElementById('deleteCurrentStatus').textContent = currentStatus;
            });
        });
        
        // ฟังก์ชันอัปเดต Progress Timeline
        function updateProgressTimeline(currentStatus) {
            const steps = document.querySelectorAll('.progress-step');
            const statusOrder = ['pending', 'sent', 'in_progress', 'quality_check', 'ready_pickup', 'received', 'delivered'];
            
            const currentIndex = statusOrder.indexOf(currentStatus);
            
            steps.forEach((step, index) => {
                step.classList.remove('active', 'current');
                
                if (index < currentIndex) {
                    step.classList.add('active');
                } else if (index === currentIndex) {
                    step.classList.add('current');
                }
            });
        }
        
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