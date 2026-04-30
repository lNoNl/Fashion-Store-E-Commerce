<?php
session_start();
require 'config.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// 2. รับค่าจากฟอร์ม
if (!isset($_POST['new_status']) || empty($_POST['new_status'])) {
    header("Location: all_orders.php"); // ถ้าไม่มีสถานะใหม่ส่งมา ให้กลับไปหน้าเดิม
    exit();
}
$new_status = $_POST['new_status'];
$filter_status = $_POST['filter_status'] ?? 'all';
$search_term = $_POST['search_term'] ?? '';

try {
    // 3. สร้างเงื่อนไข WHERE แบบไดนามิกให้ตรงกับหน้า all_orders.php
    $where_clauses = [];
    $params = [$new_status]; // parameter แรกสำหรับ SET
    $types = "s";

    if ($filter_status !== 'all') {
        $where_clauses[] = "o.order_status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    if (!empty($search_term)) {
        $where_clauses[] = "(o.id LIKE ? OR u.username LIKE ?)";
        $search_param = "%" . $search_term . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }
    
    // สร้าง SQL UPDATE statement
    $sql = "UPDATE orders o JOIN users u ON o.user_id = u.id SET o.order_status = ?";
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    // 4. Execute a query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    $_SESSION['message'] = "อัปเดตสถานะออเดอร์ $affected_rows รายการเป็น '$new_status' สำเร็จ";

} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// 5. กลับไปหน้าเดิมพร้อมกับเงื่อนไขการกรอง
$redirect_params = http_build_query(['status' => $filter_status, 'search' => $search_term]);
header("Location: all_orders.php?" . $redirect_params);
exit();
?>