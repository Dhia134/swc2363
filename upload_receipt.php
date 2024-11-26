<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'user_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $receipt_file = $_FILES['receipt_file'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png'];
    $file_type = mime_content_type($receipt_file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        die("Invalid file type. Only JPEG and PNG are allowed.");
    }

    // Save the file
    $file_name = uniqid() . "_" . basename($receipt_file['name']);
    $target_path = "uploads/receipts/" . $file_name;
    if (move_uploaded_file($receipt_file['tmp_name'], $target_path)) {
        // Update the database
        $query = "UPDATE orders SET receipt_file = :receipt_file, receipt_status = 'Pending' WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'receipt_file' => $file_name,
            'order_id' => $order_id
        ]);

        echo "Receipt uploaded successfully! Please wait for admin approval.";
    } else {
        echo "Failed to upload receipt.";
    }
}
