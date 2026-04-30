<?php
require 'header.php';

// 1. ตรวจสอบว่ามี order_id และผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

// 2. ดึงข้อมูล Order หลัก (ตรวจสอบว่าเป็นของ user ที่ login อยู่จริง)
$stmt_order = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$order_result = $stmt_order->get_result();

if ($order_result->num_rows === 0) {
    echo "<div class='container'><div class='alert-danger'>ไม่พบคำสั่งซื้อ หรือคุณไม่มีสิทธิ์เข้าถึง</div></div>";
    require 'footer.php';
    exit();
}
$order = $order_result->fetch_assoc();
$stmt_order->close();

// 3. ดึงรายการสินค้าใน Order นั้นๆ
$stmt_items = $conn->prepare(
    "SELECT p.name, v.color, v.size, oi.quantity, oi.price 
     FROM order_items oi 
     JOIN product_variants v ON oi.variant_id = v.id
     JOIN products p ON v.product_id = p.id 
     WHERE oi.order_id = ?"
);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$stmt_items->close();
?>

<div class="order-success-container">
    <h1>สั่งซื้อสำเร็จ!</h1>
    <p>ขอบคุณสำหรับคำสั่งซื้อของคุณ เราได้รับข้อมูลและจะรีบดำเนินการจัดส่งโดยเร็วที่สุด</p>

    <div class="order-summary-card">
        <h3>สรุปคำสั่งซื้อ #<?= $order_id ?></h3>
        
        <div class="summary-details">
            <div><strong>วันที่สั่งซื้อ:</strong> <span><?= date("d/m/Y H:i", strtotime($order['order_date'])) ?></span></div>
            <div><strong>สถานะ:</strong> <span class="status-badge status-pending"><?= htmlspecialchars($order['order_status']) ?></span></div>
            <div><strong>ที่อยู่จัดส่ง:</strong> <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p></div>
        </div>
        
        <table class="summary-items-table">
            <thead>
                <tr>
                    <th>สินค้า</th>
                    <th class="text-center">จำนวน</th>
                    <th class="text-right">ราคารวม</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['name']) ?>
                        <small>(<?= htmlspecialchars($item['color']) ?> / <?= htmlspecialchars($item['size']) ?>)</small>
                    </td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-right">฿<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="summary-total">
            <strong>ยอดรวมสุทธิ:</strong> <span>฿<?= number_format($order['total_amount'], 2) ?></span>
        </div>
    </div>

    <div class="action-buttons">
        <a href="order_history.php" class="btn btn--primary">ดูประวัติการสั่งซื้อทั้งหมด</a>
        <a href="index.php" class="btn btn--outline">กลับไปหน้าแรก</a>
    </div>
</div>
