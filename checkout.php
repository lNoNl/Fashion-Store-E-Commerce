<?php
require 'header.php';

// 1. ตรวจสอบว่าล็อกอินและมี order_id หรือไม่
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['order_id']);

// 2. ดึงข้อมูล Order และตรวจสอบว่าเป็นของ User ที่ล็อกอินอยู่
$stmt_order = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();
$stmt_order->close();

if (!$order) {
    // ไม่พบออเดอร์ หรือไม่ใช่ของ user คนนี้
    header("Location: order_history.php");
    exit();
}

// 3. ดึงข้อมูลรายการสินค้าในออเดอร์
$stmt_items = $conn->prepare(
    "SELECT p.name, p.image_url, v.color, v.size, oi.quantity, oi.price
     FROM order_items oi
     JOIN product_variants v ON oi.variant_id = v.id
     JOIN products p ON v.product_id = p.id
     WHERE oi.order_id = ?"
);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$cart_items_result = $stmt_items->get_result();
?>

<div class="page-header">
    <h1>ชำระเงิน</h1>
    <a href="order_history.php" class="btn btn--outline btn--small">ดูประวัติการสั่งซื้อ</a>
</div>

<?php if ($order['order_status'] === 'ยังไม่ได้ชำระเงิน'): ?>
    <div class="alert-warning" style="margin-bottom: 20px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="alert-icon"><info/></svg>
        ออเดอร์ของคุณยังไม่เสร็จสมบูรณ์ กรุณาชำระเงินและอัปโหลดสลิปเพื่อยืนยันการสั่งซื้อ
    </div>
<?php else: ?>
    <div class="alert-success" style="margin-bottom: 20px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="alert-icon"><check-circle/></svg>
        คุณได้ยืนยันการชำระเงินสำหรับออเดอร์นี้แล้ว ขณะนี้ออเดอร์ของคุณอยู่ในสถานะ: <strong><?= htmlspecialchars($order['order_status']) ?></strong>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'upload_failed'): ?>
    <div class="alert-danger" style="margin-bottom: 20px;">
        เกิดข้อผิดพลาดในการอัปโหลดไฟล์สลิป โปรดลองอีกครั้ง
    </div>
<?php endif; ?>

<div class="checkout-container">
    <div class="checkout-details">
        <div class="detail-card">
            <h3>ที่อยู่ในการจัดส่ง</h3>
            <address><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></address>
        </div>
        
        <?php if ($order['order_status'] === 'ยังไม่ได้ชำระเงิน'): ?>
        <div class="detail-card">
            <h3>การชำระเงิน</h3>
            <p>กรุณาโอนเงินมาที่บัญชีด้านล่าง และอัปโหลดสลิปเพื่อยืนยัน</p>
            <div class="bank-details">
                <p><strong>ธนาคาร:</strong> กสิกรไทย</p>
                <p><strong>ชื่อบัญชี:</strong> นางสาว ธนัญนดา มิลินทสูตร</p>
                <p><strong>เลขที่บัญชี:</strong> 123-4-56789-0</p>
            </div>
            <div class="qr-code">
                <p><strong>หรือสแกน QR Code:</strong></p>
                <img src="img/my-qr-code.jpg" alt="QR Code for payment" class="qr-code-img">
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="checkout-summary">
        <div class="detail-card">
            <h3>สรุปรายการสั่งซื้อ #<?= $order_id ?></h3>
            
            <?php while($item = $cart_items_result->fetch_assoc()): ?>
            <div class="summary-item-row">
                <img src="<?= htmlspecialchars($item['image_url']) ?>" width="50">
                <span class="item-name">
                    <?= htmlspecialchars($item['name']) ?> 
                    (<?= htmlspecialchars($item['color']) ?> / <?= htmlspecialchars($item['size']) ?>) x<?= $item['quantity'] ?>
                </span>
                <span class="item-price">฿<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
            </div>
            <?php endwhile; ?>
            <hr>
            
            <div class="summary-row grand-total">
                <span>ยอดรวมสุทธิ</span>
                <span>฿<?= number_format($order['total_amount'], 2) ?></span>
            </div>

            <?php if ($order['order_status'] === 'ยังไม่ได้ชำระเงิน'): ?>
                <form action="payment_process.php" method="post" enctype="multipart/form-data" id="checkout-form" class="mt-20">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    <div class="form-group">
                        <label for="payment_slip" class="form-label-bold">อัปโหลดสลิปการโอนเงิน:</label>
                        <input type="file" name="payment_slip" id="payment_slip" class="form-control" required accept="image/*">
                    </div>
                    
                    <!-- UPDATED: Removed 'disabled' attribute to make the button clickable initially -->
                    <button type="submit" id="confirm-order-btn" class="btn btn--success btn--full-width mt-15">กรุณาอัปโหลดสลิป</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const slipInput = document.getElementById('payment_slip');
    const confirmBtn = document.getElementById('confirm-order-btn');
    const checkoutForm = document.getElementById('checkout-form');

    // 1. UPDATED: เปลี่ยนข้อความปุ่มตามการเลือกไฟล์
    if (slipInput && confirmBtn) {
        slipInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                confirmBtn.textContent = 'ยืนยันการชำระเงิน';
            } else {
                confirmBtn.textContent = 'กรุณาอัปโหลดสลิป';
            }
        });
    }

    // 2. เพิ่มการตรวจสอบไฟล์เมื่อกดส่งฟอร์ม
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            // ตรวจสอบว่ามีการเลือกไฟล์สลิปหรือไม่เมื่อผู้ใช้พยายามส่งฟอร์ม
            if (slipInput.files.length === 0) {
                event.preventDefault(); // หยุดการส่งฟอร์ม
                alert('คุณยังไม่อัพโหลดสลิปการโอนเงิน'); // แสดงข้อความแจ้งเตือน
                return; 
            }
            
            // หากมีไฟล์แล้ว ให้ถามเพื่อยืนยันอีกครั้งก่อนส่งข้อมูล
            if (!confirm('คุณยืนยันการชำระเงินสำหรับออเดอร์นี้ใช่หรือไม่?')) {
                event.preventDefault(); // ยกเลิกการส่งฟอร์มถ้าผู้ใช้กด Cancel
            }
        });
    }
});
</script>

<?php 
if(isset($stmt_items)) $stmt_items->close();
?>