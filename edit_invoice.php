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
$invoice = getInvoiceByIdWithQueue($conn, $invoiceId);

if (!$invoice) {
    header("Location: invoice_list.php");
    exit;
}

// ดึงรายการสินค้า
$invoiceItems = getInvoiceItems($conn, $invoiceId);

// ดึงข้อมูลประเภทผ้า
$fabricTypes = getAllFabricTypes($conn);

// ดึงข้อมูลคิวออกแบบ (สำหรับ autocomplete)
$designsQuery = "SELECT design_id, queue_code, customer_name, team_name FROM design_queue ORDER BY created_at DESC LIMIT 50";
$designsResult = $conn->query($designsQuery);
$existingDesigns = [];
while ($row = $designsResult->fetch_assoc()) {
    $existingDesigns[] = $row;
}

// ดึงข้อมูลคิวออกแบบหรือคำสั่งซื้อ (ถ้ามี)
$designData = null;
$orderData = null;

if (!empty($invoice['design_id'])) {
    $designData = getDesignById($conn, $invoice['design_id']);
}

if (!empty($invoice['order_id'])) {
    $orderData = getOrderById($conn, $invoice['order_id']);
}

// บันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $invoiceData = [
        'invoice_id' => $invoiceId,
        'order_id' => $_POST['order_id'] ? $_POST['order_id'] : null,
        'design_id' => !empty($_POST['existing_design_id']) ? $_POST['existing_design_id'] : $invoice['design_id'],
        'custom_queue_code' => !empty($_POST['custom_queue_code']) ? $_POST['custom_queue_code'] : null,
        'customer_name' => $_POST['customer_name'],
        'customer_phone' => $_POST['customer_phone'],
        'customer_contact' => $_POST['customer_contact'],
        'team_name' => $_POST['team_name'],
        'deposit_amount' => $_POST['deposit_amount'],
        'total_amount' => $_POST['total_amount'],
        'special_discount' => $_POST['special_discount'],
        'notes' => $_POST['notes'],
        'status' => $invoice['status'],
        'created_by' => $invoice['created_by']
    ];
    
    // รับข้อมูลรายการสินค้า
    $invoiceItemsData = [];
    for ($i = 0; $i < count($_POST['fabric_id']); $i++) {
        if (empty($_POST['fabric_id'][$i])) continue;
        
        $invoiceItemsData[] = [
            'fabric_id' => $_POST['fabric_id'][$i],
            'quantity' => $_POST['quantity'][$i],
            'has_long_sleeve' => isset($_POST['has_long_sleeve'][$i]) ? 1 : 0,
            'has_collar' => isset($_POST['has_collar'][$i]) ? 1 : 0,
            'size_s' => $_POST['size_s'][$i],
            'size_m' => $_POST['size_m'][$i],
            'size_l' => $_POST['size_l'][$i],
            'size_xl' => $_POST['size_xl'][$i],
            'size_2xl' => $_POST['size_2xl'][$i],
            'size_3xl' => $_POST['size_3xl'][$i],
            'size_4xl' => $_POST['size_4xl'][$i],
            'size_5xl' => $_POST['size_5xl'][$i],
            'size_6xl' => $_POST['size_6xl'][$i],
            'additional_costs' => $_POST['additional_costs'][$i],
            'additional_notes' => $_POST['additional_notes'][$i],
            'item_total' => $_POST['item_total'][$i]
        ];
    }
    
    $result = saveIndependentInvoice($conn, $invoiceData, $invoiceItemsData);
    
    if ($result['success']) {
        header("Location: view_invoice.php?id=" . $result['invoice_id']);
        exit;
    } else {
        $errorMessage = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ແກ້ໄຂໃບສະເໜີລາຄາ #<?php echo $invoice['invoice_no']; ?></title>
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

        .logo-image {
            max-width: 150px;
            height: auto;
            margin-bottom: 15px;
        }

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
        
        .item-row {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .free-items {
            background-color: #e9f7ef;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }

        .queue-code-display {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: bold;
            display: inline-block;
            margin: 5px 0;
        }

        .queue-type-indicator {
            font-size: 0.8em;
            opacity: 0.9;
            display: block;
            margin-top: 2px;
        }

        .queue-code-option {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .queue-code-option:hover {
            border-color: #007bff;
            background-color: #f8f9ff;
        }

        .queue-code-option.active {
            border-color: #007bff;
            background-color: #e7f3ff;
        }

        .autocomplete-suggestions {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            width: 100%;
        }

        .autocomplete-suggestion {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .autocomplete-suggestion:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-edit"></i> ແກ້ໄຂໃບສະເໜີລາຄາ #<?php echo $invoice['invoice_no']; ?></h2>
            </div>
        </div>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
        <?php endif; ?>
        
        <form method="post" id="invoiceForm">
            <div class="row">
                <div class="col-md-4">
                    <div class="company-info">
                        <img src="assets/LOGO.png" alt="ໂລໂກ້ຮ້ານ" class="logo-image">
                        <h4>ຮ້ານ ບີຈີ ສປອຮ໌ດ ເສື້ອພິມລາຍ</h4>
                        <p>ທີ່ຢູ່: ບ. ສາຍນ້ຳເງິນ ມ. ໄຊທານີ ນະຄອນຫຼວງວຽງຈັນ, ລາວ</p>
                        <p>ໂທ: 020 922 012 88 - 20 92 58 22 88</p>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">ຂໍ້ມູນໃບສະເໜີລາຄາ</h5>
                        </div>
                        <div class="card-body">
                            <!-- แสดงรหัสคิวปัจจุบัน -->
                            <?php if (!empty($invoice['display_queue_code'])): ?>
                            <div class="mb-3">
                                <label class="form-label">ລະຫັດຄິວປະຈຸບັນ:</label>
                                <div>
                                    <span class="queue-code-display">
                                        <?php echo $invoice['display_queue_code']; ?>
                                        <span class="queue-type-indicator">
                                            <?php if (!empty($invoice['design_queue_code'])): ?>
                                                ຄິວອອກແບບ
                                            <?php elseif (!empty($invoice['custom_queue_code'])): ?>
                                                ລະຫັດອິສະລະ
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- ตัวเลือกรหัสคิว (ถ้าเป็นใบเสนอราคาอิสระ) -->
                            <?php if (!empty($invoice['custom_queue_code']) || empty($invoice['design_id'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label">ตัวเลือกรหัสคิว:</label>
                                    
                                    <!-- ตัวเลือก 1: ใช้คิวที่มีอยู่แล้ว -->
                                    <div class="queue-code-option" data-option="existing">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="queue_option" id="existing_queue" value="existing" <?php echo !empty($invoice['design_id']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="existing_queue">
                                                <strong><i class="fas fa-search"></i> ເລືອກຈາກຄິວທີ່ມີຢູ່ແລ້ວ</strong>
                                            </label>
                                        </div>
                                        <div class="mt-2" id="existing_queue_section" style="<?php echo !empty($invoice['design_id']) ? '' : 'display: none;'; ?>">
                                            <div class="position-relative">
                                                <input type="text" class="form-control" id="design_search" placeholder="ຄົ້ນຫາລະຫັດຄິວ ຫຼື ຊື່ລູກຄ້າ..." value="<?php echo $designData ? $designData['queue_code'] : ''; ?>">
                                                <div id="autocomplete_results" class="autocomplete-suggestions" style="display: none;"></div>
                                            </div>
                                            <input type="hidden" name="existing_design_id" id="existing_design_id" value="<?php echo $invoice['design_id']; ?>">
                                            <div id="selected_design_info" class="mt-2" style="<?php echo $designData ? '' : 'display: none;'; ?>">
                                                <div class="alert alert-info">
                                                    <strong>ເລືອກແລ້ວ:</strong> <span id="selected_design_text"><?php echo $designData ? $designData['queue_code'] . ' - ' . $designData['customer_name'] . ($designData['team_name'] ? ' (' . $designData['team_name'] . ')' : '') : ''; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ตัวเลือก 2: ใส่รหัสคิวเอง -->
                                    <div class="queue-code-option" data-option="custom">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="queue_option" id="custom_queue" value="custom" <?php echo !empty($invoice['custom_queue_code']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="custom_queue">
                                                <strong><i class="fas fa-edit"></i> ໃສ່ລະຫັດຄິວເອງ</strong>
                                            </label>
                                        </div>
                                        <div class="mt-2" id="custom_queue_section" style="<?php echo !empty($invoice['custom_queue_code']) ? '' : 'display: none;'; ?>">
                                            <input type="text" class="form-control" name="custom_queue_code" id="custom_queue_code" placeholder="ໃສ່ລະຫັດຄິວ ເຊັ່ນ: CUSTOM-001, TEST-123" value="<?php echo $invoice['custom_queue_code']; ?>">
                                            <div class="form-text">ສາມາດໃສ່ລະຫັດຄິວໄດ້ຕາມຕ້ອງການ ສຳລັບລູກຄ້າທີ່ບໍ່ມີຄິວອອກແບບ</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- ถ้าเป็นใบเสนอราคาจากคิวออกแบบ ให้แสดงข้อมูลปกติ -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ລະຫັດການອອກແບບ</label>
                                    <input type="text" class="form-control" name="design_code" 
                                        value="<?php echo $designData ? $designData['queue_code'] : ''; ?>" readonly>
                                    <input type="hidden" name="existing_design_id" value="<?php echo $invoice['design_id']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ລະຫັດຄຳສັ່ງຊື້</label>
                                    <input type="text" class="form-control" name="order_code" 
                                        value="<?php echo $orderData ? $orderData['order_code'] : ''; ?>" readonly>
                                    <input type="hidden" name="order_id" value="<?php echo $invoice['order_id']; ?>">
                                </div>
                            </div>
                            <input type="hidden" name="custom_queue_code" value="">
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຊື່ລູກຄ້າ *</label>
                                    <input type="text" class="form-control" name="customer_name" id="customer_name" required
                                        value="<?php echo $invoice['customer_name']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ເບີໂທລູກຄ້າ</label>
                                    <input type="text" class="form-control" name="customer_phone" id="customer_phone"
                                        value="<?php echo $invoice['customer_phone']; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຊ່ອງທາງຕິດຕໍ່ອື່ນໆ</label>
                                    <input type="text" class="form-control" name="customer_contact" id="customer_contact"
                                        value="<?php echo $invoice['customer_contact']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ຊື່ທີມ *</label>
                                    <input type="text" class="form-control" name="team_name" id="team_name" required
                                        value="<?php echo $invoice['team_name']; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຜູ້ອອກໃບສະເໜີລາຄາ</label>
                                    <input type="text" class="form-control" value="<?php echo $invoice['created_by_name']; ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ວັນທີ</label>
                                    <input type="text" class="form-control" value="<?php echo formatThaiDate($invoice['created_at']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">ລາຍການສິນຄ້າ</h5>
                    <button type="button" class="btn btn-light btn-sm" id="addItemBtn">
                        <i class="fas fa-plus"></i> ເພີ່ມລາຍການ
                    </button>
                </div>
                <div class="card-body">
                    <div id="itemsContainer">
                        <?php foreach ($invoiceItems as $index => $item): ?>
                            <div class="item-row" data-index="<?php echo $index; ?>">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">ປະເພດຜ້າ *</label>
                                        <select class="form-select fabric-select" name="fabric_id[]" required>
                                            <option value="">ເລືອກປະເພດຜ້າ</option>
                                            <?php foreach ($fabricTypes as $fabric): ?>
                                                <option value="<?php echo $fabric['fabric_id']; ?>" 
                                                        data-price="<?php echo $fabric['base_price']; ?>"
                                                        <?php echo ($item['fabric_id'] == $fabric['fabric_id']) ? 'selected' : ''; ?>>
                                                    <?php echo $fabric['fabric_name_lao']; ?> - <?php echo number_format($fabric['base_price']); ?> ₭
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ຈຳນວນລວມ *</label>
                                        <input type="number" class="form-control quantity-input" name="quantity[]" 
                                              min="1" value="<?php echo $item['quantity']; ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ລາຄາຕໍ່ຫນ່ວຍ</label>
                                        <input type="text" class="form-control unit-price" readonly
                                              value="<?php echo number_format($item['base_price']); ?> ₭">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input option-checkbox" type="checkbox" 
                                                  name="has_long_sleeve[]" id="longSleeve<?php echo $index; ?>"
                                                  <?php echo $item['has_long_sleeve'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="longSleeve<?php echo $index; ?>">ແຂນຍາວ (+20,000₭)</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input option-checkbox" type="checkbox" 
                                                  name="has_collar[]" id="collar<?php echo $index; ?>"
                                                  <?php echo $item['has_collar'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="collar<?php echo $index; ?>">ຄໍປົກ (+20,000₭)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມອື່ນໆ</label>
                                        <input type="number" class="form-control additional-cost" name="additional_costs[]" 
                                              value="<?php echo $item['additional_costs']; ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label">ຈຳນວນຕາມຂະໜາດ (ຜົນລວມຕ້ອງເທົ່າກັບຈຳນວນລວມ)</label>
                                        <div class="row">
                                            <div class="col">
                                                <label class="form-label">S</label>
                                                <input type="number" class="form-control size-input" name="size_s[]" 
                                                      min="0" value="<?php echo $item['size_s']; ?>">
                                            </div>
                                            <div class="col">
                                                <label class="form-label">M</label>
                                                <input type="number" class="form-control size-input" name="size_m[]" 
                                                      min="0" value="<?php echo $item['size_m']; ?>">
                                            </div>
                                            <div class="col">
                                                <label class="form-label">L</label>
                                                <input type="number" class="form-control size-input" name="size_l[]" 
                                                      min="0" value="<?php echo $item['size_l']; ?>">
                                            </div>
                                            <div class="col">
                                                <label class="form-label">XL</label>
                                                <input type="number" class="form-control size-input" name="size_xl[]" 
                                                      min="0" value="<?php echo $item['size_xl']; ?>">
                                            </div>
                                            <div class="col">
                                                <label class="form-label">2XL</label>
                                                <input type="number" class="form-control size-input" name="size_2xl[]" 
                                                      min="0" value="<?php echo $item['size_2xl']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label">ຂະໜາດພິເສດ (ມີຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມ)</label>
                                        <div class="row">
                                            <div class="col">
                                                <label class="form-label">3XL (+20,000₭)</label>
                                                <input type="number" class="form-control special-size" name="size_3xl[]" 
                                                      min="0" value="<?php echo $item['size_3xl']; ?>">
                                            </div>
                                            <div class="col">
                                                <label class="form-label">4XL (+25,000₭)</label>
                                                <input type="number" class="form-control special-size" name="size_4xl[]" 
                                                      min="0" value="<?php echo $item['size_4xl']; ?>">
                                            </div>
                                            <div class="col">
                                                <label class="form-label">5XL (+35,000₭)</label>
                                                <input type="number" class="form-control special-size" name="size_5xl[]" 
                                                      min="0" value="<?php echo $item['size_5xl']; ?>">
                                            </div>
                                            <div class="col">
                                                <label class="form-label">6XL (+35,000₭)</label>
                                                <input type="number" class="form-control special-size" name="size_6xl[]" 
                                                      min="0" value="<?php echo $item['size_6xl']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label class="form-label">ໝາຍເຫດເພີ່ມເຕີມ</label>
                                        <input type="text" class="form-control" name="additional_notes[]" 
                                              value="<?php echo $item['additional_notes']; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ລາຄາລວມ</label>
                                        <input type="text" class="form-control item-total" name="item_total[]" 
                                              readonly value="<?php echo $item['item_total']; ?>">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger remove-item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="free-items">
                                    <div class="row">
                                        <div class="col">
                                            <?php $freeItems = floor($item['quantity'] / 12); ?>
                                            <p class="mb-0"><i class="fas fa-gift"></i> <strong>ໂປຣໂມຊັ່ນ:</strong> 
                                               <span class="free-items-text">ສັ່ງ 12 ແຖມ 1 (ໄດ້ຮັບຟຣີ <?php echo $freeItems; ?> ຜືນ)</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">ໝາຍເຫດ</label>
                                <textarea class="form-control" name="notes" rows="3"><?php echo $invoice['notes']; ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <label>ລາຄາທັງໝົດ:</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <span id="subtotalBeforePromo">0</span> ₭
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <label>ຫັກມັດຈຳ:</label>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" class="form-control form-control-sm" id="specialDiscount" name="special_discount" value="<?php echo isset($invoice['special_discount']) ? $invoice['special_discount'] : 0; ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label>ຍອດລວມທັງໝົດ:</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <strong id="grandTotal">0</strong> ₭
                                            <input type="hidden" name="total_amount" id="totalAmountInput" value="<?php echo $invoice['total_amount']; ?>">
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <label>ມັດຈຳ (50%):</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <span id="depositAmount">0</span> ₭
                                            <input type="hidden" name="deposit_amount" id="depositAmountInput" value="<?php echo $invoice['deposit_amount']; ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <label>ຊຳລະສ່ວນທີ່ເຫຼືອ:</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <span id="remainingAmount">0</span> ₭
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" id="calculateBtn" class="btn btn-primary">
                        <i class="fas fa-calculator"></i> ຄິດໄລ່ລາຄາ
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> ບັນທຶກການແກ້ໄຂ
                    </button>
                    <a href="view_invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> ຍົກເລີກ
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Item template for JavaScript -->
    <template id="itemTemplate">
        <div class="item-row" data-index="{index}">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">ປະເພດຜ້າ *</label>
                    <select class="form-select fabric-select" name="fabric_id[]" required>
                        <option value="">ເລືອກປະເພດຜ້າ</option>
                        <?php foreach ($fabricTypes as $fabric): ?>
                            <option value="<?php echo $fabric['fabric_id']; ?>" data-price="<?php echo $fabric['base_price']; ?>">
                                <?php echo $fabric['fabric_name_lao']; ?> - <?php echo number_format($fabric['base_price']); ?> ₭
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ຈຳນວນລວມ *</label>
                    <input type="number" class="form-control quantity-input" name="quantity[]" min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ລາຄາຕໍ່ຫນ່ວຍ</label>
                    <input type="text" class="form-control unit-price" readonly>
                </div>
            </div>
          <!-- ส่วนของ item row ที่แก้ไขแล้ว -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="form-check form-check-inline">
            <input class="form-check-input option-checkbox" type="checkbox" name="has_long_sleeve[]" id="longSleeve{index}">
            <label class="form-check-label" for="longSleeve{index}">ແຂນຍາວ (+20,000₭)</label>
            <input type="number" class="form-control form-control-sm ms-2 long-sleeve-qty" 
                   name="long_sleeve_qty[]" min="0" value="0" style="width: 80px; display: none;" 
                   placeholder="ຈຳນວນ">
        </div>
        <div class="form-check form-check-inline mt-2">
            <input class="form-check-input option-checkbox" type="checkbox" name="has_collar[]" id="collar{index}">
            <label class="form-check-label" for="collar{index}">ຄໍປົກ (+20,000₭)</label>
            <input type="number" class="form-control form-control-sm ms-2 collar-qty" 
                   name="collar_qty[]" min="0" value="0" style="width: 80px; display: none;" 
                   placeholder="ຈຳນວນ">
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">ຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມອື່ນໆ</label>
        <input type="number" class="form-control additional-cost" name="additional_costs[]" value="0">
    </div>
</div>

<script>
// แก้ไข JavaScript สำหรับจัดการ Checkbox และการคำนวณ
$(document).ready(function() {
    // เพิ่มฟังก์ชันจัดการ checkbox และช่องกรอกจำนวน
    function bindOptionEvents() {
        // จัดการ checkbox แขนยาว
        $('.option-checkbox[name="has_long_sleeve[]"]').off('change').on('change', function() {
            const row = $(this).closest('.item-row');
            const qtyInput = row.find('.long-sleeve-qty');
            
            if ($(this).is(':checked')) {
                qtyInput.show().attr('required', true);
                // ตั้งค่าเริ่มต้นเป็นจำนวนทั้งหมด
                const totalQty = parseInt(row.find('.quantity-input').val() || 0);
                qtyInput.val(totalQty);
            } else {
                qtyInput.hide().removeAttr('required').val(0);
            }
            calculateItemTotal(row);
        });
        
        // จัดการ checkbox คอปก
        $('.option-checkbox[name="has_collar[]"]').off('change').on('change', function() {
            const row = $(this).closest('.item-row');
            const qtyInput = row.find('.collar-qty');
            
            if ($(this).is(':checked')) {
                qtyInput.show().attr('required', true);
                // ตั้งค่าเริ่มต้นเป็นจำนวนทั้งหมด
                const totalQty = parseInt(row.find('.quantity-input').val() || 0);
                qtyInput.val(totalQty);
            } else {
                qtyInput.hide().removeAttr('required').val(0);
            }
            calculateItemTotal(row);
        });
        
        // จัดการการเปลี่ยนจำนวน
        $('.long-sleeve-qty, .collar-qty').off('change keyup').on('change keyup', function() {
            const row = $(this).closest('.item-row');
            calculateItemTotal(row);
        });
        
        // จัดการเมื่อเปลี่ยนจำนวนทั้งหมด
        $('.quantity-input').off('change keyup').on('change keyup', function() {
            const row = $(this).closest('.item-row');
            const totalQty = parseInt($(this).val() || 0);
            
            // อัปเดตจำนวนแขนยาวและคอปกให้เท่ากับจำนวนทั้งหมด (ถ้าถูกเลือก)
            if (row.find('[name="has_long_sleeve[]"]').is(':checked')) {
                row.find('.long-sleeve-qty').val(totalQty);
            }
            if (row.find('[name="has_collar[]"]').is(':checked')) {
                row.find('.collar-qty').val(totalQty);
            }
            
            calculateItemTotal(row);
        });
    }
    
    // แก้ไขฟังก์ชันคำนวณ
    function calculateItemTotal(row) {
        const fabricPrice = parseFloat(row.find('.fabric-select').find(':selected').data('price') || 0);
        const quantity = parseInt(row.find('.quantity-input').val() || 0);
        const additionalCost = parseFloat(row.find('.additional-cost').val() || 0);
        
        // คำนวณค่าใช้จ่ายจากไซส์พิเศษ
        let specialSizesCost = 0;
        const size3xl = parseInt(row.find('[name="size_3xl[]"]').val() || 0);
        const size4xl = parseInt(row.find('[name="size_4xl[]"]').val() || 0);
        const size5xl = parseInt(row.find('[name="size_5xl[]"]').val() || 0);
        const size6xl = parseInt(row.find('[name="size_6xl[]"]').val() || 0);
        
        specialSizesCost += size3xl * 20000;
        specialSizesCost += size4xl * 25000;
        specialSizesCost += size5xl * 35000;
        specialSizesCost += size6xl * 35000;
        
        // คำนวณค่าใช้จ่ายจากแขนยาวและคอปก (ใช้จำนวนที่กรอกแยก)
        let optionsCost = 0;
        
        // แขนยาว - ใช้จำนวนที่กรอกในช่องแยก
        if (row.find('[name="has_long_sleeve[]"]').is(':checked')) {
            const longSleeveQty = parseInt(row.find('.long-sleeve-qty').val() || 0);
            optionsCost += longSleeveQty * 20000;
        }
        
        // คอปก - ใช้จำนวนที่กรอกในช่องแยก
        if (row.find('[name="has_collar[]"]').is(':checked')) {
            const collarQty = parseInt(row.find('.collar-qty').val() || 0);
            optionsCost += collarQty * 20000;
        }
        
        // คำนวณราคารวม
        const total = (fabricPrice * quantity) + additionalCost + specialSizesCost + optionsCost;
        row.find('.item-total').val(total);
        
        // อัปเดตข้อความโปรโมชั่น
        const freeItems = Math.floor(quantity / 12);
        row.find('.free-items-text').text(`ສັ່ງ 12 ແຖມ 1 (ໄດ້ຮັບຟຣີ ${freeItems} ຜືນ)`);
        
        calculateTotals();
    }
    
    // เรียกใช้ฟังก์ชันผูกเหตุการณ์
    bindOptionEvents();
    
    // แก้ไขฟังก์ชัน bindEvents หลักให้รวมฟังก์ชันใหม่
    function bindEvents() {
        $('.remove-item').off('click').on('click', function() {
            if ($('.item-row').length > 1) {
                $(this).closest('.item-row').remove();
                calculateTotals();
            } else {
                alert('ຕ້ອງມີລາຍການສິນຄ້າຢ່າງໜ້ອຍ 1 ລາຍການ');
            }
        });
        
        $('.fabric-select').off('change').on('change', function() {
            const row = $(this).closest('.item-row');
            const price = $(this).find(':selected').data('price') || 0;
            row.find('.unit-price').val(numberWithCommas(price) + ' ₭');
            calculateItemTotal(row);
        });
        
        $('.size-input, .special-size, .additional-cost').off('change keyup').on('change keyup', function() {
            const row = $(this).closest('.item-row');
            calculateItemTotal(row);
        });
        
        $('#specialDiscount').off('change keyup').on('change keyup', function() {
            calculateTotals();
        });
        
        // เรียกใช้ฟังก์ชันจัดการ options
        bindOptionEvents();
    }
    
    // ฟังก์ชันสำหรับ template รายการใหม่
    function getItemTemplate(index) {
        return `
            <div class="item-row" data-index="${index}">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">ປະເພດຜ້າ *</label>
                        <select class="form-select fabric-select" name="fabric_id[]" required>
                            <option value="">ເລືອກປະເພດຜ້າ</option>
                            <?php foreach ($fabricTypes as $fabric): ?>
                                <option value="<?php echo $fabric['fabric_id']; ?>" data-price="<?php echo $fabric['base_price']; ?>">
                                    <?php echo $fabric['fabric_name_lao']; ?> - <?php echo number_format($fabric['base_price']); ?> ₭
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ຈຳນວນລວມ *</label>
                        <input type="number" class="form-control quantity-input" name="quantity[]" min="1" value="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ລາຄາຕໍ່ຫນ່ວຍ</label>
                        <input type="text" class="form-control unit-price" readonly>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input option-checkbox" type="checkbox" name="has_long_sleeve[]" id="longSleeve${index}">
                            <label class="form-check-label" for="longSleeve${index}">ແຂນຍາວ (+20,000₭)</label>
                            <input type="number" class="form-control form-control-sm ms-2 long-sleeve-qty" 
                                   name="long_sleeve_qty[]" min="0" value="0" style="width: 80px; display: none;" 
                                   placeholder="ຈຳນວນ">
                        </div>
                        <div class="form-check form-check-inline mt-2">
                            <input class="form-check-input option-checkbox" type="checkbox" name="has_collar[]" id="collar${index}">
                            <label class="form-check-label" for="collar${index}">ຄໍປົກ (+20,000₭)</label>
                            <input type="number" class="form-control form-control-sm ms-2 collar-qty" 
                                   name="collar_qty[]" min="0" value="0" style="width: 80px; display: none;" 
                                   placeholder="ຈຳນວນ">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມອື່ນໆ</label>
                        <input type="number" class="form-control additional-cost" name="additional_costs[]" value="0">
                    </div>
                </div>
                
                <!-- ส่วนไซส์ต่างๆ (เหมือนเดิม) -->
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label">ຈຳນວນຕາມຂະໜາດ (ຜົນລວມຕ້ອງເທົ່າກັບຈຳນວນລວມ)</label>
                        <div class="row">
                            <div class="col"><label class="form-label">S</label><input type="number" class="form-control size-input" name="size_s[]" min="0" value="0"></div>
                            <div class="col"><label class="form-label">M</label><input type="number" class="form-control size-input" name="size_m[]" min="0" value="0"></div>
                            <div class="col"><label class="form-label">L</label><input type="number" class="form-control size-input" name="size_l[]" min="0" value="1"></div>
                            <div class="col"><label class="form-label">XL</label><input type="number" class="form-control size-input" name="size_xl[]" min="0" value="0"></div>
                            <div class="col"><label class="form-label">2XL</label><input type="number" class="form-control size-input" name="size_2xl[]" min="0" value="0"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label">ຂະໜາດພິເສດ (ມີຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມ)</label>
                        <div class="row">
                            <div class="col"><label class="form-label">3XL (+20,000₭)</label><input type="number" class="form-control special-size" name="size_3xl[]" min="0" value="0"></div>
                            <div class="col"><label class="form-label">4XL (+25,000₭)</label><input type="number" class="form-control special-size" name="size_4xl[]" min="0" value="0"></div>
                            <div class="col"><label class="form-label">5XL (+35,000₭)</label><input type="number" class="form-control special-size" name="size_5xl[]" min="0" value="0"></div>
                            <div class="col"><label class="form-label">6XL (+35,000₭)</label><input type="number" class="form-control special-size" name="size_6xl[]" min="0" value="0"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">ໝາຍເຫດເພີ່ມເຕີມ</label>
                        <input type="text" class="form-control" name="additional_notes[]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ລາຄາລວມ</label>
                        <input type="text" class="form-control item-total" name="item_total[]" readonly value="0">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-danger remove-item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <div class="free-items">
                    <div class="row">
                        <div class="col">
                            <p class="mb-0"><i class="fas fa-gift"></i> <strong>ໂປຣໂມຊັ່ນ:</strong> <span class="free-items-text">ສັ່ງ 12 ແຖມ 1</span></p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // ปุ่มเพิ่มรายการ
    $('#addItemBtn').click(function() {
        const template = getItemTemplate(itemIndex);
        $('#itemsContainer').append(template);
        bindEvents();
        itemIndex++;
    });
    
    // เรียกใช้ฟังก์ชันผูกเหตุการณ์
    bindEvents();
});
</script>
</body>
</html>