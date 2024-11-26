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

// Handle receipt approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Approve receipt
    if (isset($_POST['approve_receipt'])) {
        $order_id = $_POST['order_id'];

        $query = "UPDATE orders SET receipt_status = 'Approved', payment_status = 'Completed' WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['order_id' => $order_id]);

        echo "Receipt approved successfully!";
    }
    // Reject receipt
    elseif (isset($_POST['reject_receipt'])) {
        $order_id = $_POST['order_id'];

        $query = "UPDATE orders SET receipt_status = 'Rejected' WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['order_id' => $order_id]);

        echo "Receipt rejected.";
    }
    // Delete record
    elseif (isset($_POST['delete_record'])) {
        $order_id = $_POST['order_id'];

        $query = "DELETE FROM orders WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['order_id' => $order_id]);

        echo "Record deleted successfully!";
    }
    // Add record
    elseif (isset($_POST['add_record'])) {
        $email = $_POST['email'];
        $uc_amount = $_POST['uc_amount'];
        $price = $_POST['price'];
        $payment_method = $_POST['payment_method'];

        // Fetch user ID from email
        $userQuery = "SELECT id FROM users WHERE email = :email";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute(['email' => $email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            die("User with the given email does not exist.");
        }

        $query = "INSERT INTO orders (user_id, uc_amount, price, payment_method, payment_status, receipt_status, order_date)
                  VALUES (:user_id, :uc_amount, :price, :payment_method, 'Pending', 'Pending', NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'user_id' => $user['id'],
            'uc_amount' => $uc_amount,
            'price' => $price,
            'payment_method' => $payment_method
        ]);

        echo "Record added successfully!";
    }
}

// Search orders
$search = $_GET['search'] ?? '';
$orderQuery = "
    SELECT o.id AS order_id, u.email, o.uc_amount, o.price, o.payment_method, o.payment_status, o.order_date, o.receipt_file, o.receipt_status
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    WHERE u.email LIKE :search OR o.id LIKE :search
    ORDER BY o.order_date DESC
";
$orderStmt = $pdo->prepare($orderQuery);
$orderStmt->execute(['search' => "%$search%"]);
$orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }
        h1, h2 {
            text-align: center;
        }
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #fff;
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        form {
            display: inline-block;
        }
    </style>
</head>
<body>
    <h1>Admin Dashboard</h1>

    <section>
        <h2>Search Records</h2>
        <form method="GET" action="">
            <input type="text" name="search" placeholder="Search by Order ID or Email" value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>
    </section>

    <section>
        <h2>Add Record</h2>
        <form method="POST" action="">
            <label>Email:</label><br>
            <input type="email" name="email" required><br>
            <label>UC Amount:</label><br>
            <input type="number" name="uc_amount" required><br>
            <label>Price:</label><br>
            <input type="number" step="0.01" name="price" required><br>
            <label>Payment Method:</label><br>
            <select name="payment_method" required>
                <option value="PayPal">PayPal</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select><br><br>
            <button type="submit" name="add_record">Add Record</button>
        </form>
    </section>

    <section>
        <h2>Manage Orders</h2>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>User Email</th>
                    <th>UC Amount</th>
                    <th>Price</th>
                    <th>Payment Method</th>
                    <th>Receipt</th>
                    <th>Receipt Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['email']) ?></td>
                        <td><?= htmlspecialchars($order['uc_amount']) ?></td>
                        <td>$<?= htmlspecialchars($order['price']) ?></td>
                        <td><?= htmlspecialchars($order['payment_method']) ?></td>
                        <td>
                            <?php if (!empty($order['receipt_file'])): ?>
                                <a href="uploads/receipts/<?= htmlspecialchars($order['receipt_file']) ?>" target="_blank">View Receipt</a>
                            <?php else: ?>
                                No Receipt
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($order['receipt_status']) ?></td>
                        <td>
                            <?php if ($order['receipt_status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                    <button type="submit" name="approve_receipt">Approve</button>
                                    <button type="submit" name="reject_receipt">Reject</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                <button type="submit" name="delete_record">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</body>
</html>
