<?php
require 'header.php';
$error = '';

// ตรวจสอบว่ามี username อยู่ในระบบหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];

    // 1. ค้นหาผู้ใช้จาก username
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($user = $result->fetch_assoc()) {
        $user_id = $user['id'];

        // 2. สร้าง Token ที่ไม่ซ้ำกันและกำหนดวันหมดอายุ (เช่น 1 ชั่วโมง)
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // 3600 วินาที = 1 ชั่วโมง

        // 3. อัปเดต Token และวันหมดอายุลงในฐานข้อมูล
        $stmt_update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $token, $expires_at, $user_id);
        
        if ($stmt_update->execute()) {
            header("Location: reset_password.php?token=" . $token);
            exit();
        } else {
            $error = "เกิดข้อผิดพลาดในการสร้างลิงก์สำหรับรีเซ็ตรหัสผ่าน";
        }

    } else {
        $error = "ไม่พบชื่อผู้ใช้นี้ในระบบ";
    }
}
?>
<div class="auth-container">
    <div class="auth-card">
        <h1>ลืมรหัสผ่าน</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert-danger" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p style="margin-bottom: 25px; color: var(--text-muted-color);">กรอกชื่อผู้ใช้ (Username) เพื่อรับลิงก์สำหรับตั้งรหัสผ่านใหม่</p>

        <form action="forgot_password.php" method="post">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้:</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn--primary">ขอลิงก์ตั้งรหัสผ่านใหม่</button>
            </div>
        </form>

        <p class="auth-footer">
            จำรหัสผ่านได้แล้ว? <a href="login.php">กลับไปหน้าล็อกอิน</a>
        </p>
    </div>
</div>