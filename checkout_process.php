<?php
session_start();
require_once 'config.php';

// 1. ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'checkout.php';
    header("Location: login.php");
    exit();
}

// 2. ตรวจสอบว่าตะกร้าไม่ว่าง
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit();
}

// --- เริ่มต้น Database Transaction ---
$conn->begin_transaction();

try {
    // --- 3. จัดการไฟล์อัปโหลด ---
    $payment_slip_url = null;
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['payment_slip']['tmp_name'];
        $file_name = $_FILES['payment_slip']['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $upload_dir = 'uploads/slips/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $new_filename = uniqid('slip-', true) . '.' . $file_extension;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                $payment_slip_url = $dest_path;
            } else {
                throw new Exception('ไม่สามารถบันทึกไฟล์สลิปได้');
            }
        } else {
            throw new Exception('ประเภทไฟล์ไม่ถูกต้อง (อนุญาตเฉพาะ jpg, jpeg, png, gif)');
        }
    } else {
        throw new Exception('กรุณาอัปโหลดสลิปการโอนเงิน');
    }

    // --- 4. ดึงข้อมูลจาก variants และตรวจสอบสต็อก ---
    $user_id = $_SESSION['user_id'];
    $subtotal = 0;
    $variant_ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($variant_ids), '?'));
    $types = str_repeat('i', count($variant_ids));
    
    $sql_variants = "SELECT v.id as variant_id, v.stock_quantity, p.id as product_id, p.name, p.price 
                     FROM product_variants v
                     JOIN products p ON v.product_id = p.id
                     WHERE v.id IN ($placeholders)";
    
    $stmt_variants = $conn->prepare($sql_variants);
    $stmt_variants->bind_param($types, ...$variant_ids);
    $stmt_variants->execute();
    $variants_result = $stmt_variants->get_result();

    $order_items_data = [];
    while ($variant = $variants_result->fetch_assoc()) {
        $variant_id = $variant['variant_id'];
        $quantity = $_SESSION['cart'][$variant_id];
        if ($variant['stock_quantity'] < $quantity) {
            throw new Exception("สินค้า '" . htmlspecialchars($variant['name']) . "' มีไม่เพียงพอในสต็อก!");
        }
        $subtotal += $variant['price'] * $quantity;

        // ========== ★★★ จุดที่แก้ไข ★★★ ==========
        // แก้ไขให้เก็บ product_id และ variant_id ให้ถูกต้อง
        $order_items_data[] = [
            'product_id' => $variant['product_id'], // ดึง product_id จากผลลัพธ์
            'variant_id' => $variant_id,         // ดึง variant_id จากผลลัพธ์
            'price'      => $variant['price'],
            'quantity'   => $quantity
        ];
    }
    $stmt_variants->close();

    // --- 5. คำนวณยอดเงินและดึงข้อมูลที่อยู่ ---
    $shipping_fee = ($subtotal > 0 && $subtotal < 1500) ? 29 : 0;
    $grand_total = $subtotal + $shipping_fee;
    
    $stmt_user = $conn->prepare("SELECT address, province, amphoe, tambon, zipcode FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    $shipping_address = ($user_data) 
        ? trim($user_data['address'] . "\n" . 'ต.' . $user_data['tambon'] . ' อ.' . $user_data['amphoe'] . "\n" . 'จ.' . $user_data['province'] . ' ' . $user_data['zipcode'])
        : 'N/A';

    // --- 6. บันทึกข้อมูลลงตาราง `orders` ---
    $stmt_order = $conn->prepare(
        "INSERT INTO orders (user_id, total_amount, shipping_address, order_status, payment_slip_url) 
         VALUES (?, ?, ?, 'รอดำเนินการ', ?)"
    );
    $stmt_order->bind_param("idss", $user_id, $grand_total, $shipping_address, $payment_slip_url);
    $stmt_order->execute();
    $order_id = $stmt_order->insert_id;
    $stmt_order->close();

    // --- 7. บันทึกรายการลง order_items และตัดสต็อก ---
    $stmt_item = $conn->prepare(
        "INSERT INTO order_items (order_id, product_id, variant_id, quantity, price) 
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt_stock = $conn->prepare("UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ?");

    foreach ($order_items_data as $item) {
        $stmt_item->bind_param("iiiid", $order_id, $item['product_id'], $item['variant_id'], $item['quantity'], $item['price']);
        $stmt_item->execute();
        
        $stmt_stock->bind_param("ii", $item['quantity'], $item['variant_id']);
        $stmt_stock->execute();
    }
    $stmt_item->close();
    $stmt_stock->close();

    // --- 8. ยืนยัน Transaction และส่งต่อไปยังหน้าสำเร็จ ---
    $conn->commit();
    unset($_SESSION['cart']);
    header("Location: order_success.php?order_id=" . $order_id);
    exit();

} catch (Exception $e) {
    // --- หากเกิดข้อผิดพลาด ให้ยกเลิก Transaction ---
    $conn->rollback();
    
    // แสดงข้อความผิดพลาด
    echo "<!DOCTYPE html><html><head><title>Error</title><link rel='stylesheet' href='style.css'></head><body>";
    echo "<div class='container' style='text-align:center; padding: 40px;'>";
    echo "<h1>เกิดข้อผิดพลาดในการสั่งซื้อ</h1>";
    echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='cart.php' class='back-to-shop-button'>กลับไปที่ตะกร้าสินค้า</a>";
    echo "</div></body></html>";
    exit();
}
?>