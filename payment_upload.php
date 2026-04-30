<?php
require 'header.php';
// ... (โค้ดดึงข้อมูลออเดอร์จาก $_GET['order_id'] เพื่อมาแสดงสรุป) ...
?>
<div class="auth-container">
    <div class="auth-card">
        <h1>ยืนยันการชำระเงิน</h1>
        <p>ออเดอร์ของคุณถูกสร้างเรียบร้อยแล้ว กรุณาชำระเงินและอัปโหลดสลิปเพื่อยืนยัน</p>

        <form action="payment_process.php" method="post" enctype="multipart/form-data" class="mt-20">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($_GET['order_id']) ?>">
            <div class="form-group">
                <label for="payment_slip" class="form-label-bold">อัปโหลดสลิปการโอนเงิน:</label>
                <input type="file" name="payment_slip" id="payment_slip" class="form-control" required accept="image/*">
            </div>
            <button type="submit" class="btn btn--success btn--full-width mt-15">ยืนยันการชำระเงิน</button>
        </form>
    </div>
</div>
