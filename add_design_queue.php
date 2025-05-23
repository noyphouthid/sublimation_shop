<?php
require_once 'db_connect.php';
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ดึงรายชื่อดีไซเนอร์
$designersQuery = "SELECT user_id, full_name FROM users WHERE role = 'designer'";
$designersResult = $conn->query($designersQuery);

// การจัดการการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // สร้างรหัสคิวอัตโนมัติ
    $queueCode = generateQueueCode($conn);
    
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
    
    // เพิ่มข้อมูลลงในฐานข้อมูล
    $sql = "INSERT INTO design_queue (queue_code, customer_name, customer_phone, customer_contact, 
            design_details, team_name, shirt_color, design_type, notes, designer_id, deadline) 
            VALUES ('$queueCode', '$customerName', '$customerPhone', '$customerContact', 
            '$designDetails', '$teamName', '$shirtColor', '$designType', '$notes', " . 
            ($designerId ? $designerId : "NULL") . ", " . 
            ($deadline ? "'$deadline'" : "NULL") . ")";
    
    if ($conn->query($sql) === TRUE) {
        $designId = $conn->insert_id;
        
        // บันทึกประวัติสถานะ
        $user_id = $_SESSION['user_id'];
        $statusSql = "INSERT INTO status_history (design_id, old_status, new_status, changed_by, comment) 
                     VALUES ($designId, NULL, 'pending', $user_id, 'ສ້າງຄິວໃໝ່')";
        $conn->query($statusSql);
        
        // อัปโหลดไฟล์ (ถ้ามี)
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
        
        // แสดงข้อความสำเร็จและเปลี่ยนเส้นทาง
        $_SESSION['success_message'] = "ເພີ່ມຄິວອອກແບບໃໝ່ສຳເລັດແລ້ວ (ລະຫັດຄິວ: $queueCode)";
        header("Location: design_queue_list.php");
        exit();
    } else {
        $error_message = "ເກີດຂໍ້ຜິດພາດ: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
       <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ລະບົບເຂົ້າຄິວອອກແບບ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
  
       <?php include_once 'navbar.php'; ?>

   

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-2xl font-bold">ເພີ່ມຄິວອອກແບບໃໝ່</h1>
            <a href="design_queue_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> ກັບໄປໜ້າລາຍການ
            </a>
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
                                <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">ເບີໂທລະສັບ</label>
                                <input type="text" class="form-control" id="customer_phone" name="customer_phone">
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_contact" class="form-label">ຊ່ອງທາງຕິດຕໍ່ອື່ນໆ</label>
                                <input type="text" class="form-control" id="customer_contact" name="customer_contact" 
                                       placeholder="WhatsApp, Facebook, Email, ອື່ນໆ">
                            </div>
                            
                            <div class="mb-3">
                                <label for="team_name" class="form-label">ຊື່ທີມ (ຖ້າມີ)</label>
                                <input type="text" class="form-control" id="team_name" name="team_name">
                            </div>
                        </div>

                        <!-- รายละเอียดการออกแบบ -->
                        <div class="col-md-6 mb-4">
                            <h3 class="text-lg font-semibold mb-3 border-bottom pb-2">ລາຍລະອຽດການອອກແບບ</h3>
                            
                            <div class="mb-3">
                                <label for="design_type" class="form-label">ປະເພດຂອງການອອກແບບ</label>
                                <select class="form-select" id="design_type" name="design_type">
                                    <option value="">-- ເລືອກປະເພດ --</option>
                                    <option value="ເສື້ອກິລາ">ເສື້ອກິລາ</option>
                                    <option value="ເສື້ອຮຽນ/ຮຸ່ນ">ເສື້ອທີມງານ</option>
                                    <option value="ເສື້ອຮຽນ/ຮຸ່ນ">ເສື້ອອື່ນໆ</option>

                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="shirt_color" class="form-label">ສີເສື້ອ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-palette"></i></span>
                                    <input type="text" class="form-control" id="shirt_color" name="shirt_color" 
                                           placeholder="ເຊັ່ນ: ຂາວ, ດຳ, ຟ້າເຂັ້ມ">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="deadline" class="form-label">ວັນທີ່ຕ້ອງການ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" id="deadline" name="deadline" 
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="designer_id" class="form-label">ມອບໝາຍໃຫ້ນັກອອກແບບ</label>
                                <select class="form-select" id="designer_id" name="designer_id">
                                    <option value="">-- ຍັງບໍ່ໄດ້ກຳນົດ --</option>
                                    <?php while ($designer = $designersResult->fetch_assoc()): ?>
                                        <option value="<?php echo $designer['user_id']; ?>">
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
                                          placeholder="ລະບຸລາຍລະອຽດການອອກແບບ ເຊັ່ນ ໂລໂກ້, ສີ, ຟອນ, ຂໍ້ຄວາມ, ແນວຄິດການອອກແບບ ອື່ນໆ"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">ບັນທຶກເພີ່ມເຕີມ</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                          placeholder="ບັນທຶກເພີ່ມເຕີມອື່ນໆ ທີ່ຕ້ອງການແຈ້ງໃຫ້ທີມອອກແບບຮູ້"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reference_files" class="form-label">ໄຟລ໌ອ້າງອີງ (ຮູບພາບ, ໂລໂກ້, ອື່ນໆ)</label>
                                <input type="file" class="form-control" id="reference_files" name="reference_files[]" multiple>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> ໄຟລ໌ທີ່ອະນຸຍາດ: ຮູບພາບ (JPG, PNG, GIF), ໄຟລ໌ PDF, AI, PSD, SVG, ໄຟລ໌ ZIP ຂະໜາດບໍ່ເກີນ 10MB ຕໍ່ໄຟລ໌
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> ບັນທຶກຄິວອອກແບບ
                        </button>
                        <a href="design_queue_list.php" class="btn btn-outline-secondary btn-lg ms-2">
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