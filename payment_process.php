<?php
require 'config.php'; // $conn should be established here
session_start();

// --- 1. Validate Form Submission & File Upload ---
// Check if the form was submitted correctly and a file was successfully uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] == UPLOAD_ERR_OK) {
    
    $order_id = intval($_POST['order_id']);
    
    // Ensure the user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    $user_id = $_SESSION['user_id'];

    // --- 2. File Upload Processing ---
    $target_dir = "uploads/slips/";
    // Create the directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $file_info = pathinfo($_FILES["payment_slip"]["name"]);
    $file_extension = strtolower($file_info['extension']);
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    // Validate the file type
    if (!in_array($file_extension, $allowed_extensions)) {
        header("Location: checkout.php?order_id=" . $order_id . "&error=invalid_file_type");
        exit();
    }

    $new_filename = "slip_" . $order_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // --- 3. Move Uploaded File ---
    if (move_uploaded_file($_FILES["payment_slip"]["tmp_name"], $target_file)) {
        
        // --- 4. Database Update Logic ---
        try {
            // **FIX: Check and re-establish connection if it has gone away**
            if (!$conn || !$conn->ping()) {
                // Connection was lost, close the old handle and reconnect.
                if ($conn) $conn->close();
                require 'config.php';
            }

            // Update the database with the new status and slip URL
            $new_status = 'รอดำเนินการ'; // Change status to pending for admin review
            $stmt = $conn->prepare("UPDATE orders SET order_status = ?, payment_slip_url = ? WHERE id = ? AND user_id = ?");
            
            if ($stmt === false) {
                throw new Exception("Database prepare statement failed.");
            }

            $stmt->bind_param("ssii", $new_status, $target_file, $order_id, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Database execute statement failed.");
            }
            
            $stmt->close();
            
            // Redirect to a success page
            header("Location: order_success.php?order_id=" . $order_id);
            exit();

        } catch (Exception $e) {
            // If something goes wrong with the DB, redirect back with a generic error
            // It's good practice to log the actual error: error_log($e->getMessage());
            header("Location: checkout.php?order_id=" . $order_id . "&error=db_error");
            exit();
        }

    } else {
        // File move failed
        header("Location: checkout.php?order_id=" . $order_id . "&error=upload_failed");
        exit();
    }
} else {
    // Handle cases where the upload failed or form was not submitted correctly
    $order_id_redirect = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if ($order_id_redirect > 0) {
         header("Location: checkout.php?order_id=" . $order_id_redirect . "&error=upload_failed");
    } else {
        // Bad request, maybe go to order history
        header("Location: order_history.php");
    }
    exit();
}
?>
