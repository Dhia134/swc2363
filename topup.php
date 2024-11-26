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

// Placeholder for user session
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Replace this with authentication logic
}

$user_id = $_SESSION['user_id'];

// Handle UC purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_uc'])) {
    $uc_package = $_POST['uc_package'];
    $payment_method = $_POST['payment_method'];
    $price = 0;

    // Validate UC package and set price
    $uc_prices = [
        '60' => 0.99,
        '325' => 4.99,
        '660' => 9.99,
        '1800' => 24.99,
    ];

    if (!array_key_exists($uc_package, $uc_prices)) {
        die("Invalid UC package selected.");
    }

    $price = $uc_prices[$uc_package];
    $transaction_id = uniqid('TRANS-');

    try {
        // Insert into orders table
        $orderQuery = "INSERT INTO orders (user_id, uc_amount, price, payment_method, payment_status, receipt_status, order_date) 
                       VALUES (:user_id, :uc_amount, :price, :payment_method, 'Pending', 'Pending', NOW())";
        $orderStmt = $pdo->prepare($orderQuery);
        $orderStmt->execute([
            ':user_id' => $user_id,
            ':uc_amount' => $uc_package,
            ':price' => $price,
            ':payment_method' => $payment_method,
        ]);

        // Get the order ID
        $orderId = $pdo->lastInsertId();

        // Redirect to receipt submission page
        header("Location: topup.php?upload_receipt=true&order_id=$orderId&uc_package=$uc_package&price=$price&payment_method=$payment_method");
        exit;
    } catch (Exception $e) {
        die("An error occurred while processing your request. Please try again later.");
    }
}

// Handle receipt submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_receipt'])) {
    $order_id = $_POST['order_id'];

    // Validate order ID
    $orderQuery = "SELECT * FROM orders WHERE id = :order_id AND user_id = :user_id";
    $orderStmt = $pdo->prepare($orderQuery);
    $orderStmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $orderStmt->fetch();

    if (!$order) {
        die("Invalid order ID.");
    }

    // Handle file upload
    if (!empty($_FILES['receipt']['name'])) {
        $targetDir = "uploads/receipts/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES['receipt']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        // Allow only certain file formats
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $targetFilePath)) {
                // Update the order with the receipt file
                $updateQuery = "UPDATE orders SET receipt_file = :receipt_file, receipt_status = 'Pending' WHERE id = :order_id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([':receipt_file' => $fileName, ':order_id' => $order_id]);

                echo "<p>Receipt uploaded successfully! Please wait for admin approval.</p>";
            } else {
                echo "<p>Failed to upload receipt. Please try again.</p>";
            }
        } else {
            echo "<p>Invalid file type. Only JPG, JPEG, PNG, and PDF are allowed.</p>";
        }
    } else {
        echo "<p>Please upload a receipt file.</p>";
    }
    exit;
}

// Fetch user orders
$orderQuery = "SELECT * FROM orders WHERE user_id = :user_id ORDER BY order_date DESC";
$stmt = $pdo->prepare($orderQuery);
$stmt->execute(['user_id' => $user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUBG UC Top-Up</title>
    <link rel="stylesheet" href="topup.css">
    <style>
        .payment-methods img {
            display: block;
            margin: 20px auto;
            width: 200px;
            height: auto;
        }
        .receipt-container {
            text-align: center;
            padding: 20px;
            background-color: #333;
            color: #fff;
            border-radius: 10px;
            margin: 20px auto;
            width: 80%;
        }
        .receipt-container h2 {
            margin-bottom: 20px;
        }
        .return-button {
            display: block;
            margin: 20px auto;
            text-align: center;
            color: #007BFF;
            text-decoration: none;
        }
        .return-button:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>PUBG UC Top-Up</h1>

    <?php if (isset($_GET['upload_receipt']) && isset($_GET['order_id'])): ?>
        <div class="receipt-container">
            <h2>Order Summary</h2>
            <p><b>Order ID:</b> <?= htmlspecialchars($_GET['order_id']) ?></p>
            <p><b>UC Package:</b> <?= htmlspecialchars($_GET['uc_package']) ?> UC</p>
            <p><b>Price:</b> $<?= htmlspecialchars($_GET['price']) ?></p>
            <p><b>Payment Method:</b> <?= htmlspecialchars($_GET['payment_method']) ?></p>
            
            <h2>Make Payment</h2>
            <div class="payment-methods">
                <?php if ($_GET['payment_method'] === 'PayPal'): ?>
                    <img src="images/Paypal-QR-Code-253x300.png" alt="PayPal QR Code">
                <?php elseif ($_GET['payment_method'] === 'Credit Card'): ?>
                    <img src="images/JotForm-Credit-Card-QR-Code-Orange.png" alt="Credit Card QR Code">
                <?php elseif ($_GET['payment_method'] === 'Bank Transfer'): ?>
                    <img src="images/bank.jpg" alt="Bank Transfer QR Code">
                <?php else: ?>
                    <p>No QR code available for this payment method.</p>
                <?php endif; ?>
            </div>

            <form method="POST" action="topup.php" enctype="multipart/form-data">
                <h2>Upload Your Payment Receipt</h2>
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($_GET['order_id']) ?>" required>
                <label for="receipt">Upload Receipt (JPG, PNG, PDF):</label><br>
                <input type="file" id="receipt" name="receipt" required><br><br>
                <button type="submit" name="submit_receipt">Submit Receipt</button>
            </form>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <label for="uc_package">Select UC Package:</label><br>
            <select id="uc_package" name="uc_package" required>
                <option value="60">60 UC - $0.99</option>
                <option value="325">325 UC - $4.99</option>
                <option value="660">660 UC - $9.99</option>
                <option value="1800">1800 UC - $24.99</option>
            </select><br><br>

            <label for="payment_method">Payment Method:</label><br>
            <select id="payment_method" name="payment_method" required>
                <option value="PayPal">PayPal</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select><br><br>

            <button type="submit" name="purchase_uc">Purchase UC</button>
        </form>

        <h2>Your Orders</h2>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>UC Amount</th>
                    <th>Price</th>
                    <th>Payment Status</th>
                    <th>Receipt Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['id']) ?></td>
                        <td><?= htmlspecialchars($order['uc_amount']) ?> UC</td>
                        <td>$<?= htmlspecialchars($order['price']) ?></td>
                        <td><?= htmlspecialchars($order['payment_status']) ?></td>
                        <td><?= htmlspecialchars($order['receipt_status']) ?></td>
                        <td>
                            <?php if ($order['receipt_status'] === 'Approved' && $order['payment_status'] === 'Completed'): ?>
                                <a href="generate_receipt.php?order_id=<?= htmlspecialchars($order['id']) ?>" target="_blank">Download Receipt</a>
                            <?php else: ?>
                                Waiting for admin approval
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a class="return-button" href="index.php">Return to Home</a>
</body>
</html>
