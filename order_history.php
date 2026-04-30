<?php
require 'header.php';

// 1. ตรวจสอบว่าล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// NEW: CSRF Protection - สร้าง Token เพื่อความปลอดภัยในการยกเลิกออเดอร์
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// 2. ใช้ Prepared Statement เพื่อดึงข้อมูลอย่างปลอดภัย
$sql = "SELECT id, total_amount, order_date, order_status 
        FROM orders 
        WHERE user_id = ? 
        ORDER BY order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="page-header">
    <h1>ประวัติการสั่งซื้อ</h1>
    <a href="index.php" class="btn btn--outline btn--small">กลับไปเลือกซื้อสินค้า</a>
</div>

<div class="table-wrapper">
    <?php if (!empty($orders)): ?>
        <table class="table order-history-table">
            <thead>
                <tr>
                    <th>รหัสคำสั่งซื้อ</th>
                    <th>วันที่สั่งซื้อ</th>
                    <th>ยอดรวม</th>
                    <th>สถานะ</th>
                    <!-- ❗️ EDIT: ลบ class="text-right" ออกเพื่อให้ CSS (ที่ตั้งค่าเป็น text-align: left) ทำงาน -->
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?= $order['id'] ?></td>
                    <td><?= date("d/m/Y H:i", strtotime($order['order_date'])) ?></td>
                    <td>฿<?= number_format($order['total_amount'], 2) ?></td>
                    <td>
                        <?php
                            $status = $order['order_status'];
                            $status_class = '';
                            switch ($status) {
                                case 'ยังไม่ได้ชำระเงิน':
                                case 'รอดำเนินการ': 
                                    $status_class = 'status-pending'; 
                                    break;
                                case 'กำลังจัดส่ง': 
                                    $status_class = 'status-shipped'; 
                                    break;
                                case 'ส่งแล้ว': 
                                    $status_class = 'status-delivered'; 
                                    break;
                                case 'ยกเลิก': 
                                    $status_class = 'status-cancelled'; 
                                    break;
                            }
                        ?>
                        <span class="status-badge <?= $status_class ?>">
                            <?= htmlspecialchars($order['order_status']) ?>
                        </span>
                    </td>
                    <td class="action-links">
                        <a href="order_detail.php?order_id=<?= $order['id'] ?>" class="btn btn--outline btn--small">ดูรายละเอียด</a>
                        
                        <?php if ($order['order_status'] == 'รอดำเนินการ'): ?>
                            <!-- ❗️ EDIT: เปลี่ยน onclick="confirm()" เป็น data-href และ class สำหรับ Modal -->
                            <a href="#" 
                               class="btn btn--danger btn--small cancel-order-btn" 
                               data-href="cancel_order.php?order_id=<?= $order['id'] ?>&csrf_token=<?= $csrf_token ?>">ยกเลิก</a>
                        
                        <?php elseif ($order['order_status'] == 'ยังไม่ได้ชำระเงิน'): ?>
                            <a href="checkout.php?order_id=<?= $order['id'] ?>" class="btn btn--success btn--small">ชำระเงิน</a>
                            <!-- ❗️ EDIT: เปลี่ยน onclick="confirm()" เป็น data-href และ class สำหรับ Modal -->
                            <a href="#" 
                               class="btn btn--danger btn--small cancel-order-btn" 
                               data-href="cancel_order.php?order_id=<?= $order['id'] ?>&csrf_token=<?= $csrf_token ?>">ยกเลิก</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; padding: 20px;">คุณยังไม่มีประวัติการสั่งซื้อ</p>
    <?php endif; ?>
</div>


<!-- 
=================================
    ❗️ NEW: CUSTOM CONFIRMATION MODAL
=================================
-->
<!-- Modal CSS -->
<style>
:root {
    /* ตรวจสอบว่ามี CSS Variables เหล่านี้อยู่แล้วหรือไม่ */
    --surface-color: #fffffc;
    --primary-color: #1f2937;
    --text-color: #333333;
}
.custom-modal-backdrop {
    display: none;
    position: fixed;
    z-index: 1050;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    -webkit-backdrop-filter: blur(5px);
    backdrop-filter: blur(5px);
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
}
.custom-modal-backdrop.is-visible {
    display: block;
    opacity: 1;
}
.custom-modal {
    position: fixed;
    z-index: 1060;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.9);
    width: 90%;
    max-width: 450px;
    background: var(--surface-color, #ffffff); /* ใช้สีจาก CSS หลัก ถ้ามี */
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    opacity: 0;
    transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
}
.custom-modal-backdrop.is-visible .custom-modal {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}
.custom-modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e0e0e0;
}
.custom-modal-header h3 {
    margin: 0;
    font-size: 1.4rem;
    color: var(--primary-color, #1f2937); /* ใช้สีจาก CSS หลัก ถ้ามี */
}
.custom-modal-body {
    padding: 25px;
    font-size: 1rem;
    color: var(--text-color, #333); /* ใช้สีจาก CSS หลัก ถ้ามี */
    line-height: 1.6;
}
.custom-modal-body p {
    margin: 0;
}
.custom-modal-footer {
    padding: 20px 25px;
    background-color: #f9fafb;
    border-top: 1px solid #e0e0e0;
    border-radius: 0 0 12px 12px;
    text-align: right;
}
.custom-modal-footer .btn {
    margin-left: 10px;
}
</style>

<!-- Confirmation Modal HTML -->
<div id="cancel-confirm-modal-backdrop" class="custom-modal-backdrop">
    <div id="cancel-confirm-modal" class="custom-modal">
        <div class="custom-modal-header">
            <h3>ยืนยันการยกเลิก</h3>
        </div>
        <div class="custom-modal-body">
            <p>คุณต้องการยกเลิกคำสั่งซื้อนี้ใช่หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" id="modal-cancel-btn" class="btn btn--outline">ปิด</button>
            <!-- นี่คือลิงก์ที่ถูกเปลี่ยน URL โดย JS -->
            <a href="#" id="modal-confirm-btn" class="btn btn--danger">ยืนยันการยกเลิก</a>
        </div>
    </div>
</div>

<!-- ❗️ NEW: JavaScript for Modal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalBackdrop = document.getElementById('cancel-confirm-modal-backdrop');
    const modalConfirmBtn = document.getElementById('modal-confirm-btn');
    const modalCancelBtn = document.getElementById('modal-cancel-btn');
    const cancelButtons = document.querySelectorAll('.cancel-order-btn');

    // ฟังก์ชันแสดง Modal
    function showModal(event) {
        event.preventDefault(); // ป้องกันไม่ให้ลิงก์ (#) ทำงาน
        const cancelUrl = event.currentTarget.getAttribute('data-href');
        modalConfirmBtn.setAttribute('href', cancelUrl); // ตั้งค่า URL ให้ปุ่ม "ยืนยัน"
        modalBackdrop.classList.add('is-visible');
    }

    // ฟังก์ชันซ่อน Modal
    function hideModal() {
        modalBackdrop.classList.remove('is-visible');
        modalConfirmBtn.setAttribute('href', '#'); // ล้าง URL ออกจากปุ่ม "ยืนยัน"
    }

    // เพิ่ม Event Listener ให้กับปุ่ม "ยกเลิก" ทุกปุ่มในตาราง
    cancelButtons.forEach(function(button) {
        button.addEventListener('click', showModal);
    });

    // เพิ่ม Event Listener ให้กับปุ่ม "ปิด" ใน Modal
    modalCancelBtn.addEventListener('click', hideModal);

    // เพิ่ม Event Listener ให้กับพื้นหลัง Modal (สำหรับคลิกนอกกรอบเพื่อปิด)
    modalBackdrop.addEventListener('click', function(event) {
        if (event.target === modalBackdrop) {
            hideModal();
        }
    });
});
</script>

<?php
?>