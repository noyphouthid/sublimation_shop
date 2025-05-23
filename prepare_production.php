<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: design_queue_list.php");
    exit();
}

$designId = intval($_GET['id']);

// ดึงข้อมูลคิวออกแบบ
$sql = "SELECT dq.*, u.full_name as designer_name 
        FROM design_queue dq 
        LEFT JOIN users u ON dq.designer_id = u.user_id 
        WHERE dq.design_id = $designId";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header("Location: design_queue_list.php");
    exit();
}

$queue = $result->fetch_assoc();

// ตรวจสอบว่าสถานะเป็น approved หรือไม่
if ($queue['status'] !== 'approved') {
    $_SESSION['error_message'] = "ບໍ່ສາມາດສົ່ງຜະລິດໄດ້ເນື່ອງຈາກຍັງບໍ່ໄດ້ຮັບການອະນຸມັດຈາກລູກຄ້າ";
    header("Location: view_design_queue.php?id=$designId");
    exit();
}

// ดึงไฟล์แนบสุดท้าย
$filesQuery = "SELECT * FROM design_files WHERE design_id = $designId AND file_type = 'final' ORDER BY upload_date DESC";
$filesResult = $conn->query($filesQuery);

// ดึงรายชื่อโรงงานคู่ค้า (สำหรับเชื่อมต่อกับระบบอื่นในอนาคต)
// ในตัวอย่างนี้เราจะสร้างข้อมูลจำลอง
$factories = [
    ['id' => 1, 'name' => 'Life Football','contact' => 'ອ້າຍ ໜິງ +856 20 96 299 953',],
];
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ກຽມສົ່ງຜະລິດ - <?php echo $queue['queue_code']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
    </style>
</head>
<body class="bg-gray-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-tshirt me-2"></i> ລະບົບບໍລິຫານຮ້ານເສື້ອພິມລາຍ
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="design_queue_list.php">
                            <i class="fas fa-list-ul"></i> ຄິວອອກແບບ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-shopping-cart"></i> ຄຳສັ່ງຊື້
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-industry"></i> ການຜະລິດ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-chart-line"></i> ລາຍງານ
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
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog"></i> ຕັ້ງຄ່າບັນຊີ</a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> ອອກຈາກລະບົບ</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-between align-items-center mb-4">
            <div>
                <h1 class="text-2xl font-bold mb-1">ກຽມສົ່ງຜະລິດ</h1>
                <h2 class="text-xl text-primary"><?php echo $queue['queue_code']; ?></h2>
            </div>
            <div>
                <a href="view_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າລາຍລະອຽດ
                </a>
            </div>
        </div>

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

        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white">
                <h2 class="text-xl font-semibold mb-0">
                    <i class="fas fa-info-circle me-2"></i> ຂໍ້ມູນຄິວອອກແບບ
                </h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <h5 class="text-muted mb-2">ຂໍ້ມູນລູກຄ້າ</h5>
                        <p><strong>ລະຫັດຄິວ:</strong> <?php echo $queue['queue_code']; ?></p>
                        <p><strong>ລູກຄ້າ:</strong> <?php echo htmlspecialchars($queue['customer_name']); ?></p>
                        <p><strong>ທີມ:</strong> <?php echo $queue['team_name'] ? htmlspecialchars($queue['team_name']) : '-'; ?></p>
                        <p><strong>ຕິດຕໍ່:</strong> 
                            <?php
                            if ($queue['customer_phone']) {
                                echo '<i class="fas fa-phone-alt me-1"></i> ' . htmlspecialchars($queue['customer_phone']);
                            }
                            if ($queue['customer_contact']) {
                                echo ' / ' . htmlspecialchars($queue['customer_contact']);
                            }
                            if (!$queue['customer_phone'] && !$queue['customer_contact']) {
                                echo '-';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h5 class="text-muted mb-2">ລາຍລະອຽດງານ</h5>
                        <p><strong>ປະເພດ:</strong> <?php echo $queue['design_type'] ? htmlspecialchars($queue['design_type']) : '-'; ?></p>
                        <p><strong>ສີເສື້ອ:</strong> <?php echo $queue['shirt_color'] ? htmlspecialchars($queue['shirt_color']) : '-'; ?></p>
                        <p><strong>ນັກອອກແບບ:</strong> <?php echo $queue['designer_name'] ? htmlspecialchars($queue['designer_name']) : '-'; ?></p>
                        <p><strong>ສະຖານະ:</strong> <span class="badge bg-success">ລູກຄ້າອະນຸມັດແລ້ວ</span></p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <h5 class="text-muted mb-2">ກຳນົດເວລາ</h5>
                        <p><strong>ວັນທີສ້າງ:</strong> <?php echo date('d/m/Y', strtotime($queue['created_at'])); ?></p>
                        <p><strong>ອັບເດດລ່າສຸດ:</strong> <?php echo date('d/m/Y H:i', strtotime($queue['updated_at'])); ?></p>
                        
                        <?php if ($queue['deadline']): ?>
                            <?php
                            $deadline = new DateTime($queue['deadline']);
                            $today = new DateTime();
                            $interval = $today->diff($deadline);
                            $isPast = $today > $deadline;
                            $daysRemaining = $interval->days;
                            ?>
                            
                            <div class="mt-2 p-2 rounded 
                                <?php echo $isPast ? 'bg-danger text-white' : ($daysRemaining <= 3 ? 'bg-warning' : 'bg-info text-white'); ?>">
                                <strong>
                                    <?php if ($isPast): ?>
                                        <i class="fas fa-exclamation-triangle me-1"></i> ກາຍກຳນົດສົ່ງມອບ:
                                    <?php else: ?>
                                        <i class="fas fa-clock me-1"></i> ກຳນົດສົ່ງມອບ:
                                    <?php endif; ?>
                                </strong>
                                
                                <?php echo date('d/m/Y', strtotime($queue['deadline'])); ?>
                                <br>
                                <?php if ($isPast): ?>
                                    (ກາຍມາ <?php echo $daysRemaining; ?> ວັນ)
                                <?php else: ?>
                                    (ອີກ <?php echo $daysRemaining; ?> ວັນ)
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p><strong>ວັນທີ່ຕ້ອງການ:</strong> <span class="text-muted">ບໍ່ລະບຸ</span></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- ไฟล์สุดท้ายสำหรับส่งผลิต -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-file-archive me-2 text-success"></i> ໄຟລ໌ສຸດທ້າຍສຳລັບສົ່ງຜະລິດ
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if ($filesResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ຊື່ໄຟລ໌</th>
                                            <th class="text-center">ປະເພດ</th>
                                            <th>ວັນທີອັບໂຫລດ</th>
                                            <th>ຈັດການ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($file = $filesResult->fetch_assoc()): ?>
                                            <?php
                                            $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                            $fileIcon = 'file-alt';
                                            $fileType = 'ໄຟລ໌';
                                            
                                            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                $fileIcon = 'file-image';
                                                $fileType = 'ຮູບພາບ';
                                            } elseif ($fileExt === 'pdf') {
                                                $fileIcon = 'file-pdf';
                                                $fileType = 'PDF';
                                            } elseif (in_array($fileExt, ['ai', 'psd'])) {
                                                $fileIcon = 'file-image';
                                                $fileType = strtoupper($fileExt);
                                            } elseif ($fileExt === 'svg') {
                                                $fileIcon = 'file-code';
                                                $fileType = 'SVG';
                                            } elseif ($fileExt === 'zip') {
                                                $fileIcon = 'file-archive';
                                                $fileType = 'ZIP';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-<?php echo $fileIcon; ?> text-secondary me-2"></i>
                                                    <?php echo htmlspecialchars($file['file_name']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?php echo $fileType; ?></span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($file['upload_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-info" target="_blank" title="ເບິ່ງໄຟລ໌">
                                                            <i class="fas fa-eye"></i> ເບິ່ງ
                                                        </a>
                                                        <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download title="ດາວໂຫລດ">
                                                            <i class="fas fa-download"></i> ດາວໂຫລດ
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> 
                                <strong>ຍັງບໍ່ມີໄຟລ໌ສຸດທ້າຍສຳລັບສົ່ງຜະລິດ</strong> ກະລຸນາອັບໂຫລດໄຟລ໌ກ່ອນສົ່ງຜະລິດ
                            </div>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadFinalModal">
                                <i class="fas fa-upload me-1"></i> ອັບໂຫລດໄຟລ໌ສຸດທ້າຍ
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ฟอร์มส่งผลิต -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-industry me-2"></i> ສົ່ງງານໄປຍັງໂຮງງານຜະລິດ
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if ($filesResult->num_rows === 0): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> 
                                <strong>ບໍ່ສາມາດສົ່ງຜະລິດໄດ້</strong> ກະລຸນາອັບໂຫລດໄຟລ໌ສຸດທ້າຍກ່ອນສົ່ງຜະລິດ
                            </div>
                        <?php else: ?>
                            <form action="send_to_production.php" method="post">
                                <input type="hidden" name="design_id" value="<?php echo $designId; ?>">
                                
                                <div class="mb-3">
                                    <label for="factory_id" class="form-label required">ເລືອກໂຮງງານຜະລິດ</label>
                                    <select class="form-select" id="factory_id" name="factory_id" required>
                                        <option value="">-- ເລືອກໂຮງງານ --</option>
                                        <?php foreach ($factories as $factory): ?>
                                            <option value="<?php echo $factory['id']; ?>">
                                                <?php echo htmlspecialchars($factory['name']); ?> 
                                                (<?php echo htmlspecialchars($factory['contact']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="product_details" class="form-label required">ລາຍລະອຽດການຜະລິດ</label>
                                    <textarea class="form-control" id="product_details" name="product_details" rows="4" required
                                            placeholder="ລະບຸຈຳນວນ, ຂະໜາດ, ລາຍລະອຽດການຜະລິດອື່ນໆ ເຊັ່ນ S-20 ຜືນ, M-30 ຜືນ, L-20 ຜືນ"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="expected_completion_date" class="form-label required">ວັນທີຄາດວ່າຈະແລ້ວ</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                            <input type="date" class="form-control" id="expected_completion_date" 
                                                name="expected_completion_date" min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="production_cost" class="form-label required">ຕົ້ນທຶນການຜະລິດ (ກີບ)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-money-bill-alt"></i></span>
                                            <input type="number" class="form-control" id="production_cost" 
                                                name="production_cost" min="0" step="0.01" required>
                                            <span class="input-group-text">ກີບ</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="production_notes" class="form-label">ໝາຍເຫດເພີ່ມເຕີມ</label>
                                    <textarea class="form-control" id="production_notes" name="production_notes" rows="3"
                                            placeholder="ຂໍ້ມູນເພີ່ມເຕີມທີ່ຕ້ອງການແຈ້ງໃຫ້ໂຮງງານຮູ້"></textarea>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="confirm_files" name="confirm_files" required>
                                    <label class="form-check-label" for="confirm_files">
                                        <strong>ຢືນຢັນວ່າໄດ້ກວດສອບໄຟລ໌ສຸດທ້າຍແລະພ້ອມສຳລັບການຜະລິດແລ້ວ</strong>
                                    </label>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-lg btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> ສົ່ງໄປຍັງໂຮງງານຜະລິດ
                                    </button>
                                    <a href="view_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-lg btn-outline-secondary ms-2">
                                        <i class="fas fa-times me-1"></i> ຍົກເລີກ
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> ໝາຍເຫດ: ເມື່ອສົ່ງງານໄປຍັງໂຮງງານຜະລິດແລ້ວ, ລະບົບຈະສ້າງລາຍການໃນລະບົບຕິດຕາມການຜະລິດໂດຍອັດຕະໂນມັດ
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- รายละเอียดงาน -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-tasks me-2 text-primary"></i> ລາຍລະອຽດງານ
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-2">ລາຍລະອຽດການອອກແບບ</h5>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($queue['design_details'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($queue['notes']): ?>
                            <div>
                                <h5 class="border-bottom pb-2 mb-2">ບັນທຶກເພີ່ມເຕີມ</h5>
                                <div class="bg-light p-3 rounded">
                                    <?php echo nl2br(htmlspecialchars($queue['notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ข้อมูลโรงงาน -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="text-xl font-semibold mb-0">
                            <i class="fas fa-building me-2 text-secondary"></i> ຂໍ້ມູນໂຮງງານຄູ່ຄ້າ
                        </h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($factories as $factory): ?>
                                <div class="list-group-item">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($factory['name']); ?></h5>
                                    <p class="mb-1"><i class="fas fa-phone-alt me-1 text-primary"></i> <?php echo htmlspecialchars($factory['contact']); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i> ເວລາຜະລິດສະເລ່ຍ: 5-7 ວັນ
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal อัปโหลดไฟล์สุดท้าย -->
    <div class="modal fade" id="uploadFinalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ອັບໂຫຼດໄຟລ໌ສຸດທ້າຍ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="upload_handler.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="design_id" value="<?php echo $designId; ?>">
                        <input type="hidden" name="file_type" value="final">
                        
                        <div class="mb-3">
                            <label for="final_files" class="form-label">ເລືອກໄຟລ໌:</label>
                            <input type="file" class="form-control" id="final_files" name="files[]" multiple required>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> ໄຟລ໌ທີ່ອະນຸຍາດ: ຮູບພາບ (JPG, PNG, GIF), ໄຟລ໌ PDF, AI, PSD, SVG, ໄຟລ໌ ZIP ຂະໜາດບໍ່ເກີນ 10MB ຕໍ່ໄຟລ໌
                        
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ (ถ้ามี):</label>
                            <textarea name="note" class="form-control" rows="3" 
                                      placeholder="บันทึกเพิ่มเติมเกี่ยวกับไฟล์ที่อัปโหลด"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-1"></i> อัปโหลด
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>