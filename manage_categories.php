<?php
require 'header.php';

// --- 1. ตรวจสอบสิทธิ์ Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// --- NEW: CSRF Protection - Generate Token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =================================================================
// SECTION C: PHP LOGIC FOR CATEGORY MANAGEMENT
// =================================================================

// --- C1. Handle Add Category ---
if (isset($_POST['add_category'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Token ไม่ถูกต้อง, การดำเนินการล้มเหลว';
        header("Location: manage_categories.php");
        exit();
    }
    $category_name = trim($_POST['category_name']);
    if (!empty($category_name)) {
        try {
            $stmt_add_cat = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt_add_cat->bind_param("s", $category_name);
            $stmt_add_cat->execute();
            $_SESSION['message'] = "เพิ่มหมวดหมู่ '" . htmlspecialchars($category_name) . "' สำเร็จ";
            $stmt_add_cat->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $_SESSION['error_message'] = "ไม่สามารถเพิ่มหมวดหมู่ได้: ชื่อ '" . htmlspecialchars($category_name) . "' มีอยู่ในระบบแล้ว";
            } else {
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }
        header("Location: manage_categories.php");
        exit();
    }
}

// --- C2. Handle Delete Category ---
if (isset($_GET['delete_category'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        $_SESSION['error_message'] = 'Token ไม่ถูกต้อง, การดำเนินการล้มเหลว';
        header("Location: manage_categories.php");
        exit();
    }
    $category_id = intval($_GET['delete_category']);
    $stmt_del_cat = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt_del_cat->bind_param("i", $category_id);
    if ($stmt_del_cat->execute()) {
        $_SESSION['message'] = "ลบหมวดหมู่สำเร็จ";
    } else {
        $_SESSION['error_message'] = "ไม่สามารถลบหมวดหมู่ได้ (อาจเกิดจากมีสินค้าใช้หมวดหมู่นี้อยู่)";
    }
    $stmt_del_cat->close();
    header("Location: manage_categories.php");
    exit();
}

// --- C3. Fetch Categories with Pagination ---
$results_per_page = 7;
$current_page = isset($_GET['c_page']) && is_numeric($_GET['c_page']) ? (int)$_GET['c_page'] : 1;
$offset = ($current_page - 1) * $results_per_page;

// นับจำนวนหมวดหมู่ทั้งหมด
$total_sql = "SELECT COUNT(*) as total FROM categories";
$total_result = $conn->query($total_sql);
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $results_per_page);

// ดึงข้อมูลหมวดหมู่สำหรับหน้าปัจจุบัน
$sql = "SELECT * FROM categories ORDER BY name ASC LIMIT ?, ?";
$stmt_cat = $conn->prepare($sql);
$stmt_cat->bind_param("ii", $offset, $results_per_page);
$stmt_cat->execute();
$categories_result = $stmt_cat->get_result();

?>

<!-- NEW: Add main content wrapper -->
<main class="main-content">
<style>
    /* เพิ่มขอบสีดำให้กรอบด้านนอก (detail-card) ตามคำขอ */
    .detail-card {
        border: 1px solid #000;
    }
</style>
<div class="container">

    <div class="page-header">
        <h1>จัดการหมวดหมู่</h1>
        <div class="button-group">
            <a href="dashboard.php" class="btn btn--primary">📋 จัดการออเดอร์</a>
            <a href="manage_products.php" class="btn btn--primary">📦 จัดการสินค้า</a>
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

    <div class="detail-card">
        <h2 class="section-title">เพิ่มหมวดหมู่ใหม่</h2>
        <div class="controls-panel">
            <!-- EDIT: Changed class to 'category-form' -->
            <form method="POST" class="category-form" action="manage_categories.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="text" name="category_name" class="form-control" placeholder="ชื่อหมวดหมู่..." required>
                <button type="submit" name="add_category" class="btn btn--primary">เพิ่มหมวดหมู่</button>
            </form>
        </div>
    </div>

    <div class="detail-card" style="margin-top: 20px;">
        <h2 id="categories" class="section-title">หมวดหมู่ทั้งหมด</h2>
        <div class="table-wrapper">
            <table class="table category-table">
                <thead>
                    <tr>
                        <th>ชื่อหมวดหมู่</th>
                        <th class="text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                        <?php while($category = $categories_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($category['name']) ?></td>
                                <td class="action-links">
                                    <!-- EDIT: Changed button class to btn--danger -->
                                    <a href="manage_categories.php?delete_category=<?= $category['id'] ?>&csrf_token=<?= htmlspecialchars($csrf_token) ?>#categories" class="btn btn--danger btn--small" onclick="return confirm('คำเตือน: การลบหมวดหมู่อาจส่งผลกระทบต่อสินค้าที่ใช้หมวดหมู่นี้อยู่ ยืนยันที่จะลบ?')">ลบ</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" class="text-center table-empty-row">ยังไม่มีหมวดหมู่สินค้า</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <?php
                $query_params = $_GET;
                if ($total_pages > 1):
                    // Previous button
                    if ($current_page > 1) {
                        $query_params['c_page'] = $current_page - 1;
                    }
                    // Page numbers
                    for ($page = 1; $page <= $total_pages; $page++) {
                        $query_params['c_page'] = $page;
                        echo '<a href="manage_categories.php?' . http_build_query($query_params) . '#categories" class="' . ($page == $current_page ? 'active' : '') . '">' . $page . '</a>';
                    }
                endif;
            ?>
        </div>
    </div>

</div> <!-- /.container -->
</main> <!-- /.main-content -->

<?php
if(isset($stmt_cat)) $stmt_cat->close();
?>