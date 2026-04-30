<!-- (สำคัญ!) ปิดแท็ก main ที่เปิดจาก header.php -->
</main>

<!-- ======== FOOTER ดีไซน์ใหม่ ======== -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">

            <!-- Columns remain the same -->
             <div class="footer-column"><h3>เกี่ยวกับ La_maison</h3><p>เรามุ่งมั่นที่จะนำเสนอสินค้าแฟชั่นและไลฟ์สไตล์ที่คัดสรรมาอย่างดี พร้อมบริการที่ประทับใจ</p></div>
             <div class="footer-column"><h3>ลิงก์ด่วน</h3><ul class="footer-links"><li><a href="index.php">หน้าแรก</a></li><li><a href="index.php">สินค้าทั้งหมด</a></li><li><a href="cart.php">ตะกร้าสินค้า</a></li><li><a href="order_history.php">บัญชีของฉัน</a></li></ul></div>
             <div class="footer-column"><h3>ติดต่อเรา</h3><ul class="footer-contact"><li><i class="fas fa-phone"></i> 02-123-4567</li><li><i class="fas fa-envelope"></i> info@la_maison.com</li></ul></div>
             <div class="footer-column"><h3>ติดตามเรา</h3><div class="social-links"><a href="https://www.facebook.com/Lamaisonscafe" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a><a href="https://www.instagram.com/la_maisons/" aria-label="Instagram"><i class="fab fa-instagram"></i></a><a href="https://shopee.co.th/la_maisons" aria-label="Shopee"><i class="fas fa-bag-shopping"></i></a></div></div>

        </div>
        <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> La_maison. All Rights Reserved.</p></div>
    </div>
</footer>
<!-- ======== จบ FOOTER ดีไซน์ใหม่ ======== -->

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // ★ ห่อหุ้มโค้ดทั้งหมดใน $(document).ready เพื่อให้แน่ใจว่า DOM พร้อมใช้งาน
    $(document).ready(function() {
        console.log("Footer script loaded and document ready."); // Log: เช็คว่า script โหลด

        // --- AJAX สำหรับปุ่ม "เพิ่มลงตะกร้า" ---
        // ★ ใช้ event delegation ที่แม่นยำขึ้น
        //    เปลี่ยนจาก document เป็น element ที่ครอบ form แต่ไม่เปลี่ยนบ่อย เช่น #main-content หรือ body
        $('body').on('submit', '.add-to-cart-form', function(event) {
            console.log("Add to cart form submitted."); // Log: เช็คว่า event ทำงาน

            // ★★★ Prevent Default ทันทีเป็นอันดับแรก ★★★
            event.preventDefault();
            console.log("preventDefault() called."); // Log: เช็คว่า preventDefault ถูกเรียก

            var form = $(this);
            var button = form.find('button[type="submit"]');
            // ★ เพิ่ม: เช็คว่าหาปุ่มเจอหรือไม่
            if (!button.length) {
                console.error("Submit button not found in form:", form);
                return; // หยุดทำงานถ้าหาปุ่มไม่เจอ
            }
            var originalButtonText = button.html();
            var formData = form.serialize();
            console.log("Form Data:", formData); // Log: เช็คข้อมูลที่ส่ง

            // ★★★ Extract product_id from formData for redirection ★★★
            var productId = null;
            var params = new URLSearchParams(formData); // Use URLSearchParams to easily get values
            if (params.has('product_id')) {
                productId = params.get('product_id');
                console.log("Product ID for redirect:", productId);
            } else {
                 console.warn("product_id not found in form data for redirect.");
                 // Decide fallback behavior: maybe don't redirect, or redirect to index.php?
            }


            // ★ เพิ่ม: แสดงสถานะกำลังโหลด
            button.html('กำลังเพิ่ม...').prop('disabled', true);


            $.ajax({
                type: 'POST',
                url: 'cart_handler.php', // ตรวจสอบว่า path ถูกต้อง
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log("AJAX Success Response:", response); // Log: เช็ค response ที่ได้รับ
                    if (response && response.success) {
                        const cartCountElement = $('#cart-count');
                        if(cartCountElement.length) {
                             cartCountElement.text(response.cart_item_count);
                        } else {
                             console.warn("#cart-count element not found in header.");
                        }

                        // ★★★ Change: Show success message briefly, then redirect ★★★
                        button.html('✔︎ เพิ่มแล้ว!').addClass('added-to-cart');

                        // Wait a short moment to show "Added!", then redirect
                        setTimeout(function(){
                            // Redirect only if we found a productId
                            if (productId) {
                                console.log("Redirecting to product_detail.php?id=" + productId);
                                window.location.href = 'product_detail.php?id=' + productId + '&added=1'; // Add param for potential success message on load
                            } else {
                                // Fallback if no product ID: maybe just reset the button
                                console.log("No product ID, resetting button instead of redirecting.");
                                if(button.closest('body').length) {
                                     // ★ แก้ไข: ต้องเช็คด้วยว่าปุ่มไม่ได้ถูก disable จากการเลือก variant หรือไม่
                                     //    ถ้าปุ่มถูก disable อยู่แล้ว ไม่ควร enable กลับมา
                                     const isDisabledByVariant = button.prop('disabled') && !button.hasClass('added-to-cart');
                                     if (!isDisabledByVariant) {
                                          button.html(originalButtonText).removeClass('added-to-cart').prop('disabled', false);
                                     } else {
                                          button.html(originalButtonText).removeClass('added-to-cart'); // เอาแค่ class ออก แต่ยัง disable ไว้
                                     }
                                }
                            }
                        }, 1000); // Redirect after 1 second (adjust as needed)

                    } else {
                         const message = (response && response.message) ? response.message : 'ไม่สามารถเพิ่มสินค้าได้';
                        alert('เกิดข้อผิดพลาด: ' + message);
                        // ★ แก้ไข: ถ้า error ให้คืนค่าปุ่ม และ enable (ถ้าไม่ได้ถูก disable จาก variant)
                        if(button.closest('body').length) {
                             const isDisabledByVariant = button.prop('disabled') && !button.hasClass('added-to-cart'); // เช็คเหมือนเดิม
                             if (!isDisabledByVariant) {
                                  button.html(originalButtonText).prop('disabled', false);
                             } else {
                                  button.html(originalButtonText); // คืนข้อความ แต่ยัง disable
                             }
                        }
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                    alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ หรือเกิดข้อผิดพลาดในการประมวลผล โปรดลองอีกครั้ง');
                    // ★ แก้ไข: ถ้า error ให้คืนค่าปุ่ม และ enable (ถ้าไม่ได้ถูก disable จาก variant)
                    if(button.closest('body').length) {
                        const isDisabledByVariant = button.prop('disabled') && !button.hasClass('added-to-cart'); // เช็คเหมือนเดิม
                         if (!isDisabledByVariant) {
                              button.html(originalButtonText).prop('disabled', false);
                         } else {
                              button.html(originalButtonText); // คืนข้อความ แต่ยัง disable
                         }
                    }
                }
            });
        }); // <-- ปิด .on('submit') ที่นี่

        // --- Logic สำหรับ Slip Modal ---
        const modal = document.getElementById("slip-modal");
        if (modal) {
            const modalImg = document.getElementById("slip-modal-image");
            const viewSlipButtons = document.querySelectorAll(".view-slip-btn");
            const closeBtn = modal.querySelector(".slip-viewer-close");

            const closeModal = () => {
                if(modal) modal.style.display = "none";
            };

            viewSlipButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const slipUrl = this.dataset.slipUrl;
                     if(modal && modalImg && slipUrl) {
                        modalImg.src = slipUrl;
                        modal.style.display = "block";
                    } else {
                         console.error("Modal, modal image or slip URL not found for slip button.");
                    }
                });
            });

            if (closeBtn) {
                closeBtn.onclick = closeModal;
            } else {
                 console.warn("Slip modal close button not found.");
            }

            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

             document.addEventListener('keydown', function(event) {
                if (event.key === "Escape" && modal.style.display === "block") {
                     closeModal();
                 }
             });

        } else {
             // console.log("Slip modal element not found on this page.");
        }
        // --- End Slip Modal Logic ---

    }); // <-- ปิด $(document).ready ทั้งหมด
</script>
</body>
</html>