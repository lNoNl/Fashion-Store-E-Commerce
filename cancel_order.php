<?php
require 'config.php';
session_start();

// 1. ตรวจสอบเงื่อนไขเบื้องต้น
if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

$conn->begin_transaction();

try {
    // 2. ดึงข้อมูลออเดอร์และตรวจสอบว่าเป็นของ User ที่ล็อกอินอยู่จริง
    $stmt_order = $conn->prepare("SELECT id, order_status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt_order->bind_param("ii", $order_id, $user_id);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();
    $stmt_order->close();

    if (!$order) {
        throw new Exception("ไม่พบออเดอร์ หรือคุณไม่มีสิทธิ์เข้าถึง");
    }

    // 3. ตรวจสอบว่าสถานะของออเดอร์สามารถยกเลิกได้หรือไม่
    if ($order['order_status'] === 'ยังไม่ได้ชำระเงิน' || $order['order_status'] === 'รอดำเนินการ') {
        
        // --- ส่วนสำคัญ: การคืนสต็อกสินค้า ---
        // 4. ดึงรายการสินค้าทั้งหมดในออเดอร์นี้
        $stmt_items = $conn->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = ?");
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        
        $stmt_update_stock = $conn->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?");

        while ($item = $items_result->fetch_assoc()) {
            // 5. วนลูปเพื่อคืนสต็อกของสินค้าแต่ละรายการ
            $stmt_update_stock->bind_param("ii", $item['quantity'], $item['variant_id']);
            $stmt_update_stock->execute();
        }
        $stmt_items->close();
        $stmt_update_stock->close();
        
        // 6. อัปเดตสถานะออเดอร์เป็น 'ยกเลิก'
        $new_status = 'ยกเลิก';
        $stmt_cancel = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $stmt_cancel->bind_param("si", $new_status, $order_id);
        $stmt_cancel->execute();
        $stmt_cancel->close();

        $conn->commit();
        $_SESSION['message'] = "ยกเลิกคำสั่งซื้อ #" . $order_id . " สำเร็จแล้ว";

    } else {
        // ถ้าสถานะเป็นอย่างอื่น (เช่น ส่งแล้ว) จะไม่สามารถยกเลิกได้
        throw new Exception("ไม่สามารถยกเลิกคำสั่งซื้อนี้ได้ เนื่องจากอยู่ในสถานะ '" . htmlspecialchars($order['order_status']) . "'");
    }

} catch (Exception $e) {
    $conn->rollback();
    // แก้ไข: ใช้ $_SESSION['error_message'] เพื่อให้แสดงผลเป็นสีแดง
    $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// 7. กลับไปยังหน้าประวัติการสั่งซื้อ
header("Location: order_history.php");
exit();

?>

