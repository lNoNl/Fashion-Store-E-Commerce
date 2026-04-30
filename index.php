<?php
require 'header.php';

// --- 1. การตั้งค่าการแบ่งหน้า (Pagination) ---
$results_per_page = 12;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $results_per_page;

// --- 3. รับค่าจากฟอร์มฟิลเตอร์ ---
$search_term = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort_order = $_GET['sort'] ?? 'newest';

// --- 4. สร้างเงื่อนไข Query ---
$where_clauses = ["p.status = 'visible'"];
$params = [];
$types = '';

if (!empty($search_term)) {
    $where_clauses[] = "p.name LIKE ?";
    $params[] = "%{$search_term}%";
    $types .= 's';
}
if (!empty($category)) {
    $where_clauses[] = "p.category = ?";
    $params[] = $category;
    $types .= 's';
}
if ($min_price !== '' && is_numeric($min_price)) {
    $where_clauses[] = "p.price >= ?";
    $params[] = $min_price;
    $types .= 'd';
}
if ($max_price !== '' && is_numeric($max_price)) {
    $where_clauses[] = "p.price <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

$where_sql = " WHERE " . implode(' AND ', $where_clauses);

// --- 5. นับจำนวนสินค้าทั้งหมดที่ตรงเงื่อนไข ---
$total_sql = "SELECT COUNT(DISTINCT p.id) as total FROM products p" . $where_sql;
$stmt_total = $conn->prepare($total_sql);
if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_results = $stmt_total->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $results_per_page);
$stmt_total->close();

// --- 6. ดึงข้อมูลสินค้าสำหรับแสดงผลในหน้าปัจจุบัน ---
$order_by_clause = " ORDER BY p.created_at DESC"; // Default sort
switch ($sort_order) {
    case 'price_asc': $order_by_clause = " ORDER BY p.price ASC"; break;
    case 'price_desc': $order_by_clause = " ORDER BY p.price DESC"; break;
}

$sql = "SELECT p.id, p.name, p.price, p.image_url, SUM(v.stock_quantity) as total_stock
        FROM products p
        LEFT JOIN product_variants v ON p.id = v.product_id
        {$where_sql}
        GROUP BY p.id
        {$order_by_clause}
        LIMIT ?, ?";

$params_for_fetch = $params;
$types_for_fetch = $types;
$params_for_fetch[] = $offset;
$params_for_fetch[] = $results_per_page;
$types_for_fetch .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params_for_fetch)) {
    $stmt->bind_param($types_for_fetch, ...$params_for_fetch);
}
$stmt->execute();
$result = $stmt->get_result();

// ดึงข้อมูลสำหรับ Dropdown Filters
$categories_result = $conn->query("SELECT DISTINCT category FROM products WHERE status='visible' ORDER BY category ASC");
?>
<main class="main-content">
<div class="container">
    <div class="filter-toggle-bar">
        <button id="toggle-filter-btn" class="btn btn--primary">
            <span class="icon">🔍</span> ตัวกรองสินค้า
        </button>
    </div>
    
    <div class="controls-panel" id="filter-panel">
        <form action="index.php" method="GET" class="controls-form">
            <div class="filter-inputs">
                <input type="text" name="search" class="form-control" placeholder="ค้นหาสินค้า..." value="<?= htmlspecialchars($search_term) ?>">
                <select name="category" class="form-control custom-select">
                    <option value="">-- ทุกประเภท --</option>
                    <?php while ($cat = $categories_result->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= ($category === $cat['category'] ? 'selected' : '') ?>><?= htmlspecialchars($cat['category']) ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="number" name="min_price" class="form-control" placeholder="ราคาต่ำสุด" value="<?= htmlspecialchars($min_price) ?>">
                <input type="number" name="max_price" class="form-control" placeholder="ราคาสูงสุด" value="<?= htmlspecialchars($max_price) ?>">
                <select name="sort" class="form-control custom-select">
                    <option value="newest" <?= ($sort_order === 'newest' ? 'selected' : '') ?>>สินค้าใหม่ล่าสุด</option>
                    <option value="price_asc" <?= ($sort_order === 'price_asc' ? 'selected' : '') ?>>ราคา: ต่ำไปสูง</option>
                    <option value="price_desc" <?= ($sort_order === 'price_desc' ? 'selected' : '') ?>>ราคา: สูงไปต่ำ</option>
                </select>
            </div>
            <div class="filter-button">
                <button type="submit" class="btn btn--primary">ค้นหา</button>
            </div>
        </form>
    </div>

    <div class="product-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="product-card">
                    <!-- NEW: Added image wrapper for modern styling -->
                    <div class="product-image-wrapper">
                        <a href="product_detail.php?id=<?= $row['id'] ?>">
                            <img src="<?= htmlspecialchars($row['image_url']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                        </a>
                    </div>
                    <div class="product-info">
                        <div class="product-name">
                            <a href="product_detail.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a>
                        </div>
                        <div class="product-price">฿<?= number_format($row['price'], 2) ?></div>
                        
                        <!-- REMOVED Stock Status Text -> Replaced with Button -->
                        
                        <a href="product_detail.php?id=<?= $row['id'] ?>" class="btn btn--primary">ดูรายละเอียด</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-products-found">ไม่พบสินค้าที่ตรงกับเงื่อนไขของคุณ</p>
        <?php endif; ?>
    </div>

    <div class="pagination">
        <?php
            $query_params = $_GET;
            if ($total_pages > 1):
                // Previous button
                if ($current_page > 1) {
                    $query_params['page'] = $current_page - 1;
                    echo '<a href="?' . http_build_query($query_params) . '" class="prev-next-btn">ก่อนหน้า</a>';
                }
                // Page numbers
                for ($page = 1; $page <= $total_pages; $page++) {
                    $query_params['page'] = $page;
                    echo '<a href="?' . http_build_query($query_params) . '" class="' . ($page == $current_page ? 'active' : '') . '">' . $page . '</a>';
                }
                // Next button
                if ($current_page < $total_pages) {
                    $query_params['page'] = $current_page + 1;
                    echo '<a href="?' . http_build_query($query_params) . '" class="prev-next-btn">ถัดไป</a>';
                }
            endif;
        ?>
    </div>

</div>
</main> 



<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-filter-btn');
    const filterPanel = document.getElementById('filter-panel');
    toggleBtn.addEventListener('click', function() {
        filterPanel.classList.toggle('is-open');
    });
});
</script>

<?php 
if (isset($stmt)) $stmt->close();
require 'footer.php';
?>