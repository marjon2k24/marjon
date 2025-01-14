<?php
session_start();

// Check if the user is logged in and has the 'admin' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit();
}

// Include database connection
include 'connection.php';

// Handle approve/decline actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $req_id = filter_var($_POST['req_id'], FILTER_SANITIZE_NUMBER_INT); // Sanitize input
    $action = $_POST['action'];

    // Fetch requisition details
    $sql = "SELECT * FROM requisitions WHERE req_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $req_result = $stmt->get_result();
    $req_row = $req_result->fetch_assoc();
    $stmt->close();

    if ($action == 'approve') {
        // Check if enough inventory exists
        $check_sql = "SELECT * 
                        FROM Inventory 
                        WHERE item_name = ? 
                          AND class = ? 
                          AND unit = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $req_row['req_item_name'], $req_row['req_class'], $req_row['req_unit']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $inventory_row = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($check_result->num_rows > 0 && $inventory_row['quantity'] >= $req_row['req_quantity']) {
            // Sufficient inventory, proceed with approval and delivery

            // Update inventory quantity (subtract the requested quantity)
            $new_quantity = $inventory_row['quantity'] - $req_row['req_quantity'];
            $update_inventory_sql = "UPDATE Inventory 
                                        SET quantity = ?
                                        WHERE item_name = ? 
                                          AND class = ? 
                                          AND unit = ?";
            $update_inventory_stmt = $conn->prepare($update_inventory_sql);
            $update_inventory_stmt->bind_param("isss", $new_quantity, $req_row['req_item_name'], $req_row['req_class'], $req_row['req_unit']);
            
            if ($update_inventory_stmt->execute()) { 
                // Inventory updated successfully
            } else {
                $error_message = "Error updating inventory: " . $update_inventory_stmt->error;
                error_log("Error updating inventory: " . $update_inventory_stmt->error); // Log the error
            }
            $update_inventory_stmt->close();

            // Approve requisition and set status to 'delivering'
            $sql = "UPDATE requisitions SET req_status = 'delivering' WHERE req_id = ?";
            $approve_stmt = $conn->prepare($sql);
            $approve_stmt->bind_param("i", $req_id);
            
            if ($approve_stmt->execute()) {
                // Requisition approved successfully
            } else {
                $error_message = "Error approving requisition: " . $approve_stmt->error; 
                error_log("Error approving requisition: " . $approve_stmt->error); // Log the error
            }
            $approve_stmt->close();

            // Redirect to delivery.php
            header("Location: delivery.php?req_id=" . $req_id); 
            exit();
        } else {
            // Not enough inventory, set status to 'purchasing'
            $sql = "UPDATE requisitions SET req_status = 'purchasing' WHERE req_id = ?";
            $purchasing_stmt = $conn->prepare($sql);
            $purchasing_stmt->bind_param("i", $req_id);

            if ($purchasing_stmt->execute()) {
                $success_message = "Not enough inventory. Requisition marked as 'purchasing'.";
            } else {
                $error_message = "Error updating requisition status: " . $purchasing_stmt->error;
                error_log("Error updating requisition status: " . $purchasing_stmt->error); // Log the error
            }
            $purchasing_stmt->close();
        }

    } elseif ($action == 'decline') {
        $sql = "UPDATE requisitions SET req_status = 'declined' WHERE req_id = ?";
        $decline_stmt = $conn->prepare($sql);
        $decline_stmt->bind_param("i", $req_id);
        
        if ($decline_stmt->execute()) {
            $success_message = "Requisition declined successfully!";
        } else {
            $error_message = "Error declining requisition: " . $decline_stmt->error;
            error_log("Error declining requisition: " . $decline_stmt->error); // Log the error
        }
        $decline_stmt->close();
    }
}

// Fetch all requisitions, grouped by username and ordered by date
$sql = "SELECT r.*, u.username, 
            CASE 
                WHEN r.req_item_name = 'rubberbond' THEN '-'
                WHEN r.req_class = 'class_a' THEN 'A'
                WHEN r.req_class = 'class_b' THEN 'B'
                WHEN r.req_class = 'class_c' THEN 'C'
                ELSE r.req_class 
            END AS req_class_display
        FROM requisitions r
        INNER JOIN users u ON r.req_user_id = u.id
        ORDER BY u.username, r.req_date DESC"; 
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Requisition Management</title>
    <button onclick="location.href='welcome.php'">Home</button>
    <style>
        body { font-family: Arial; background: #f4f4f4; color: #333; }
        .container { width: 800px; margin: 50px auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .history { margin-top: 20px; }
        .history table { width: 100%; border-collapse: collapse; }
        .history th, .history td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .action-btns { white-space: nowrap; } 
        .logout-btn {
            display: block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #dc3545; 
            color: white;
            border: none;
            text-decoration: none; 
            text-align: center;
        }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Requisition Management</h2>

        <?php if (isset($success_message)): ?>
            <p class="success"><?= htmlspecialchars($success_message) ?></p> 
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <p class="error"><?= htmlspecialchars($error_message) ?></p> 
        <?php endif; ?>

        <div class="history">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>No.</th>
                        <th>Username</th> 
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Class</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th> 
                    </tr>
                    <?php 
                    $count = 1; 
                    $current_username = null; 
                    ?> 
                    <?php while($row = $result->fetch_assoc()): ?>
                        <?php if ($current_username != $row['username']): ?> 
                            <tr>
                                <td colspan="9"><h3><?= htmlspecialchars($row['username']) ?></h3></td> 
                            </tr>
                            <?php $count = 1; ?> 
                            <?php $current_username = $row['username']; ?> 
                        <?php endif; ?>
                        <tr>
                            <td><?= $count ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td> 
                            <td><?= htmlspecialchars($row['req_item_name']) ?></td>
                            <td><?= htmlspecialchars($row['req_quantity']) ?></td>
                            <td><?= htmlspecialchars($row['req_unit']) ?></td>
                            <td>
                                <?= htmlspecialchars($row['req_class_display']) ?> 
                            </td>
                            <td><?= htmlspecialchars($row['req_date']) ?></td>
                            <td><?= htmlspecialchars($row['req_status']) ?></td>
                            <td class="action-btns">
                                <?php if ($row['req_status'] == 'pending'): ?>
                                    <form method="post" style="display:inline-block;"> 
                                        <input type="hidden" name="req_id" value="<?= $row['req_id'] ?>">
                                        <button type="submit" name="action" value="approve">Approve</button>
                                    </form>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="req_id" value="<?= $row['req_id'] ?>">
                                        <button type="submit" name="action" value="decline">Decline</button>
                                    </form>
                                <?php elseif ($row['req_status'] == 'approved'): ?> 
                                    <a href="delivery.php?req_id=<?= $row['req_id'] ?>">Proceed to Delivery</a>
                                <?php else: ?>
                                    <?= htmlspecialchars($row['req_status']) ?> 
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php $count++; ?> 
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>No requisitions found.</p>
            <?php endif; ?>
        </div>

        <a href="purchasing.php" class="logout-btn">Proceed to purchasing</a> 
    </div>
</body>
</html>