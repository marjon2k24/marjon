<?php
session_start();
include 'connection.php';

// Check if the user is logged in and has the 'admin' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("location: ../login.php");
    exit();
}

// Initialize variables
$supplier_name = "";
$supplier_contact = "";
$item_name = "";
$class = "";
$price = "";
$error_message = "";
$success_message = "";

// Check if the supplier ID is provided
if (isset($_GET['id'])) {
    $supplier_id = $_GET['id'];

    // Fetch supplier data
    $sql = "SELECT * FROM Suppliers WHERE supplier_id = $supplier_id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $supplier_name = $row['supplier_name'];
        $supplier_contact = $row['supplier_contact'];
        $item_name = $row['item_name'];
        $class = $row['class'];
        $price = $row['price'];
    } else {
        $error_message = "Supplier not found.";
    }
} else {
    $error_message = "Supplier ID not provided.";
}

// Handle form submission for updating supplier
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    $supplier_name = $conn->real_escape_string($_POST['supplier_name']);
    $supplier_contact = $conn->real_escape_string($_POST['supplier_contact']);
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $class = $conn->real_escape_string($_POST['class']);
    $price = floatval($_POST['price']);

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("UPDATE Suppliers 
                            SET supplier_name = ?, 
                                supplier_contact = ?,
                                item_name = ?,
                                class = ?,
                                price = ? 
                            WHERE supplier_id = ?");
    $stmt->bind_param("ssssdi", $supplier_name, $supplier_contact, $item_name, $class, $price, $supplier_id);

    if ($stmt->execute()) {
        $success_message = "Supplier information updated successfully!";
    } else {
        $error_message = "Error updating supplier information: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Supplier</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 60%;
            margin: 50px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        form input, form select {
            display: block;
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
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
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Supplier</h2>

        <?php if ($error_message): ?>
            <p class="error"><?= $error_message ?></p>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <p class="success"><?= $success_message ?></p>
        <?php endif; ?>

        <?php if (isset($_GET['id'])): ?>
            <form method="post">
                <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">

                <label for="supplier_name">Supplier Name:</label>
                <input type="text" id="supplier_name" name="supplier_name" value="<?= $supplier_name ?>" required>

                <label for="supplier_contact">Supplier Contact:</label>
                <input
                    type="tel"
                    id="supplier_contact"
                    name="supplier_contact"
                    value="<?= $supplier_contact ?>"
                    placeholder="09XXXXXXXXX"
                    pattern="09[0-9]{9}"
                    title="Please enter an 11-digit number starting with 09."
                    required
                    maxlength="11"
                />

                <label for="item_name">Item Name:</label>
                <select id="item_name" name="item_name">
                    <option value="lomboy" <?php if ($item_name == 'lomboy') echo 'selected'; ?>>Lomboy</option>
                    <option value="tobacco" <?php if ($item_name == 'tobacco') echo 'selected'; ?>>Tobacco</option>
                </select>

                <label for="class">Class:</label>
                <select id="class" name="class">
                    <option value="class_a" <?php if ($class == 'class_a') echo 'selected'; ?>>Class A</option>
                    <option value="class_b" <?php if ($class == 'class_b') echo 'selected'; ?>>Class B</option>
                    <option value="class_c" <?php if ($class == 'class_c') echo 'selected'; ?>>Class C</option>
                </select>

                <label for="price">Price:</label>
                <input type="number" id="price" name="price" value="<?= $price ?>" step="0.01" required>

                <input type="submit" name="update_supplier" value="Update Supplier">
            </form>
        <?php endif; ?>

        <a href="supplier.php" class="logout-btn">Back to Supplier List</a>
    </div>
</body>
</html>
