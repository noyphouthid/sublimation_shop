<?php
session_start();
require_once 'db_connect.php';
require_once 'invoice_functions.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ดึงข้อมูลประเภทผ้า
$fabricTypes = getAllFabricTypes($conn);

// ดึงข้อมูลคิวออกแบบ (สำหรับ autocomplete)
$designsQuery = "SELECT design_id, queue_code, customer_name, team_name FROM design_queue ORDER BY created_at DESC LIMIT 50";
$designsResult = $conn->query($designsQuery);
$existingDesigns = [];
while ($row = $designsResult->fetch_assoc()) {
    $existingDesigns[] = $row;
}

// บันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $invoiceData = [
        'order_id' => null, // ไม่ใช้ order_id สำหรับใบเสนอราคาอิสระ
        'design_id' => !empty($_POST['existing_design_id']) ? $_POST['existing_design_id'] : null,
        'custom_queue_code' => !empty($_POST['custom_queue_code']) ? $_POST['custom_queue_code'] : null,
        'customer_name' => $_POST['customer_name'],
        'customer_phone' => $_POST['customer_phone'],
        'customer_contact' => $_POST['customer_contact'],
        'team_name' => $_POST['team_name'],
        'created_by' => $_SESSION['user_id'],
        'deposit_amount' => $_POST['deposit_amount'],
        'total_amount' => $_POST['total_amount'],
        'special_discount' => $_POST['special_discount'],
        'notes' => $_POST['notes'],
        'status' => 'draft'
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
    
    $result = saveIndependentInvoice($conn, $invoiceData, $invoiceItems);
    
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
    <title>ສ້າງໃບສະເໜີລາຄາ (ອິສະລະ)</title>
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
                <h2><i class="fas fa-file-invoice"></i> ສ້າງໃບປະເມີນລາຄາ (ອິສະລະ)</h2>
                <p class="text-muted">ສາມາດສ້າງໃບປະເມີນລາຄາໄດ້ໂດຍບໍ່ຕ້ອງຜູກກັບຄິວອອກແບບ</p>
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
                            <h5 class="mb-0">ຂໍ້ມູນໃບປະເມີນລາຄາ</h5>
                        </div>
                        <div class="card-body">
                            <!-- ตัวเลือกรหัสคิว -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label">ຕົວເລືອກລະຫັດຄິວ:</label>
                                    
                                    <!-- ตัวเลือก 1: ใช้คิวที่มีอยู่แล้ว -->
                                    <div class="queue-code-option" data-option="existing">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="queue_option" id="existing_queue" value="existing" checked>
                                            <label class="form-check-label" for="existing_queue">
                                                <strong><i class="fas fa-search"></i> ເລືອກຈາກຄິວທີ່ມີຢູ່ແລ້ວ</strong>
                                            </label>
                                        </div>
                                        <div class="mt-2" id="existing_queue_section">
                                            <div class="position-relative">
                                                <input type="text" class="form-control" id="design_search" placeholder="ຄົ້ນຫາລະຫັດຄິວ ຫຼື ຊື່ລູກຄ້າ...">
                                                <div id="autocomplete_results" class="autocomplete-suggestions" style="display: none;"></div>
                                            </div>
                                            <input type="hidden" name="existing_design_id" id="existing_design_id">
                                            <div id="selected_design_info" class="mt-2" style="display: none;">
                                                <div class="alert alert-info">
                                                    <strong>ເລືອກແລ້ວ:</strong> <span id="selected_design_text"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ตัวเลือก 2: ใส่รหัสคิวเอง -->
                                    <div class="queue-code-option" data-option="custom">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="queue_option" id="custom_queue" value="custom">
                                            <label class="form-check-label" for="custom_queue">
                                                <strong><i class="fas fa-edit"></i> ໃສ່ລະຫັດຄິວເອງ</strong>
                                            </label>
                                        </div>
                                        <div class="mt-2" id="custom_queue_section" style="display: none;">
                                            <input type="text" class="form-control" name="custom_queue_code" id="custom_queue_code" placeholder="ໃສ່ລະຫັດຄິວ ເຊັ່ນ: CUSTOM-001, TEST-123">
                                            <div class="form-text">ສາມາດໃສ່ລະຫັດຄິວໄດ້ຕາມຕ້ອງການ ສຳລັບລູກຄ້າທີ່ບໍ່ມີຄິວອອກແບບ</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຊື່ລູກຄ້າ *</label>
                                    <input type="text" class="form-control" name="customer_name" id="customer_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ເບີໂທລູກຄ້າ</label>
                                    <input type="text" class="form-control" name="customer_phone" id="customer_phone">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຊ່ອງທາງຕິດຕໍ່ອື່ນໆ</label>
                                    <input type="text" class="form-control" name="customer_contact" id="customer_contact">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ຊື່ທີມ *</label>
                                    <input type="text" class="form-control" name="team_name" id="team_name" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ຜູ້ອອກໃບປະເມີນລາຄາ</label>
                                    <input type="text" class="form-control" value="<?php echo $_SESSION['full_name']; ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ວັນທີ</label>
                                    <input type="text" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ส่วนรายการสินค้า (เหมือนเดิม) -->
            <div class="card mt-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">ລາຍການສິນຄ້າ</h5>
                    <button type="button" class="btn btn-light btn-sm" id="addItemBtn">
                        <i class="fas fa-plus"></i> ເພີ່ມລາຍການ
                    </button>
                </div>
                <div class="card-body">
                    <div id="itemsContainer">
                        <!-- ส่วนนี้จะเหมือนกับ add_invoice.php เดิม -->
                        <div class="item-row" data-index="0">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ປະເພດຄ้າ *</label>
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
                                        <input class="form-check-input option-checkbox" type="checkbox" name="has_long_sleeve[]" id="longSleeve0">
                                        <label class="form-check-label" for="longSleeve0">ແຂນຍາວ (+20,000₭)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input option-checkbox" type="checkbox" name="has_collar[]" id="collar0">
                                        <label class="form-check-label" for="collar0">ຄໍປົກ (+20,000₭)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມອື່ນໆ</label>
                                    <input type="number" class="form-control additional-cost" name="additional_costs[]" value="0">
                                </div>
                            </div>
                            
                            <!-- ส่วนไซส์ต่างๆ เหมือนเดิม -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">ຈຳນວນຕາມຂະໜາດ (ຜົນລວມຕ້ອງເທົ່າກັບຈຳນວນລວມ)</label>
                                    <div class="row">
                                        <div class="col">
                                            <label class="form-label">S</label>
                                            <input type="number" class="form-control size-input" name="size_s[]" min="0" value="0">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">M</label>
                                            <input type="number" class="form-control size-input" name="size_m[]" min="0" value="0">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">L</label>
                                            <input type="number" class="form-control size-input" name="size_l[]" min="0" value="1">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">XL</label>
                                            <input type="number" class="form-control size-input" name="size_xl[]" min="0" value="0">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">2XL</label>
                                            <input type="number" class="form-control size-input" name="size_2xl[]" min="0" value="0">
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
                                            <input type="number" class="form-control special-size" name="size_3xl[]" min="0" value="0">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">4XL (+25,000₭)</label>
                                            <input type="number" class="form-control special-size" name="size_4xl[]" min="0" value="0">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">5XL (+35,000₭)</label>
                                            <input type="number" class="form-control special-size" name="size_5xl[]" min="0" value="0">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">6XL (+35,000₭)</label>
                                            <input type="number" class="form-control special-size" name="size_6xl[]" min="0" value="0">
                                        </div>
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
                    </div>
                    
                    <!-- ส่วนสรุปราคา เหมือนเดิม -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">ໝາຍເຫດ</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
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
                                            <input type="number" class="form-control form-control-sm" id="specialDiscount" name="special_discount" value="0" min="0">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label>ຍອດລວມທັງໝົດ:</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <strong id="grandTotal">0</strong> ₭
                                            <input type="hidden" name="total_amount" id="totalAmountInput" value="0">
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <label>ມັດຈຳ (50%):</label>
                                        </div>
                                        <div class="col-6 text-end">
                                            <span id="depositAmount">0</span> ₭
                                            <input type="hidden" name="deposit_amount" id="depositAmountInput" value="0">
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
                        <i class="fas fa-save"></i> ບັນທຶກໃບສະເໜີລາຄາ
                    </button>
                    <a href="invoice_list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> ຍົກເລີກ
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // ข้อมูลคิวที่มีอยู่แล้ว
        const existingDesigns = <?php echo json_encode($existingDesigns); ?>;
        
        // จัดการการเปลี่ยนตัวเลือกรหัสคิว
        $('input[name="queue_option"]').change(function() {
            const selectedOption = $(this).val();
            
            // ซ่อนทุกส่วน
            $('#existing_queue_section, #custom_queue_section').hide();
            
            // แสดงส่วนที่เลือก
            if (selectedOption === 'existing') {
                $('#existing_queue_section').show();
                $('#custom_queue_code').removeAttr('required');
            } else {
                $('#custom_queue_section').show();
                $('#custom_queue_code').attr('required', 'required');
                // ล้างข้อมูลคิวที่เลือก
                $('#existing_design_id').val('');
                $('#selected_design_info').hide();
            }
            
            // อัปเดตสีของการ์ด
            $('.queue-code-option').removeClass('active');
            $(this).closest('.queue-code-option').addClass('active');
        });
        
        // จัดการการคลิกที่การ์ด
        $('.queue-code-option').click(function() {
            const radio = $(this).find('input[type="radio"]');
            radio.prop('checked', true).trigger('change');
        });
        
        // ระบบ Autocomplete สำหรับค้นหาคิว
        $('#design_search').on('input', function() {
            const query = $(this).val().toLowerCase();
            const results = $('#autocomplete_results');
            
            if (query.length < 2) {
                results.hide();
                return;
            }
            
            const filtered = existingDesigns.filter(design => 
                design.queue_code.toLowerCase().includes(query) ||
                design.customer_name.toLowerCase().includes(query) ||
                (design.team_name && design.team_name.toLowerCase().includes(query))
            );
            
            if (filtered.length > 0) {
                let html = '';
                filtered.slice(0, 10).forEach(design => {
                    html += `
                        <div class="autocomplete-suggestion" data-design='${JSON.stringify(design)}'>
                            <strong>${design.queue_code}</strong><br>
                            <small>${design.customer_name}${design.team_name ? ' - ' + design.team_name : ''}</small>
                        </div>
                    `;
                });
                results.html(html).show();
            } else {
                results.hide();
            }
        });
        
        // จัดการการเลือกจาก autocomplete
        $(document).on('click', '.autocomplete-suggestion', function() {
            const design = JSON.parse($(this).attr('data-design'));
            
            // ตั้งค่าข้อมูล
            $('#design_search').val(design.queue_code);
            $('#existing_design_id').val(design.design_id);
            $('#selected_design_text').text(`${design.queue_code} - ${design.customer_name}${design.team_name ? ' (' + design.team_name + ')' : ''}`);
            $('#selected_design_info').show();
            $('#autocomplete_results').hide();
            
            // เติมข้อมูลลูกค้า
            $('#customer_name').val(design.customer_name);
            $('#team_name').val(design.team_name || '');
        });
        
        // ซ่อน autocomplete เมื่อคลิกข้างนอก
        $(document).click(function(e) {
            if (!$(e.target).closest('#design_search, #autocomplete_results').length) {
                $('#autocomplete_results').hide();
            }
        });
        
        // ฟังก์ชันการคำนวณ (เหมือนเดิม)
        let itemIndex = 0;
        
        // Function to add new item
        $('#addItemBtn').click(function() {
            itemIndex++;
            const template = getItemTemplate(itemIndex);
            $('#itemsContainer').append(template);
            bindEvents();
        });
        
        // Function to remove item
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
            
            $('.quantity-input, .size-input, .special-size, .additional-cost, .option-checkbox').off('change keyup').on('change keyup', function() {
                const row = $(this).closest('.item-row');
                calculateItemTotal(row);
            });
            
            $('#specialDiscount').off('change keyup').on('change keyup', function() {
                calculateTotals();
            });
        }
        
        // Calculate item total
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
            
            // Check options
            let optionsCost = 0;
            if (row.find('[name="has_long_sleeve[]"]').is(':checked')) {
                optionsCost += quantity * 20000;
            }
            if (row.find('[name="has_collar[]"]').is(':checked')) {
                optionsCost += quantity * 20000;
            }
            
            // Calculate total
            const total = (fabricPrice * quantity) + additionalCost + specialSizesCost + optionsCost;
            row.find('.item-total').val(total);
            
            // Update free items text
            const freeItems = Math.floor(quantity / 12);
            row.find('.free-items-text').text(`ສັ່ງ 12 ແຖມ 1 (ໄດ້ຮັບຟຣີ ${freeItems} ຜືນ)`);
            
            calculateTotals();
        }
        
        // Calculate all totals
        function calculateTotals() {
            let subtotal = 0;
            
            $('.item-total').each(function() {
                subtotal += parseFloat($(this).val() || 0);
            });
            
            const specialDiscount = parseFloat($('#specialDiscount').val() || 0);
            const grandTotal = subtotal - specialDiscount;
            const depositAmount = grandTotal * 0.5;
            const remainingAmount = grandTotal - depositAmount;
            
            // Update UI
            $('#subtotalBeforePromo').text(numberWithCommas(subtotal));
            $('#grandTotal').text(numberWithCommas(grandTotal));
            $('#depositAmount').text(numberWithCommas(depositAmount));
            $('#remainingAmount').text(numberWithCommas(remainingAmount));
            
            // Update hidden inputs
            $('#totalAmountInput').val(grandTotal);
            $('#depositAmountInput').val(depositAmount);
        }
        
        // Calculate button click
        $('#calculateBtn').click(function() {
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
        
        // Template for new items
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
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input option-checkbox" type="checkbox" name="has_collar[]" id="collar${index}">
                                <label class="form-check-label" for="collar${index}">ຄໍປົກ (+20,000₭)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ຄ່າໃຊ້ຈ່າຍເພີ່ມເຕີມອື່ນໆ</label>
                            <input type="number" class="form-control additional-cost" name="additional_costs[]" value="0">
                        </div>
                    </div>
                    
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
        
        // Initial bindings
        bindEvents();
    });
    </script>
</body>
</html>