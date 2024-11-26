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

    if (isset($_POST['approve_receipt'])) {
        $query = "UPDATE orders SET receipt_status = 'Approved', uc_added = TRUE WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['order_id' => $order_id]);
        echo "Receipt approved, and UC successfully added.";
    } elseif (isset($_POST['reject_receipt'])) {
        $query = "UPDATE orders SET receipt_status = 'Rejected' WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['order_id' => $order_id]);
        echo "Receipt rejected.";
    }
}
