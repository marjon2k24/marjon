<?php
session_start();
include 'connection.php';

// Check if the user is logged in and has the 'admin' or 'superuser' role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'superuser'])) {
    header("location: ../login.php");
    exit();
}

// Handle form submissions for marking PO as delivered and generating delivery receipt
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_delivered'])) {
        $po_id = $_POST['po_id'];

        // Update PO status to 'delivered'
        $sql = "UPDATE purchase_orders SET status = 'delivered' WHERE po_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $po_id);

        if ($stmt->execute()) {
            // Redirect to generate delivery receipt
            header("Location: delivery_receipt.php?po_id=" . $po_id);
            exit();
        } else {
            $error_message = "Error marking PO as delivered: " . $stmt->error;
        }
    }
}

// Fetch approved and delivered POs
$approved_pos = $conn->query("
    SELECT po.*, s.supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE po.status IN ('approved', 'delivered') 
    ORDER BY po.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Management</title>
    <style>
        /* Add your CSS styles here */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1, h2 {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f0f0f5;
        }

        .btn {
            background-color: #007bff;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <button onclick="location.href='welcome.php'">Home</button>
        <h1>Delivery Management</h1>

        <?php if (isset($success_message)): ?>
            <div style="color: green;"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div style="color: red;"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <h2>Purchase Orders</h2>
        <table>
            <tr>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
            <?php while ($po = $approved_pos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                    <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                    <td><?php echo number_format($po['total_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($po['status']); ?></td>
                    <td><?php echo htmlspecialchars($po['created_at']); ?></td>
                    <td>
                        <?php if ($po['status'] == 'approved'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="po_id" value="<?php echo $po['po_id']; ?>">
                                <button type="submit" name="mark_delivered" class="btn">Approve delivery</button>
                            </form>
                        <?php elseif ($po['status'] == 'delivered'): ?>
                            <a href="delivery_receipt.php?po_id=<?php echo $po['po_id']; ?>">View Receipt</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>