<?php
session_start();

// Check if the user is logged in and has the 'user' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("location: ../login.php");
    exit();
}

// Include database connection
include '../connection.php';

// Get user_id
$username = $_SESSION['username'];
$sql = "SELECT id FROM users WHERE username='$username'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$user_id = $row['id'];

// Fetch requisition history for the user
$sql = "SELECT * FROM requisitions WHERE req_user_id = '$user_id' ORDER BY req_date DESC";
$result = $conn->query($sql);

$conn->close(); 
?>

<!DOCTYPE html>
<html>
<head>
<title>User Requisition History</title>
<style>
    body { font-family: Arial; background: #f4f4f4; color: #333; }
    .container { width: 800px; margin: 50px auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    h2 { text-align: center; color: #333; }
    .history { margin-top: 20px; }
    .history table { width: 100%; border-collapse: collapse; }
    .history th, .history td { border: 1px solid #ddd; padding: 8px; text-align: left; }
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
</style>
</head>
<body>
    <div class="container">
        <h2>Requisition History</h2>

        <div class="history">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>No.</th> 
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Class</th>
                        <th>Date</th>
                        <th>Status</th> 
                    </tr>
                    <?php $count = 1; ?> 
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $count ?></td> 
                            <td><?= $row['req_item_name'] ?></td>
                            <td><?= $row['req_quantity'] ?></td>
                            <td><?= $row['req_unit'] ?></td>
                            <td>
                                <?php 
                                if ($row['req_item_name'] == 'rubberbond') {
                                    echo "-"; 
                                } else { 
                                    echo $row['req_class']; 
                                } 
                                ?>
                            </td>
                            <td><?= $row['req_date'] ?></td>
                            <td><?= $row['req_status'] ?></td> 
                        </tr>
                        <?php $count++; ?> 
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>No requisition history found.</p>
            <?php endif; ?>
        </div>

        <a href="home.php" class="logout-btn">Back to Requisition Form</a> 
    </div>
</body>
</html>