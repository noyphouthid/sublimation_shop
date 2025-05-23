<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// สถิติการผลิตรายเดือน
$currentMonth = date('Y-m');
$statsQuery = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
    COUNT(CASE WHEN status = 'ready_pickup' THEN 1 END) as ready_pickup,
    COUNT(CASE WHEN status = 'received' THEN 1 END) as received,
    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
FROM production_orders 
WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// งานที่เกินกำหนด
$overdueQuery = "SELECT po.*, dq.queue_code, dq.customer_name, dq.team_name
FROM production_orders po 
LEFT JOIN design_queue dq ON po.design_id = dq.design_id
WHERE po.expected_completion_date < CURDATE() 
AND po.status NOT IN ('delivered', 'cancelled')
ORDER BY po.expected_completion_date ASC
LIMIT 10";

$overdueResult = $conn->query($overdueQuery);

// งานที่จะครบกำหนดใน 3 วัน
$upcomingQuery = "SELECT po.*, dq.queue_code, dq.customer_name, dq.team_name
FROM production_orders po 
LEFT JOIN design_queue dq ON po.design_id = dq.design_id
WHERE po.expected_completion_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
AND po.status NOT IN ('delivered', 'cancelled')
ORDER BY po.expected_completion_date ASC
LIMIT 10";

$upcomingResult = $conn->query($upcomingQuery);

// งานล่าสุดที่เสร็จสิ้น
$recentCompletedQuery = "SELECT po.*, dq.queue_code, dq.customer_name, dq.team_name
FROM production_orders po 
LEFT JOIN design_queue dq ON po.design_id = dq.design_id
WHERE po.status = 'delivered'
ORDER BY po.updated_at DESC
LIMIT 5";

$recentCompletedResult = $conn->query($recentCompletedQuery);

// สถิติต้นทุนและรายได้ (ถ้ามีข้อมูล)
$costQuery = "SELECT 
    SUM(production_cost) as total_cost,
    AVG(production_cost) as avg_cost,
    COUNT(*) as total_orders
FROM production_orders 
WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'";

$costResult = $conn->query($costQuery);
$costStats = $costResult->fetch_assoc();

// ข้อมูลโรงงาน
$factories = [
    1 => 'Life Football Factory',

];

// สถิติตามโรงงาน
$factoryStatsQuery = "SELECT 
    factory_id,
    COUNT(*) as total_orders,
    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders,
    AVG(production_cost) as avg_cost
FROM production_orders 
WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
GROUP BY factory_id";

$factoryStatsResult = $conn->query($factoryStatsQuery);
$factoryStats = [];
while ($row = $factoryStatsResult->fetch_assoc()) {
    $factoryStats[$row['factory_id']] = $row;
}

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
    <title>ແດຊບອຮ໌ດການຜະລິດ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stats-card {
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .overdue-item {
            background-color: #fff3cd;
            border-left: 4px solid #e17055;
        }

        .upcoming-item {
            background-color: #e7f3ff;
            border-left: 4px solid #0066cc;
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="productionDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-industry"></i> ການຜະລິດ
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="production_dashboard.php"><i class="fas fa-chart-pie"></i> ແດຊບອຮ໌ດ</a></li>
                            <li><a class="dropdown-item" href="production_list.php"><i class="fas fa-list"></i> ລາຍການຜະລິດ</a></li>
                        </ul>
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-3xl font-bold mb-1">ແດຊບອຮ໌ດການຜະລິດ</h1>
                <p class="text-muted">ເດືອນ <?php echo date('m/Y'); ?></p>
            </div>
            <div>
                <a href="production_list.php" class="btn btn-outline-primary">
                    <i class="fas fa-list"></i> ລາຍການຜະລິດທັງໝົດ
                </a>
            </div>
        </div>

        <!-- สถิติรวม -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ລາຍການທັງໝົດ</h6>
                                <h2 class="mb-0"><?php echo $stats['total_orders']; ?></h2>
                                <small>ລາຍການຜະລິດເດືອນນີ້</small>
                            </div>
                            <i class="fas fa-clipboard-list fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ກຳລັງດຳເນີນການ</h6>
                                <h2 class="mb-0"><?php echo ($stats['pending'] + $stats['sent'] + $stats['in_progress'] + $stats['ready_pickup']); ?></h2>
                                <small>ຍັງບໍ່ເສັດ</small>
                            </div>
                            <i class="fas fa-cogs fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ສຳເລັດແລ້ວ</h6>
                                <h2 class="mb-0"><?php echo ($stats['delivered']); ?></h2>
                                <small>ສຳເລັດໃນເດືອນນີ້</small>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ຕົ້ນທຶນລວມ</h6>
                                <h2 class="mb-0"><?php echo number_format($costStats['total_cost']); ?></h2>
                                <small>₭ ເດືອນນີ້</small>
                            </div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- กราฟสถิติ -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>ສະຖິຕິສະຖານະການຜະລິດ</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- งานที่เกินกำหนด -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>ງານທີ່ເກີນກຳນົດ</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($overdueResult->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($item = $overdueResult->fetch_assoc()): ?>
                                    <div class="list-group-item overdue-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="view_production.php?id=<?php echo $item['production_id']; ?>" class="text-decoration-none">
                                                        <?php echo $item['queue_code']; ?>
                                                    </a>
                                                </h6>
                                                <p class="mb-1"><?php echo htmlspecialchars($item['customer_name']); ?></p>
                                                <small>ກຳນົດ: <?php echo date('d/m/Y', strtotime($item['expected_completion_date'])); ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo getProductionStatusColor($item['status']); ?>">
                                                <?php echo getProductionStatusThai($item['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-3 text-center text-success">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <p class="mb-0">ບໍ່ມີງານທີ່ເກີນກຳນົດ!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ข้อมูลด้านขาง -->
            <div class="col-md-4">
                <!-- งานที่จะครบกำหนดเร็วๆ นี้ -->
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>ງານທີ່ກຳລັງໃກ້ກຳນົດ</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($upcomingResult->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($item = $upcomingResult->fetch_assoc()): ?>
                                    <div class="list-group-item upcoming-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="view_production.php?id=<?php echo $item['production_id']; ?>" class="text-decoration-none">
                                                        <?php echo $item['queue_code']; ?>
                                                    </a>
                                                </h6>
                                                <p class="mb-1 small"><?php echo htmlspecialchars($item['customer_name']); ?></p>
                                                <small class="text-primary">ກຳນົດ: <?php echo date('d/m/Y', strtotime($item['expected_completion_date'])); ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo getProductionStatusColor($item['status']); ?>">
                                                <?php echo getProductionStatusThai($item['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <p class="mb-0">ບໍ່ມີງານທີ່ໃກ້ກຳນົດ</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- สถิติโรงงาน -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-industry me-2"></i>ສະຖິຕິໂຮງງານ</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($factories as $factoryId => $factoryName): ?>
                            <?php $factoryStat = isset($factoryStats[$factoryId]) ? $factoryStats[$factoryId] : null; ?>
                            <div class="mb-3 pb-3 <?php echo next($factories) ? 'border-bottom' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><?php echo $factoryName; ?></h6>
                                    <span class="badge bg-primary"><?php echo $factoryStat ? $factoryStat['total_orders'] : 0; ?> ງານ</span>
                                </div>
                                <?php if ($factoryStat): ?>
                                    <small class="text-muted">
                                        ສຳເລັດ: <?php echo $factoryStat['completed_orders']; ?> ງານ | 
                                        ຄ່າເຉລີ່ຍ: <?php echo number_format($factoryStat['avg_cost']); ?> ₭
                                    </small>
                                    <div class="progress mt-1" style="height: 5px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $factoryStat['total_orders'] > 0 ? ($factoryStat['completed_orders'] / $factoryStat['total_orders']) * 100 : 0; ?>%"></div>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted">ບໍ່ມີງານໃນເດືອນນີ້</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- งานล่าสุดที่เสร็จ -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-check me-2"></i>ງານທີ່ສຳເລັດລ່າສຸດ</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recentCompletedResult->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($item = $recentCompletedResult->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="view_production.php?id=<?php echo $item['production_id']; ?>" class="text-decoration-none">
                                                        <?php echo $item['queue_code']; ?>
                                                    </a>
                                                </h6>
                                                <p class="mb-1 small"><?php echo htmlspecialchars($item['customer_name']); ?></p>
                                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($item['updated_at'])); ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo getProductionStatusColor($item['status']); ?>">
                                                <?php echo getProductionStatusThai($item['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted">
                                <p class="mb-0">ຍັງບໍ່ມີງານທີ່ສຳເລັດ</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // กราฟสถิติสถานะ
        const ctx = document.getElementById('statusChart').getContext('2d');
        
        const statusData = {
            labels: [
                'ຖ້າສົ່ງ', 'ສົ່ງແລ້ວ', 'ກຳລັງຜະລິດ', 
                'ພ້ອມຮັບ', 'ຮັບແລ້ວ', 'ສົ່ງລູກຄ້າ'
            ],
            datasets: [{
                data: [
                    <?php echo $stats['pending']; ?>,
                    <?php echo $stats['sent']; ?>,
                    <?php echo $stats['in_progress']; ?>,
                    <?php echo $stats['ready_pickup']; ?>,
                    <?php echo $stats['received']; ?>,
                    <?php echo $stats['delivered']; ?>
                ],
                backgroundColor: [
                    '#6c757d', // pending - secondary
                    '#17a2b8', // sent - info
                    '#007bff', // in_progress - primary
                    '#28a745', // ready_pickup - success
                    '#28a745', // received - success
                    '#343a40'  // delivered - dark
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        };

        new Chart(ctx, {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' ງານ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>