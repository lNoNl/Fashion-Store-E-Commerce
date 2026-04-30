<?php
require 'header.php';

// --- 1. ตรวจสอบสิทธิ์ Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// --- NEW: Fetch categories for filter dropdown ---
$all_categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$all_categories = $all_categories_result->fetch_all(MYSQLI_ASSOC);

// =================================================================
// SECTION A: PHP LOGIC FOR PRODUCT MANAGEMENT
// =================================================================
$product_results_per_page = 6;
$product_current_page = isset($_GET['p_page']) && is_numeric($_GET['p_page']) ? (int)$_GET['p_page'] : 1;
$product_offset = ($product_current_page - 1) * $product_results_per_page;
$product_search_term = isset($_GET['p_search']) ? trim($_GET['p_search']) : '';
$filter_category = isset($_GET['p_category']) ? intval($_GET['p_category']) : 0;
$low_stock_threshold = 20;
$p_where_clauses = ["p.status IN ('visible', 'hidden')"];
$p_params = [];
$p_types = '';

if (!empty($product_search_term)) {
    $p_where_clauses[] = "p.name LIKE ?";
    $p_params[] = "%{$product_search_term}%";
    $p_types .= 's';
}
// ★★★ FIXED: Filter by category ID on the joined table 'c'
if (!empty($filter_category)) {
    $p_where_clauses[] = "c.id = ?";
    $p_params[] = $filter_category;
    $p_types .= 'i';
}

$p_where_sql = " WHERE " . implode(' AND ', $p_where_clauses);

// ★★★ FIXED: Added JOIN for filtering to work correctly
$p_total_sql = "SELECT COUNT(DISTINCT p.id) as total 
                FROM products p 
                LEFT JOIN categories c ON p.category = c.name" . $p_where_sql;
$stmt_p_total = $conn->prepare($p_total_sql);
if (!empty($p_params)) {
    $stmt_p_total->bind_param($p_types, ...$p_params);
}
$stmt_p_total->execute();
$p_total_results = $stmt_p_total->get_result()->fetch_assoc()['total'];
$p_total_pages = ceil($p_total_results / $product_results_per_page);
$stmt_p_total->close();

// ★★★ FIXED: Changed JOIN condition from p.category_id to p.category = c.name
$products_sql = "SELECT p.*, p.category as category_name, SUM(v.stock_quantity) as total_stock 
                 FROM products p
                 LEFT JOIN product_variants v ON p.id = v.product_id
                 LEFT JOIN categories c ON p.category = c.name
                 {$p_where_sql} 
                 GROUP BY p.id
                 ORDER BY p.created_at DESC LIMIT ?, ?";
$p_params_for_fetch = $p_params;
$p_types_for_fetch = $p_types;
$p_params_for_fetch[] = $product_offset;
$p_params_for_fetch[] = $product_results_per_page;
$p_types_for_fetch .= 'ii';
$stmt_products = $conn->prepare($products_sql);
$stmt_products->bind_param($p_types_for_fetch, ...$p_params_for_fetch);
$stmt_products->execute();
$product_list_result = $stmt_products->get_result();

$low_stock_sql = "
    SELECT p.id, p.name, v.color, v.size, v.stock_quantity
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    WHERE v.stock_quantity <= ? AND v.stock_quantity > 0 AND p.status = 'visible'
    ORDER BY p.id, v.stock_quantity ASC
";
$stmt_low_stock = $conn->prepare($low_stock_sql);
$stmt_low_stock->bind_param("i", $low_stock_threshold);
$stmt_low_stock->execute();
$low_stock_items = $stmt_low_stock->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_low_stock->close();
$grouped_low_stock = [];
foreach ($low_stock_items as $item) {
    $grouped_low_stock[$item['id']]['name'] = $item['name'];
    $grouped_low_stock[$item['id']]['variants'][] = $item;
}
?>

<main class="main-content">
<style>
    /* เพิ่มขอบสีดำให้ตารางและแผงควบคุมตามคำขอ */
    .table-wrapper {
        border: 1px solid #000;
    }
    .controls-panel {
        border: 1px solid #000;
    }
</style>
<div class="container">

    <div class="page-header">
        <h1>จัดการสินค้า</h1>
        <div class="button-group">
            <a href="dashboard.php" class="btn btn--primary">📋 จัดการออเดอร์</a>
            <a href="manage_categories.php" class="btn btn--primary">🗂️ จัดการหมวดหมู่</a>
            <a href="sales_summary.php" class="btn btn--primary">📊 สรุปยอดขาย</a>
            <a href="add_product.php" class="btn btn--primary">＋ เพิ่มสินค้าใหม่</a>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert-success mb-20"><?= htmlspecialchars($_SESSION['message']) ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert-danger mb-20"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (!empty($grouped_low_stock)): ?>
    <div class="detail-card low-stock-alert">
        <h3>⚠️ สินค้าใกล้หมดสต็อก! (เหลือน้อยกว่าหรือเท่ากับ <?= $low_stock_threshold ?> ชิ้น)</h3>
        <ul class="low-stock-list">
            <?php foreach($grouped_low_stock as $product_id => $product_data): ?>
                <li>
                    <a href="edit_product.php?id=<?= $product_id ?>"><?= htmlspecialchars($product_data['name']) ?></a>
                    <div class="low-stock-variants">
                        <?php foreach ($product_data['variants'] as $variant): ?>
                            <span><?= htmlspecialchars($variant['color']) ?> / <?= htmlspecialchars($variant['size']) ?>: <strong><?= $variant['stock_quantity'] ?> ชิ้น</strong></span>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <hr class="section-divider">

    <h2 id="products" class="section-title">รายการสินค้าทั้งหมด</h2>
    <div class="controls-panel">
        <form method="GET" class="product-search-form" action="manage_products.php#products">
            <select name="p_category" class="form-control">
                <option value="">-- ทุกหมวดหมู่ --</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($filter_category == $cat['id'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="p_search" class="form-control" placeholder="ค้นหาด้วยชื่อสินค้า..." value="<?= htmlspecialchars($product_search_term) ?>">
            <button type="submit" class="btn btn--primary">ค้นหา</button>
        </form>
    </div>

    <div class="table-wrapper">
        <table class="table product-table">
            <thead>
                <tr>
                    <th>รูปภาพ</th>
                    <th>ชื่อสินค้า</th>
                    <th>ราคา</th>
                    <th>หมวดหมู่</th>
                    <th>สต็อกรวม</th>
                    <th>สถานะ</th>
                    <th class="text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody id="product-table-body">
                <?php if ($product_list_result && $product_list_result->num_rows > 0): ?>
                    <?php while($product = $product_list_result->fetch_assoc()): ?>
                        <?php
                            $stock = (int)($product['total_stock'] ?? 0);
                            $stock_class = '';
                            if ($stock <= 0) { $stock_class = 'stock-out'; } 
                            elseif ($stock <= $low_stock_threshold) { $stock_class = 'stock-low'; }
                        ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($product['image_url']) ?>" width="60" class="product-table-img"></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td>฿<?= number_format($product['price'], 2) ?></td>
                            <td><?= htmlspecialchars($product['category_name'] ?? $product['category'] ?? 'N/A') ?></td>
                            <td class="stock-cell <?= $stock_class ?>"><?= $stock ?></td>
                            <td class="status-cell">
                                <?php if ($product['status'] == 'visible'): ?>
                                    <span class="status-badge status-delivered">✔️ แสดงสินค้า</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending status-hidden">➖ ซ่อนสินค้า</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-links">
                                <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn--outline btn--small">แก้ไข</a>
                                <?php if ($product['status'] == 'visible'): ?>
                                    <button type="button" class="btn btn--outline btn--small toggle-status-btn" data-action="hide" data-id="<?= $product['id'] ?>">ซ่อน</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn--success btn--small toggle-status-btn" data-action="unhide" data-id="<?= $product['id'] ?>">แสดง</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center table-empty-row">ไม่พบสินค้าในระบบ</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php if ($p_total_pages > 1):
            $query_params = $_GET;
            for ($page = 1; $page <= $p_total_pages; $page++):
                $query_params['p_page'] = $page;
        ?>
                <a href="manage_products.php?<?= http_build_query($query_params) ?>#products" class="<?= ($page == $product_current_page) ? 'active' : '' ?>"><?= $page ?></a>
        <?php endfor; endif; ?>
    </div>

</div> <!-- Close container -->
</main> <!-- Close main-content -->


<script>
document.addEventListener('DOMContentLoaded', function() {
    const productTableBody = document.getElementById('product-table-body');
    if (productTableBody) {
        productTableBody.addEventListener('click', function(event) {
            const button = event.target.closest('.toggle-status-btn');
            if (!button) return;
            
            event.preventDefault();

            if (button.dataset.action === 'hide' && !confirm('ต้องการซ่อนสินค้านี้ใช่หรือไม่?')) {
                return;
            }

            const action = button.dataset.action;
            const productId = button.dataset.id;
            const statusCell = button.closest('tr').querySelector('.status-cell');
            
            fetch('product_status_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${action}&id=${productId}`
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    let newButtonHTML = '';
                    if (data.new_status === 'visible') {
                        statusCell.innerHTML = '<span class="status-badge status-delivered">✔️ แสดงสินค้า</span>';
                        newButtonHTML = `<button type="button" class="btn btn--outline btn--small toggle-status-btn" data-action="hide" data-id="${productId}">ซ่อน</button>`;
                    } else {
                        statusCell.innerHTML = '<span class="status-badge status-pending status-hidden">➖ ซ่อนสินค้า</span>';
                        newButtonHTML = `<button type="button" class="btn btn--success btn--small toggle-status-btn" data-action="unhide" data-id="${productId}">แสดง</button>`;
                    }
                    const actionLinksCell = button.parentElement;
                    const editButton = actionLinksCell.querySelector('a.btn');
                    actionLinksCell.innerHTML = editButton.outerHTML + ' ' + newButtonHTML;
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            });
        });
    }
});
</script>

<?php
if(isset($stmt_products)) $stmt_products->close();
?>