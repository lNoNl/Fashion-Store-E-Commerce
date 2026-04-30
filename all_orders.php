<?php
require 'header.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// NEW: CSRF Protection - Generate Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =================================================================
// PHP LOGIC FOR ORDER MANAGEMENT
// =================================================================
$order_results_per_page = 15;
$order_current_page = isset($_GET['o_page']) && is_numeric($_GET['o_page']) ? (int)$_GET['o_page'] : 1;
$order_offset = ($order_current_page - 1) * $order_results_per_page;
$order_filter_status = $_GET['o_status'] ?? 'all';
$order_search_term = $_GET['o_search'] ?? '';
$o_where_clauses = [];
$o_params = [];
$o_types = "";

if ($order_filter_status !== 'all') {
    $o_where_clauses[] = "o.order_status = ?";
    $o_params[] = $order_filter_status;
    $o_types .= "s";
}
if (!empty($order_search_term)) {
    $o_where_clauses[] = "(o.id LIKE ? OR u.username LIKE ?)";
    $o_search_param = "%" . $order_search_term . "%";
    $o_params[] = $o_search_param;
    $o_params[] = $o_search_param;
    $o_types .= "ss";
}
$o_where_sql = "";
if (!empty($o_where_clauses)) {
    $o_where_sql = " WHERE " . implode(" AND ", $o_where_clauses);
}

// Count total results for pagination
$o_total_sql = "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id" . $o_where_sql;
$stmt_o_total = $conn->prepare($o_total_sql);
if (!empty($o_params)) {
    $stmt_o_total->bind_param($o_types, ...$o_params);
}
$stmt_o_total->execute();
$o_total_results = $stmt_o_total->get_result()->fetch_assoc()['total'];
$o_total_pages = ceil($o_total_results / $order_results_per_page);
$stmt_o_total->close();

// Fetch orders for the current page
$orders_sql = "SELECT o.id, o.total_amount, o.order_date, o.order_status, u.username, o.payment_slip_url
               FROM orders o
               JOIN users u ON o.user_id = u.id"
               . $o_where_sql .
               " ORDER BY o.order_date DESC
               LIMIT ?, ?";
$o_params_for_fetch = $o_params;
$o_types_for_fetch = $o_types;
$o_params_for_fetch[] = $order_offset;
$o_params_for_fetch[] = $order_results_per_page;
$o_types_for_fetch .= "ii";
$stmt_orders = $conn->prepare($orders_sql);
$stmt_orders->bind_param($o_types_for_fetch, ...$o_params_for_fetch);
$stmt_orders->execute();
$all_orders_result = $stmt_orders->get_result();

// Check for orders that can be processed in bulk
$status_shipping = 'กำลังจัดส่ง';
$check_shipping_sql = "SELECT COUNT(*) as count FROM orders WHERE order_status = ?";
$stmt_check = $conn->prepare($check_shipping_sql);
$stmt_check->bind_param("s", $status_shipping);
$stmt_check->execute();
$has_shipping_orders = $stmt_check->get_result()->fetch_assoc()['count'] > 0;
$stmt_check->close();

$status_pending = 'รอดำเนินการ';
$check_pending_sql = "SELECT COUNT(*) as count FROM orders WHERE order_status = ?";
$stmt_check_pending = $conn->prepare($check_pending_sql);
$stmt_check_pending->bind_param("s", $status_pending);
$stmt_check_pending->execute();
$has_pending_orders = $stmt_check_pending->get_result()->fetch_assoc()['count'] > 0;
$stmt_check_pending->close();
?>

<div class="page-header">
    <h1>ออเดอร์ลูกค้าทั้งหมด</h1>
    <a href="dashboard.php" class="btn btn--outline">กลับไปหน้า Dashboard</a>
</div>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert-success mb-20"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert-danger mb-20"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="controls-panel">
    <div class="status-filters">
        <?php
            $statuses = ['all' => 'ทั้งหมด', 'ยังไม่ได้ชำระเงิน' => 'ยังไม่ชำระเงิน', 'รอดำเนินการ' => 'รอดำเนินการ', 'กำลังจัดส่ง' => 'กำลังจัดส่ง', 'ส่งแล้ว' => 'ส่งแล้ว', 'ยกเลิก' => 'ยกเลิก'];
            foreach ($statuses as $key => $label):
                $query_params = $_GET;
                $query_params['o_status'] = $key;
                unset($query_params['o_page']);
                $active_class = ($order_filter_status === $key) ? 'active' : '';
        ?>
            <a href="all_orders.php?<?= http_build_query($query_params) ?>" class="status-filter-btn <?= $active_class ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <div class="action-forms">
                <form method="GET" class="order-search-form" action="dashboard.php#orders">
                    <input type="hidden" name="o_status" value="<?= htmlspecialchars($order_filter_status) ?>">
                    <input type="text" name="o_search" class="form-control" placeholder="ค้นหา ID หรือชื่อลูกค้า..." value="<?= htmlspecialchars($order_search_term) ?>">
                    <button type="submit" class="btn btn--primary">ค้นหา</button>

                    <?php if ($has_pending_orders): ?>
                        <a href="update_all_orders.php?action=accept&csrf_token=<?= $csrf_token ?>&redirect=dashboard.php" class="btn btn--primary" onclick="return confirm('คุณต้องการรับออเดอร์ที่รอดำเนินการทั้งหมดใช่หรือไม่?')">รับออเดอร์ทั้งหมด</a> 
                        <?php /* <-- อาจจะเปลี่ยน class เป็น btn--outline ถ้าต้องการ */ ?>
                    <?php else: ?>
                        <a href="#" class="btn btn--primary" onclick="alert('ไม่มีออเดอร์ที่รอดำเนินการ'); return false;">รับออเดอร์ทั้งหมด</a> 
                        <?php /* <-- อาจจะเปลี่ยน class เป็น btn--outline ถ้าต้องการ */ ?>
                    <?php endif; ?>
                    </form>
                 <div class="button-group">
                    <?php /* ปุ่ม "รับออเดอร์ทั้งหมด" ถูกย้ายออกไปแล้ว */ ?>
                    <?php if ($has_shipping_orders): ?>
                        <a href="update_all_orders.php?action=ship&csrf_token=<?= $csrf_token ?>&redirect=dashboard.php" class="btn btn--primary" onclick="return confirm('คุณต้องการอัปเดตสถานะออเดอร์ที่ \'กำลังจัดส่ง\' ทั้งหมดเป็น \'ส่งแล้ว\' ใช่หรือไม่?')">จัดส่งทั้งหมด</a>
                    <?php endif; ?>
                </div>
            </div>
</div>

<div class="table-wrapper">
    <table class="table order-history-table">
        <thead>
            <tr>
                <th>รหัส</th><th>ชื่อลูกค้า</th><th>วันที่สั่งซื้อ</th><th>ยอดรวม</th><th>สถานะ</th><th class="text-right">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($all_orders_result && $all_orders_result->num_rows > 0): ?>
                <?php while($order = $all_orders_result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $order['id'] ?></td>
                    <td><?= htmlspecialchars($order['username']) ?></td>
                    <td><?= date("d/m/Y H:i", strtotime($order['order_date'])) ?></td>
                    <td>฿<?= number_format($order['total_amount'], 2) ?></td>
                    <td>
                        <?php
                            $status = $order['order_status'];
                            $status_class = '';
                            switch ($status) {
                                case 'ยังไม่ได้ชำระเงิน':
                                case 'รอดำเนินการ': $status_class = 'status-pending'; break;
                                case 'กำลังจัดส่ง': $status_class = 'status-shipped'; break;
                                case 'ส่งแล้ว': $status_class = 'status-delivered'; break;
                                case 'ยกเลิก': $status_class = 'status-cancelled'; break;
                            }
                        ?>
                        <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                    </td>
                    <td class="action-links">
                        <?php if (!empty($order['payment_slip_url'])): ?>
                            <a href="#" class="btn btn--outline btn--small view-slip-btn" data-slip-url="<?= htmlspecialchars($order['payment_slip_url']) ?>">ดูสลิป</a>
                        <?php endif; ?>

                        <?php if ($order['order_status'] == 'รอดำเนินการ'): ?>
                            <a href="update_order_status.php?order_id=<?= $order['id'] ?>&status=กำลังจัดส่ง&csrf_token=<?= $csrf_token ?>" class="btn btn--success btn--small" onclick="return confirm('ยืนยันการรับออเดอร์นี้?')">รับออเดอร์</a>
                        <?php elseif ($order['order_status'] == 'กำลังจัดส่ง'): ?>
                            <a href="update_order_status.php?order_id=<?= $order['id'] ?>&status=ส่งแล้ว&csrf_token=<?= $csrf_token ?>" class="btn btn--primary btn--small" onclick="return confirm('ยืนยันว่าจัดส่งออเดอร์นี้แล้ว?')">ส่งแล้ว</a>
                        <?php endif; ?>

                        <a href="order_detail.php?order_id=<?= $order['id'] ?>" class="btn btn--outline btn--small">ดูรายละเอียด</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center table-empty-row">ไม่พบข้อมูลคำสั่งซื้อ</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php if ($o_total_pages > 1):
        $query_params = $_GET;
        for ($page = 1; $page <= $o_total_pages; $page++):
            $query_params['o_page'] = $page;
    ?>
            <a href="all_orders.php?<?= http_build_query($query_params) ?>" class="<?= ($page == $order_current_page) ? 'active' : '' ?>"><?= $page ?></a>
    <?php endfor; endif; ?>
</div>

<div id="slip-modal" class="slip-viewer-modal">
  <span class="slip-viewer-close">&times;</span>
  <img class="slip-viewer-modal-content" id="slip-modal-image">
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById("slip-modal");
    if (modal) {
        const modalImg = document.getElementById("slip-modal-image");
        const closeBtn = document.querySelector(".slip-viewer-close");
        const closeModal = () => { modal.style.display = "none"; };

        document.body.addEventListener('click', function(event) {
            const button = event.target.closest('.view-slip-btn');
            if (button) {
                event.preventDefault();
                const slipUrl = button.dataset.slipUrl;
                if (modalImg && slipUrl) {
                    modalImg.src = slipUrl;
                    modal.style.display = "block";
                }
            }
        });

        if (closeBtn) closeBtn.onclick = closeModal;
        modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
        document.addEventListener('keydown', (event) => { if (event.key === "Escape") closeModal(); });
    }
});
</script>

<?php
if(isset($stmt_orders)) $stmt_orders->close();
?>