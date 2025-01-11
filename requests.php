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
    $req_id = $_POST['req_id'];
    $action = $_POST['action'];

    if ($action == 'approve') {
        $sql = "UPDATE requisitions SET req_status = 'approved' WHERE req_id = '$req_id'";
    } elseif ($action == 'decline') {
        $sql = "UPDATE requisitions SET req_status = 'declined' WHERE req_id = '$req_id'";
    }

    if ($conn->query($sql) === TRUE) {
        $success_message = "Requisition " . ($action == 'approve' ? 'approved' : 'declined') . " successfully!";
    } else {
        $error_message = "Error updating requisition: " . $conn->error;
    }
}

// Fetch all requisitions, grouped by username and ordered by date
$sql = "SELECT r.*, u.username 
        FROM requisitions r
        INNER JOIN users u ON r.req_user_id = u.id
        ORDER BY u.username, r.req_date DESC"; 
$result = $conn->query($sql);

$conn->close();
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
            <p class="success"><?= $success_message ?></p>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <p class="error"><?= $error_message ?></p>
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
                                <td colspan="9"><h3><?= $row['username'] ?></h3></td> 
                            </tr>
                            <?php $count = 1; ?> 
                            <?php $current_username = $row['username']; ?> 
                        <?php endif; ?>
                        <tr>
                            <td><?= $count ?></td>
                            <td><?= $row['username'] ?></td> 
                            <td><?= $row['req_item_name'] ?></td>
                            <td><?= $row['req_quantity'] ?></td>
                            <td><?= $row['req_unit'] ?></td>
                            <td>
                                <?php 
                                if ($row['req_item_name'] == 'rubberbond') {
                                    echo "-"; 
                                } else { 
                                    preg_match('/(a|b|c)/i', $row['req_class'], $matches);
                                    echo strtoupper($matches[1]); 
                                } 
                                ?>
                            </td>
                            <td><?= $row['req_date'] ?></td>
                            <td><?= $row['req_status'] ?></td>
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
                                <?php else: ?>
                                    <?= $row['req_status'] ?> 
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

        <a href="logout.php" class="logout-btn">Logout</a> 
    </div>
</body>
</html>