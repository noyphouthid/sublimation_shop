
<?php
session_start();
require_once 'db_connect.php';
require_once 'invoice_functions.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบว่ามี ID ที่ส่งมาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: invoice_list.php");
    exit;
}

$invoiceId = $_GET['id'];
$invoice = getInvoiceById($conn, $invoiceId);

if (!$invoice) {
    header("Location: invoice_list.php");
    exit;
}

// ดึงรายการสินค้า
$invoiceItems = getInvoiceItems($conn, $invoiceId);

// ดึงข้อมูลคิวออกแบบหรือคำสั่งซื้อ (ถ้ามี)
$designData = null;
$orderData = null;

if (!empty($invoice['design_id'])) {
    $designData = getDesignById($conn, $invoice['design_id']);
}

if (!empty($invoice['order_id'])) {
    $orderData = getOrderById($conn, $invoice['order_id']);
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ໃບສະເໜີລາຄາ #<?php echo $invoice['invoice_no']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        
        /* รูปแบบโลโก้ */
        .logo-image {
            max-width: 150px;
            height: auto;
            margin-bottom: 15px;
        }

        /* ปรับปรุงรูปแบบส่วนหัวใบเสนอราคา */
        .company-info {
            margin-bottom: 20px;
        }

        .company-info h4 {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .company-info p {
            margin-bottom: 5px;
            color: #555;
        }
        .logo-placeholder {
            width: 150px;
            height: 150px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background-color: white;
        }
        
        .invoice-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .invoice-table th, .invoice-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        
        .invoice-table th {
            background-color: #f5f5f5;
        }
        
        .invoice-total {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .special-size {
            color: #dc3545;
            font-weight: bold;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                padding: 0;
                margin: 0;
            }
            
            .invoice-container {
                width: 100%;
                max-width: 100%;
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <?php include 'navbar.php'; ?>
        
        <div class="container mt-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-file-invoice"></i> ໃບສະເໜີລາຄາ #<?php echo $invoice['invoice_no']; ?></h2>
                <div>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> ພິມ
                    </button>
                    <button class="btn btn-success" id="exportJpgBtn">
                        <i class=""fas fa-file-image"></i> ບັນທຶກເປັນ JPG
                    </button>
                    <a href="edit_invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> ແກ້ໄຂ
                    </a>
                   <a href="invoice_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> ກັບຄືນ
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="invoice-container" id="invoiceContent">
        <div class="invoice-header">
            <div class="row">
            
                   <div class="col-md-4">
                    <div class="company-info">
                        <img src="assets/LOGO.png" alt="ໂລໂກ້ຮ້ານ" class="logo-image">
                        <h4>ຮ້ານ ບີຈີ ສປອຮ໌ດ</h4>
                        <p>ຕັ້ງຢູ່: ບ. ຕານມີໄຊ ມ. ໄຊທານີ ນະຄອນຫຼວງວຽງຈັນ, ລາວ</p>
                        <p>ໂທ: 020 9220 1288-20 9258 2288</p>
                    </div>
                </div>
                <div class="col-md-8 text-end">
                    <h2 class="text-primary">ໃບປະເມີນລາຄາ</h2>
                    <p><strong>ເລກທີ:</strong> <?php echo $invoice['invoice_no']; ?></p>
                    <p><strong>ວັນທີ:</strong> <?php echo formatThaiDate($invoice['created_at']); ?></p>
                    <?php if ($designData): ?>
                        <p><strong>ລະຫັດການອອກແບບ:</strong> <?php echo $designData['queue_code']; ?></p>
                    <?php endif; ?>
                    <?php if ($orderData): ?>
                        <p><strong>ລະຫັດຄຳສັ່ງຊື້:</strong> <?php echo $orderData['order_code']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>ຂໍ້ມູນລູກຄ້າ</h5>
                <p><strong>ຊື່:</strong> <?php echo $invoice['customer_name']; ?></p>
                <p><strong>ເບີໂທ:</strong> <?php echo $invoice['customer_phone']; ?></p>
                <p><strong>ຊ່ອງທາງຕິດຕໍ່ອື່ນໆ:</strong> <?php echo $invoice['customer_contact']; ?></p>
            </div>
            <div class="col-md-6">
                <h5>ຂໍ້ມູນທີມ</h5>
                <p><strong>ຊື່ທີມ:</strong> <?php echo $invoice['team_name']; ?></p>
                <p><strong>ຜູ້ອອກໃບປະເມີນລາຄາ:</strong> <?php echo $invoice['created_by_name']; ?></p>
            </div>
        </div>
        
        <h5>ລາຍການສິນຄ້າ</h5>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ລາຍການ</th>
                    <th>ຈຳນວນ</th>
                    <th>ຂະໜາດ</th>
                    <th>ລາຄາຕໍ່ຜືນ</th>
                    <th>ລາຄາລວມ</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotal = 0;
                $totalQuantity = 0;
                foreach ($invoiceItems as $index => $item): 
                    $subtotal += $item['item_total'];
                    $totalQuantity += $item['quantity'];
                ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <strong><?php echo $item['fabric_name_lao']; ?></strong>
                            <?php if ($item['has_long_sleeve'] == 1): ?>
                                <br><small>- ແຂນຍາວ (+20,000₭)</small>
                            <?php endif; ?>
                            <?php if ($item['has_collar'] == 1): ?>
                                <br><small>- ຄໍປົກ (+20,000₭)</small>
                            <?php endif; ?>
                            <?php if ($item['additional_costs'] > 0): ?>
                                <br><small>- ຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມ: <?php echo number_format($item['additional_costs']); ?> ₭</small>
                            <?php endif; ?>
                            <?php if ($item['additional_notes']): ?>
                                <br><small>- <?php echo $item['additional_notes']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>
                            <?php if ($item['size_s'] > 0): ?>
                                S: <?php echo $item['size_s']; ?><br>
                            <?php endif; ?>
                            <?php if ($item['size_m'] > 0): ?>
                                M: <?php echo $item['size_m']; ?><br>
                            <?php endif; ?>
                            <?php if ($item['size_l'] > 0): ?>
                                L: <?php echo $item['size_l']; ?><br>
                            <?php endif; ?>
                            <?php if ($item['size_xl'] > 0): ?>
                                XL: <?php echo $item['size_xl']; ?><br>
                            <?php endif; ?>
                            <?php if ($item['size_2xl'] > 0): ?>
                                2XL: <?php echo $item['size_2xl']; ?><br>
                            <?php endif; ?>
                            <?php if ($item['size_3xl'] > 0): ?>
                                <span class="special-size">3XL: <?php echo $item['size_3xl']; ?> (+20,000₭)</span><br>
                            <?php endif; ?>
                            <?php if ($item['size_4xl'] > 0): ?>
                                <span class="special-size">4XL: <?php echo $item['size_4xl']; ?> (+25,000₭)</span><br>
                            <?php endif; ?>
                            <?php if ($item['size_5xl'] > 0): ?>
                                <span class="special-size">5XL: <?php echo $item['size_5xl']; ?> (+35,000₭)</span><br>
                            <?php endif; ?>
                            <?php if ($item['size_6xl'] > 0): ?>
                                <span class="special-size">6XL: <?php echo $item['size_6xl']; ?> (+35,000₭)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($item['base_price']); ?> ₭</td>
                        <td><?php echo number_format($item['item_total']); ?> ₭</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Calculate promo info (แสดงจำนวนเสื้อฟรี แต่ไม่คำนวณส่วนลด)
        $freeItems = floor($totalQuantity / 12);
        
        // คำนวณราคาโดยไม่ใช้ส่วนลดจากโปรโมชั่น
        $specialDiscount = !empty($invoice['special_discount']) ? $invoice['special_discount'] : 0;
        $grandTotal = $subtotal - $specialDiscount;
        $depositAmount = $grandTotal * 0.5;
        $remainingAmount = $grandTotal - $depositAmount;
        ?>
        
        <div class="row">
            <div class="col-md-7">
                <div class="alert alert-info">
                    <h6><i class="fas fa-gift"></i> ໂປຣໂມຊັ່ນ: ສັ່ງ 12 ແຖມ 1</h6>
                    <p class="mb-0">ຈຳນວນທັງໝົດ <?php echo $totalQuantity; ?> ຜືນ, ໄດ້ຮັບຟຣີ <?php echo $freeItems; ?> ຜືນ</p>
                </div>
                <?php if ($invoice['notes']): ?>
                    <div class="mt-3">
                        <h6>ໝາຍເຫດ:</h6>
                        <p><?php echo nl2br($invoice['notes']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-5">
                <div class="invoice-total">
                    <div class="row mb-2">
                        <div class="col-6">
                            <strong>ລາຄາທັງໝົດ:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?php echo number_format($subtotal); ?> ₭
                        </div>
                    </div>
                    <?php if (!empty($invoice['special_discount']) && $invoice['special_discount'] > 0): ?>
                    <div class="row mb-2">
                        <div class="col-6">
                            <strong>ຫັກມັດຈຳ:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?php echo number_format($specialDiscount); ?> ₭
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>ຍອດລວມທັງໝົດ:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <strong><?php echo number_format($grandTotal); ?> ₭</strong>
                        </div>
                    </div>
                    <hr>
                    <div class="row mb-2">
                        <div class="col-6">
                            <strong>ມັດຈຳ(50%):</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?php echo number_format($depositAmount); ?> ₭
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>ຄ້າງຊຳລະ:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <?php echo number_format($remainingAmount); ?> ₭
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-12 text-center">
                <p>*ຂອບໃຈສຳລັບການເລືອກໃຊ້ບໍລິການຮ້ານເສື້ອພິມລາຍຂອງພວກເຮົາ*</p>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
  <script>
    $(document).ready(function() {
        // Export to JPG
        $('#exportJpgBtn').click(function() {
            const invoice = document.getElementById('invoiceContent');

            html2canvas(invoice, {
                scale: 2
            }).then(canvas => {
                // แปลง canvas เป็น data URL (JPG)
                const imgData = canvas.toDataURL('image/jpeg', 1.0);

                // สร้างลิงก์ดาวน์โหลด
                const link = document.createElement('a');
                link.href = imgData;
                link.download = 'invoice-<?php echo $invoice['invoice_no']; ?>.jpg';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    });
</script>

</body>
</html>