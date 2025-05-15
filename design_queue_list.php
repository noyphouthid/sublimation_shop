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
$designerFilter = isset($_GET['designer']) ? intval($_GET['designer']) : 0;
$searchKeyword = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// สร้าง query พื้นฐาน
$query = "SELECT dq.*, u.full_name as designer_name 
          FROM design_queue dq 
          LEFT JOIN users u ON dq.designer_id = u.user_id 
          WHERE 1=1";

// เพิ่มเงื่อนไขการกรอง
if ($statusFilter) {
    $query .= " AND dq.status = '$statusFilter'";
}

if ($designerFilter > 0) {
    $query .= " AND dq.designer_id = $designerFilter";
}

if ($searchKeyword) {
    $query .= " AND (dq.queue_code LIKE '%$searchKeyword%' 
                  OR dq.customer_name LIKE '%$searchKeyword%'
                  OR dq.team_name LIKE '%$searchKeyword%'
                  OR dq.design_details LIKE '%$searchKeyword%')";
}

// เรียงตามการอัปเดตล่าสุด
$query .= " ORDER BY dq.updated_at DESC";

$result = $conn->query($query);

// ดึงรายชื่อดีไซเนอร์สำหรับตัวกรอง
$designersQuery = "SELECT user_id, full_name FROM users WHERE role = 'designer'";
$designersResult = $conn->query($designersQuery);

// คำนวณสถิติ
$pendingQuery = "SELECT COUNT(*) as count FROM design_queue WHERE status = 'pending'";
$inProgressQuery = "SELECT COUNT(*) as count FROM design_queue WHERE status = 'in_progress'";
$reviewQuery = "SELECT COUNT(*) as count FROM design_queue WHERE status = 'customer_review'";
$approvedQuery = "SELECT COUNT(*) as count FROM design_queue WHERE status = 'approved'";

$pendingResult = $conn->query($pendingQuery)->fetch_assoc();
$inProgressResult = $conn->query($inProgressQuery)->fetch_assoc();
$reviewResult = $conn->query($reviewQuery)->fetch_assoc();
$approvedResult = $conn->query($approvedQuery)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบคิวออกแบบ - รายการคิวทั้งหมด</title>
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
        <!-- สรุปสถิติคิวด่วน -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">รอออกแบบ</h6>
                            <h2 class="my-2"><?php echo $pendingResult['count']; ?></h2>
                            <p class="card-text mb-0">คิวที่รอดำเนินการ</p>
                        </div>
                        <i class="fas fa-clock fa-3x text-white-50"></i>
                    </div>
                    <a href="?status=pending" class="card-footer bg-secondary text-white text-decoration-none d-block small">
                        <i class="fas fa-search me-1"></i> ดูคิวที่รอออกแบบ
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">กำลังออกแบบ</h6>
                            <h2 class="my-2"><?php echo $inProgressResult['count']; ?></h2>
                            <p class="card-text mb-0">คิวที่กำลังดำเนินการ</p>
                        </div>
                        <i class="fas fa-paint-brush fa-3x text-white-50"></i>
                    </div>
                    <a href="?status=in_progress" class="card-footer bg-primary text-white text-decoration-none d-block small">
                        <i class="fas fa-search me-1"></i> ดูคิวที่กำลังออกแบบ
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">รอลูกค้าตรวจสอบ</h6>
                            <h2 class="my-2"><?php echo $reviewResult['count']; ?></h2>
                            <p class="card-text mb-0">คิวที่รอการอนุมัติ</p>
                        </div>
                        <i class="fas fa-eye fa-3x text-white-50"></i>
                    </div>
                    <a href="?status=customer_review" class="card-footer bg-info text-white text-decoration-none d-block small">
                        <i class="fas fa-search me-1"></i> ดูคิวที่รอลูกค้าตรวจสอบ
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">อนุมัติแล้ว</h6>
                            <h2 class="my-2"><?php echo $approvedResult['count']; ?></h2>
                            <p class="card-text mb-0">คิวที่พร้อมส่งผลิต</p>
                        </div>
                        <i class="fas fa-check-circle fa-3x text-white-50"></i>
                    </div>
                    <a href="?status=approved" class="card-footer bg-success text-white text-decoration-none d-block small">
                        <i class="fas fa-search me-1"></i> ดูคิวที่อนุมัติแล้ว
                    </a>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-2xl font-bold">รายการคิวออกแบบ</h1>
            <a href="add_design_queue.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> เพิ่มคิวใหม่
            </a>
        </div>

        <!-- ตัวกรอง -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">สถานะ:</label>
                        <select name="status" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>รอออกแบบ</option>
                            <option value="in_progress" <?php echo $statusFilter == 'in_progress' ? 'selected' : ''; ?>>กำลังออกแบบ</option>
                            <option value="customer_review" <?php echo $statusFilter == 'customer_review' ? 'selected' : ''; ?>>ส่งให้ลูกค้าตรวจสอบ</option>
                            <option value="revision" <?php echo $statusFilter == 'revision' ? 'selected' : ''; ?>>ลูกค้าขอแก้ไข</option>
                            <option value="approved" <?php echo $statusFilter == 'approved' ? 'selected' : ''; ?>>ลูกค้าอนุมัติแล้ว</option>
                            <option value="production" <?php echo $statusFilter == 'production' ? 'selected' : ''; ?>>ส่งไปยังระบบผลิต</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>เสร็จสมบูรณ์</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ดีไซเนอร์:</label>
                        <select name="designer" class="form-select">
                            <option value="0">ทั้งหมด</option>
                            <?php 
                            // รีเซ็ตตัวชี้ตำแหน่งผลลัพธ์
                            $designersResult->data_seek(0);
                            while ($designer = $designersResult->fetch_assoc()): ?>
                                <option value="<?php echo $designer['user_id']; ?>" <?php echo $designerFilter == $designer['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo $designer['full_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ค้นหา:</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchKeyword); ?>" 
                               placeholder="รหัสคิว, ชื่อลูกค้า, ทีม, รายละเอียด...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                            <a href="design_queue_list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync"></i> รีเซ็ต
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

        <!-- ตารางแสดงคิวออกแบบ -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-3 py-3">รหัสคิว</th>
                                <th class="px-3 py-3">ลูกค้า/ทีม</th>
                                <th class="px-3 py-3">รายละเอียด</th>
                                <th class="px-3 py-3">ดีไซเนอร์</th>
                                <th class="px-3 py-3">สถานะ</th>
                                <th class="px-3 py-3">วันที่ต้องการ</th>
                                <th class="px-3 py-3">อัปเดตล่าสุด</th>
                                <th class="px-3 py-3">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-3 py-3"><?php echo $row['queue_code']; ?></td>
                                        <td class="px-3 py-3">
                                            <div><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                            <?php if ($row['team_name']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['team_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <?php echo mb_substr(htmlspecialchars($row['design_details']), 0, 50, 'UTF-8') . (mb_strlen($row['design_details'], 'UTF-8') > 50 ? '...' : ''); ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <?php if ($row['designer_name']): ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($row['designer_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary">ยังไม่กำหนด</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="badge bg-<?php echo getStatusColor($row['status']); ?>">
                                                <?php echo getStatusThai($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <?php if ($row['deadline']): ?>
                                                <?php
                                                $deadline = new DateTime($row['deadline']);
                                                $today = new DateTime();
                                                $interval = $today->diff($deadline);
                                                $isPast = $today > $deadline;
                                                $daysRemaining = $interval->days;
                                                
                                                echo date('d/m/Y', strtotime($row['deadline']));
                                                
                                                if ($isPast) {
                                                    echo ' <span class="badge bg-danger">เลยกำหนด ' . $daysRemaining . ' วัน</span>';
                                                } elseif ($daysRemaining <= 3) {
                                                    echo ' <span class="badge bg-warning text-dark">อีก ' . $daysRemaining . ' วัน</span>';
                                                }
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span data-bs-toggle="tooltip" title="<?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?>">
                                                <?php 
                                                $updated = new DateTime($row['updated_at']);
                                                $now = new DateTime();
                                                $interval = $updated->diff($now);
                                                
                                                if ($interval->d == 0) {
                                                    if ($interval->h == 0) {
                                                        echo $interval->i . ' นาทีที่แล้ว';
                                                    } else {
                                                        echo $interval->h . ' ชั่วโมงที่แล้ว';
                                                    }
                                                } elseif ($interval->d == 1) {
                                                    echo 'เมื่อวาน';
                                                } else {
                                                    echo $interval->d . ' วันที่แล้ว';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="btn-group">
                                                <a href="view_design_queue.php?id=<?php echo $row['design_id']; ?>" class="btn btn-sm btn-primary" title="ดูรายละเอียด">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_design_queue.php?id=<?php echo $row['design_id']; ?>" class="btn btn-sm btn-warning" title="แก้ไข">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-success status-update-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#updateStatusModal" 
                                                        data-id="<?php echo $row['design_id']; ?>"
                                                        data-status="<?php echo $row['status']; ?>"
                                                        title="อัปเดตสถานะ">
                                                    <i class="fas fa-arrow-right"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>ไม่พบข้อมูลคิวออกแบบ</p>
                                            <?php if ($statusFilter || $designerFilter || $searchKeyword): ?>
                                                <a href="design_queue_list.php" class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="fas fa-sync"></i> ล้างตัวกรอง
                                                </a>
                                            <?php else: ?>
                                                <a href="add_design_queue.php" class="btn btn-sm btn-primary mt-2">
                                                    <i class="fas fa-plus"></i> เพิ่มคิวใหม่
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

    <!-- Modal อัปเดตสถานะ -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">อัปเดตสถานะคิวออกแบบ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update_status.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="design_id" id="modal_design_id">
                        <div class="mb-3">
                            <label class="form-label">เปลี่ยนสถานะเป็น:</label>
                            <select name="new_status" id="new_status" class="form-select" required>
                                <!-- ตัวเลือกจะถูกเติมด้วย JavaScript -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ (ถ้ามี):</label>
                            <textarea name="comment" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // เปิดใช้งาน tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // การทำงานของ Modal อัปเดตสถานะ
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
                        { value: 'in_progress', label: 'กำลังออกแบบ' }
                    ];
                case 'in_progress':
                    return [
                        { value: 'customer_review', label: 'ส่งให้ลูกค้าตรวจสอบ' }
                    ];
                case 'customer_review':
                    return [
                        { value: 'revision', label: 'ลูกค้าขอแก้ไข' },
                        { value: 'approved', label: 'ลูกค้าอนุมัติแล้ว' }
                    ];
                case 'revision':
                    return [
                        { value: 'in_progress', label: 'กำลังออกแบบ (แก้ไข)' },
                        { value: 'customer_review', label: 'ส่งให้ลูกค้าตรวจสอบอีกครั้ง' }
                    ];
                case 'approved':
                    return [
                        { value: 'revision', label: 'ลูกค้าขอแก้ไขใหม่' },
                        { value: 'production', label: 'ส่งไปยังระบบผลิต' }
                    ];
                case 'production':
                    return [
                        { value: 'approved', label: 'ย้อนกลับไปสถานะอนุมัติแล้ว' },
                        { value: 'completed', label: 'เสร็จสมบูรณ์' }
                    ];
                case 'completed':
                    return [
                        { value: 'production', label: 'ย้อนกลับไปสถานะการผลิต' }
                    ];
                default:
                    return [
                        { value: 'pending', label: 'รอออกแบบ' },
                        { value: 'in_progress', label: 'กำลังออกแบบ' },
                        { value: 'customer_review', label: 'ส่งให้ลูกค้าตรวจสอบ' },
                        { value: 'revision', label: 'ลูกค้าขอแก้ไข' },
                        { value: 'approved', label: 'ลูกค้าอนุมัติแล้ว' },
                        { value: 'production', label: 'ส่งไปยังระบบผลิต' },
                        { value: 'completed', label: 'เสร็จสมบูรณ์' }
                    ];
            }
        }
    });
    </script>
</body>
</html>