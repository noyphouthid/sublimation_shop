<?php
require_once 'db_connect.php';
session_start();

// ຖ້າເຂົ້າລະບົບແລ້ວ ໃຫ້ໄປໜ້າຫຼັກ
if (isset($_SESSION['user_id'])) {
    header("Location: design_queue_list.php");
    exit();
}

// ຈັດການການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    // ກວດສອບຜູ້ໃຊ້
    $sql = "SELECT user_id, username, password, full_name, role FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // ກວດສອບລະຫັດຜ່ານ
        if (password_verify($password, $user['password'])) {
            // ເຂົ້າລະບົບສຳເລັດ
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: design_queue_list.php");
            exit();
        } else {
            $error_message = "ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ";
        }
    } else {
        $error_message = "ບໍ່ພົບຊື່ຜູ້ໃຊ້";
    }
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ເຂົ້າລະບົບ - ລະບົບບໍລິຫານຮ້ານເສື້ອພິມລາຍ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">

    <!-- ຟ້ອນ Saysettha OT -->
    <style>
        @font-face {
            font-family: 'Saysettha OT';
            src: url('assets/fonts/SaysetthaOT.ttf') format('truetype');
        }

        body {
            font-family: 'Saysettha OT', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full p-6">
            <div class="text-center mb-6">
                <!-- ໂລໂກ້ຮ້ານ -->
                <img src="assets/LOGO.png" alt="ໂລໂກ້ຮ້ານ" class="mx-auto mb-4 h-20">
                <h1 class="text-3xl font-bold mb-2">ລະບົບບໍລິຫານຮ້ານເສື້ອພິມລາຍ</h1>
                <p class="text-gray-500">Sublimation Order Management System</p>
            </div>
            
            <div class="card shadow-md">
                <div class="card-body p-6">
                    <h2 class="text-2xl font-semibold mb-6 text-center">ປ້ອນຂໍ້ມູນເພື່ອເຂົ້າສູ່ລະບົບ</h2>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="post">
                        <div class="mb-4">
                            <label for="username" class="form-label">ຊື່ຜູ້ໃຊ້</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">ລະຫັດຜ່ານ</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">ຈື່ຈຳຂ້ອຍ</label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i> ເຂົ້າລະບົບ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-gray-500">© <?php echo date('Y'); ?> ລະບົບບໍລິຫານຮ້ານເສື້ອພິມລາຍ</p>
                <p class="text-xs text-gray-400 mt-1">Version 1.0.0</p>
            </div>
        </div>
    </div>
</body>
</html>
