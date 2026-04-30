<?php
// ★★★ เพิ่ม: เปิดการแสดงผล Error สำหรับ Debug ★★★
// ini_set('display_errors', 1); // Consider removing/commenting out for production
// ini_set('display_startup_errors', 1); // Consider removing/commenting out for production
// error_reporting(E_ALL); // Consider adjusting for production
// ★★★ สิ้นสุดส่วน Debug ★★★

require 'header.php'; // Ensure header starts session and includes config.php

// ★ Use filter_input for security and validation
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirect if product_id is invalid
if ($product_id === false || $product_id === null || $product_id <= 0) {
    header("Location: index.php");
    exit();
}


// 1. ดึงข้อมูลสินค้าหลัก
$stmt_product = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'visible'");
// ★ Check prepare statement success
if ($stmt_product === false) {
     error_log("Prepare failed for product select: (" . $conn->errno . ") " . $conn->error); // Log error
     die("เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า กรุณาลองใหม่ภายหลัง"); // Show user-friendly error
}

$stmt_product->bind_param("i", $product_id);
// ★ Check execute success
if (!$stmt_product->execute()) {
     error_log("Execute failed for product select: (" . $stmt_product->errno . ") " . $stmt_product->error);
     $stmt_product->close(); // Close statement before dying
     die("เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า กรุณาลองใหม่ภายหลัง");
}

$product_result = $stmt_product->get_result();
// Check if product was found
if ($product_result->num_rows === 0) {
    // Show message within the layout
    echo "<main class='main-content container'><h1>ไม่พบสินค้า หรือสินค้านี้ไม่พร้อมจำหน่าย</h1><p><a href='index.php' class='btn btn--outline'>&larr; กลับหน้าหลัก</a></p></main>";
    require 'footer.php'; // Include footer for proper page structure
    $stmt_product->close();
    exit();
}
$product = $product_result->fetch_assoc();
$stmt_product->close(); // Close statement after fetching

// 2. ค้นหาสินค้าก่อนหน้าและถัดไป (Optional, keep error logging but don't die)
$prev_product_id = null;
$stmt_prev = $conn->prepare("SELECT id FROM products WHERE id < ? AND status = 'visible' ORDER BY id DESC LIMIT 1");
if ($stmt_prev) {
    $stmt_prev->bind_param("i", $product_id);
    if ($stmt_prev->execute()){ // Check execute success
        $prev_result = $stmt_prev->get_result();
        if ($prev_row = $prev_result->fetch_assoc()) {
            $prev_product_id = $prev_row['id'];
        }
    } else {
        error_log("Execute failed for prev product: (" . $stmt_prev->errno . ") " . $stmt_prev->error);
    }
    $stmt_prev->close();
} else {
     error_log("Prepare failed for prev product: (" . $conn->errno . ") " . $conn->error);
}


$next_product_id = null;
$stmt_next = $conn->prepare("SELECT id FROM products WHERE id > ? AND status = 'visible' ORDER BY id ASC LIMIT 1");
if($stmt_next) {
    $stmt_next->bind_param("i", $product_id);
     if ($stmt_next->execute()) { // Check execute success
        $next_result = $stmt_next->get_result();
        if ($next_row = $next_result->fetch_assoc()) {
            $next_product_id = $next_row['id'];
        }
     } else {
         error_log("Execute failed for next product: (" . $stmt_next->errno . ") " . $stmt_next->error);
     }
    $stmt_next->close();
} else {
     error_log("Prepare failed for next product: (" . $conn->errno . ") " . $conn->error);
}


// 3. ดึงข้อมูลตัวเลือกสินค้า (variants)
$variants = []; // Initialize as empty array
$stmt_variants = $conn->prepare("SELECT id, color, size, stock_quantity FROM product_variants WHERE product_id = ?");
// ★ Check prepare statement success
if ($stmt_variants === false) {
     error_log("Prepare failed for variants select: (" . $conn->errno . ") " . $conn->error);
     // Don't die here, let the page load but show a message later if $variants is empty
} else {
    $stmt_variants->bind_param("i", $product_id);
    // ★ Check execute success
    if (!$stmt_variants->execute()) {
         error_log("Execute failed for variants select: (" . $stmt_variants->errno . ") " . $stmt_variants->error);
         // Don't die, let the page load but show a message later if $variants is empty
    } else {
        $variants_result = $stmt_variants->get_result();
        // ★ Check get_result success
        if ($variants_result) {
            while ($row = $variants_result->fetch_assoc()) {
                // Ensure stock_quantity is an integer for json_encode
                $row['stock_quantity'] = intval($row['stock_quantity']);
                $variants[] = $row;
            }
        } else {
             error_log("Failed to get result for variants query: (" . $conn->errno . ") " . $conn->error);
        }
    }
    $stmt_variants->close(); // Close statement after fetching or on error
}
?>

<style>
    /* Styles remain the same */
    .add-to-cart-container .btn { display: flex; justify-content: center; align-items: center; }
    .product-detail-container { display: grid; grid-template-columns: 1fr 2fr; gap: 40px; align-items: start; margin-top: 20px;}
    .product-image-section img { width: 100%; height: auto; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }
    .product-price-detail { font-size: 1.8em; font-weight: 600; color: var(--primary-color); margin-bottom: 15px; }
    .product-description { color: var(--text-muted-color); line-height: 1.8; margin-bottom: 20px;}
    hr { border: 0; border-top: 1px solid #eee; margin: 25px 0; }
    .form-group label { margin-bottom: 8px; font-weight: 500;}

    @media (max-width: 900px) { .product-detail-container { grid-template-columns: 1fr; } }

    .stock-status { font-size: 0.9em; margin-top: 10px; font-weight: 500; min-height: 1.2em; /* Ensure space even when empty */ }
    .stock-status.in-stock { color: var(--success-color); }
    .stock-status.out-of-stock { color: var(--danger-color); }
    .add-to-cart-container .btn.added-to-cart { background-color: var(--success-color); border-color: var(--success-color); color: white; cursor: default; }

    /* Style for quantity input next to button */
    .add-to-cart-container { display: flex; gap: 10px; align-items: center;}
    .add-to-cart-container .quantity-input { width: 80px; text-align: center;}
    .add-to-cart-container .btn { flex-grow: 1; } /* Allow button to take remaining space */

</style>

<div class="page-header">
    <a href="index.php" class="btn btn--outline">&larr; กลับไปหน้าสินค้าทั้งหมด</a>
    <div class="header-nav-buttons">
        <?php if ($prev_product_id): ?>
             <a href="product_detail.php?id=<?= $prev_product_id ?>" class="btn btn--outline">สินค้าก่อนหน้า</a>
        <?php endif; ?>
        <?php if ($next_product_id): ?>
             <a href="product_detail.php?id=<?= $next_product_id ?>" class="btn btn--outline">สินค้าต่อไป →</a>
        <?php endif; ?>
    </div>
</div>

<div class="product-detail-container">
    <div class="product-image-section">
        <img src="<?= htmlspecialchars($product['image_url']) ?>"
             alt="<?= htmlspecialchars($product['name']) ?>"
             onerror="this.onerror=null; this.src='https://placehold.co/600x600/EEE/CCC?text=Image+Not+Found'; this.style.border='none';" >
    </div>
    <div class="product-details-section">
        <h1><?= htmlspecialchars($product['name']) ?></h1>
        <div class="product-price-detail">฿<?= number_format($product['price'], 2) ?></div>
        <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <hr>

        <?php if (!empty($variants)): ?>
            <form class="add-to-cart-form product-options-form" method="post" action="cart_handler.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="variant_id" id="selected_variant_id" value="">
                <!-- ★★★ เพิ่ม: Hidden input for product_id needed for redirect ★★★ -->
                <input type="hidden" name="product_id" value="<?= $product_id ?>">

                <div class="form-group">
                    <label for="color_select">เลือกสี:</label>
                    <select id="color_select" name="color" class="form-control custom-select" required>
                        <option value="">-- กรุณาเลือกสี --</option>
                        <!-- Options populated by JavaScript -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="size_select">เลือกไซต์:</label>
                    <select id="size_select" name="size" class="form-control custom-select" disabled required>
                        <option value="">-- กรุณาเลือกไซต์ --</option>
                         <!-- Options populated by JavaScript -->
                    </select>
                </div>
                <p id="stock_status_text" class="stock-status"></p> <!-- Element for stock status -->
                <div class="add-to-cart-container">
                    <input type="number" id="quantity_input" name="quantity" value="1" min="1" class="form-control quantity-input" disabled required>
                    <button type="submit" id="add_to_cart_button" class="btn btn--primary" disabled>เพิ่มลงตะกร้า</button>
                </div>
            </form>
        <?php else: ?>
            <p class="stock-status out-of-stock">ขออภัย สินค้ารายการนี้ยังไม่มีตัวเลือกพร้อมจำหน่ายในขณะนี้</p>
        <?php endif; ?>
    </div>
</div>
<script>
// JavaScript remains the same as previous version, including console logs
document.addEventListener("DOMContentLoaded", function() {
    // ★ Pass variants from PHP using JSON_NUMERIC_CHECK to ensure numbers are numbers
    const variants = <?= json_encode($variants, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?>;
    console.log("Variants data:", variants);

    // Get references to elements
    const colorSelect = document.getElementById('color_select');
    const sizeSelect = document.getElementById('size_select');
    const stockStatusText = document.getElementById('stock_status_text');
    const selectedVariantIdInput = document.getElementById('selected_variant_id');
    const addToCartButton = document.getElementById('add_to_cart_button');
    const quantityInput = document.getElementById('quantity_input');

    // ★ Check if all required elements exist before proceeding
    if (!colorSelect || !sizeSelect || !stockStatusText || !selectedVariantIdInput || !addToCartButton || !quantityInput) {
        console.error("One or more required elements not found for product options.");
        // Optionally hide the form or show an error message to the user
        const formElement = document.querySelector('.add-to-cart-form');
        if(formElement) formElement.style.display = 'none';
        if(stockStatusText) stockStatusText.textContent = 'เกิดข้อผิดพลาดในการโหลดตัวเลือกสินค้า';
        return; // Stop execution if elements are missing
    }

    // ★ Handle case where there are no variants for the product
    if (!variants || variants.length === 0) {
         console.warn("No variants found for this product.");
         // Hide the form if it exists
         const formElement = document.querySelector('.add-to-cart-form');
         if(formElement) formElement.style.display = 'none';
         // Display message using the existing p tag
         if(stockStatusText) {
             stockStatusText.textContent = 'สินค้านี้ยังไม่มีตัวเลือกพร้อมจำหน่าย';
             stockStatusText.className = 'stock-status out-of-stock';
         }
         return; // Stop execution
    }

    // --- Populate Color Options ---
    const uniqueColors = [...new Set(variants.map(v => v.color))];
    uniqueColors.forEach(color => {
        const option = document.createElement('option');
        option.value = color;
        option.textContent = color;
        colorSelect.appendChild(option);
    });

    // --- Event Listener for Color Selection ---
    colorSelect.addEventListener('change', function() {
        const selectedColor = this.value;
        console.log("Color selected:", selectedColor);

        // Reset size dropdown and subsequent elements
        sizeSelect.innerHTML = '<option value="">-- กรุณาเลือกไซต์ --</option>'; // Clear previous size options
        sizeSelect.disabled = true; // Disable size select initially
        updateSelectionStatus(); // Reset status/button/quantity

        if (selectedColor) {
            // Filter available sizes for the selected color
            const availableSizes = [...new Set(
                variants
                    .filter(v => v.color === selectedColor)
                    .map(v => v.size)
            )];
            console.log("Available sizes for", selectedColor, ":", availableSizes);

            // Populate size options
            availableSizes.forEach(size => {
                const option = document.createElement('option');
                option.value = size;
                option.textContent = size;
                sizeSelect.appendChild(option);
            });

            // Enable size dropdown only if there are available sizes
            sizeSelect.disabled = availableSizes.length === 0;

            // Show message if no sizes available for the selected color
             if(availableSizes.length === 0){
                 stockStatusText.textContent = 'ไม่มีไซต์สำหรับสีนี้';
                 stockStatusText.className = 'stock-status out-of-stock';
             }
        }
    });

    // --- Event Listener for Size Selection ---
    sizeSelect.addEventListener('change', updateSelectionStatus);

    // --- Function to Update Status based on Selection ---
    function updateSelectionStatus() {
        const selectedColor = colorSelect.value;
        const selectedSize = sizeSelect.value;
        console.log("Size selected:", selectedSize, " (Color:", selectedColor, ")");

        // Reset elements related to variant selection
        quantityInput.value = 1; // Reset quantity to 1
        stockStatusText.textContent = ''; // Clear previous stock status
        stockStatusText.className = 'stock-status'; // Reset class
        addToCartButton.disabled = true; // Disable button initially
        addToCartButton.textContent = 'เพิ่มลงตะกร้า'; // Reset button text
        addToCartButton.classList.remove('added-to-cart'); // Remove success class
        quantityInput.disabled = true; // Disable quantity input initially
        selectedVariantIdInput.value = ''; // Clear selected variant ID

        // Proceed only if both color and size are selected
        if (selectedColor && selectedSize) {
            // Find the matching variant
            const variant = variants.find(v => v.color === selectedColor && v.size === selectedSize);
            console.log("Found Variant:", variant);

            if (variant) {
                selectedVariantIdInput.value = variant.id; // Set the hidden input value
                console.log("Selected Variant ID:", selectedVariantIdInput.value);

                const stock = variant.stock_quantity; // Already an integer from PHP

                // Check stock quantity
                if (stock > 0) {
                    stockStatusText.textContent = `มีสินค้า (เหลือ ${stock} ชิ้น)`;
                    stockStatusText.className = 'stock-status in-stock'; // Add in-stock class
                    addToCartButton.disabled = false; // Enable add to cart button
                    addToCartButton.textContent = 'เพิ่มลงตะกร้า';
                    quantityInput.disabled = false; // Enable quantity input
                    quantityInput.max = stock; // Set max quantity based on stock
                    // ★ Ensure current quantity input value doesn't exceed new max
                     if (parseInt(quantityInput.value) > stock) {
                         quantityInput.value = stock;
                     }

                } else {
                    stockStatusText.textContent = 'สินค้าหมดสต็อก';
                    stockStatusText.className = 'stock-status out-of-stock'; // Add out-of-stock class
                    // Keep button and quantity input disabled
                }
            } else {
                // This case should ideally not happen if dropdowns are populated correctly based on `variants` array
                console.error("Variant not found for selected color/size combination. This indicates an issue in filtering/finding logic.");
                stockStatusText.textContent = 'ตัวเลือกนี้ไม่มีจำหน่าย';
                stockStatusText.className = 'stock-status out-of-stock';
                 // Keep button and quantity input disabled
            }
        } else {
             console.log("Color or size not fully selected."); // Log when only one or none is selected
        }
    }

    // --- Event Listeners for Quantity Input Validation ---
    quantityInput.addEventListener('change', function() {
        const maxQuantity = parseInt(this.max); // Max stock for selected variant
        let currentValue = parseInt(this.value);
        console.log(`Quantity changed: Value=${this.value}, Max=${maxQuantity}`);

        if (isNaN(currentValue) || currentValue < 1) {
            console.log("Quantity invalid or < 1, setting to 1");
            this.value = 1; // Set to minimum if invalid or less than 1
        } else if (!isNaN(maxQuantity) && currentValue > maxQuantity) {
            // Check maxQuantity is a valid number before comparing
            console.log(`Quantity ${currentValue} exceeds max ${maxQuantity}, setting to max`);
            this.value = maxQuantity; // Set to maximum if exceeding stock
            alert(`ขออภัย สินค้ามีเหลือเพียง ${maxQuantity} ชิ้น`); // Optional: notify user
        }
    });

     quantityInput.addEventListener('input', function() { // Handle direct input/paste/spinner clicks
        const maxQuantity = parseInt(this.max);
        const rawValue = this.value;

         // Allow empty input while typing, but reset to 1 if focus is lost (handled by 'change')
         if (rawValue === '') return;

         const currentValue = parseInt(rawValue);

         // Prevent typing values higher than max
         if (!isNaN(maxQuantity) && currentValue > maxQuantity) {
             console.log(`Quantity input ${currentValue} exceeds max ${maxQuantity}, adjusting`);
             this.value = maxQuantity;
         }
         // Prevent typing values less than 1 (allow temporary empty string)
         else if (currentValue < 1 && rawValue !== '') {
             console.log("Quantity input < 1, adjusting to 1");
              // Check if user is trying to type (like typing '0' before '5')
              // Simple check: if length is 1 and it's '0', maybe wait.
              // More robust: use setTimeout, but for now just force to 1.
              this.value = 1;
         }
     });

});
</script>