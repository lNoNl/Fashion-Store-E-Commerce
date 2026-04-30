<?php
session_start();
require_once 'config.php';

// ตรวจสอบสิทธิ์ Admin และ Parameter ที่จำเป็น
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin' || !isset($_GET['order_id']) || !isset($_GET['status'])) {
    header("Location: dashboard.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$new_status = $_GET['status'];

// ป้องกันการตั้งค่าสถานะที่ไม่ได้รับอนุญาต
$allowed_statuses = ['กำลังจัดส่ง', 'ส่งแล้ว', 'ยกเลิก'];
if (!in_array($new_status, $allowed_statuses)) {
    $_SESSION['message'] = "สถานะที่ระบุไม่ถูกต้อง";
    header("Location: all_orders.php");
    exit();
}

// อัปเดตสถานะด้วย Prepared Statement
$stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $order_id);

if ($stmt->execute()) {
    $_SESSION['message'] = "อัปเดตสถานะ Order #$order_id เป็น '$new_status' สำเร็จ!";
} else {
    $_SESSION['message'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะ";
}

$stmt->close();
header("Location: all_orders.php");
exit();
?>