<?php
session_start();
require_once 'db_connect.php';
require_once 'invoice_functions.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ดึงรายการ invoice โดยใช้ฟังก์ชันที่รองรับ custom queue code
$invoices = getAllInvoicesWithQueue($conn);

// ตัวกรองสถานะ
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchKeyword = isset($_GET['search']) ? $_GET['search'] : '';

if ($statusFilter || $searchKeyword) {
    // สร้าง query สำหรับกรองข้อมูล
    $query = "SELECT i.*, u.full_name as created_by_name,
                     COALESCE(dq.queue_code, i.custom_queue_code, 'ບໍ່ມີລະຫັດຄິວ') as display_queue_code,
                     CASE 
                         WHEN i.design_id IS NOT NULL THEN 'design'
                         WHEN i.custom_queue_code IS NOT NULL THEN 'custom'
                         ELSE 'none'
                     END as queue_type
              FROM invoices i 
              LEFT JOIN users u ON i.created_by = u.user_id 
              LEFT JOIN design_queue dq ON i.design_id = dq.design_id
              WHERE 1=1";
    
    if ($statusFilter) {
        $query .= " AND i.status = '" . $conn->real_escape_string($statusFilter) . "'";
    }
    
    if ($searchKeyword) {
        $keyword = $conn->real_escape_string($searchKeyword);
        $query .= " AND (i.invoice_no LIKE '%$keyword%' 
                      OR i.customer_name LIKE '%$keyword%'
                      OR i.team_name LIKE '%$keyword%'
                      OR dq.queue_code LIKE '%$keyword%'
                      OR i.custom_queue_code LIKE '%$keyword%')";
    }
    
    $query .= " ORDER BY i.created_at DESC";
    
    $result = $conn->query($query);
    $invoices = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }
    }
}

// คำนวณสถิติ
$totalInvoices = count($invoices);
$draftCount = count(array_filter($invoices, function($inv) { return $inv['status'] === 'draft'; }));
$sentCount = count(array_filter($invoices, function($inv) { return $inv['status'] === 'sent'; }));
$paidCount = count(array_filter($invoices, function($inv) { return $inv['status'] === 'paid'; }));
$totalAmount = array_sum(array_column($invoices, 'total_amount'));
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ລາຍການໃບສະເໜີລາຄາ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
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

        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }

        .queue-type-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }

        .stats-card {
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- สถิติรวม -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ທັງໝົດ</h6>
                                <h2 class="mb-0"><?php echo $totalInvoices; ?></h2>
                                <small>ໃບສະເໜີລາຄາ</small>
                            </div>
                            <i class="fas fa-file-invoice fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ຮ່າງ</h6>
                                <h2 class="mb-0"><?php echo $draftCount; ?></h2>
                                <small>ຍັງບໍ່ສົ່ງ</small>
                            </div>
                            <i class="fas fa-edit fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ສົ່ງແລ້ວ</h6>
                                <h2 class="mb-0"><?php echo $sentCount; ?></h2>
                                <small>ຖ້າຊຳລະ</small>
                            </div>
                            <i class="fas fa-paper-plane fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title">ຊຳລະແລ້ວ</h6>
                                <h2 class="mb-0"><?php echo $paidCount; ?></h2>
                                <small><?php echo number_format($totalAmount); ?> ₭</small>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-file-invoice"></i> ລາຍການໃບສະເໜີລາຄາ</h2>
            </div>
            <div class="col text-end">
                <div class="btn-group">
                    <a href="add_invoice_independent.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> ສ້າງໃບສະເໜີລາຄາໃໝ່
                    </a>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="add_invoice_independent.php">
                            <i class="fas fa-edit"></i> ໃບສະເໜີລາຄາອິສະລະ
                        </a></li>
                        <li><a class="dropdown-item" href="add_invoice.php">
                            <i class="fas fa-paint-brush"></i> ຈາກຄິວອອກແບບ
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ตัวกรอง -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">ສະຖານະ:</label>
                        <select name="status" class="form-select">
                            <option value="">ທັງໝົດ</option>
                            <option value="draft" <?php echo $statusFilter == 'draft' ? 'selected' : ''; ?>>ສະບັບຮ່າງ</option>
                            <option value="sent" <?php echo $statusFilter == 'sent' ? 'selected' : ''; ?>>ສົ່ງແລ້ວ</option>
                            <option value="paid" <?php echo $statusFilter == 'paid' ? 'selected' : ''; ?>>ຊຳລະແລ້ວ</option>
                            <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>ຍົກເລີກ</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ຄົ້ນຫາ:</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchKeyword); ?>" 
                               placeholder="ເລກທີໃບສະເໜີລາຄາ, ລະຫັດຄິວ, ຊື່ລູກຄ້າ, ທີມ...">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> ຄົ້ນຫາ
                            </button>
                            <a href="invoice_list.php" class="btn btn-outline-secondary">
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
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ເລກທີ</th>
                                <th>ລະຫັດຄິວ</th>
                                <th>ລູກຄ້າ</th>
                                <th>ທີມ</th>
                                <th>ຍອດລວມ</th>
                                <th>ມັດຈຳ</th>
                                <th>ສະຖານະ</th>
                                <th>ຜູ້ສ້າງ</th>
                                <th>ວັນທີ</th>
                                <th>ຈັດການ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>ບໍ່ມີຂໍ້ມູນໃບສະເໜີລາຄາ</p>
                                            <?php if ($statusFilter || $searchKeyword): ?>
                                                <a href="invoice_list.php" class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="fas fa-sync"></i> ລ້າງຕົວກັ່ນ
                                                </a>
                                            <?php else: ?>
                                                <a href="add_invoice_independent.php" class="btn btn-sm btn-primary mt-2">
                                                    <i class="fas fa-plus"></i> ສ້າງໃບສະເໜີລາຄາໃໝ່
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo $invoice['invoice_no']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span><?php echo $invoice['display_queue_code']; ?></span>
                                                <?php if ($invoice['queue_type'] === 'design'): ?>
                                                    <span class="badge bg-info queue-type-badge ms-2" title="ຈາກຄິວອອກແບບ">
                                                        <i class="fas fa-paint-brush"></i> ຄິວ
                                                    </span>
                                                <?php elseif ($invoice['queue_type'] === 'custom'): ?>
                                                    <span class="badge bg-secondary queue-type-badge ms-2" title="ລະຫັດຄິວອິສະລະ">
                                                        <i class="fas fa-edit"></i> ອິສະລະ
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['team_name']); ?></td>
                                        <td><?php echo number_format($invoice['total_amount']); ?> ₭</td>
                                        <td><?php echo number_format($invoice['deposit_amount']); ?> ₭</td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusText = '';
                                            
                                            switch ($invoice['status']) {
                                                case 'draft':
                                                    $statusClass = 'bg-secondary';
                                                    $statusText = 'ສະບັບຮ່າງ';
                                                    break;
                                                case 'sent':
                                                    $statusClass = 'bg-primary';
                                                    $statusText = 'ສົ່ງແລ້ວ';
                                                    break;
                                                case 'paid':
                                                    $statusClass = 'bg-success';
                                                    $statusText = 'ຊຳລະແລ້ວ';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'bg-danger';
                                                    $statusText = 'ຍົກເລີກ';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-secondary';
                                                    $statusText = $invoice['status'];
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> status-badge">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['created_by_name']); ?></td>
                                        <td><?php echo formatThaiDate($invoice['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-info" title="ເບີ່ງລາຍລະອຽດ">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($invoice['status'] !== 'paid'): ?>
                                                <a href="edit_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-warning" title="ແກ້ໄຂ">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <!-- ปุ่มเปลี่ยนสถานะ -->
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown" title="ປ່ຽນສະຖານະ">
                                                        <i class="fas fa-arrow-right"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if ($invoice['status'] === 'draft'): ?>
                                                            <li><a class="dropdown-item" href="update_invoice_status.php?id=<?php echo $invoice['invoice_id']; ?>&status=sent">
                                                                <i class="fas fa-paper-plane text-primary"></i> ສົ່ງໃຫ້ລູກຄ້າ
                                                            </a></li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($invoice['status'] === 'sent'): ?>
                                                            <li><a class="dropdown-item" href="update_invoice_status.php?id=<?php echo $invoice['invoice_id']; ?>&status=paid">
                                                                <i class="fas fa-check text-success"></i> ລູກຄ້າຊຳລະແລ້ວ
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="update_invoice_status.php?id=<?php echo $invoice['invoice_id']; ?>&status=draft">
                                                                <i class="fas fa-undo text-secondary"></i> ຍ້ອນກັບສະບັບຮ່າງ
                                                            </a></li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($invoice['status'], ['draft', 'sent'])): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-danger" href="update_invoice_status.php?id=<?php echo $invoice['invoice_id']; ?>&status=cancelled">
                                                                <i class="fas fa-times text-danger"></i> ຍົກເລີກ
                                                            </a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>

                                                <?php if ($invoice['queue_type'] === 'design' && !empty($invoice['design_id'])): ?>
                                                    <a href="view_design_queue.php?id=<?php echo $invoice['design_id']; ?>" class="btn btn-sm btn-outline-primary" title="ເບີ່ງຄິວອອກແບບ">
                                                        <i class="fas fa-paint-brush"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $invoice['created_by']) && $invoice['status'] !== 'paid'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger delete-invoice" 
                                                            data-id="<?php echo $invoice['invoice_id']; ?>"
                                                            data-no="<?php echo $invoice['invoice_no']; ?>"
                                                            data-customer="<?php echo htmlspecialchars($invoice['customer_name']); ?>"
                                                            title="ລຶບ">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal for Delete Confirmation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">ຢືນຢັນການລຶບ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                    </div>
                    
                    <p class="text-center">ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບໃບສະເໜີລາຄານີ້?</p>
                    
                    <div class="alert alert-info">
                        <strong>ເລກທີ:</strong> <span id="deleteInvoiceNo"></span><br>
                        <strong>ລູກຄ້າ:</strong> <span id="deleteCustomerName"></span>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> ຄຳເຕືອນ:</h6>
                        <ul class="mb-0">
                            <li>ການດຳເນີນການນີ້ບໍ່ສາມາດຍົກເລີກໄດ້</li>
                            <li>ໃບສະເໜີລາຄາແລະລາຍການສິນຄ້າທັງໝົດຈະຖືກລຶບ</li>
                            <li>ຂໍ້ມູນທີ່ລຶບແລ້ວຈະບໍ່ສາມາດກູ້ຄືນໄດ້</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                    <a href="#" class="btn btn-danger" id="confirmDelete">
                        <i class="fas fa-trash"></i> ຢືນຢັນການລຶບ
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Delete confirmation
            $('.delete-invoice').click(function() {
                const invoiceId = $(this).data('id');
                const invoiceNo = $(this).data('no');
                const customerName = $(this).data('customer');
                
                $('#deleteInvoiceNo').text(invoiceNo);
                $('#deleteCustomerName').text(customerName);
                $('#confirmDelete').attr('href', 'delete_invoice.php?id=' + invoiceId);
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert-dismissible').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>