<?php
require 'db_connection.php'; // Ensure this file points to your database connection

if (!isset($_GET['order_id'])) {
    die("Invalid request.");
}

$order_id = $_GET['order_id'];

// Fetch the order details
$query = "
    SELECT o.id AS order_id, u.email, o.uc_amount, o.price, o.payment_method, o.order_date, o.payment_status
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    WHERE o.id = :order_id AND o.receipt_status = 'Approved'
";
$stmt = $pdo->prepare($query);
$stmt->execute(['order_id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Invalid order or receipt not approved.");
}

// Generate receipt content
header("Content-Type: text/html"); // Use HTML content type for browser display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            text-align: center;
        }
        h1 {
            color: #4CAF50;
        }
        table {
            width: 60%;
            margin: 0 auto;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
    </style>
</head>
<body>
    <h1>Payment Receipt</h1>
    <table>
        <tr>
            <th>Order ID</th>
            <td><?= htmlspecialchars($order['order_id']) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= htmlspecialchars($order['email']) ?></td>
        </tr>
        <tr>
            <th>UC Package</th>
            <td><?= htmlspecialchars($order['uc_amount']) ?> UC</td>
        </tr>
        <tr>
            <th>Price</th>
            <td>$<?= htmlspecialchars($order['price']) ?></td>
        </tr>
        <tr>
            <th>Payment Method</th>
            <td><?= htmlspecialchars($order['payment_method']) ?></td>
        </tr>
        <tr>
            <th>Order Date</th>
            <td><?= htmlspecialchars($order['order_date']) ?></td>
        </tr>
    </table>
    <p>Thank you for your purchase!</p>
</body>
</html>
