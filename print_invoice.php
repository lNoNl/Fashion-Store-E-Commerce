<?php
session_start();
require_once 'config.php';

// 1. ตรวจสอบสิทธิ์เบื้องต้น
if (!isset($_GET['order_id']) || !isset($_SESSION['user_id'])) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// 2. ดึงข้อมูล Order ด้วย Prepared Statement และตรวจสอบสิทธิ์
$sql = "SELECT o.*, u.first_name, u.username 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ?";
$params = [$order_id];
$types = "i";
if ($user_role != 'admin') {
    $sql .= " AND o.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}
$stmt_order = $conn->prepare($sql);
$stmt_order->bind_param($types, ...$params);
$stmt_order->execute();
$order_result = $stmt_order->get_result();
if ($order_result->num_rows == 0) {
    die("ไม่พบคำสั่งซื้อ หรือคุณไม่มีสิทธิ์เข้าถึง");
}
$order = $order_result->fetch_assoc();
$stmt_order->close();

// 3. ดึงรายการสินค้าใน Order และเก็บใน Array
$stmt_items = $conn->prepare(
    "SELECT p.name, v.color, v.size, oi.quantity, oi.price 
     FROM order_items oi
     JOIN product_variants v ON oi.variant_id = v.id
     JOIN products p ON v.product_id = p.id 
     WHERE oi.order_id = ?"
);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

// 4. คำนวณยอดรวมย่อยและค่าส่ง
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping_fee = $order['total_amount'] - $subtotal;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบสั่งซื้อ #<?= $order_id ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f4f4;
            color: #333;
            font-size: 16px;
            line-height: 1.6;
        }
        .invoice-container {
            max-width: 800px;
            margin: 30px auto;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.07);
            padding: 40px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-header {
            margin-bottom: 40px;
        }
        .invoice-header .logo {
            font-size: 2em;
            font-weight: bold;
            color: #000;
        }
        .invoice-header .invoice-details {
            text-align: right;
        }
        .invoice-details strong {
            display: inline-block;
            width: 80px;
        }
        .customer-info {
            margin-bottom: 40px;
        }
        .customer-info td {
            vertical-align: top;
            width: 50%;
        }
        .items-table thead th {
            background-color: #f9f9f9;
            border-bottom: 2px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .items-table tbody td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }
        .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        .totals-section {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        .totals-table {
            width: 50%;
        }
        .totals-table td {
            padding: 8px 10px;
        }
        .totals-table .grand-total td {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 1.2em;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .no-print {
            text-align: center;
            margin: 30px auto;
        }

        @media print {
            body { background-color: #fff; padding: 0; margin: 0; }
            .invoice-container { box-shadow: none; border: none; max-width: 100%; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <table class="invoice-header">
            <tr>
                <td class="logo">La_maison</td>
                <td class="invoice-details">
                    <strong>ใบสั่งซื้อ #:</strong> <?= $order_id ?><br>
                    <strong>วันที่:</strong> <?= date("d/m/Y", strtotime($order['order_date'])) ?><br>
                    <strong>สถานะ:</strong> <?= htmlspecialchars($order['order_status']) ?><br>
                    <?php if (!empty($order['tracking_number'])): ?>
                        <strong>เลขพัสดุ:</strong> <?= htmlspecialchars($order['tracking_number']) ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table class="customer-info">
            <tr>
                <td>
                    <strong>จาก:</strong><br>
                    La Maison Store<br>
                    123 ถนนตัวอย่าง แขวงตัวอย่าง<br>
                    เขตตัวอย่าง กรุงเทพมหานคร 10100<br>
                    โทร: 08x-xxx-xxxx
                </td>
                <td class="text-right">
                    <strong>ข้อมูลลูกค้า:</strong><br>
                    คุณ <?= htmlspecialchars($order['first_name'] ?? $order['username']) ?><br>
                    <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>รายการสินค้า</th>
                    <th class="text-center">จำนวน</th>
                    <th class="text-right">ราคา/หน่วย</th>
                    <th class="text-right">ราคารวม</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['name']) ?>
                        <br><small style="color:#666;">(สี: <?= htmlspecialchars($item['color']) ?>, ไซต์: <?= htmlspecialchars($item['size']) ?>)</small>
                    </td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-right">฿<?= number_format($item['price'], 2) ?></td>
                    <td class="text-right">฿<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>ยอดรวม (Subtotal)</td>
                    <td class="text-right">฿<?= number_format($subtotal, 2) ?></td>
                </tr>
                <tr>
                    <td>ค่าจัดส่ง (Shipping)</td>
                    <td class="text-right">฿<?= number_format($shipping_fee, 2) ?></td>
                </tr>
                <tr class="grand-total">
                    <td><strong>ยอดรวมสุทธิ</strong></td>
                    <td class="text-right"><strong>฿<?= number_format($order['total_amount'], 2) ?></strong></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn--primary">พิมพ์ใบสั่งซื้อ</button>
    </div>
</body>
</html>