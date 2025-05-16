<?php
require_once '../db_connect.php';
require_once 'invoice_functions.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ตรวจสอบว่ามี invoice_id ส่งมาหรือไม่
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    header("Location: invoice_list.php");
    exit();
}

// ดึงข้อมูลใบกำกับภาษี
$sql = "SELECT i.*, u.full_name as staff_name, dq.queue_code, dq.design_id 
        FROM invoices i 
        LEFT JOIN users u ON i.issued_by = u.user_id
        LEFT JOIN design_queue dq ON i.order_id = dq.order_id
        WHERE i.invoice_id = $invoice_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: invoice_list.php");
    exit();
}

$invoice = $result->fetch_assoc();

// ดึงรายการสินค้า
$sql = "SELECT * FROM invoice_items WHERE invoice_id = $invoice_id";
$itemsResult = $conn->query($sql);
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดใบกำกับภาษี - <?php echo $invoice['invoice_code']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <!-- นำเข้า navbar -->
    <?php include_once '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">รายละเอียดใบกำกับภาษี</h1>
                <h2 class="text-primary"><?php echo $invoice['invoice_code']; ?></h2>
            </div>
            <div class="d-flex gap-2">
                <a href="edit_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> แก้ไข
                </a>
                <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-success">
                    <i class="fas fa-print"></i> พิมพ์
                </a>
                <a href="invoice_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการ
                </a>
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
        
        <div class="row">
            <div class="col-md-8">
                <!-- ข้อมูลใบกำกับภาษี -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">ข้อมูลใบกำกับภาษี</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>รหัสใบกำกับภาษี:</strong> <?php echo htmlspecialchars($invoice['invoice_code']); ?></p>
                                <p><strong>ลูกค้า:</strong> <?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                                <p><strong>รหัสออเดอร์:</strong> <?php echo $invoice['queue_code'] ? htmlspecialchars($invoice['queue_code']) : '-'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>วันที่ออกใบกำกับภาษี:</strong> <?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></p>
                                <p><strong>ผู้ออกใบกำกับภาษี:</strong> <?php echo htmlspecialchars($invoice['staff_name']); ?></p>
                                <p>
                                    <strong>สถานะการชำระเงิน:</strong> 
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
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($invoice['design_id']): ?>
                            <div class="mt-3">
                                <a href="../view_design_queue.php?id=<?php echo $invoice['design_id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-link"></i> ดูรายละเอียดออเดอร์
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- รายการสินค้า -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">รายการสินค้า</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="30%">ประเภทผ้า</th>
                                        <th width="15%">ราคาต่อชิ้น</th>
                                        <th width="15%">จำนวน</th>
                                        <th width="15%">แถมฟรี</th>
                                        <th width="20%">ราคารวม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($item['fabric_type']); ?></td>
                                        <td class="text-end"><?php echo number_format($item['unit_price']); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-center"><?php echo $item['free_items']; ?></td>
                                        <td class="text-end"><?php echo number_format($item['subtotal']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>ยอดรวมทั้งหมด:</strong></td>
                                        <td class="text-end"><?php echo number_format($invoice['total_amount']); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>ส่วนลด:</strong></td>
                                        <td class="text-end"><?php echo number_format($invoice['discount_amount']); ?></td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="5" class="text-end"><strong>ยอดหลังหักส่วนลด:</strong></td>
                                        <td class="text-end"><strong><?php echo number_format($invoice['final_amount']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>มัดจำจ่อง 50%:</strong></td>
                                        <td class="text-end"><?php echo number_format($invoice['deposit_amount']); ?></td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="5" class="text-end"><strong>ยอดคงค้างจ่าย:</strong></td>
                                        <td class="text-end"><strong><?php echo number_format($invoice['remaining_amount']); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($invoice['notes'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">หมายเหตุ</h3>
                    </div>
                    <div class="card-body">
                        <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <!-- การดำเนินการ -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title">การดำเนินการ</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="edit_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> แก้ไขใบกำกับภาษี
                            </a>
                            <a href="print_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-success">
                                <i class="fas fa-print"></i> พิมพ์ใบกำกับภาษี
                            </a>
                            
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#updatePaymentModal">
                                <i class="fas fa-money-bill"></i> อัปเดตสถานะการชำระเงิน
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- สรุปการชำระเงิน -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">สรุปการชำระเงิน</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>ยอดรวมทั้งหมด:</span>
                            <strong><?php echo number_format($invoice['final_amount']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>ชำระแล้ว:</span>
                            <strong><?php 
                                if ($invoice['payment_status'] == 'fully_paid') {
                                    echo number_format($invoice['final_amount']);
                                } elseif ($invoice['payment_status'] == 'deposit_paid') {
                                    echo number_format($invoice['deposit_amount']);
                                } else {
                                    echo '0';
                                }
                            ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>คงค้างชำระ:</span>
                            <strong><?php 
                                if ($invoice['payment_status'] == 'fully_paid') {
                                    echo '0';
                                } elseif ($invoice['payment_status'] == 'deposit_paid') {
                                    echo number_format($invoice['remaining_amount']);
                                } else {
                                    echo number_format($invoice['final_amount']);
                                }
                            ?></strong>
                        </div>
                        
                        <hr>
                        
                        <div class="alert <?php 
                            if ($invoice['payment_status'] == 'fully_paid') {
                                echo 'alert-success';
                            } elseif ($invoice['payment_status'] == 'deposit_paid') {
                                echo 'alert-warning';
                            } else {
                                echo 'alert-danger';
                            }
                        ?> mb-0">
                            <strong>สถานะการชำระเงิน:</strong> 
                            <?php 
                                if ($invoice['payment_status'] == 'fully_paid') {
                                    echo 'ชำระเงินครบถ้วนแล้ว';
                                } elseif ($invoice['payment_status'] == 'deposit_paid') {
                                    echo 'ชำระมัดจำแล้ว';
                                } else {
                                    echo 'รอการชำระเงิน';
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal อัปเดตสถานะการชำระเงิน -->
    <div class="modal fade" id="updatePaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">อัปเดตสถานะการชำระเงิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="update_payment.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">สถานะปัจจุบัน:</label>
                            <div>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">เปลี่ยนสถานะเป็น:</label>
                            <select name="payment_status" class="form-select" required>
                                <option value="pending" <?php echo $invoice['payment_status'] == 'pending' ? 'selected' : ''; ?>>รอชำระเงิน</option>
                                <option value="deposit_paid" <?php echo $invoice['payment_status'] == 'deposit_paid' ? 'selected' : ''; ?>>ชำระมัดจำแล้ว</option>
                                <option value="fully_paid" <?php echo $invoice['payment_status'] == 'fully_paid' ? 'selected' : ''; ?>>ชำระเต็มจำนวนแล้ว</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ:</label>
                            <textarea name="payment_note" class="form-control" rows="3"></textarea>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>