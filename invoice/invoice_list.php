<?php
require_once '../db_connect.php';
require_once 'invoice_functions.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// กำหนดจำนวนรายการต่อหน้า
$perPage = 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// ดึงข้อมูลรายการใบกำกับภาษีทั้งหมด
$whereClause = "1=1";
$params = [];

// ตัวกรอง
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $whereClause .= " AND (i.invoice_code LIKE ? OR i.customer_name LIKE ? OR dq.queue_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = $_GET['status'];
    $whereClause .= " AND i.payment_status = ?";
    $params[] = $status;
}

// SQL สำหรับนับจำนวนทั้งหมด
$countSql = "SELECT COUNT(*) as total FROM invoices i 
             LEFT JOIN design_queue dq ON i.order_id = dq.order_id 
             WHERE $whereClause";

$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result()->fetch_assoc();
$total = $totalResult['total'];

// คำนวณจำนวนหน้าทั้งหมด
$totalPages = ceil($total / $perPage);

// SQL สำหรับดึงข้อมูล
$sql = "SELECT i.*, dq.queue_code, u.full_name as staff_name 
        FROM invoices i 
        LEFT JOIN design_queue dq ON i.order_id = dq.order_id 
        LEFT JOIN users u ON i.issued_by = u.user_id 
        WHERE $whereClause 
        ORDER BY i.issue_date DESC 
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $offset;
    $params[] = $perPage;
    $types = str_repeat('s', count($params) - 2) . 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $offset, $perPage);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการใบกำกับภาษี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <!-- นำเข้า navbar -->
    <?php include_once '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">รายการใบกำกับภาษี</h1>
            <a href="create_invoice.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> สร้างใบกำกับภาษีใหม่
            </a>
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
        
        <!-- การ์ดค้นหาและกรอง -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="invoice_list.php">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="ค้นหา รหัสใบกำกับภาษี, ชื่อลูกค้า, รหัสออเดอร์" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> ค้นหา
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="status" class="form-select">
                                <option value="">-- ทุกสถานะการชำระเงิน --</option>
                                <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>รอชำระเงิน</option>
                                <option value="deposit_paid" <?php echo (isset($_GET['status']) && $_GET['status'] === 'deposit_paid') ? 'selected' : ''; ?>>ชำระมัดจำแล้ว</option>
                                <option value="fully_paid" <?php echo (isset($_GET['status']) && $_GET['status'] === 'fully_paid') ? 'selected' : ''; ?>>ชำระเต็มจำนวนแล้ว</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> กรอง
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- ตารางรายการ -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>รหัสใบกำกับภาษี</th>
                                <th>รหัสออเดอร์</th>
                                <th>ลูกค้า</th>
                                <th>วันที่ออก</th>
                                <th>ยอดรวม</th>
                                <th>สถานะการชำระเงิน</th>
                                <th>ผู้ออกบิล</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($invoice = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['invoice_code']); ?></td>
                                        <td><?php echo $invoice['queue_code'] ? htmlspecialchars($invoice['queue_code']) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                                        <td><?php echo number_format($invoice['final_amount']); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            switch ($invoice['payment_status']) {
                                                case 'fully_paid':
                                                    $statusClass = 'bg-success';
                                                    $statusText = 'ชำระแล้ว';
                                                    break;
                                                case 'deposit_paid':
                                                    $statusClass = 'bg-warning';
                                                    $statusText = 'ชำระมัดจำแล้ว';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-danger';
                                                    $statusText = 'รอชำระ';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td><?php echo $invoice['staff_name'] ? htmlspecialchars($invoice['staff_name']) : '-'; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="print_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-3">ไม่พบรายการใบกำกับภาษี</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- การแบ่งหน้า -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>&status=<?php echo isset($_GET['status']) ? urlencode($_GET['status']) : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>&status=<?php echo isset($_GET['status']) ? urlencode($_GET['status']) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>&status=<?php echo isset($_GET['status']) ? urlencode($_GET['status']) : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>