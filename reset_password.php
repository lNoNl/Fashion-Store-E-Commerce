<?php
require 'header.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$show_form = false;

if (empty($token)) {
    $error = "ลิงก์ไม่ถูกต้องหรือไม่สมบูรณ์";
} else {
    // 1. ตรวจสอบว่า Token มีอยู่ในระบบและยังไม่หมดอายุหรือไม่
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt_check->bind_param("s", $token);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $stmt_check->close();

    if ($user = $result->fetch_assoc()) {
        $show_form = true; // ถ้า Token ถูกต้อง ให้แสดงฟอร์ม
        $user_id = $user['id'];

        // 2. ตรวจสอบเมื่อมีการส่งฟอร์มรหัสผ่านใหม่
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // ★ แก้ไข: รับค่าจาก name="new_password" ให้ถูกต้อง
            $password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if ($password !== $confirm_password) {
                $error = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
            } elseif (strlen($password) < 6) { // แนะนำให้ใช้ 8 ตัวอักษรขึ้นไป
                $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
            } else {
                // 3. อัปเดตรหัสผ่านใหม่และล้างค่า Token
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
                $stmt_update->bind_param("si", $hashed_password, $user_id);

                if ($stmt_update->execute()) {
                    $message = "เปลี่ยนรหัสผ่านสำเร็จแล้ว! คุณสามารถ <a href='login.php'>เข้าสู่ระบบ</a> ได้ทันที";
                    $show_form = false; // ซ่อนฟอร์มหลังจากสำเร็จ
                } else {
                    $error = "เกิดข้อผิดพลาดในการอัปเดตรหัสผ่าน";
                }
                $stmt_update->close();
            }
        }
    } else {
        $error = "ลิงก์สำหรับตั้งรหัสผ่านใหม่ไม่ถูกต้องหรือหมดอายุแล้ว";
    }
}
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>ตั้งรหัสผ่านใหม่</h1>

        <?php if (!empty($message)): ?>
            <div class="alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <p style="margin-bottom: 25px; color: var(--text-muted-color);">กรอกรหัสผ่านใหม่ที่คุณต้องการใช้งาน</p>

            <form action="reset_password.php?token=<?= htmlspecialchars($token) ?>" method="post">
                
                <div class="form-group">
                    <label for="new_password">รหัสผ่านใหม่:</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">ยืนยันรหัสผ่านใหม่:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn--primary">บันทึกรหัสผ่านใหม่</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>