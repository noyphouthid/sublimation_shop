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
$sql = "SELECT * FROM design_queue WHERE design_id = $designId";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    header("Location: design_queue_list.php");
    exit();
}

$queue = $result->fetch_assoc();

// ดึงรายชื่อดีไซเนอร์
$designersQuery = "SELECT user_id, full_name FROM users WHERE role = 'designer'";
$designersResult = $conn->query($designersQuery);

// ดึงไฟล์แนบ
$filesQuery = "SELECT * FROM design_files WHERE design_id = $designId AND file_type = 'reference' ORDER BY upload_date DESC";
$filesResult = $conn->query($filesQuery);

// การจัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $customerName = $conn->real_escape_string($_POST['customer_name']);
    $customerPhone = $conn->real_escape_string($_POST['customer_phone']);
    $customerContact = $conn->real_escape_string($_POST['customer_contact']);
    $designDetails = $conn->real_escape_string($_POST['design_details']);
    $teamName = $conn->real_escape_string($_POST['team_name']);
    $shirtColor = $conn->real_escape_string($_POST['shirt_color']);
    $designType = $conn->real_escape_string($_POST['design_type']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $designerId = !empty($_POST['designer_id']) ? intval($_POST['designer_id']) : NULL;
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : NULL;
    
    // อัปเดตข้อมูลในฐานข้อมูล
    $sql = "UPDATE design_queue SET 
            customer_name = '$customerName',
            customer_phone = '$customerPhone',
            customer_contact = '$customerContact',
            design_details = '$designDetails',
            team_name = '$teamName',
            shirt_color = '$shirtColor',
            design_type = '$designType',
            notes = '$notes',
            designer_id = " . ($designerId ? $designerId : "NULL") . ",
            deadline = " . ($deadline ? "'$deadline'" : "NULL") . "
            WHERE design_id = $designId";
    
    if ($conn->query($sql) === TRUE) {
        // อัปโหลดไฟล์ใหม่ (ถ้ามี)
        if (isset($_FILES['reference_files']) && $_FILES['reference_files']['error'][0] !== UPLOAD_ERR_NO_FILE) {
            $fileCount = count($_FILES['reference_files']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['reference_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = $_FILES['reference_files']['name'][$i];
                    $tmpName = $_FILES['reference_files']['tmp_name'][$i];
                    
                    // สร้างโฟลเดอร์ถ้ายังไม่มี
                    $uploadDir = "uploads/design_queue/$designId/";
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // เพิ่มเวลาปัจจุบันเข้าไปในชื่อไฟล์เพื่อป้องกันชื่อซ้ำ
                    $uniqueFileName = time() . '_' . $fileName;
                    $targetPath = $uploadDir . $uniqueFileName;
                    
                    // ย้ายไฟล์
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        // บันทึกข้อมูลไฟล์ลงฐานข้อมูล
                        $fileSql = "INSERT INTO design_files (design_id, file_name, file_path, file_type, uploaded_by) 
                                   VALUES ($designId, '$fileName', '$targetPath', 'reference', {$_SESSION['user_id']})";
                        $conn->query($fileSql);
                    }
                }
            }
        }
        
        // บันทึกประวัติการแก้ไข
        $comment = "แก้ไขรายละเอียดคิวออกแบบ";
        $user_id = $_SESSION['user_id'];
        $status = $queue['status']; // ไม่เปลี่ยนสถานะ
        
        $historySql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                     VALUES ($designId, '$status', '$status', $user_id, '$comment')";
        $conn->query($historySql);
        
        // แสดงข้อความสำเร็จและเปลี่ยนเส้นทาง
        $_SESSION['success_message'] = "อัปเดตข้อมูลคิวออกแบบเรียบร้อยแล้ว";
        header("Location: view_design_queue.php?id=$designId");
        exit();
    } else {
        $error_message = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ແກ້ໄຂຄິວອອກແບບ - <?php echo $queue['queue_code']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <!-- ใช้ฟอนต์ Saysettha สำหรับภาษาลาว -->
    <style>
        @font-face {
            font-family: 'Saysettha';
            src: url('assets/fonts/Saysettha.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body, h1, h2, h3, h4, h5, h6, p, a, button, input, textarea, select, option, label, span, div, 
        .form-control, .btn, .card-title, .card-text, .navbar-brand, .nav-link, .dropdown-item {
            font-family: 'Saysettha', sans-serif !important;
        }

        input::placeholder, textarea::placeholder {
            font-family: 'Saysettha', sans-serif !important;
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
    </style>
</head>
<body class="bg-gray-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
               
                ລະບົບບໍລິຫານຮ້ານເສື້ອພິມລາຍ
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
                <h1 class="text-2xl font-bold mb-1">ແກ້ໄຂຄິວອອກແບບ</h1>
                <h2 class="text-xl text-primary"><?php echo $queue['queue_code']; ?></h2>
            </div>
            <div>
                <a href="view_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-info me-2">
                    <i class="fas fa-eye"></i> ເບິ່ງລາຍລະອຽດ
                </a>
                <a href="design_queue_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າລາຍການ
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="row">
                        <!-- ข้อมูลลูกค้า -->
                        <div class="col-md-6 mb-4">
                            <h3 class="text-lg font-semibold mb-3 border-bottom pb-2">ຂໍ້ມູນລູກຄ້າ</h3>
                            
                            <div class="mb-3">
                                <label for="customer_name" class="form-label required">ຊື່ລູກຄ້າ/ທີມ</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                       value="<?php echo htmlspecialchars($queue['customer_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">ເບີໂທລະສັບ/FB</label>
                                <input type="text" class="form-control" id="customer_phone" name="customer_phone"
                                       value="<?php echo htmlspecialchars($queue['customer_phone']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_contact" class="form-label">ຊ່ອງທາງຕິດຕໍ່ອື່ນໆ</label>
                                <input type="text" class="form-control" id="customer_contact" name="customer_contact" 
                                       placeholder="LINE ID, Facebook, Email, ອື່ນໆ"
                                       value="<?php echo htmlspecialchars($queue['customer_contact']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="team_name" class="form-label">ຊື່ທີມ (ຖ້າມີ)</label>
                                <input type="text" class="form-control" id="team_name" name="team_name"
                                       value="<?php echo htmlspecialchars($queue['team_name']); ?>">
                            </div>
                        </div>

                        <!-- รายละเอียดการออกแบบ -->
                        <div class="col-md-6 mb-4">
                            <h3 class="text-lg font-semibold mb-3 border-bottom pb-2">ລາຍລະອຽດການອອກແບບ</h3>
                            
                            <div class="mb-3">
                                <label for="design_type" class="form-label">ປະເພດເສື້ອ</label>
                                <select class="form-select" id="design_type" name="design_type">
                                    <option value="">-- ເລືອກປະເພດ --</option>
                                    <option value="ເສື້ອກິລາ" <?php echo $queue['design_type'] === 'เสื้อกีฬา' ? 'selected' : ''; ?>>ເສື້ອກິລາ</option>
                                    <option value="ເສື້ອທີມງາມ" <?php echo $queue['design_type'] === 'เสื้อทีมงาน' ? 'selected' : ''; ?>>ເສື້ອທີມງາມ</option>
                                    <option value="ອື່ນໆ" <?php echo $queue['design_type'] === 'อื่นๆ' ? 'selected' : ''; ?>>ອື່ນໆ</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="shirt_color" class="form-label">ສີເສື້ອ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-palette"></i></span>
                                    <input type="text" class="form-control" id="shirt_color" name="shirt_color" 
                                           placeholder="ເຊັ່ນ: ຂາວ, ດຳ, ຟ້າເຂັ້ມ"
                                           value="<?php echo htmlspecialchars($queue['shirt_color']); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deadline" class="form-label">ວັນທີ່ຕ້ອງການ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" id="deadline" name="deadline"
                                           value="<?php echo $queue['deadline']; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="designer_id" class="form-label">ຜູ້ຮັບຜິດຊອບຄິວ</label>
                                <select class="form-select" id="designer_id" name="designer_id">
                                    <option value="">-- ຍັງບໍ່ໄດ້ກຳນົດ --</option>
                                    <?php 
                                    $designersResult->data_seek(0); // รีเซ็ต pointer ของผลลัพธ์
                                    while ($designer = $designersResult->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $designer['user_id']; ?>" <?php echo $queue['designer_id'] == $designer['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo $designer['full_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- รายละเอียดเพิ่มเติม -->
                        <div class="col-12 mb-4">
                            <h3 class="text-lg font-semibold mb-3 border-bottom pb-2">ລາຍລະອຽດເພີ່ມເຕີມ</h3>
                            
                            <div class="mb-3">
                                <label for="design_details" class="form-label required">ລາຍລະອຽດການອອກແບບ</label>
                                <textarea class="form-control" id="design_details" name="design_details" rows="5" required
                                          placeholder="ລະບຸລາຍລະອຽດການອອກແບບ ເຊັ່ນ ໂລໂກ້, ສີ, ຟອນ, ຂໍ້ຄວາມ, ແນວຄິດການອອກແບບ ອື່ນໆ"><?php echo htmlspecialchars($queue['design_details']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">ບັນທຶກເພີ່ມເຕີມ</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                          placeholder="ບັນທຶກເພີ່ມເຕີມອື່ນໆ ທີ່ຕ້ອງການແຈ້ງໃຫ້ທີມອອກແບບຮູ້"><?php echo htmlspecialchars($queue['notes']); ?></textarea>
                            </div>
                            
                            <!-- ไฟล์แนบที่มีอยู่ -->
                            <?php if ($filesResult->num_rows > 0): ?>
                                <div class="mb-4">
                                    <label class="form-label">ໄຟລ໌ຕົວຢ່າງທີ່ມີຢູ່</label>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
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
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <a href="<?php echo $file['file_path']; ?>" class="btn btn-sm btn-success" download title="ດາວໂຫລດ">
                                                                    <i class="fas fa-download"></i>
                                                                </a>
                                                                <a href="delete_file.php?id=<?php echo $file['file_id']; ?>&design_id=<?php echo $designId; ?>" 
                                                                   class="btn btn-sm btn-danger" 
                                                                   onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບໄຟລ໌ນີ້?');" title="ລຶບໄຟລ໌">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="reference_files" class="form-label">ເພີ່ມໄຟລ໌ຕົວຢ່າງໃໝ່</label>
                                <input type="file" class="form-control" id="reference_files" name="reference_files[]" multiple>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> ໄຟລ໌ທີ່ອະນຸຍາດ: ຮູບພາບ (JPG, PNG, GIF), ໄຟລ໌ PDF, AI, PSD, SVG, ໄຟລ໌ ZIP ຂະໜາດບໍ່ເກີນ 10MB ຕໍ່ໄຟລ໌
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-save"></i> ບັນທຶກການປ່ຽນແປງ
                        </button>
                        <a href="view_design_queue.php?id=<?php echo $designId; ?>" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="fas fa-times"></i> ຍົກເລີກ
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ตรวจสอบการแสดงผลไฟล์ที่เลือก
        const fileInput = document.getElementById('reference_files');
        
        fileInput.addEventListener('change', function() {
            // ตรวจสอบว่ามีไฟล์ที่เลือกหรือไม่
            if (this.files.length > 0) {
                // แสดงจำนวนไฟล์ที่เลือก
                console.log(`ເລືອກ ${this.files.length} ໄຟລ໌`);
                
                // ตรวจสอบขนาดไฟล์
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const fileSize = file.size / 1024 / 1024; // แปลงเป็น MB
                    
                    if (fileSize > 10) {
                        alert(`ໄຟລ໌ "${file.name}" ມີຂະໜາດໃຫຍ່ເກີນໄປ (${fileSize.toFixed(2)} MB). ຂະໜາດໄຟລ໌ສູງສຸດທີ່ອະນຸຍາດແມ່ນ 10 MB.`);
                        this.value = ''; // ล้างการเลือกไฟล์
                        break;
                    }
                }
            }
        });
    });
    </script>
</body>
</html>
