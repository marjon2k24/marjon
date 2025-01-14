<?php
session_start();

// Check if the user is logged in and has the 'admin' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit();
}

// Include database connection
include 'connection.php';

// Handle form submission for adding a new item
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_item'])) {
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $quantity = (int)$_POST['quantity'];
    $class = $conn->real_escape_string($_POST['class']);
    $unit = "Pack"; // Fixed unit type

    // Check if the item already exists with the same class and unit
    $checkSql = "SELECT inv_id FROM Inventory WHERE item_name = ? AND class = ? AND unit = ?";
    $check_stmt = $conn->prepare($checkSql);
    $check_stmt->bind_param("sss", $item_name, $class, $unit);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error_message = "Item with this name, class, and unit already exists!";
    } else {
        $sql = "INSERT INTO Inventory (item_name, quantity, class, unit) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siss", $item_name, $quantity, $class, $unit);

        if ($stmt->execute()) {
            $success_message = "Item added successfully!";
        } else {
            $error_message = "Error adding item: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle form submission for updating item quantity
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_quantity'])) {
    $inv_id = (int)$_POST['inv_id']; 
    $change_quantity = (int)$_POST['new_quantity']; 

    // Fetch the current quantity
    $sql = "SELECT quantity FROM Inventory WHERE inv_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $inv_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $current_quantity = $row['quantity'];
    $stmt->close();

    // Calculate the new quantity and ensure it doesn't go below zero
    $new_quantity = max(0, $current_quantity + $change_quantity);

    // Update the inventory
    $sql = "UPDATE Inventory SET quantity = ? WHERE inv_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_quantity, $inv_id);

    if ($stmt->execute()) {
        $success_message = "Quantity updated successfully!";
    } else {
        $error_message = "Error updating quantity: " . $conn->error;
    }
    $stmt->close();
}

// Handle form submission for deleting an item
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_item'])) {
    $inv_id = (int)$_POST['inv_id'];

    $sql = "DELETE FROM Inventory WHERE inv_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $inv_id);

    if ($stmt->execute()) {
        $success_message = "Item deleted successfully!";
    } else {
        $error_message = "Error deleting item: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch inventory data
$sql = "SELECT inv_id, 
            CASE 
                WHEN item_name = 'rubberbond' THEN '-'
                WHEN class = 'class_a' THEN 'A'
                WHEN class = 'class_b' THEN 'B'
                WHEN class = 'class_c' THEN 'C'
                ELSE class  
            END AS class, 
            item_name, 
            unit, 
            quantity
        FROM Inventory 
        ORDER BY item_name, class, unit"; 
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html>
<head>
<title>Inventory Management</title>
</head>
<body>
<button onclick="location.href='welcome.php'">Home</button>
<div class="container">
    <h2>Inventory Management</h2>

    <?php if (isset($success_message)): ?>
        <p class="success"><?= $success_message ?></p>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <p class="error"><?= $error_message ?></p>
    <?php endif; ?>

    <h3>Add New Item</h3>
    <form method="post">
        <label for="item_name">Item Name:</label>
        <select id="item_name" name="item_name">
            <option value="lomboy">Lomboy</option> 
            <option value="tobacco">Tobacco</option> 
            <option value="rubberbond">rubberbond</option> 
        </select><br>

        <label for="quantity">Quantity:</label>
        <input type="number" id="quantity" name="quantity" min="0" required><br>

        <label for="class">Class:</label>
        <select id="class" name="class">
            <option value="class_a">Class A</option>
            <option value="class_b">Class B</option>
            <option value="class_c">Class C</option>
        </select><br>

        <input type="hidden" id="unit" name="unit" value="Pack">

        <input type="submit" name="add_item" value="Add Item">
    </form>

    <h3>Current Inventory</h3>
    <table class="inventory-table">
        <tr>
            <th>Item Name</th>
            <th>Class</th>
            <th>Unit</th>
            <th>Quantity</th>
            <th>Actions</th> 
        </tr>
        <?php 
        $current_item = null; 
        $rowspan = 1; 
        while($row = $result->fetch_assoc()): 
            if ($row['item_name'] != $current_item):
                $current_item = $row['item_name']; 
                $countSql = "SELECT COUNT(*) FROM Inventory WHERE item_name = ?";
                $count_stmt = $conn->prepare($countSql);
                $count_stmt->bind_param("s", $current_item);
                $count_stmt->execute();
                $countResult = $count_stmt->get_result();
                $rowspan = $countResult->fetch_row()[0];
        ?>
                <tr>
                    <td rowspan="<?= $rowspan ?>"><?= $row['item_name'] ?></td> 
                    <td><?= $row['class'] ?></td>
                    <td><?= $row['unit'] ?></td>
                    <td><?= $row['quantity'] ?></td> 
                    <td class="actions">
                        <form method="post" style="display: inline-block; margin: 0 5px;"> 
                            <input type="hidden" name="inv_id" value="<?= $row['inv_id'] ?>">
                            <input type="number" name="new_quantity" required style="width: 50px;"> 
                            <input type="submit" name="update_quantity" value="Update">
                        </form>
                        <form method="post" style="display: inline-block; margin: 0 5px;">
                            <input type="hidden" name="inv_id" value="<?= $row['inv_id'] ?>">
                            <input type="submit" name="delete_item" value="Delete" onclick="return confirm('Are you sure you want to delete this item?');">
                        </form>
                    </td>
                </tr>
        <?php   else: ?>
                <tr>
                    <td><?= $row['class'] ?></td>
                    <td><?= $row['unit'] ?></td>
                    <td><?= $row['quantity'] ?></td> 
                    <td class="actions">
                        <form method="post" style="display: inline-block; margin: 0 5px;"> 
                            <input type="hidden" name="inv_id" value="<?= $row['inv_id'] ?>">
                            <input type="number" name="new_quantity" required style="width: 50px;"> 
                            <input type="submit" name="update_quantity" value="Update">
                        </form>
                        <form method="post" style="display: inline-block; margin: 0 5px;">
                            <input type="hidden" name="inv_id" value="<?= $row['inv_id'] ?>">
                            <input type="submit" name="delete_item" value="Delete" onclick="return confirm('Are you sure you want to delete this item?');">
                        </form>
                    </td>
                </tr>
        <?php 
            endif; 
        endwhile; 
        ?>
    </table>

    <a href="logout.php" class="logout-btn">Logout</a> 
</div>
</body>
</html>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-color: #f4f4f4;
        color: #333;
    }

    .container {
        width: 80%;
        margin: 20px auto;
        background: #fff;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
    }

    h2, h3 {
        text-align: center;
    }

    .inventory-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .inventory-table th, .inventory-table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
    }

    .inventory-table th {
        background-color: #007bff;
        color: white;
    }

    .inventory-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    .inventory-table tr:hover {
        background-color: #ddd;
    }

    form {
        margin: 10px 0;
    }

    input[type="number"], select {
        padding: 5px;
        margin: 5px 0;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: calc(100% - 10px);
    }

    input[type="submit"] {
        background-color: #007bff;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    input[type="submit"]:hover {
        background-color: #0056b3;
    }

    .logout-btn {
        display: inline-block;
        margin-top: 20px;
        padding: 10px 20px;
        background-color: #dc3545;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        text-align: center;
    }

    .logout-btn:hover {
        background-color: #c82333;
    }

    .success {
        color: green;
        font-weight: bold;
        text-align: center;
    }

    .error {
        color: red;
        font-weight: bold;
        text-align: center;
    }
    .actions {
        white-space: nowrap; /* Keep actions in the same line */
    }
    .actions form {
        display: inline-block; /* Make forms inline */
        margin: 0 5px; /* Add some space between forms */
    }
    .actions input[type="number"] {
        width: 50px; /* Adjust width of number input */
    }
</style>

<?php 
$conn->close(); 
?>