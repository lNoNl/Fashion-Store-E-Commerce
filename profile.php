<?php
require 'header.php';

// 1. ตรวจสอบว่าล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success_message = '';

// 2. อัปเดตข้อมูลเมื่อมีการ POST
if (isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $province = trim($_POST['province']);
    $amphoe = trim($_POST['amphoe']);
    $tambon = trim($_POST['tambon']);
    $zipcode = trim($_POST['zipcode']);
    
    $update_sql = "UPDATE users SET first_name=?, phone_number=?, address=?, province=?, amphoe=?, tambon=?, zipcode=? WHERE id=?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("sssssssi", $first_name, $phone_number, $address, $province, $amphoe, $tambon, $zipcode, $user_id);
    
    if ($stmt_update->execute()) {
        $success_message = "บันทึกข้อมูลส่วนตัวสำเร็จแล้ว!";
    } else {
        $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_update->error;
    }
    $stmt_update->close();
}

// 3. ดึงข้อมูลผู้ใช้ล่าสุดมาแสดง (เพื่อให้ข้อมูลในหน้าเว็บเป็นข้อมูลล่าสุดเสมอ)
$sql = "SELECT username, first_name, address, phone_number, province, amphoe, tambon, zipcode FROM users WHERE id = ?";
$stmt_select = $conn->prepare($sql);
$stmt_select->bind_param("i", $user_id);
$stmt_select->execute();
$user = $stmt_select->get_result()->fetch_assoc();
$stmt_select->close();
?>

<!-- 
 * =================================================================
 * FIX: เพิ่ม Select2 CSS ที่ขาดไป 
 * (นี่คือสาเหตุที่ Dropdown ในภาพ image_c7f56b.png ไม่สวยงาม)
 * =================================================================
-->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* ขยายช่องกรอกข้อมูลให้ใหญ่ขึ้น */
    .form-control {
        height: 50px !important;
        padding: 0.75rem 1rem !important;
        font-size: 1.1rem !important;
    }

    /* --- Styles for Select2 Dropdown to vertically center text --- */
    .select2-container--classic .select2-selection--single {
        height: 50px !important;
        display: flex !important;          /* Use flexbox for alignment */
        align-items: center !important;    /* Vertically center content */
        padding: 0 !important;             /* Reset padding as it's handled by inner elements */
    }

    .select2-container--classic .select2-selection--single .select2-selection__rendered {
        line-height: normal !important; /* Reset line-height */
        padding-left: 1rem !important; 
        padding-right: 30px !important; 
    }

     .select2-container--classic .select2-selection--single .select2-selection__arrow {
        height: 100% !important; /* Make arrow full height */
        top: 0 !important;
        right: 5px !important;
        display: flex;
        align-items: center;
    }
</style>

<div class="page-header">
    <h1>ข้อมูลส่วนตัว</h1>
    <div class="button-group">
        <?php if (isset($_GET['from']) && $_GET['from'] === 'checkout'): ?>
            <a href="checkout.php" class="btn btn--primary">กลับไปหน้าชำระเงิน</a>
        <?php else: ?>
            <a href="index.php" class="btn btn--primary">กลับไปหน้าสินค้า</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert-success" style="margin-bottom: 20px;"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-danger" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php 
if (!empty($user['address']) && !empty($user['province'])): 
    $full_address = htmlspecialchars($user['address']) . "<br>";
    if (!empty($user['tambon'])) $full_address .= "ต." . htmlspecialchars($user['tambon']) . " ";
    if (!empty($user['amphoe'])) $full_address .= "อ." . htmlspecialchars($user['amphoe']) . "<br>";
    $full_address .= "จ." . htmlspecialchars($user['province']) . " " . htmlspecialchars($user['zipcode']);
?>
<div class="detail-card current-address-display">
    <h3>ที่อยู่ที่บันทึกไว้</h3>
    <address>
        <strong><?= htmlspecialchars($user['first_name'] ?? '') ?></strong><br>
        <?= $full_address ?><br>
        <?= htmlspecialchars($user['phone_number'] ?? '') ?>
    </address>
</div>
<?php endif; ?>

<div class="detail-card">
    <h3>แก้ไขข้อมูลส่วนตัวและที่อยู่</h3>
    <form action="profile.php<?php if (isset($_GET['from']) && $_GET['from'] === 'checkout') echo '?from=checkout'; ?>" method="post">
        <div class="profile-grid">
            <div class="form-group">
                <label>ชื่อ-นามสกุล</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>หมายเลขโทรศัพท์</label>
                <input type="tel" name="phone_number" class="form-control" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
            </div>
            <div class="form-group full-width">
                <label>บ้านเลขที่, อาคาร, ซอย, ถนน</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address'] ?? '') ?>" required placeholder="เช่น 123/45 หมู่ 6 ตึก A ซอยพัฒนา ถนนสุขุมวิท">
            </div>
            <div class="form-group">
                <label>จังหวัด</label>
                <select id="province" name="province" class="form-control" required></select>
            </div>
            <div class="form-group">
                <label>เขต/อำเภอ</label>
                <select id="amphoe" name="amphoe" class="form-control" required></select>
            </div>
            <div class="form-group">
                <label>แขวง/ตำบล</label>
                <select id="tambon" name="tambon" class="form-control" required></select>
            </div>
            <div class="form-group">
                <label>รหัสไปรษณีย์</label>
                <input type="text" id="zipcode" name="zipcode" class="form-control" value="<?= htmlspecialchars($user['zipcode'] ?? '') ?>" readonly required>
            </div>
        </div>
        
        <div class="form-group mt-20">
            <button type="submit" name="update_profile" class="btn btn--primary">บันทึกข้อมูล</button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let db;
    const provinceSelect = $('#province');
    const amphoeSelect = $('#amphoe');
    const tambonSelect = $('#tambon');
    const zipcodeText = document.getElementById('zipcode');

    provinceSelect.select2({ theme: "classic" });
    amphoeSelect.select2({ theme: "classic" });
    tambonSelect.select2({ theme: "classic" });

    const savedData = {
        province: '<?= htmlspecialchars($user['province'] ?? '') ?>',
        amphoe: '<?= htmlspecialchars($user['amphoe'] ?? '') ?>',
        tambon: '<?= htmlspecialchars($user['tambon'] ?? '') ?>'
    };

    function showAmphoes(shouldSetValue) {
        const selectedProvince = db.find(p => p.name_th === provinceSelect.val());
        amphoeSelect.html('<option value="">-- เลือกเขต/อำเภอ --</option>');
        tambonSelect.html('<option value="">-- เลือกแขวง/ตำบล --</option>').trigger('change');
        zipcodeText.value = '';
        if (selectedProvince) {
            selectedProvince.districts.forEach(item => {
                const option = new Option(item.name_th, item.name_th);
                amphoeSelect.append(option);
            });
            if (shouldSetValue && savedData.amphoe) {
                amphoeSelect.val(savedData.amphoe);
            }
        }
        amphoeSelect.trigger('change');
    }

    function showTambons(shouldSetValue) {
        const selectedProvince = db.find(p => p.name_th === provinceSelect.val());
        const selectedAmphoe = selectedProvince?.districts.find(a => a.name_th === amphoeSelect.val());
        tambonSelect.html('<option value="">-- เลือกแขวง/ตำบล --</option>');
        zipcodeText.value = '';
        if (selectedAmphoe) {
            selectedAmphoe.sub_districts.forEach(item => {
                const option = new Option(item.name_th, item.name_th);
                tambonSelect.append(option);
            });
            if (shouldSetValue && savedData.tambon) {
                tambonSelect.val(savedData.tambon);
            }
        }
        tambonSelect.trigger('change');
    }

    function showZipcode() {
        const selectedProvince = db.find(p => p.name_th === provinceSelect.val());
        const selectedAmphoe = selectedProvince?.districts.find(a => a.name_th === amphoeSelect.val());
        const selectedTambon = selectedAmphoe?.sub_districts.find(t => t.name_th === tambonSelect.val());
        zipcodeText.value = selectedTambon ? selectedTambon.zip_code : '';
    }

    provinceSelect.on('change', () => showAmphoes(false));
    amphoeSelect.on('change', () => showTambons(false));
    tambonSelect.on('change', showZipcode);

    fetch('data/province_with_district_and_sub_district.json')
        .then(response => response.json())
        .then(data => {
            db = data;
            provinceSelect.html('<option value="">-- เลือกจังหวัด --</option>');
            db.forEach(item => {
                const option = new Option(item.name_th, item.name_th);
                provinceSelect.append(option);
            });
            if (savedData.province) {
                provinceSelect.val(savedData.province);
                showAmphoes(true); 
            }
        });
});
</script>