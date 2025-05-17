<?php
// ตรวจสอบว่ามีการล็อกอินไว้หรือไม่
$isLoggedIn = isset($_SESSION['user_id']);
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="fas fa-tshirt me-2"></i> ລະບົບບໍລິຫານຮ້ານເສື້ອພິມລາຍ
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if ($isLoggedIn): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($currentPage, 'design_queue') !== false ? 'active' : ''; ?>" href="design_queue_list.php">
                            <i class="fas fa-list-ul"></i> ຄິວອອກແບບ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($currentPage, 'invoice') !== false ? 'active' : ''; ?>" href="invoice_list.php">
                            <i class="fas fa-file-invoice"></i> ໃບສະເໜີລາຄາ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($currentPage, 'order') !== false ? 'active' : ''; ?>" href="#">
                            <i class="fas fa-shopping-cart"></i> ຄຳສັ່ງຊື້
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($currentPage, 'production') !== false ? 'active' : ''; ?>" href="#">
                            <i class="fas fa-industry"></i> ການຜະລິດ
                        </a>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($currentPage, 'report') !== false ? 'active' : ''; ?>" href="#">
                                <i class="fas fa-chart-line"></i> ລາຍງານ
                            </a>
                        </li>
                    <?php endif; ?>
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
            <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'login.php' ? 'active' : ''; ?>" href="login.php">
                            <i class="fas fa-sign-in-alt"></i> ເຂົ້າສູ່ລະບົບ
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>