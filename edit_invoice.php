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

// ดึงข้อมูลประเภทผ้า
$fabricTypes = getAllFabricTypes($conn);

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
        'design_id' => $_POST['design_id'] ? $_POST['design_id'] : null,
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
    $invoiceItems = [];
    for ($i = 0; $i < count($_POST['fabric_id']); $i++) {
        if (empty($_POST['fabric_id'][$i])) continue;
        
        $invoiceItems[] = [
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
    
    $result = saveInvoice($conn, $invoiceData, $invoiceItems);
    
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
    <title>ແກ້ໄຂໃບສະເໜີລາຄາ</title>
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
            <!-- ส่วนนี้เหมือนกับ add_invoice.php แต่มีการใส่ค่าเดิมที่มีอยู่แล้ว -->
            <div class="row">
               
                   <div class="col-md-4">
                    <div class="company-info">
                        <img src="assets/LOGO.png" alt="ໂລໂກ້ຮ້ານ" class="logo-image">
                        <h4>ຮ້ານ ບີຈີ ສປອຮ໌ດ</h4>
                        <p>ຕັ້ງຢູ່: ບ. ຕານມີໄຊ ມ. ໄຊທານີ ນະຄອນຫຼວງວຽງຈັນ, ລາວ</p>
                        <p>ໂທ: 020 9220 1288-20 9258 2288</p>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">ຂໍ້ມູນໃບສະເໜີລາຄາ</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ລະຫັດການອອກແບບ</label>
                                    <input type="text" class="form-control" name="design_code" 
                                        value="<?php echo $designData ? $designData['queue_code'] : ''; ?>" readonly>
                                    <input type="hidden" name="design_id" 
                                        value="<?php echo $invoice['design_id']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ລະຫັດຄຳສັ່ງຊື້</label>
                                    <input type="text" class="form-control" name="order_code" 
                                        value="<?php echo $orderData ? $orderData['order_code'] : ''; ?>" readonly>
                                    <input type="hidden" name="order_id" 
                                        value="<?php echo $invoice['order_id']; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຊື່ລູກຄ້າ *</label>
                                    <input type="text" class="form-control" name="customer_name" required
                                        value="<?php echo $invoice['customer_name']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ເບີໂທລູກຄ້າ</label>
                                    <input type="text" class="form-control" name="customer_phone"
                                        value="<?php echo $invoice['customer_phone']; ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຊ່ອງທາງຕິດຕໍ່ອື່ນໆ</label>
                                    <input type="text" class="form-control" name="customer_contact"
                                        value="<?php echo $invoice['customer_contact']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ຊື່ທີມ *</label>
                                    <input type="text" class="form-control" name="team_name" required
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
                                               <span class="free-items-text">ສັ່ງ 12 ແຖມ 1 (ໄດ້ຮັບຟຣີ <?php echo $freeItems; ?> ຊຸດ)</span></p>
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
                                            <label>ລາຄາລວມກ່ອນໂປຣໂມຊັ່ນ:</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <span id="subtotalBeforePromo">0</span> ₭
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <label>ສ່ວນຫຼຸດ (ໂປຣໂມຊັ່ນ):</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <span id="promoDiscount">0</span> ₭
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <label>ສ່ວນຫຼຸດພິເສດ:</label>
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
    
    <!-- Item template for JavaScript (เหมือนกับใน add_invoice.php) -->
    <template id="itemTemplate">
        <!-- เนื้อหาเหมือนกับใน add_invoice.php -->
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
            
            <!-- ส่วนอื่นๆ เหมือนกับในรายการแรก -->
            <!-- (เหมือนใน add_invoice.php) -->
        </div>
    </template>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <script>
        // JavaScript เหมือนกับใน add_invoice.php
        $(document).ready(function() {
            let itemIndex = <?php echo count($invoiceItems); ?>;
            
            // Function to add new item
            $('#addItemBtn').click(function() {
                const template = $('#itemTemplate').html();
                const newItem = template.replace(/{index}/g, itemIndex);
                $('#itemsContainer').append(newItem);
                bindEvents();
                itemIndex++;
            });
            
            // Function to remove item
            function bindEvents() {
                $('.remove-item').off('click').on('click', function() {
                    $(this).closest('.item-row').remove();
                    calculateTotals();
                });
                
                $('.fabric-select').off('change').on('change', function() {
                    const row = $(this).closest('.item-row');
                    const price = $(this).find(':selected').data('price') || 0;
                    row.find('.unit-price').val(numberWithCommas(price) + ' ₭');
                    calculateItemTotal(row);
                });
                
                $('.quantity-input, .size-input, .special-size, .additional-cost, .option-checkbox').off('change keyup').on('change keyup', function() {
                    const row = $(this).closest('.item-row');
                    calculateItemTotal(row);
                });
                
                // Special discount handling
                $('#specialDiscount').off('change keyup').on('change keyup', function() {
                    calculateTotals();
                });
            }
            
            // Calculate item total (เหมือนกับใน add_invoice.php)
            function calculateItemTotal(row) {
                const fabricPrice = parseFloat(row.find('.fabric-select').find(':selected').data('price') || 0);
                const quantity = parseInt(row.find('.quantity-input').val() || 0);
                const additionalCost = parseFloat(row.find('.additional-cost').val() || 0);
                
                // Calculate special sizes cost
                let specialSizesCost = 0;
                const size3xl = parseInt(row.find('[name="size_3xl[]"]').val() || 0);
                const size4xl = parseInt(row.find('[name="size_4xl[]"]').val() || 0);
                const size5xl = parseInt(row.find('[name="size_5xl[]"]').val() || 0);
                const size6xl = parseInt(row.find('[name="size_6xl[]"]').val() || 0);
                
                specialSizesCost += size3xl * 20000;
                specialSizesCost += size4xl * 25000;
                specialSizesCost += size5xl * 35000;
                specialSizesCost += size6xl * 35000;
                
                // Check if long sleeve option is selected
                let optionsCost = 0;
                if (row.find('[name="has_long_sleeve[]"]').is(':checked')) {
                    optionsCost += quantity * 20000;
                }
                
                // Check if collar option is selected
                if (row.find('[name="has_collar[]"]').is(':checked')) {
                    optionsCost += quantity * 20000;
                }
                
                // Calculate total
                const total = (fabricPrice * quantity) + additionalCost + specialSizesCost + optionsCost;
                row.find('.item-total').val(total);
                
                // Update free items text
                const freeItems = Math.floor(quantity / 12);
                row.find('.free-items-text').text(`ສັ່ງ 12 ແຖມ 1 (ໄດ້ຮັບຟຣີ ${freeItems} ຊຸດ)`);
                
                calculateTotals();
            }
            
            // Calculate all totals (เหมือนกับใน add_invoice.php)
            function calculateTotals() {
                let subtotal = 0;
                
                $('.item-total').each(function() {
                    subtotal += parseFloat($(this).val() || 0);
                });
                
                // Calculate promo discount
                let totalQuantity = 0;
                $('.quantity-input').each(function() {
                    totalQuantity += parseInt($(this).val() || 0);
                });
                
                let freeItems = Math.floor(totalQuantity / 12);
                
                // Calculate average price per item for the discount
                const avgPrice = totalQuantity > 0 ? subtotal / totalQuantity : 0;
                const promoDiscount = freeItems * avgPrice;
                
                // Get special discount
                const specialDiscount = parseFloat($('#specialDiscount').val() || 0);
                
                // Calculate grand total
                const grandTotal = subtotal - promoDiscount - specialDiscount;
                
                // Calculate deposit amount (50%)
                const depositAmount = grandTotal * 0.5;
                const remainingAmount = grandTotal - depositAmount;
                
                // Update UI
                $('#subtotalBeforePromo').text(numberWithCommas(subtotal));
                $('#promoDiscount').text(numberWithCommas(promoDiscount));
                $('#grandTotal').text(numberWithCommas(grandTotal));
                $('#depositAmount').text(numberWithCommas(depositAmount));
                $('#remainingAmount').text(numberWithCommas(remainingAmount));
                
                // Update hidden inputs
                $('#totalAmountInput').val(grandTotal);
                $('#depositAmountInput').val(depositAmount);
            }
            
            // Calculate button click
            $('#calculateBtn').click(function() {
                // Validate all items
                let valid = true;
                
                $('.item-row').each(function() {
                    const fabricId = $(this).find('.fabric-select').val();
                    const quantity = parseInt($(this).find('.quantity-input').val() || 0);
                    
                    if (!fabricId || quantity <= 0) {
                        valid = false;
                        return false;
                    }
                    
                    // Validate sizes total match quantity
                    let sizesTotal = 0;
                    $(this).find('.size-input, .special-size').each(function() {
                        sizesTotal += parseInt($(this).val() || 0);
                    });
                    
                    if (sizesTotal !== quantity) {
                        alert('ຈຳນວນຕາມຂະໜາດທັງໝົດຕ້ອງກົງກັບຈຳນວນລວມ!');
                        valid = false;
                        return false;
                    }
                });
                
                if (!valid) {
                    alert('ກະລຸນາກວດສອບຂໍ້ມູນຂອງທ່ານອີກຄັ້ງ!');
                    return;
                }
                
                calculateTotals();
            });
            
            // Helper function for formatting numbers
            function numberWithCommas(x) {
                return Math.round(x).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            
            // Initial bindings and calculation
            bindEvents();
            calculateTotals();
        });
    </script>
</body>
</html>