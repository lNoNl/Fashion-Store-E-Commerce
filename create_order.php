<?php
require 'config.php';
session_start();

// 1. ตรวจสอบว่าล็อกอินหรือยัง และมีของในตะกร้าหรือไม่
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'cart.php';
    header('Location: login.php');
    exit();
}
if (empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. ดึงข้อมูลที่อยู่ผู้ใช้
$stmt_user = $conn->prepare("SELECT address, province, amphoe, tambon, zipcode FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// ตรวจสอบว่าที่อยู่ครบถ้วนหรือไม่
if (empty($user['address']) || empty($user['province'])) {
    $_SESSION['error_message'] = "กรุณากรอกที่อยู่ในการจัดส่งให้ครบถ้วนก่อนทำการสั่งซื้อ";
    header("Location: profile.php?from=cart");
    exit();
}
$shipping_address = implode(', ', array_filter([$user['address'], 'ต.' . $user['tambon'], 'อ.' . $user['amphoe'], 'จ.' . $user['province'], $user['zipcode']]));


// 3. ดึงข้อมูลตะกร้า, คำนวณยอดรวม, และตรวจสอบสต็อก
$variant_ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($variant_ids), '?'));
$types = str_repeat('i', count($variant_ids));

// ★★★ แก้ไข: เพิ่ม p.id as product_id เข้ามาใน Query ★★★
$sql = "SELECT v.id as variant_id, p.id as product_id, v.stock_quantity, p.price, p.name 
        FROM product_variants v 
        JOIN products p ON v.product_id = p.id 
        WHERE v.id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$variant_ids);
$stmt->execute();
$items_result = $stmt->get_result();

$subtotal = 0;
$order_items_data = [];
while ($item = $items_result->fetch_assoc()) {
    $quantity_in_cart = $_SESSION['cart'][$item['variant_id']];

    if ($quantity_in_cart > $item['stock_quantity']) {
        $_SESSION['error_message'] = "ขออภัย, สินค้า '" . htmlspecialchars($item['name']) . "' มีไม่พอในสต็อก (เหลือเพียง " . $item['stock_quantity'] . " ชิ้น)";
        header("Location: cart.php");
        exit();
    }

    $subtotal += $item['price'] * $quantity_in_cart;
    // ★★★ แก้ไข: เพิ่ม product_id เข้าไปใน array ที่จะบันทึก ★★★
    $order_items_data[] = [
        'variant_id' => $item['variant_id'],
        'product_id' => $item['product_id'], // เพิ่มบรรทัดนี้
        'quantity' => $quantity_in_cart,
        'price' => $item['price']
    ];
}
$stmt->close();

$shipping_fee = ($subtotal > 0 && $subtotal < 1500) ? 29 : 0;
$grand_total = $subtotal + $shipping_fee;

$conn->begin_transaction();
try {
    // 4. สร้าง Order หลัก
    $order_status = 'ยังไม่ได้ชำระเงิน';
    $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, order_status) VALUES (?, ?, ?, ?)");
    $stmt_order->bind_param("idss", $user_id, $grand_total, $shipping_address, $order_status);
    $stmt_order->execute();
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // 5. เพิ่มรายการสินค้า และตัดสต็อก
    // ★★★ แก้ไข: เพิ่ม product_id ในคำสั่ง INSERT ★★★
    $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, product_id, variant_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
    $stmt_update_stock = $conn->prepare("UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ?");

    foreach ($order_items_data as $item_data) {
        // ★★★ แก้ไข: เพิ่ม product_id ในการ bind parameter ★★★
        $stmt_items->bind_param("iiiid", $order_id, $item_data['product_id'], $item_data['variant_id'], $item_data['quantity'], $item_data['price']);
        $stmt_items->execute();

        $stmt_update_stock->bind_param("ii", $item_data['quantity'], $item_data['variant_id']);
        $stmt_update_stock->execute();
    }
    $stmt_items->close();
    $stmt_update_stock->close();

    $conn->commit();

    // 6. ล้างตะกร้าสินค้า
    unset($_SESSION['cart']);

    // 7. ส่งไปหน้าชำระเงิน
    header("Location: checkout.php?order_id=" . $order_id);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการสร้างออเดอร์: " . $e->getMessage();
    header("Location: cart.php");
    exit();
}
?>

