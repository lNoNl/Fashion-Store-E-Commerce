<?php
require 'header.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$error = '';
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// =================================================================
// 2. ส่วนประมวลผลฟอร์มเมื่อมีการกด "บันทึกการเปลี่ยนแปลง" (POST)
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = intval($_POST['product_id']);
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $brand = $_POST['brand'];
    $status = $_POST['status'];

    $conn->begin_transaction();
    try {
        // อัปเดตข้อมูลในตาราง `products`
        $sql_product = "UPDATE products SET name=?, description=?, price=?, category=?, brand=?, status=? WHERE id=?";
        $stmt_product = $conn->prepare($sql_product);
        $stmt_product->bind_param("ssdsssi", $name, $description, $price, $category, $brand, $status, $product_id);
        $stmt_product->execute();
        $stmt_product->close();

        // จัดการ Variants (ลบของเก่าทั้งหมด แล้วเพิ่มของใหม่)
        $stmt_delete_variants = $conn->prepare("DELETE FROM product_variants WHERE product_id = ?");
        $stmt_delete_variants->bind_param("i", $product_id);
        $stmt_delete_variants->execute();
        $stmt_delete_variants->close();

        if (isset($_POST['variants'])) {
            $stmt_insert_variant = $conn->prepare("INSERT INTO product_variants (product_id, color, size, stock_quantity) VALUES (?, ?, ?, ?)");
            foreach ($_POST['variants'] as $variant) {
                if (!empty($variant['color']) && !empty($variant['size']) && isset($variant['stock']) && $variant['stock'] !== '') {
                    $stock = intval($variant['stock']);
                    $stmt_insert_variant->bind_param("issi", $product_id, $variant['color'], $variant['size'], $stock);
                    $stmt_insert_variant->execute();
                }
            }
            $stmt_insert_variant->close();
        }
        
        // (ส่วนจัดการรูปภาพ)
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            // ... (โค้ดอัปโหลดเหมือนเดิม) ...
        }
        
        $conn->commit();
        $_SESSION['message'] = "อัปเดตข้อมูลสินค้า '" . htmlspecialchars($name) . "' สำเร็จ!";
        header("Location: edit_product.php?id=" . $product_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }
}

// =================================================================
// 3. ส่วนดึงข้อมูลมาแสดงในฟอร์ม (GET)
// =================================================================
if ($product_id === 0) {
    header("Location: manage_products.php");
    exit();
}

// ดึงข้อมูลสินค้าหลัก
$stmt_get_product = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt_get_product->bind_param("i", $product_id);
$stmt_get_product->execute();
$product_result = $stmt_get_product->get_result();
if ($product_result->num_rows === 0) {
    header("Location: manage_products.php");
    exit();
}
$product = $product_result->fetch_assoc();
$stmt_get_product->close();

// ค้นหาสินค้าก่อนหน้าและถัดไป
$prev_product_id = $conn->query("SELECT id FROM products WHERE id < $product_id ORDER BY id DESC LIMIT 1")->fetch_assoc()['id'] ?? null;
$next_product_id = $conn->query("SELECT id FROM products WHERE id > $product_id ORDER BY id ASC LIMIT 1")->fetch_assoc()['id'] ?? null;

// ค้นหาไซส์ที่ใช้บ่อยที่สุด
$most_frequent_size = $conn->query("SELECT size FROM product_variants WHERE size IS NOT NULL AND size != '' GROUP BY size ORDER BY COUNT(id) DESC LIMIT 1")->fetch_assoc()['size'] ?? '';

// ดึงข้อมูล Variants ของสินค้านี้
$stmt_get_variants = $conn->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$stmt_get_variants->bind_param("i", $product_id);
$stmt_get_variants->execute();
$variants = $stmt_get_variants->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_get_variants->close();
?>

<div class="page-header">
    <h1>แก้ไขสินค้า #<?= $product_id ?></h1>
    <div class="header-nav-buttons">
        <?php if ($prev_product_id): ?>
            <a href="edit_product.php?id=<?= $prev_product_id ?>" class="btn btn--primary">รายการก่อนหน้า</a>
        <?php endif; ?>
        
        <a href="manage_products.php" class="btn btn--primary">กลับไปหน้าจัดการสินค้า</a>

        <?php if ($next_product_id): ?>
            <a href="edit_product.php?id=<?= $next_product_id ?>" class="btn btn--primary">รายการต่อไป</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($error)) { echo "<div class='alert-danger' style='margin-bottom: 20px;'>" . htmlspecialchars($error) . "</div>"; } ?>
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert-success" style="margin-bottom: 20px;"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<div class="detail-card">
    <form action="edit_product.php?id=<?= $product_id ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?= $product_id ?>">
        
        <div class="form-group">
            <label for="name">ชื่อสินค้า:</label>
            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
        </div>
        <div class="form-group">
            <label for="description">คำอธิบาย:</label>
            <textarea id="description" name="description" class="form-control" rows="6" required><?= htmlspecialchars($product['description']) ?></textarea>
        </div>
        
        <div class="form-grid-3">
            <div class="form-group">
                <label for="price">ราคา (บาท):</label>
                <input type="number" id="price" name="price" class="form-control" step="0.01" value="<?= htmlspecialchars($product['price']) ?>" required>
            </div>
            <div class="form-group">
                <label for="category">ประเภท:</label>
                <input type="text" id="category" name="category" class="form-control" value="<?= htmlspecialchars($product['category']) ?>" required>
            </div>
            <div class="form-group">
                <label for="brand">ยี่ห้อ:</label>
                <input type="text" id="brand" name="brand" class="form-control" value="<?= htmlspecialchars($product['brand']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="status">สถานะการแสดงผล:</label>
            <select id="status" name="status" class="form-control" required>
                <option value="visible" <?= ($product['status'] == 'visible' ? 'selected' : '') ?>>แสดง (Visible)</option>
                <option value="hidden" <?= ($product['status'] == 'hidden' ? 'selected' : '') ?>>ซ่อน (Hidden)</option>
            </select>
        </div>
        <div class="form-group">
            <label>รูปภาพปัจจุบัน:</label><br>
            <img src="<?= htmlspecialchars($product['image_url']) ?>" width="150" class="current-product-image"><br>
            <label for="product_image">เปลี่ยนรูปภาพ (ไม่ต้องเลือกถ้าไม่ต้องการเปลี่ยน):</label>
            <input type="file" id="product_image" name="product_image" class="form-control">
        </div>
        <hr>

        <h3>ตัวเลือกสินค้า (สี/ไซต์/สต็อก)</h3>
        <div id="variants-container">
            <?php foreach ($variants as $index => $variant): ?>
                <div class="variant-row">
                    <input type="text" name="variants[<?= $index ?>][color]" class="form-control" placeholder="สี" value="<?= htmlspecialchars($variant['color']) ?>" required>
                    <input type="text" name="variants[<?= $index ?>][size]" class="form-control" placeholder="ไซต์" value="<?= htmlspecialchars($variant['size']) ?>" required>
                    <input type="number" name="variants[<?= $index ?>][stock]" class="form-control" placeholder="จำนวนสต็อก" value="<?= htmlspecialchars($variant['stock_quantity']) ?>" min="0" required>
                    <button type="button" class="btn btn--danger btn--small remove-variant-btn">ลบ</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-variant-btn" class="btn btn--primary">+ เพิ่มตัวเลือก</button>
        <hr>

        <div class="form-group mt-20">
            <button type="submit" class="btn btn--primary">บันทึกการเปลี่ยนแปลง</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('variants-container');
    let variantIndex = <?= count($variants) ?>;
    const mostFrequentSize = <?= json_encode($most_frequent_size) ?>;

    document.getElementById('add-variant-btn').addEventListener('click', function() {
        const variantRow = document.createElement('div');
        variantRow.className = 'variant-row';
        variantRow.innerHTML = `
            <input type="text" name="variants[${variantIndex}][color]" class="form-control" placeholder="สี" required>
            <input type="text" name="variants[${variantIndex}][size]" class="form-control" placeholder="ไซต์" value="${mostFrequentSize}" required>
            <input type="number" name="variants[${variantIndex}][stock]" class="form-control" placeholder="จำนวนสต็อก" min="0" required>
            <button type="button" class="btn btn--danger btn--small remove-variant-btn">ลบ</button>
        `;
        container.appendChild(variantRow);
        variantIndex++;
    });

    container.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-variant-btn')) {
            e.target.closest('.variant-row').remove();
        }
    });
});