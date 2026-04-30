<?php
require 'header.php';

$username = '';
// $email = ''; // ลบตัวแปร email ออก
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    // $email = trim($_POST['email']); // ลบการรับค่า email
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Validation Checks ---
    if (empty($username) || empty($password)) { // ลบ email ออกจากการตรวจสอบ
        $error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    // } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // ลบการตรวจสอบรูปแบบ email
    //     $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password) < 6) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    } else {
        // --- Check if user already exists ---
        // แก้ไข SQL ไม่ให้ตรวจสอบ email ที่ซ้ำกัน
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว!";
        } else {
            // --- Insert new user ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // แก้ไข SQL ไม่ให้เพิ่มข้อมูล email
            $stmt_insert = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $username, $hashed_password);

            if ($stmt_insert->execute()) {
                $success = "สมัครสมาชิกสำเร็จ! กรุณา <a href='login.php'>เข้าสู่ระบบ</a>";
                // Clear form fields on success
                $username = '';
                // $email = ''; // ลบตัวแปร email ออก
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
?>
<style>
    .auth-card {
        border: 1px solid #000;
    }
</style>
<div class="auth-container">
    <div class="auth-card">
        <h1>สมัครสมาชิก</h1>

        <?php if (!empty($success)): ?>
            <div class="alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
            <form action="register.php" method="post">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้:</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
                </div>

                <!-- ลบช่องกรอกอีเมลออกจากฟอร์ม -->
                <!-- 
                <div class="form-group">
                    <label for="email">อีเมล:</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                -->

                <div class="form-group">
                    <label for="password">รหัสผ่าน (อย่างน้อย 6 ตัวอักษร):</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">ยืนยันรหัสผ่าน:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn--primary">สมัครสมาชิก</button>
                </div>
            </form>
        <?php endif; ?>

        <p class="auth-footer">
            มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a>
        </p>
    </div>
</div>