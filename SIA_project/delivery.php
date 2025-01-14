<?php
session_start();

// Check if the user is logged in and has the 'admin' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit();
}

include 'connection.php';

// Get the req_id from the URL parameter
if (isset($_GET['req_id'])) {
    $req_id = filter_var($_GET['req_id'], FILTER_SANITIZE_NUMBER_INT);

    // Fetch requisition details
    $sql = "SELECT r.*, u.username 
            FROM requisitions r
            INNER JOIN users u ON r.req_user_id = u.id
            WHERE r.req_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $req_row = $result->fetch_assoc();
    $stmt->close();

    if (!$req_row) {
        $error_message = "Requisition not found.";
    }
} else {
    $error_message = "Invalid request.";
}

// Handle delivery confirmation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delivery'])) {
    // Update requisition status to 'delivered'
    $sql = "UPDATE requisitions SET req_status = 'delivered' WHERE req_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $req_id);

    if ($stmt->execute()) {
        // Redirect to delivery_receipt.php to generate and display the receipt in the same tab
        header("Location: delivery_receipt.php?req_id=" . $req_id); 
        exit(); 
    } else {
        $error_message = "Error confirming delivery: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Confirmation</title>
    <button onclick="location.href='welcome.php'">Home</button>
    <style>
        body { font-family: Arial; background: #f4f4f4; color: #333; }
        .container { width: 600px; margin: 50px auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .error { color: red; margin-bottom: 10px; }
        .success { color: green; margin-bottom: 10px; }
        .confirm-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Delivery Confirmation</h2>

        <?php if (isset($error_message)): ?>
            <p class="error"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>

        <?php if (isset($req_row)): ?>
            <h3>Requisition Details</h3>
            <table>
                <tr>
                    <th>Requisition ID:</th>
                    <td><?= htmlspecialchars($req_row['req_id']) ?></td>
                </tr>
                <tr>
                    <th>Username:</th>
                    <td><?= htmlspecialchars($req_row['username']) ?></td>
                </tr>
                <tr>
                    <th>Item Name:</th>
                    <td><?= htmlspecialchars($req_row['req_item_name']) ?></td>
                </tr>
                <tr>
                    <th>Quantity:</th>
                    <td><?= htmlspecialchars($req_row['req_quantity']) ?></td>
                </tr>
                <tr>
                    <th>Unit:</th>
                    <td><?= htmlspecialchars($req_row['req_unit']) ?></td>
                </tr>
                <tr>
                    <th>Class:</th>
                    <td><?= htmlspecialchars($req_row['req_class']) ?></td>
                </tr>
                <tr>
                    <th>Date:</th>
                    <td><?= htmlspecialchars($req_row['req_date']) ?></td>
                </tr>
            </table>

            <?php if ($req_row['req_status'] == 'approved'): ?>
                <form method="post" action="delivery_receipt.php?req_id=<?= htmlspecialchars($req_row['req_id']) ?>">
                    <button type="submit" name="confirm_delivery" class="confirm-btn">Confirm Delivery</button>
                </form>
            <?php elseif ($req_row['req_status'] == 'delivered'): ?>
                <p>This requisition has been delivered.</p>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>