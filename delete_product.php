<?php
session_start();
require 'config.php';

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Admin)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// 2. ตรวจสอบว่ามี Parameter ครบถ้วนหรือไม่
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: dashboard.php");
    exit();
}

$id = intval($_GET['id']);
$action = $_GET['action'];

// ========== จุดที่ 1: สร้างตัวแปรสำหรับเช็คสถานะ Transaction ==========
$transaction_started = false;

try {
    switch ($action) {
        case 'hide':
            $stmt = $conn->prepare("UPDATE products SET status = 'hidden' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $_SESSION['message'] = 'ซ่อนสินค้าสำเร็จแล้ว';
            break;

        case 'unhide':
            $stmt = $conn->prepare("UPDATE products SET status = 'visible' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $_SESSION['message'] = 'ตั้งค่าให้แสดงสินค้าสำเร็จแล้ว';
            break;

        case 'delete':
            $conn->begin_transaction();
            // ========== จุดที่ 2: ตั้งค่าสถานะว่า Transaction ได้เริ่มขึ้นแล้ว ==========
            $transaction_started = true;

            $stmt_select = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
            $stmt_select->bind_param("i", $id);
            $stmt_select->execute();
            $result = $stmt_select->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (file_exists($row['image_url'])) {
                    unlink($row['image_url']);
                }
            }
            $stmt_select->close();

            $stmt_delete = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            $stmt_delete->execute();
            $stmt_delete->close();
            
            $conn->commit();
            
            $_SESSION['message'] = 'ลบสินค้าออกจากระบบอย่างถาวรแล้ว';
            break;

        default:
            header("Location: dashboard.php");
            exit();
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }

} catch (mysqli_sql_exception $e) {
    // ========== จุดที่ 3: แก้ไข: ใช้ตัวแปร flag ที่สร้างขึ้นมาเอง ==========
    if ($transaction_started) {
        $conn->rollback();
    }
    $_SESSION['message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

header("Location: dashboard.php");
exit();
?>