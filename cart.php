<?php
require 'header.php';

// --- ตั้งค่าโซนเวลาและภาษาเพื่อให้วันที่แสดงผลถูกต้อง ---
date_default_timezone_set('Asia/Bangkok');
setlocale(LC_TIME, 'th_TH.UTF-8');

$subtotal = 0;
?>

<main class="main-content">
<style>
    /* เพิ่มขอบสีดำให้กรอบด้านนอก (table-wrapper, cart-summary, detail-card) ตามคำขอ */
    .table-wrapper,
    .cart-summary,
    .detail-card {
        border: 1px solid #000;
    }
</style>
<div class="container">

<div class="page-header">
    <h1>ตะกร้าสินค้าของคุณ</h1>
</div>

<!-- ▼▼▼ เพิ่ม: ส่วนแสดงข้อความแจ้งเตือน ▼▼▼ -->
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert-danger" style="margin-bottom: 20px;">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert-success" style="margin-bottom: 20px;">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>


<?php if (empty($_SESSION['cart'])): ?>
    <div class="detail-card text-center empty-cart-message">
        <h2>ตะกร้าของคุณว่างเปล่า</h2>
        <p>ดูเหมือนว่าจะยังไม่มีสินค้าในตะกร้าของคุณ</p>
        <a href="index.php" class="btn btn--primary">กลับไปเลือกซื้อสินค้า</a>
    </div>

<?php else: 
    // --- 1. ดึงข้อมูลสินค้าในตะกร้า ---
    $variant_ids = array_keys($_SESSION['cart']);
    $cart_items = [];
    if (!empty($variant_ids)) {
        $placeholders = implode(',', array_fill(0, count($variant_ids), '?'));
        $types = str_repeat('i', count($variant_ids));
        
        $sql = "SELECT v.id as variant_id, v.color, v.size, v.stock_quantity, p.id as product_id, p.name, p.price, p.image_url 
                FROM product_variants v
                JOIN products p ON v.product_id = p.id
                WHERE v.id IN ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$variant_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cart_items[] = $row;
        }
        $stmt->close();
    }
?>
    <div class="cart-page-container">
        <div class="cart-items-list">
            <div class="table-wrapper">
                <table class="table cart-items-table">
                    <thead>
                        <tr>
                            <th class="col-product">สินค้า</th>
                            <th class="col-price">ราคา</th>
                            <th class="col-quantity">จำนวน</th>
                            <th class="col-total">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): 
                        $variant_id = $item['variant_id'];
                        $quantity = $_SESSION['cart'][$variant_id];
                        $line_total = $item['price'] * $quantity;
                        $subtotal += $line_total;
                        ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <a href="product_detail.php?id=<?= $item['product_id'] ?>">
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" width="80">
                                    </a>
                                    <div>
                                        <a href="product_detail.php?id=<?= $item['product_id'] ?>" class="product-cell-info"><?= htmlspecialchars($item['name']) ?></a>
                                        <p class="product-variant-info">
                                            สี: <?= htmlspecialchars($item['color']) ?>, ไซต์: <?= htmlspecialchars($item['size']) ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>฿<?= number_format($item['price'], 2) ?></td>
                            <td>
                                <form action="cart_handler.php" method="post" class="quantity-update-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="variant_id" value="<?= $variant_id ?>">
                                    <input type="number" name="quantity" class="form-control quantity-input" value="<?= $quantity ?>" min="1" max="<?= $item['stock_quantity'] ?>" title="เปลี่ยนจำนวนแล้วกด Enter หรือคลิกที่อื่นเพื่ออัปเดต">
                                </form>
                            </td>
                            <td>
                                <div class="total-cell">
                                    <span>฿<?= number_format($line_total, 2) ?></span>
                                    <a href="cart_handler.php?action=remove&variant_id=<?= $variant_id ?>" class="remove-btn" title="ลบรายการนี้" onclick="return confirm('คุณต้องการลบสินค้านี้ออกจากตะกร้าใช่หรือไม่?')">×</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cart-summary">
            <h3>สรุปยอดสั่งซื้อ</h3>
            <?php
                $shipping_fee = ($subtotal > 0 && $subtotal < 1500) ? 29 : 0;
                $grand_total = $subtotal + $shipping_fee;
            ?>
            <div class="summary-row">
                <span>ยอดรวม (Subtotal)</span>
                <span>฿<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="summary-row">
                <span>ค่าจัดส่ง (Shipping)</span>
                <span>
                    <?php if ($shipping_fee == 0 && $subtotal >= 1500): ?>
                        <span class="free-shipping-text">ฟรี</span>
                    <?php else: ?>
                        ฿<?= number_format($shipping_fee, 2) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="summary-row grand-total">
                <span>ยอดรวมสุทธิ</span>
                <span>฿<?= number_format($grand_total, 2) ?></span>
            </div>

            <a href="create_order.php" class="btn btn--success btn--full-width" onclick="return confirm('คุณต้องการยืนยันการสั่งซื้อและไปยังหน้าชำระเงินใช่หรือไม่?');">ดำเนินการสั่งซื้อ</a>
            <a href="index.php" class="btn btn--outline mt-20 btn--full-width">&larr; กลับไปเลือกซื้อสินค้า</a>
        </div>
    </div>

    <!-- ▼▼▼ เพิ่ม: JavaScript สำหรับอัปเดตจำนวนอัตโนมัติ ▼▼▼ -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const quantityInputs = document.querySelectorAll('.quantity-input');
        
        quantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                // เมื่อมีการเปลี่ยนแปลงค่าในช่องจำนวน ให้ส่งฟอร์มทันที
                this.closest('.quantity-update-form').submit();
            });
        });
    });
    </script>

<?php endif;?> 
</div> <!-- /.container -->
</main> <!-- /.main-content -->
<?php
?>