<?php
require 'header.php';

// 1. ถ้าผู้ใช้ล็อกอินอยู่แล้ว ให้ส่งไปหน้า profile ทันที
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

// ... โค้ด PHP ส่วนที่เหลือเหมือนเดิมทั้งหมด ...
$username = '';
$error = '';
$success_message = '';
if (isset($_GET['status']) && $_GET['status'] == 'loggedout') {
    $success_message = "คุณออกจากระบบสำเร็จแล้ว";
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role']; 
            if (isset($_SESSION['redirect_url'])) {
                $redirect_url = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header("Location: " . $redirect_url);
            } else {
                if ($user['role'] == 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: index.php");
                }
            }
            exit();
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!";
        }
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!";
    }
    $stmt->close();
}
?>


<main class="main-content">
<style>
    .auth-card {
        border: 1px solid #000;
    }
</style>
    <div class="auth-container">
        <div class="auth-card">
            <h1>เข้าสู่ระบบ</h1>

            <?php if (!empty($success_message)): ?>
                <div class="alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="login.php" method="post">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้:</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">รหัสผ่าน:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <a href="forgot_password.php" class="auth-link">ลืมรหัสผ่าน?</a>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn--primary">เข้าสู่ระบบ</button>
                </div>
            </form>
            <p class="auth-footer">
                ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกที่นี่</a>
            </p>
        </div>
    </div>
</main>
<?php
?>