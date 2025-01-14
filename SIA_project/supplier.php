<?php
session_start();

// Check if the user is logged in and has the 'admin' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit();
}

// Include database connection
include 'connection.php';

// Initialize message variables
$success_message = "";
$error_message = "";

// Handle form submission for adding a new supplier
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supplier'])) {
    $supplier_name = $_POST['supplier_name'];
    $supplier_contact = $_POST['supplier_contact'];
    $item_name = $_POST['item_name'];
    $class = $_POST['class'];
    $price = floatval($_POST['price']);

    // Check if a supplier with the same details already exists
    $check_sql = "SELECT supplier_id FROM Suppliers WHERE supplier_name = ? AND supplier_contact = ? AND item_name = ? AND class = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ssss", $supplier_name, $supplier_contact, $item_name, $class);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error_message = "Supplier with these details already exists!";
    } else {
        // Proceed with adding the new supplier
        $stmt = $conn->prepare("INSERT INTO Suppliers (supplier_name, supplier_contact, item_name, class, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssd", $supplier_name, $supplier_contact, $item_name, $class, $price);

        if ($stmt->execute()) {
            $success_message = "Supplier added successfully!";
        } else {
            $error_message = "Error adding supplier: " . $stmt->error;
        }
        $stmt->close();
    }
}


// Handle form submission for deleting a supplier
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_supplier'])) {
    $supplier_id = (int)$_POST['supplier_id'];

    // Start a transaction to ensure atomicity
    $conn->begin_transaction();

    try {
        // Delete related data
        $stmt = $conn->prepare("DELETE FROM Suppliers WHERE supplier_id = ?");
        $stmt->bind_param("i", $supplier_id);
        if (!$stmt->execute()) {
            throw new Exception("Error deleting supplier: " . $stmt->error);
        }
        $stmt->close();

        // Commit the transaction
        $conn->commit();
        $success_message = "Supplier deleted successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Fetch suppliers grouped by supplier name
$sql = "SELECT supplier_name, supplier_contact, item_name, class, price, supplier_id FROM Suppliers ORDER BY supplier_name, item_name";
$result = $conn->query($sql);

$suppliers = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[$row['supplier_name']][] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier Profiling</title>
    <style>
        /* Add your CSS styles here */
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #ddd;
        }

        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        form input, form select, form textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
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
    </style>
</head>
<body>
<button onclick="location.href='welcome.php'">Home</button>
    <div class="container">
        <h2>Supplier Profiling</h2>

        <?php if (!empty($success_message)): ?>
            <p class="success"><?= $success_message ?></p>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <p class="error"><?= $error_message ?></p>
        <?php endif; ?>

        <h3>Add New Supplier</h3>
        <form method="post">
            <label for="supplier_name">Supplier Name:</label>
            <input type="text" id="supplier_name" name="supplier_name" required>

            <label for="supplier_contact">Supplier Contact:</label>
            <input type="tel" id="supplier_contact" name="supplier_contact" pattern="[0-9]{11}" title="Please enter an 11-digit number." required maxlength="11">

            <label for="item_name">Item Name:</label>
            <select id="item_name" name="item_name">
                <option value="lomboy">Lomboy</option>
                <option value="tobacco">Tobacco</option>
                <option value="rubberbond">Rubberbond</option>
            </select>

            <label for="class">Class:</label>
            <select id="class" name="class">
                <option value="class_a">Class A</option>
                <option value="class_b">Class B</option>
                <option value="class_c">Class C</option>
            </select>

            <label for="price">Price:</label>
            <input type="number" id="price" name="price" step="0.01" required>

            <input type="submit" name="add_supplier" value="Add Supplier">
        </form>

        <h3>Supplier List</h3>
        <table>
            <tr>
                <th>Supplier Name</th>
                <th>Contact</th>
                <th>Item Name</th>
                <th>Class</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
            <?php if (!empty($suppliers)): ?>
                <?php foreach ($suppliers as $supplier_name => $supplier_rows): ?>
                    <?php $rowspan = count($supplier_rows); ?>
                    <?php foreach ($supplier_rows as $index => $row): ?>
                        <tr>
                            <?php if ($index == 0): ?>
                                <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($supplier_name) ?></td>
                                <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($row['supplier_contact']) ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td>
                                <?= $row['item_name'] == 'rubberbond' ? '-' : htmlspecialchars(strtoupper(str_replace('class_', '', $row['class']))) ?>
                            </td>
                            <td><?= htmlspecialchars($row['price']) ?></td>
                            <td>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="supplier_id" value="<?= $row['supplier_id'] ?>">
                                    <input type="submit" name="delete_supplier" value="Delete" onclick="return confirm('Are you sure?')">
                                </form>
                                <a href="edit_supplier.php?id=<?= $row['supplier_id'] ?>" class="btn btn-primary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">No suppliers found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>


