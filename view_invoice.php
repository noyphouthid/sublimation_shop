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

// ใช้ฟังก์ชันที่รองรับ custom queue code
$invoice = getInvoiceByIdWithQueue($conn, $invoiceId);

if (!$invoice) {
    header("Location: invoice_list.php");
    exit;
}

// ดึงรายการสินค้า
$invoiceItems = getInvoiceItems($conn, $invoiceId);

// ดึงข้อมูลคิวออกแบบ (ถ้ามี)
$designData = null;
if (!empty($invoice['design_id'])) {
    $designData = getDesignById($conn, $invoice['design_id']);
}

// คำนวณยอดรวม
$subtotal = 0;
foreach ($invoiceItems as $item) {
    $subtotal += $item['item_total'];
}

$totalAfterDiscount = $subtotal - $invoice['special_discount'];
$depositAmount = $invoice['deposit_amount'];
$remainingAmount = $totalAfterDiscount - $depositAmount;
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ໃບປະເມີນລາຄາ #<?php echo $invoice['invoice_no']; ?></title>
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

        /* 5x7 inch format (800x1000px) */
        .invoice-content {
            width: 800px;
            min-height: 1000px;
            max-width: 800px;
            background: white;
            margin: 0 auto;
            padding: 25px;
            font-size: 20px;
            line-height: 1.4;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .logo-image {
            max-width: 120px;
            height: auto;
        }

        .invoice-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .company-info h4 {
            color:rgb(0, 0, 0);
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-info p {
            margin-bottom: 3px;
            color: #666;
            font-size: 16px;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
            text-align: right;
            margin-bottom: 15px;
        }

        .invoice-details {
            font-size: 18px;
            text-align: right;
        }

        .queue-code-badge {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 16px;
            display: inline-block;
        }

        .customer-section {
            margin: 20px 0;
        }

        .customer-title {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 8px;
        }

        .customer-info {
            font-size: 18px;
            line-height: 1.5;
        }

        .items-table {
            width: 100%;
            font-size: 16px;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .items-table th {
            background-color: #f8f9fa;
            padding: 12px 8px;
            text-align: center;
            border: 1px solid #dee2e6;
            font-size: 16px;
            font-weight: bold;
        }

        .items-table td {
            padding: 12px 8px;
            vertical-align: top;
            
            border: 1px solid #dee2e6;
            font-size: 16px;
        }

        .item-description {
            font-size: 16px;
            line-height: 1.4;
        }

        .size-text {
            font-size: 14px;
            color: #666;
            line-height: 1.3;
        }

        /* สำหรับการแสดงผลไซส์พิเศษ */
        .size-text .text-warning {
            font-weight: bold;
            color: #f39c12 !important;
        }

        /* สำหรับการแสดงผลค่าใช้จ่ายเพิ่มเติม */
        .additional-cost-text {
            font-size: 13px;
            color: #e74c3c;
            font-weight: bold;
            font-style: italic;
        }

        .promo-box {
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            padding: 12px;
            margin: 15px 0;
            font-size: 16px;
            text-align: center;
        }

        .summary-section {
            margin-top: 20px;
            text-align: right;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 18px;
        }

        .summary-row.total {
            font-weight: bold;
            border-top: 2px solid #ddd;
            padding-top: 8px;
            margin-top: 10px;
            font-size: 20px;
        }

        .summary-row.deposit {
            color:rgb(0, 0, 0);
            font-weight: bold;
            font-size: 19px;
        }

        .summary-row.remaining {
            color:rgb(226, 73, 73);
            font-weight: bold;
            font-size: 19px;
        }

        .footer-note {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
            text-align: center;
            border-top: 1px dashed #ccc;
            padding-top: 15px;
        }

        .btn-controls {
            margin-bottom: 15px;
        }

        .btn-sm {
            font-size: 12px;
            padding: 4px 8px;
        }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
            .invoice-content { 
                box-shadow: none; 
                margin: 0; 
                padding: 20px;
                width: 800px;
                min-height: 1000px;
            }
            @page {
                size: A4;
                margin: 0.5in;
            }
            /* ปรับสีให้เข้ากับการพิมพ์ */
            .size-text .text-warning {
                color: #666 !important;
                font-weight: bold;
            }
            .additional-cost-text {
                color: #333 !important;
            }
        }

        /* Loading overlay */
        .export-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container-fluid no-print">
        <div class="d-flex justify-content-between align-items-center btn-controls">
            <h5><i class="fas fa-file-invoice"></i> ໃບປະເມີນລາຄາ #<?php echo $invoice['invoice_no']; ?></h5>
            <div class="d-flex gap-2">
                <a href="edit_invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i> ແກ້ໄຂ
                </a>
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fas fa-print"></i> ພິມ
                </button>
                <button onclick="exportAsImage()" class="btn btn-success btn-sm">
                    <i class="fas fa-image"></i> ບັນທຶກຮູບ
                </button>
                <a href="invoice_list.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-list"></i> ກັບໄປ
                </a>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div class="export-loading" id="exportLoading">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h5>ກຳລັງສ້າງຮູບພາບ...</h5>
            <p class="text-muted">ກະລຸນາລໍຖ້າຊັ່ວຄາວ</p>
        </div>
    </div>

    <div class="invoice-content" id="invoiceContent">
        <!-- Header -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-6">
                    <img src="assets/LOGO.png" alt="ໂລໂກ້ຮ້ານ" class="logo-image mb-1">
                    <div class="company-info">
                        <h4>ຮ້ານ ບີຈີ ສປອຮ໌ດ</h4>
                        <p>ທີ່ຢູ່: ບ. ສາຍນ້ຳເງິນ ມ. ໄຊທານີ ນະຄອນຫຼວງວຽງຈັນ</p>
                        <p>ໂທ: 020 922 012 88 - 20 92 58 22 88</p>
                    </div>
                </div>
                <div class="col-6">
                    <div class="invoice-title">ໃບປະເມີນລາຄາ</div>
                    <div class="invoice-details">
                        <div><strong>ເລກທີ:</strong> <?php echo $invoice['invoice_no']; ?></div>
                        <div><strong>ວັນທີ:</strong> <?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></div>
                        <?php if (!empty($invoice['display_queue_code'])): ?>
                        <div style="margin-top: 4px;">
                            <span class="queue-code-badge">
                                <?php echo $invoice['display_queue_code']; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="customer-section">
            <div class="row">
                <div class="col-6">
                    <div class="customer-title">ຂໍ້ມູນລູກຄ້າ</div>
                    <div class="customer-info">
                        <div><strong>ຊື່:</strong> <?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                        <div><strong>ທີມ:</strong> <?php echo htmlspecialchars($invoice['team_name']); ?></div>
                        <div><strong>ເບີໂທ:</strong> <?php echo htmlspecialchars($invoice['customer_phone']); ?></div>
                        <?php if ($invoice['customer_contact']): ?>
                        <div><strong>ຕິດຕໍ່ຜ່ານ:</strong> <?php echo htmlspecialchars($invoice['customer_contact']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-6">
                    <div class="customer-title">ຂໍ້ມູນໃບປະເມີນ</div>
                    <div class="customer-info">
                        <div><strong>ຜູ້ອອກໃບປະເມີນລາຄາ:</strong> <?php echo htmlspecialchars($invoice['created_by_name']); ?></div>
                        <?php if ($designData): ?>
                        <div class="no-print" style="margin-top: 4px;">
                            <a href="view_design_queue.php?id=<?php echo $designData['design_id']; ?>" style="font-size: 14px; color: #007bff;">
                                <i class="fas fa-eye"></i> ດູຄິວອອກແບບ
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="6%">#</th>
                    <th width="30%">ລາຍການ</th>
                    <th width="10%">ຈຳນວນ</th>
                    <th width="15%">ລາຄາຕໍ່ຜືນ</th>
                    <th width="24%">ຂະໜາດ</th>
                    <th width="15%">ລາຄາລວມ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceItems as $index => $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td>
                            <div class="item-description">
                                <strong><?php echo htmlspecialchars($item['fabric_name_lao']); ?></strong>
                                <?php
                                $details = [];
                                if ($item['has_long_sleeve']) $details[] = 'ແຂນຍາວ';
                                if ($item['has_collar']) $details[] = 'ຄໍປົກ';
                                if (!empty($details)) {
                                    echo '<br> ' . implode(', ', $details);
                                }
                                ?>
                                
                                <?php if ($item['additional_costs'] > 0): ?>
                                    <br><span class="additional-cost-text">
                                        <i class="fas fa-plus-circle"></i> ຄ່າໃຊ້ຈ່າຍເພີ່ມ: <?php echo number_format($item['additional_costs']); ?> ກີບ
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($item['additional_notes']): ?>
                                    <br><em style="font-size: 14px;"><?php echo htmlspecialchars($item['additional_notes']); ?></em>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-end"><?php echo number_format($item['base_price']); ?> ₭</td>
                        <td>
                            <div class="size-text">
                                <?php
                                $sizes = [];
                                
                                // ไซส์ปกติ
                                if ($item['size_s'] > 0) $sizes[] = "S: {$item['size_s']}";
                                if ($item['size_m'] > 0) $sizes[] = "M: {$item['size_m']}";
                                if ($item['size_l'] > 0) $sizes[] = "L: {$item['size_l']}";
                                if ($item['size_xl'] > 0) $sizes[] = "XL: {$item['size_xl']}";
                                if ($item['size_2xl'] > 0) $sizes[] = "2XL: {$item['size_2xl']}";
                                
                                // ไซส์พิเศษ พร้อมแสดงราคาเพิ่ม
                                $specialSizes = [];
                                if ($item['size_3xl'] > 0) {
                                    $extraCost = $item['size_3xl'] * 20000;
                                    $specialSizes[] = "3XL: {$item['size_3xl']} <span class='text-warning' style='font-size: 12px;'>(" . number_format($extraCost) . "ກີບ)</span>";
                                }
                                if ($item['size_4xl'] > 0) {
                                    $extraCost = $item['size_4xl'] * 25000;
                                    $specialSizes[] = "4XL: {$item['size_4xl']} <span class='text-warning' style='font-size: 12px;'>(" . number_format($extraCost) . "ກີບ)</span>";
                                }
                                if ($item['size_5xl'] > 0) {
                                    $extraCost = $item['size_5xl'] * 35000;
                                    $specialSizes[] = "5XL: {$item['size_5xl']} <span class='text-warning' style='font-size: 12px;'>(" . number_format($extraCost) . "ກີບ)</span>";
                                }
                                if ($item['size_6xl'] > 0) {
                                    $extraCost = $item['size_6xl'] * 35000;
                                    $specialSizes[] = "6XL: {$item['size_6xl']} <span class='text-warning' style='font-size: 12px;'>(" . number_format($extraCost) . "ກີບ)</span>";
                                }
                                
                                // รวมไซส์ทั้งหมด
                                $allSizes = array_merge($sizes, $specialSizes);
                                
                                echo !empty($allSizes) ? implode('<br>', $allSizes) : '-';
                                ?>
                            </div>
                        </td>
                        <td class="text-end"><?php echo number_format($item['item_total']); ?> ₭</td>
                    </tr>
                    
                    <?php if ($item['quantity'] >= 12): ?>
                        <?php $freeItems = floor($item['quantity'] / 12); ?>
                        <tr>
                            <td colspan="6">
                                <div class="promo-box">
                                    <i class="fas fa-gift"></i> <strong>ໂປຣໂມຊັ່ນ: ສັ່ງ 12 ແຖມ 1</strong><br>
                                    ຈຳນວນທັງໝົດ <?php echo $item['quantity']; ?> ຜືນ, ໄດ້ຮັບຟຣີ <?php echo $freeItems; ?> ຜືນ
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary-section">
            <div class="summary-row">
                <span>ລາຄາທັງໝົດ:</span>
                <span><?php echo number_format($subtotal); ?> ₭</span>
            </div>
            <?php if ($invoice['special_discount'] > 0): ?>
            <div class="summary-row">
                <span>ຫັກມັດຈຳ:</span>
                <span class="text-danger">-<?php echo number_format($invoice['special_discount']); ?> ₭</span>
            </div>
            <?php endif; ?>
            <div class="summary-row total">
                <span>ຍອດລວມທັງໝົດ:</span>
                <span><?php echo number_format($totalAfterDiscount); ?> ₭</span>
            </div>
            <div class="summary-row deposit">
                <span>ມັດຈຳ(50%):</span>
                <span><?php echo number_format($depositAmount); ?> ₭</span>
            </div>
            <div class="summary-row remaining">
                <span>ຄ້າງຈ່າຍ:</span>
                <span><?php echo number_format($remainingAmount); ?> ₭</span>
            </div>
        </div>

        <?php if ($invoice['notes']): ?>
        <div style="margin-top: 10px; font-size: 16px;">
            <strong>ໝາຍເຫດ:</strong> <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-note">
            *ລົບກວນເຊັກລາຍລະອຽດບິນໃຫ້ຊັດເຈນກ່ອນຊຳລະເງິນ ຫາກຊຳລະແລ້ວ<br>ບໍ່ສາມາດຄືນມັດຈຳເຕັມຈຳນວນໄດ້ ຫາກຕ້ອງການຍົກເລີກຈະເສຍຄ່າເຂົ້າຄິວ ແລະ ຄ່າແບບ 150,000ກີບ ຂໍຂອບໃຈ*
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        async function exportAsImage() {
            const loading = document.getElementById('exportLoading');
            const content = document.getElementById('invoiceContent');
            
            try {
                loading.style.display = 'flex';
                await document.fonts.ready;
                
                const canvas = await html2canvas(content, {
                    scale: 2, // ปรับความละเอียดให้เหมาะสมกับขนาดใหม่
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    width: content.scrollWidth,
                    height: content.scrollHeight,
                    scrollX: 0,
                    scrollY: 0,
                    windowWidth: 800,
                    windowHeight: 1000
                });
                
                const link = document.createElement('a');
                link.download = 'ໃບປະເມີນລາຄາ_<?php echo $invoice['invoice_no']; ?>_' + new Date().getTime() + '.png';
                link.href = canvas.toDataURL('image/png', 1.0);
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                setTimeout(() => {
                    alert('ບັນທຶກຮູບສຳເລັດແລ້ວ!');
                }, 500);
                
            } catch (error) {
                console.error('Export error:', error);
                alert('ເກີດຂໍ້ຜິດພາດໃນການສ້າງຮູບ ກະລຸນາລອງໃໝ່');
            } finally {
                loading.style.display = 'none';
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                exportAsImage();
            }
        });
    </script>
</body>
</html>