<?php
// Set header to return JSON
header('Content-Type: application/json');
require 'config.php';
session_start();

// Basic security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_POST['id']) || !isset($_POST['action'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request.']);
    exit();
}

$product_id = intval($_POST['id']);
$action = $_POST['action'];
$new_status = '';

// Determine the new status based on the action
if ($action === 'hide') {
    $new_status = 'hidden';
} elseif ($action === 'unhide') {
    $new_status = 'visible';
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

try {
    // Update the product status in the database
    $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $product_id);
    
    if ($stmt->execute()) {
        // Success
        echo json_encode([
            'success' => true,
            'new_status' => $new_status,
            'product_id' => $product_id
        ]);
    } else {
        // DB update failed
        throw new Exception('Database update failed.');
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>