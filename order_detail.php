<?php
require 'header.php';

// --- ตั้งค่าโซนเวลาและภาษาเพื่อให้วันที่แสดงผลถูกต้อง ---
date_default_timezone_set('Asia/Bangkok');
setlocale(LC_TIME, 'th_TH.UTF-8');

// --- NEW: CSRF Protection - Generate Token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 1. ตรวจสอบสิทธิ์ และรับค่า ID
if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$order_id = intval($_GET['order_id']);
$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'user';

// 2. จัดการการอัปเดตสถานะและเลขพัสดุ (สำหรับ Admin)
if ($user_role === 'admin' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message_error'] = 'Token ไม่ถูกต้อง, การดำเนินการล้มเหลว';
    } else if (isset($_POST['status'])) {
        $new_status = $_POST['status'];
        $tracking_number = trim($_POST['tracking_number']);

        $sql_update = "UPDATE orders SET order_status = ?, tracking_number = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ssi", $new_status, $tracking_number, $order_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['message'] = "อัปเดตสถานะและเลขพัสดุของ Order #" . htmlspecialchars($order_id) . " สำเร็จ!";
        } else {
            $_SESSION['message_error'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
        }
        $stmt_update->close();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// 3. ดึงข้อมูล Order หลัก (เพิ่ม tracking_number)
$sql = "SELECT o.*, u.username, u.first_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?";
$params = [$order_id];
$types = "i";

if ($user_role !== 'admin') {
    $sql .= " AND o.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$stmt_order = $conn->prepare($sql);
$stmt_order->bind_param($types, ...$params);
$stmt_order->execute();
$order_result = $stmt_order->get_result();

if ($order_result->num_rows === 0) {
    echo "<main class=\"main-content\"><div class=\"container\"><h1>ไม่พบคำสั่งซื้อ หรือคุณไม่มีสิทธิ์เข้าถึง</h1></div></main>";
    require 'footer.php';
    exit();
}
$order = $order_result->fetch_assoc();
$stmt_order->close();

// 4. ดึงข้อมูลรายการสินค้าใน Order
$stmt_items = $conn->prepare(
   "SELECT p.name, p.image_url, v.color, v.size, oi.quantity, oi.price 
    FROM order_items oi 
    JOIN product_variants v ON oi.variant_id = v.id
    JOIN products p ON v.product_id = p.id 
    WHERE oi.order_id = ?"
);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
?>

<main class="main-content">
<div class="container">

    <div class="page-header">
        <h1>รายละเอียดคำสั่งซื้อ #<?= htmlspecialchars($order_id) ?></h1>
        <?php if ($user_role === 'admin'): ?>
            <a href="dashboard.php#orders" class="btn btn--primary">กลับไปหน้ารายการออเดอร์</a>
        <?php else: ?>
            <a href="order_history.php" class="btn btn--primary">กลับไปประวัติการสั่งซื้อ</a>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert-success" style="margin-bottom:20px;"><?= htmlspecialchars($_SESSION['message']) ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['message_error'])): ?>
        <div class="alert-danger" style="margin-bottom:20px;"><?= htmlspecialchars($_SESSION['message_error']) ?></div>
        <?php unset($_SESSION['message_error']); ?>
    <?php endif; ?>
        
    <div class="order-detail-container">
        
        <div class="left-column">
            <div class="detail-card">
                <h3>ข้อมูลลูกค้าและหลักฐานการโอน</h3>
                <div class="info-row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="info-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <div class="info-content">
                        <strong>ชื่อลูกค้า</strong>
                        <p><?= htmlspecialchars($order['first_name'] ?? $order['username']) ?></p>
                    </div>
                </div>
                
                <div class="info-row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="info-icon"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <div class="info-content">
                        <strong>ที่อยู่จัดส่ง</strong>
                        <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                    </div>
                </div>
                
                <?php if (!empty($order['payment_slip_url'])): ?>
                <div class="info-row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="info-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <div class="info-content">
                        <strong>หลักฐานการโอนเงิน</strong>
                        <a id="slip-thumbnail-link" href="<?= htmlspecialchars($order['payment_slip_url']) ?>" title="คลิกเพื่อดูภาพขยาย">
                            <img src="<?= htmlspecialchars($order['payment_slip_url']) ?>" alt="Payment Slip" style="max-width: 100%; border-radius: 5px; margin-top: 5px; border: 1px solid #eee; cursor:pointer;" onerror="this.style.display='none'; this.parentElement.insertAdjacentHTML('beforeend', '<p>ไม่พบไฟล์สลิป</p>');">
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-column">
            <div class="detail-card">
                <h3>สรุปคำสั่งซื้อ</h3>
                <div class="info-row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="info-icon"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <div class="info-content">
                        <strong>วันที่สั่งซื้อ</strong>
                        <p><?= strftime("%d %B %Y, %H:%M", strtotime($order['order_date'])) ?></p>
                    </div>
                </div>
                <div class="info-row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="info-icon"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    <div class="info-content">
                        <strong>สถานะ</strong>
                        <p>
                            <?php
                                $status = $order['order_status'];
                                $status_class = '';
                                switch ($status) {
                                    case 'รอดำเนินการ': $status_class = 'status-pending'; break;
                                    case 'กำลังจัดส่ง': $status_class = 'status-shipped'; break;
                                    case 'ส่งแล้ว': $status_class = 'status-delivered'; break;
                                    case 'ยกเลิก': $status_class = 'status-cancelled'; break;
                                }
                            ?>
                            <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                        </p>
                    </div>
                </div>
                <?php if (!empty($order['tracking_number'])): ?>
                <div class="info-row">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="info-icon"><path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <div class="info-content">
                        <strong>เลขพัสดุ</strong>
                        <p><a href="https://track.thailandpost.co.th/?trackNumber=<?= htmlspecialchars($order['tracking_number']) ?>" target="_blank"><?= htmlspecialchars($order['tracking_number']) ?></a></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="summary-total">
                    ยอดรวมสุทธิ: <span>฿<?= number_format($order['total_amount'], 2) ?></span>
                </div>
                <a href="print_invoice.php?order_id=<?= htmlspecialchars($order_id) ?>" target="_blank" class="btn btn--primary" style="margin-top:15px; display:inline-block; font-size: 0.9em;">📄 พิมพ์ใบสั่งซื้อ</a>
            </div>
            
            <div class="detail-card" style="margin-top:20px;">
                <h3>รายการสินค้า</h3>
                <?php if ($items_result && $items_result->num_rows > 0): ?>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th colspan="2">สินค้า</th>
                            <th style="text-align:center;">จำนวน</th>
                            <th style="text-align:right;">ราคารวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td width="80">
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" width="60" style="border-radius:4px;" alt="<?= htmlspecialchars($item['name']) ?>">
                            </td>
                            <td>
                                <div class="product-cell-info">
                                    <?= htmlspecialchars($item['name']) ?>
                                    <small style="color:#6c757d; display:block;">(<?= htmlspecialchars($item['color']) ?> / <?= htmlspecialchars($item['size']) ?>)</small>
                                </div>
                            </td>
                            <td style="text-align:center;"><?= htmlspecialchars($item['quantity']) ?></td>
                            <td style="text-align:right;">฿<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>ไม่พบรายการสินค้าในคำสั่งซื้อนี้</p>
                <?php endif; ?>
            </div>
        </div>
    </div> <!-- Close order-detail-container -->

    <?php if ($user_role === 'admin'): ?>
    <div class="detail-card" style="margin-top:20px;">
        <h3>เปลี่ยนสถานะ &amp; เลขพัสดุ</h3>
        <form action="order_detail.php?order_id=<?= htmlspecialchars($order_id) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="form-group">
                <label for="status">สถานะคำสั่งซื้อ</label>
                <select name="status" id="status" class="form-control">
                    <option value="รอดำเนินการ" <?= $order['order_status'] == 'รอดำเนินการ' ? 'selected' : '' ?>>รอดำเนินการ</option>
                    <option value="กำลังจัดส่ง" <?= $order['order_status'] == 'กำลังจัดส่ง' ? 'selected' : '' ?>>กำลังจัดส่ง</option>
                    <option value="ส่งแล้ว" <?= $order['order_status'] == 'ส่งแล้ว' ? 'selected' : '' ?>>ส่งแล้ว</option>
                    <option value="ยกเลิก" <?= $order['order_status'] == 'ยกเลิก' ? 'selected' : '' ?>>ยกเลิก</option>
                </select>
            </div>
            <div class="form-group">
                <label for="tracking_number">เลขพัสดุ (EMS)</label>
                <input type="text" id="tracking_number" name="tracking_number" class="form-control" value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>" placeholder="กรอกเลขพัสดุที่นี่">
            </div>
            <button type="submit" name="update_status" class="btn btn--primary">อัปเดตข้อมูล</button>
        </form>
    </div>
    <?php endif; ?>

</div> <!-- Close container -->
</main> <!-- Close main-content -->

<div id="slip-modal" class="slip-viewer-modal">
  <span class="slip-viewer-close">&times;</span>
  <img class="slip-viewer-modal-content" id="slip-modal-image">
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById("slip-modal");
    if (!modal) return;

    const modalImg = document.getElementById("slip-modal-image");
    const thumbnailLink = document.getElementById("slip-thumbnail-link");
    const closeBtn = document.querySelector(".slip-viewer-close");

    const closeModal = () => {
        modal.style.display = "none";
    };

    if (thumbnailLink) {
        thumbnailLink.addEventListener('click', function(event) {
            event.preventDefault();
            if(modalImg) {
                modalImg.src = this.href;
            }
            modal.style.display = "block";
        });
    }

    if (closeBtn) {
        closeBtn.onclick = closeModal;
    }
    
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });
});
</script>

<?php
if(isset($stmt_items)) $stmt_items->close();
require 'footer.php';
?>