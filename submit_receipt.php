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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            text-align: center;
        }

        form {
            margin: 50px auto;
            width: 50%;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        input, button {
            margin: 10px 0;
            padding: 10px;
            width: 100%;
        }

        button {
            background-color: #0078d7;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056a3;
        }
    </style>
</head>
<body>
    <h1>Submit Payment Receipt</h1>
    <form method="POST" action="submit_receipt.php" enctype="multipart/form-data">
        <label for="order_id">Order ID:</label>
        <input type="text" id="order_id" name="order_id" required>

        <label for="receipt">Upload Receipt:</label>
        <input type="file" id="receipt" name="receipt" accept="image/*,.pdf" required>

        <button type="submit" name="submit_receipt">Submit Receipt</button>
    </form>
</body>
</html>

<?php
// Handle receipt upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_receipt'])) {
    $order_id = $_POST['order_id'];

    // Check if the order exists
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id");
    $stmt->execute(['order_id' => $order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        die("Invalid Order ID.");
    }

    // Validate and process uploaded file
    if (!empty($_FILES['receipt']['name'])) {
        $targetDir = "uploads/receipts/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true); // Create directory if it doesn't exist
        }

        $fileName = time() . '_' . basename($_FILES['receipt']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        // Allow only specific file types
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array(strtolower($fileType), $allowedTypes)) {
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $targetFilePath)) {
                // Update the database with receipt file path
                $query = "UPDATE orders SET receipt_file = :receipt_file WHERE id = :order_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':receipt_file' => $fileName,
                    ':order_id' => $order_id,
                ]);

                echo "Receipt uploaded successfully.";
            } else {
                echo "Failed to upload receipt. Please try again.";
            }
        } else {
            echo "Invalid file type. Only JPG, JPEG, PNG, and PDF files are allowed.";
        }
    } else {
        echo "Please upload a receipt file.";
    }
}
?>
