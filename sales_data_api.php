<?php
header('Content-Type: application/json');
require 'config.php'; // ★★★ ตรวจสอบให้แน่ใจว่า path ไปยังไฟล์ config.php ถูกต้อง ★★★
session_start();

// Security check: Only admins can access this API
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}


// --- ตั้งค่าพื้นฐาน ---
$response = ['labels' => [], 'data' => []];
$status_cancelled = 'ยกเลิก';
$conn->set_charset("utf8mb4");

// --- รับค่า Parameters ---
$report_type = $_GET['report_type'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$group_by = $_GET['group_by'] ?? 'day';

try {
    // --- กราฟเส้นแนวโน้มยอดขาย ---
    if ($report_type === 'sales_trend') {
        $date_group_sql = "";
        if ($group_by === 'day') {
            $date_group_sql = "DATE(o.order_date)";
        } elseif ($group_by === 'week') {
            $date_group_sql = "YEARWEEK(o.order_date, 1)";
        } elseif ($group_by === 'month') {
            $date_group_sql = "DATE_FORMAT(o.order_date, '%Y-%m')";
        }

        $sql = "SELECT {$date_group_sql} as date_group, SUM(o.total_amount) as total
                FROM orders o
                WHERE o.order_status != ? AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY date_group ORDER BY date_group ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $status_cancelled, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $response['labels'][] = $row['date_group'];
            $response['data'][] = $row['total'];
        }
        $stmt->close();
    }
    // --- กราฟแท่ง 10 อันดับสินค้าขายดี ---
    elseif ($report_type === 'top_products') {
        $sql = "SELECT p.name, SUM(oi.price * oi.quantity) as total_revenue
                FROM order_items oi
                JOIN product_variants v ON oi.variant_id = v.id
                JOIN products p ON v.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.order_status != ? AND DATE(o.order_date) BETWEEN ? AND ?
                GROUP BY p.id, p.name ORDER BY total_revenue DESC LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $status_cancelled, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $response['labels'][] = $row['name'];
            $response['data'][] = $row['total_revenue'];
        }
        $stmt->close();
    }
    // --- แผนภูมิวงกลมสัดส่วนหมวดหมู่ ---
    elseif ($report_type === 'category_sales') {
        // ## UPDATED SQL QUERY ##
        $sql = "SELECT p.category as name, SUM(oi.price * oi.quantity) as total_revenue
                FROM order_items oi
                JOIN product_variants v ON oi.variant_id = v.id
                JOIN products p ON v.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.order_status != ? AND DATE(o.order_date) BETWEEN ? AND ?
                AND p.category IS NOT NULL AND p.category != ''
                GROUP BY p.category HAVING total_revenue > 0 ORDER BY total_revenue DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $status_cancelled, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $response['labels'][] = $row['name'];
            $response['data'][] = $row['total_revenue'];
        }
        $stmt->close();
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>