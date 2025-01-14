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
    //  No specific requisition ID provided, so no need for an error message here.
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

// Fetch all delivered requisitions for history display
$reqs_sql = "SELECT r.*, u.username 
             FROM requisitions r
             JOIN users u ON r.req_user_id = u.id
             WHERE r.req_status = 'delivered'
             ORDER BY r.req_date DESC";
$reqs_result = $conn->query($reqs_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Receipt History</title>
    <style>
        /* Add your CSS styles for the receipt here */
        body {
            font-family: monospace;
        }
        .container {
            width: 800px; 
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            display: flex; 
        }
        .receipt {
            flex: 2; 
            padding-right: 20px; 
        }
        .history {
            flex: 1; 
        }
        h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 5px;
        }
    </style>
</head>
<body>
<button onclick="location.href='welcome.php'">Home</button>
    <div class="container">
        <div class="receipt"> 
            <h2>Delivery Receipt</h2>

            <?php if (isset($error_message)): ?>
                <p class="error"><?= $error_message ?></p>
            <?php endif; ?>

            <?php if (isset($req_row)): ?>
                <h3>Delivery Receipt</h3>
                <p><strong>Requisition ID:</strong> <?= htmlspecialchars($req_row['req_id']) ?></p>
                <p><strong>Username:</strong> <?= htmlspecialchars($req_row['username']) ?></p>
                <p><strong>Date:</strong> <?= date('Y-m-d') ?></p> 

                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Class</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($req_row['req_item_name']) ?></td>
                            <td><?= htmlspecialchars($req_row['req_quantity']) ?></td>
                            <td><?= htmlspecialchars($req_row['req_unit']) ?></td>
                            <td><?= htmlspecialchars($req_row['req_class']) ?></td> 
                        </tr>
                    </tbody>
                </table>

                <p>Received by:</p>
                <p>_________________________</p>
                <p>Signature</p>

                <?php if ($req_row['req_status'] == 'approved'): ?>
                    <form method="post" action="delivery_receipt.php?req_id=<?= htmlspecialchars($req_row['req_id']) ?>">
                        <button type="submit" name="confirm_delivery" class="confirm-btn">Confirm Delivery</button>
                    </form>
                <?php elseif ($req_row['req_status'] == 'delivered'): ?>
                    <p>This requisition has been delivered.</p>
                <?php endif; ?> 

            <?php endif; ?> 
        </div> 

       

    </div>

    <div class="history"> 
            <h3>Delivered history</h3>
            <table>
                <thead>
                    <tr>
                        <th>Requisition ID</th>
                        <th>Username</th>
                        <th>Item Name</th>
                        <th>Delivered At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (isset($reqs_result)) {
                        while ($req = $reqs_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['req_id']) ?></td>
                                <td><?= htmlspecialchars($req['username']) ?></td>
                                <td><?= htmlspecialchars($req['req_item_name']) ?></td>
                                <td><?= htmlspecialchars($req['req_date']) ?></td> 
                                <td>
                                    <a href="?req_id=<?= htmlspecialchars($req['req_id']) ?>">View Receipt</a>
                                </td>
                            </tr>
                        <?php endwhile; 
                    }
                    ?>
                </tbody>
            </table>
        </div> 
</body>
</html>