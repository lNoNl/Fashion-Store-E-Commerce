<?php
// ตั้งค่าพื้นฐาน ควรอยู่บนสุดเสมอ
date_default_timezone_set('Asia/Bangkok');
session_start();
require_once 'config.php'; // Ensure config.php establishes $conn

// --- PHP Logic สำหรับ Dynamic Elements ---

// 1. นับจำนวนสินค้าในตะกร้า
$cart_item_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item_variant_id => $quantity) {
        // Ensure quantity is numeric before adding
        if (is_numeric($quantity)) {
            $cart_item_count += $quantity;
        }
    }
}


// 2. กำหนดลิงก์ของโลโก้ตาม Role ของผู้ใช้
$logo_link = 'index.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $logo_link = 'dashboard.php';
}

// 3. ★★★ เพิ่ม: นับจำนวนออเดอร์ที่ยังไม่ได้ชำระเงิน ★★★
$unpaid_orders_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id_for_nav = $_SESSION['user_id'];
    $status_unpaid = 'ยังไม่ได้ชำระเงิน';

    // Check if connection is valid before preparing statement
    if ($conn && $conn->ping()) {
        try {
            $stmt_unpaid = $conn->prepare("SELECT COUNT(id) as count FROM orders WHERE user_id = ? AND order_status = ?");
            // Check if prepare was successful
            if ($stmt_unpaid) {
                $stmt_unpaid->bind_param("is", $user_id_for_nav, $status_unpaid);
                $stmt_unpaid->execute();
                $result = $stmt_unpaid->get_result();
                $row = $result->fetch_assoc();
                // Check if fetch_assoc returned data
                if ($row) {
                    $unpaid_orders_count = $row['count'];
                }
                $stmt_unpaid->close();
            } else {
                 error_log("Prepare failed for unpaid orders count: (" . $conn->errno . ") " . $conn->error);
                 $unpaid_orders_count = 0; // Keep it 0 on prepare failure
            }
        } catch (Exception $e) {
            // หากฐานข้อมูลมีปัญหา ให้ค่าเป็น 0 และทำงานต่อไป
            // ★ เพิ่ม: Log error ไว้ด้วย เพื่อให้ทราบปัญหา
            error_log("Error fetching unpaid orders count: " . $e->getMessage());
            $unpaid_orders_count = 0;
        }
    } else {
         error_log("Database connection invalid in header.php when trying to fetch unpaid count.");
         $unpaid_orders_count = 0; // Set to 0 if connection lost
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La_maison</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Third-party CSS (ถ้ามี) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="style.css?v=<?= time() // Cache busting ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>
<body>
    <nav>
        <div class="logo">
            <a href="<?= htmlspecialchars($logo_link) // Add htmlspecialchars ?>">
                <img src="img/logo.jpg" alt="La_maison Logo" class="logo-img">
                <h2>La_maison</h2>
            </a>
        </div>
        <div class="user-menu">
            <ul class="nav-links">
                <?php if (isset($_SESSION['user_id'])): // สำหรับผู้ใช้ที่ล็อกอินแล้ว ?>

                    <?php if ($_SESSION['role'] === 'admin'): // เมนูสำหรับ Admin ?>
                         <li><a href="dashboard.php">📊 Dashboard</a></li>
                         <li><a href="manage_products.php">📦 จัดการสินค้า</a></li>
                         <li><a href="all_orders.php">📋 ดูออเดอร์</a></li>
                    <?php else: // เมนูสำหรับ User ทั่วไป ?>
                        <li><a href="cart.php">🛒 ตะกร้า (<span id="cart-count"><?= $cart_item_count ?></span>)</a></li>
                        <li><a href="profile.php">👤 โปรไฟล์ (<?= htmlspecialchars($_SESSION['username']) ?>)</a></li>
                        <li>
                            <a href="order_history.php">
                                🧾 ประวัติสั่งซื้อ
                                <?php if ($unpaid_orders_count > 0): ?>
                                    <span class="nav-notification-badge" title="<?= $unpaid_orders_count ?> ออเดอร์ที่ยังไม่ได้ชำระเงิน"><?= $unpaid_orders_count ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li><a href="logout.php">🚪 ออกจากระบบ</a></li>

                <?php else: // สำหรับ Guest ที่ยังไม่ได้ล็อกอิน ?>
                    <li><a href="cart.php">🛒 ตะกร้า (<span id="cart-count"><?= $cart_item_count ?></span>)</a></li>
                    <li><a href="login.php">🔑 เข้าสู่ระบบ</a></li>
                    <li><a href="register.php">📝 สมัครสมาชิก</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

<!-- ★★★ แก้ไข: ย้าย <main> มาเริ่มต้นตรงนี้ ★★★ -->
<main class="main-content container">
<!-- เนื้อหาหลักของแต่ละหน้าจะอยู่ต่อจากนี้ -->