<?php
require 'header.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// NEW: CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = '';
// ★ เปลี่ยน: ใช้ Session message แทนตัวแปร $success_message
// $success_message = '';

// ดึงข้อมูลหมวดหมู่มาสำหรับ dropdown
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Token ไม่ถูกต้อง, การดำเนินการล้มเหลว';
    } else {
        // ★ เพิ่ม: Trim Input และตรวจสอบค่าเบื้องต้น
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT); // ใช้ filter_input สำหรับตัวเลข
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT); // ใช้ filter_input สำหรับ ID
        $image_url = '';

        // ★ เพิ่ม: ตรวจสอบค่าที่จำเป็นก่อนดำเนินการ
        if (empty($name) || empty($description) || $price === false || $price <= 0 || $category_id === false || $category_id <= 0) {
             $error = "กรุณากรอกข้อมูลสินค้าให้ถูกต้องครบถ้วน (ชื่อ, คำอธิบาย, ราคาต้องมากกว่า 0, เลือกหมวดหมู่)";
        } else {
             // --- การจัดการรูปภาพ ---
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) { // ★ แก้ไข: ใช้ UPLOAD_ERR_OK
                $target_dir = "img/";
                if (!is_dir($target_dir)) {
                    // ★ เพิ่ม: ตรวจสอบผลลัพธ์ mkdir
                    if (!mkdir($target_dir, 0755, true)) {
                        $error = "ไม่สามารถสร้างโฟลเดอร์สำหรับอัปโหลดรูปภาพได้";
                    }
                }

                // ★ เพิ่ม: ตรวจสอบ error ก่อนดำเนินการต่อ
                if (empty($error)) {
                    $file_extension = strtolower(pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION));
                    // ★ แก้ไข: สร้างชื่อไฟล์ที่ไม่ซ้ำกันและปลอดภัยมากขึ้น
                    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES["product_image"]["name"], PATHINFO_FILENAME));
                    $target_file = $target_dir . uniqid('prod_', true) . '_' . $safe_filename . '.' . $file_extension;
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (in_array($file_extension, $allowed_types)) {
                        // ★ เพิ่ม: ตรวจสอบขนาดไฟล์ (ตัวอย่าง: ไม่เกิน 5MB)
                        if ($_FILES["product_image"]["size"] > 5 * 1024 * 1024) {
                            $error = "ขออภัย, ขนาดไฟล์รูปภาพต้องไม่เกิน 5MB";
                        } elseif (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                            $image_url = $target_file;
                        } else {
                            $error = "ขออภัย, เกิดข้อผิดพลาดในการย้ายไฟล์รูปภาพ";
                        }
                    } else {
                        $error = "อนุญาตเฉพาะไฟล์รูปภาพประเภท JPG, JPEG, PNG, GIF, WEBP เท่านั้น";
                    }
                }
            } elseif ($_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // ★ เพิ่ม: จัดการ error อื่นๆ นอกเหนือจากไม่ได้เลือกไฟล์
                 switch ($_FILES['product_image']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = "ขนาดไฟล์เกินขีดจำกัดที่กำหนด";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error = "ไฟล์อัปโหลดมาไม่สมบูรณ์";
                        break;
                    default:
                        $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                 }
            } else {
                 // ถ้าไม่ได้เลือกไฟล์เลย (error == UPLOAD_ERR_NO_FILE)
                 $error = "กรุณาเลือกรูปภาพสินค้า";
            }

            // --- การบันทึกข้อมูล ถ้าไม่มี error ---
            if (empty($error) && !empty($image_url)) {
                $conn->begin_transaction();
                try {
                    $status = 'visible'; // สถานะเริ่มต้น
                    $sql = "INSERT INTO products (name, description, price, category_id, image_url, status) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    // ★ แก้ไข: Parameter type เป็น i สำหรับ category_id
                    $stmt->bind_param("ssdiss", $name, $description, $price, $category_id, $image_url, $status);
                    if (!$stmt->execute()) {
                         throw new Exception("Error inserting product: " . $stmt->error);
                    }
                    $product_id = $stmt->insert_id;
                    $stmt->close();

                    // --- การบันทึก Variants ---
                    $has_valid_variant = false; // ★ เพิ่ม: ตัวแปรเช็คว่ามี variant อย่างน้อย 1 รายการหรือไม่
                    $stmt_variant = $conn->prepare("INSERT INTO product_variants (product_id, color, size, stock_quantity) VALUES (?, ?, ?, ?)");
                    if (isset($_POST['variants']) && is_array($_POST['variants'])) { // ★ เพิ่ม: ตรวจสอบ is_array
                        foreach ($_POST['variants'] as $variant) {
                             // ★ เพิ่ม: ตรวจสอบค่า variant ให้ละเอียดขึ้น
                             $color = trim($variant['color']);
                             $size = trim($variant['size']);
                             $stock_input = $variant['stock']; // ไม่ต้อง trim เพราะจะ filter

                            if (!empty($color) && !empty($size) && isset($stock_input) && filter_var($stock_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false ) {
                                $stock_quantity = intval($stock_input);
                                $stmt_variant->bind_param("issi", $product_id, $color, $size, $stock_quantity);
                                if (!$stmt_variant->execute()) {
                                     // ★ เพิ่ม: โยน Exception ถ้าบันทึก variant ไม่สำเร็จ
                                     throw new Exception("Error inserting variant (Color: $color, Size: $size): " . $stmt_variant->error);
                                }
                                $has_valid_variant = true;
                            } else {
                                // ★ เพิ่ม: แจ้งเตือน (แต่ไม่หยุด) ถ้าข้อมูล variant แถวไหนไม่ครบ หรือ สต็อกไม่ถูกต้อง
                                 error_log("Skipping invalid variant for product ID $product_id: Color='$color', Size='$size', Stock='$stock_input'");
                            }
                        }
                    }
                    $stmt_variant->close();

                    // ★ เพิ่ม: ตรวจสอบว่าต้องมี variant อย่างน้อย 1 รายการ
                    if (!$has_valid_variant) {
                         throw new Exception("ต้องมีตัวเลือกสินค้า (สี/ไซต์/สต็อก) อย่างน้อย 1 รายการที่ถูกต้อง");
                    }


                    $conn->commit();
                    $_SESSION['message'] = "เพิ่มสินค้า '".htmlspecialchars($name)."' พร้อมตัวเลือกสำเร็จ!";
                    header("Location: manage_products.php"); // ★ แก้ไข: ส่งไปหน้า manage_products หลังสำเร็จ
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    // ★ เพิ่ม: ลบรูปภาพที่อัปโหลดไปแล้ว ถ้าเกิด DB error
                    if (!empty($image_url) && file_exists($image_url)) {
                        unlink($image_url);
                    }
                    // ★ เพิ่ม: แสดง error message ที่ละเอียดขึ้น (สำหรับ debug)
                     $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
                     error_log("Add product error: " . $e->getMessage()); // บันทึก error ลง log ด้วย
                }
            } // end if (empty($error) && !empty($image_url))
        } // end else (basic validation)
    } // end else (csrf check)
} // end if POST
?>

<main class="main-content">
<style>
    /* เพิ่มขอบสีดำให้กรอบด้านนอก (detail-card) ตามคำขอ */
    .detail-card {
        border: 1px solid #000;
    }
    /* ★ เพิ่ม: สไตล์สำหรับ variant row (ถ้ายังไม่มีใน style.css) */
    .variant-row {
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 12px;
    }
    .variant-row .form-control {
        flex: 1; /* Make inputs fill space */
    }
    .variant-row .btn {
       flex-shrink: 0; /* Prevent button from shrinking */
       background-color: #f8d7da; /* Softer red */
       border-color: #f5c6cb;
       color: var(--danger-color);
    }
     .variant-row .btn:hover {
         background-color: var(--danger-color);
         border-color: var(--danger-color);
         color: white;
     }
</style>
<div class="container">

<div class="page-header">
    <h1>เพิ่มสินค้าใหม่</h1>
     <div class="button-group">
        <a href="dashboard.php" class="btn btn--primary">📋 จัดการออเดอร์</a>
        <a href="manage_products.php" class="btn btn--primary">📦 จัดการสินค้า</a>
        <a href="manage_categories.php" class="btn btn--primary">🗂️ จัดการหมวดหมู่</a>
        <a href="sales_summary.php" class="btn btn--primary">📊 สรุปยอดขาย</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class='alert-danger mb-20'><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<!-- ★ เพิ่ม: แสดง Session message ถ้ามี -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert-success mb-20"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>


<div class="detail-card">
    <form action="add_product.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="form-group">
            <label for="name">ชื่อสินค้า:</label>
            <input type="text" id="name" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"> <!-- ★ เพิ่ม: แสดงค่าเดิมถ้ามี error -->
        </div>
        <div class="form-group">
            <label for="description">คำอธิบายสินค้า:</label>
            <textarea id="description" name="description" rows="6" class="form-control" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea> <!-- ★ เพิ่ม: แสดงค่าเดิม -->
        </div>
        <div class="profile-grid">
            <div class="form-group">
                <label for="price">ราคา (บาท):</label>
                 <!-- ★ แก้ไข: type="number" step="0.01" min="0.01" -->
                <input type="number" id="price" name="price" step="0.01" min="0.01" class="form-control" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"> <!-- ★ เพิ่ม: แสดงค่าเดิม -->
            </div>
            <div class="form-group">
                <label for="category_id">หมวดหมู่:</label>
                 <select id="category_id" name="category_id" class="form-control custom-select" required>
                    <option value="">-- เลือกหมวดหมู่ --</option>
                    <?php foreach ($categories as $category): ?>
                         <!-- ★ เพิ่ม: เช็ค selected ถ้ามี error -->
                        <option value="<?= $category['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="product_image">รูปภาพหลัก:</label>
            <input type="file" id="product_image" name="product_image" required class="form-control" accept="image/jpeg, image/png, image/gif, image/webp"> <!-- ★ เพิ่ม: accept attribute -->
        </div>
        <hr>
        <h3>ตัวเลือกสินค้า (สี/ไซต์/สต็อก) <span style="color:red;">*ต้องมีอย่างน้อย 1 รายการ</span></h3>
        <div id="variants-container">
            <!-- Variant rows will be added here by JavaScript -->
             <!-- ★ เพิ่ม: แสดง variants เดิมถ้ามี error (ซับซ้อนขึ้น อาจต้องวนลูป POST data) -->
             <?php
             if (isset($_POST['variants']) && is_array($_POST['variants'])) {
                 foreach ($_POST['variants'] as $index => $variant_data) {
                     $color_val = htmlspecialchars($variant_data['color'] ?? '');
                     $size_val = htmlspecialchars($variant_data['size'] ?? '');
                     $stock_val = htmlspecialchars($variant_data['stock'] ?? '');
                     echo <<<HTML
                     <div class="variant-row">
                         <input type="text" name="variants[{$index}][color]" class="form-control" placeholder="สี (เช่น ขาว)" required value="{$color_val}">
                         <input type="text" name="variants[{$index}][size]" class="form-control" placeholder="ไซต์ (เช่น S, M, L)" required value="{$size_val}">
                         <input type="number" name="variants[{$index}][stock]" class="form-control" placeholder="จำนวนสต็อก" min="0" required value="{$stock_val}">
                         <button type="button" class="btn btn--outline remove-variant-btn">ลบ</button>
                     </div>
                     HTML;
                 }
             }
             ?>
        </div>
        <button type="button" id="add-variant-btn" class="btn btn--outline" style="margin-top:10px;">+ เพิ่มตัวเลือก</button>
        <hr>
        <div class="form-group" style="margin-top:20px;">
            <button type="submit" class="btn btn--primary btn--full-width">บันทึกสินค้า</button>
        </div>
    </form>
</div>

</div> <!-- /.container -->
</main> <!-- /.main-content -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ★ แก้ไข: กำหนด index เริ่มต้นตามจำนวน row ที่แสดงผลจาก PHP (ถ้ามี)
    let variantIndex = document.querySelectorAll('#variants-container .variant-row').length;
    const container = document.getElementById('variants-container');
    const addBtn = document.getElementById('add-variant-btn');

    function addVariantRow() {
        const variantRow = document.createElement('div');
        variantRow.classList.add('variant-row');
        // ★ เพิ่ม: `value=""` สำหรับ input ใหม่
        variantRow.innerHTML = `
            <input type="text" name="variants[${variantIndex}][color]" class="form-control" placeholder="สี (เช่น ขาว)" required value="">
            <input type="text" name="variants[${variantIndex}][size]" class="form-control" placeholder="ไซต์ (เช่น S, M, L)" required value="">
            <input type="number" name="variants[${variantIndex}][stock]" class="form-control" placeholder="จำนวนสต็อก" min="0" required value="">
            <button type="button" class="btn btn--outline remove-variant-btn">ลบ</button>
        `;
        container.appendChild(variantRow);
        variantIndex++;
    }

    addBtn.addEventListener('click', addVariantRow);

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-variant-btn')) {
            // ★ เพิ่ม: ลบ row ที่คลิก
            e.target.closest('.variant-row').remove();
        }
    });

    // ★ แก้ไข: เพิ่มแถวแรกก็ต่อเมื่อยังไม่มีแถวที่แสดงจาก PHP
    if (variantIndex === 0) {
        addVariantRow();
    }
});
</script>

<?php
?>