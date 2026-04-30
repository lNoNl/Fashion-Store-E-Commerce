<?php
require_once 'config.php';
session_start();

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// --- NEW: ตรวจสอบ CSRF Token ---
if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    $_SESSION['error_message'] = 'Token ไม่ถูกต้อง, การดำเนินการล้มเหลว';
    header("Location: dashboard.php"); // กลับไปหน้าหลัก
    exit();
}

// 2. รับคำสั่ง (action) และหน้าที่ต้องการให้กลับไป (redirect) จาก URL
$action = $_GET['action'] ?? '';
$redirect_url = $_GET['redirect'] ?? 'dashboard.php'; // หน้าเริ่มต้นถ้าไม่ได้ระบุ

$from_status = '';
$to_status = '';
$message = '';

// 3. กำหนดค่าตัวแปรตาม action ที่ได้รับ
if ($action === 'accept') {
    $from_status = 'รอดำเนินการ';
    $to_status = 'กำลังจัดส่ง';
    $message = 'รับออเดอร์ที่ "รอดำเนินการ" ทั้งหมดเรียบร้อยแล้ว';

} elseif ($action === 'ship') {
    $from_status = 'กำลังจัดส่ง';
    $to_status = 'ส่งแล้ว';
    $message = 'อัปเดตสถานะออเดอร์ที่ "กำลังจัดส่ง" ทั้งหมดเป็น "ส่งแล้ว" เรียบร้อยแล้ว';

} else {
    // ถ้า action ไม่ถูกต้อง ให้กลับไปหน้า dashboard
    $_SESSION['error_message'] = 'การกระทำไม่ถูกต้อง';
    header("Location: " . $redirect_url);
    exit();
}

// 4. เตรียมและรัน SQL Query เพื่ออัปเดตสถานะทั้งหมด
$sql = "UPDATE orders SET order_status = ? WHERE order_status = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $to_status, $from_status);

if ($stmt->execute()) {
    // 5. หากสำเร็จ ให้ตั้งค่าข้อความแจ้งเตือน
    $_SESSION['message'] = $message;
} else {
    // หากไม่สำเร็จ ตั้งค่าข้อความ error
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะ";
}

$stmt->close();
$conn->close();

// 6. กลับไปยังหน้าที่ระบุไว้ใน redirect_url (เช่น dashboard.php)
header("Location: " . $redirect_url);
exit();
?>
