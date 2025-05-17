<?php
session_start();
require_once 'db_connect.php';
require_once 'invoice_functions.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ดึงรายการ invoice
$sql = "SELECT i.*, u.full_name as created_by_name 
        FROM invoices i 
        LEFT JOIN users u ON i.created_by = u.user_id 
        ORDER BY i.created_at DESC";

$result = $conn->query($sql);
$invoices = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
}
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-file-invoice"></i> ລາຍການໃບສະເໜີລາຄາ</h2>
            </div>
            <div class="col text-end">
                <a href="add_invoice.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> ສ້າງໃບສະເໜີລາຄາໃໝ່
                </a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ລະຫັດ</th>
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
                                    <td colspan="9" class="text-center">ບໍ່ມີຂໍ້ມູນໃບສະເໜີລາຄາ</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo $invoice['invoice_no']; ?></td>
                                        <td><?php echo $invoice['customer_name']; ?></td>
                                        <td><?php echo $invoice['team_name']; ?></td>
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
                                        <td><?php echo $invoice['created_by_name']; ?></td>
                                        <td><?php echo formatThaiDate($invoice['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger delete-invoice" data-id="<?php echo $invoice['invoice_id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
                <div class="modal-header">
                    <h5 class="modal-title">ຢືນຢັນການລຶບ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບໃບສະເໜີລາຄານີ້?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ຍົກເລີກ</button>
                    <a href="#" class="btn btn-danger" id="confirmDelete">ລຶບ</a>
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
                $('#confirmDelete').attr('href', 'delete_invoice.php?id=' + invoiceId);
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
        });
    </script>
</body>
</html>