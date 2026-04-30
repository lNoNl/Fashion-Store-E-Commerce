<?php
session_start();
require 'config.php';

// --- สำหรับ AJAX (action 'add') ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาดบางอย่าง'];

    // ★ แก้ไข: ตรวจสอบ variant_id ว่าเป็นตัวเลขที่มากกว่า 0 หรือไม่
    if (empty($_POST['variant_id']) || !filter_var($_POST['variant_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $response['message'] = 'กรุณาเลือกสีและไซต์ก่อน';
    } else {
        $variant_id = intval($_POST['variant_id']);
        // ★ แก้ไข: ตรวจสอบ quantity ว่าเป็นตัวเลขที่มากกว่า 0 หรือไม่ ถ้าไม่ ให้เป็น 1
        $quantity_to_add = (isset($_POST['quantity']) && filter_var($_POST['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))
                            ? intval($_POST['quantity'])
                            : 1;

        $stmt = $conn->prepare("SELECT stock_quantity FROM product_variants WHERE id = ?");
        $stmt->bind_param("i", $variant_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $variant = $result->fetch_assoc();
            $available_stock = $variant['stock_quantity'];
            $quantity_in_cart = isset($_SESSION['cart'][$variant_id]) ? $_SESSION['cart'][$variant_id] : 0;

            if (($quantity_in_cart + $quantity_to_add) > $available_stock) {
                // ★ แก้ไข: เพิ่มรายละเอียด สี/ขนาด ในข้อความแจ้งเตือน (ถ้าเป็นไปได้)
                // ดึงข้อมูลสี/ขนาด เพิ่มเติม (Optional แต่แนะนำ)
                $stmt_info = $conn->prepare("SELECT p.name, v.color, v.size FROM product_variants v JOIN products p ON v.product_id = p.id WHERE v.id = ?");
                $stmt_info->bind_param("i", $variant_id);
                $stmt_info->execute();
                $info_result = $stmt_info->get_result()->fetch_assoc();
                $product_name_info = $info_result ? htmlspecialchars($info_result['name'] . " (" . $info_result['color'] . "/" . $info_result['size'] . ")") : "สินค้านี้";
                $stmt_info->close();

                $response['message'] = "$product_name_info มีในสต็อกไม่เพียงพอ! (มีอยู่: $available_stock ชิ้น, ในตะกร้า: $quantity_in_cart ชิ้น)";
            } else {
                $_SESSION['cart'][$variant_id] = $quantity_in_cart + $quantity_to_add;
                $response['success'] = true;
                 // ★ เพิ่ม: ข้อความสำเร็จ
                 $response['message'] = 'เพิ่มสินค้าลงตะกร้าเรียบร้อย';
            }
        } else {
            $response['message'] = 'ไม่พบตัวเลือกสินค้านี้';
        }
        $stmt->close();
    }

    // คำนวณจำนวนสินค้าทั้งหมดในตะกร้า
    $total_items_in_cart = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $qty) {
            $total_items_in_cart += $qty;
        }
    }
    $response['cart_item_count'] = $total_items_in_cart;


}

// --- สำหรับฟอร์มในหน้าตะกร้า (action 'update' จาก POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // ★ แก้ไข: ตรวจสอบ variant_id ว่าเป็นตัวเลขที่มากกว่า 0 หรือไม่
    if (!empty($_POST['variant_id']) && filter_var($_POST['variant_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $variant_id = intval($_POST['variant_id']);
        // ★ แก้ไข: ตรวจสอบ quantity ว่าเป็นตัวเลขหรือไม่ ถ้าไม่ ให้เป็น 0
        $quantity = (isset($_POST['quantity']) && filter_var($_POST['quantity'], FILTER_VALIDATE_INT))
                    ? intval($_POST['quantity'])
                    : 0;

        // ★ เพิ่ม: ตรวจสอบสต็อกก่อนอัปเดตจำนวนในตะกร้า
        $stmt_stock_check = $conn->prepare("SELECT stock_quantity FROM product_variants WHERE id = ?");
        $stmt_stock_check->bind_param("i", $variant_id);
        $stmt_stock_check->execute();
        $stock_result = $stmt_stock_check->get_result();

        if ($stock_row = $stock_result->fetch_assoc()) {
            $available_stock = $stock_row['stock_quantity'];
            if ($quantity > $available_stock) {
                 // ถ้าจำนวนที่ต้องการมากกว่าสต็อก ให้ตั้งค่าเป็นจำนวนสต็อกสูงสุด และแจ้งเตือน
                $_SESSION['error_message'] = "ขออภัย จำนวนสินค้าไม่พอ (เหลือ $available_stock ชิ้น)";
                $_SESSION['cart'][$variant_id] = $available_stock; // ตั้งค่าเป็น max stock
            } elseif ($quantity > 0) {
                 // ถ้าจำนวนถูกต้องและมากกว่า 0 ให้อัปเดตตามปกติ
                $_SESSION['cart'][$variant_id] = $quantity;
                $_SESSION['success_message'] = "อัปเดตจำนวนสินค้าเรียบร้อย";
            } else {
                 // ถ้าจำนวนเป็น 0 หรือน้อยกว่า ให้ลบออกจากตะกร้า
                unset($_SESSION['cart'][$variant_id]);
                 $_SESSION['success_message'] = "ลบสินค้าออกจากตะกร้าเรียบร้อย";
            }
        } else {
             // ถ้าไม่พบ variant_id (อาจเกิดข้อผิดพลาด) ให้แจ้งเตือน
             $_SESSION['error_message'] = "ไม่พบข้อมูลสินค้า";
        }
        $stmt_stock_check->close();

    } else {
        $_SESSION['error_message'] = "ข้อมูลสินค้าไม่ถูกต้อง";
    }
    header('Location: cart.php'); // Redirect กลับไปหน้าตะกร้า
    exit();
}

// --- สำหรับลิงก์ลบสินค้า (action 'remove' จาก GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'remove') {
    // ★ แก้ไข: ตรวจสอบ variant_id ว่าเป็นตัวเลขที่มากกว่า 0 หรือไม่
    if (!empty($_GET['variant_id']) && filter_var($_GET['variant_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $variant_id = intval($_GET['variant_id']);
        unset($_SESSION['cart'][$variant_id]);
        $_SESSION['success_message'] = "ลบสินค้าออกจากตะกร้าเรียบร้อย"; // ★ เพิ่ม: ข้อความสำเร็จ
    } else {
        $_SESSION['error_message'] = "ข้อมูลสินค้าไม่ถูกต้อง"; // ★ เพิ่ม: ข้อความผิดพลาด
    }
    header('Location: cart.php'); // Redirect กลับไปหน้าตะกร้า
    exit();
}

// กรณีไม่มี action ที่ตรงกัน หรือเข้าถึงไฟล์โดยตรง ให้กลับไปหน้าแรก
header('Location: index.php');
exit();
?>