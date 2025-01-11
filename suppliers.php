<?php
session_start();
include 'connection.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'superuser'])) {
    header("location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_supplier'])) {
        $name = $conn->real_escape_string($_POST['supplier_name']);
        $contact = $conn->real_escape_string($_POST['contact_person']);
        $number = $conn->real_escape_string($_POST['contact_number']);
        $email = $conn->real_escape_string($_POST['email']);
        $address = $conn->real_escape_string($_POST['address']);
        
        $sql = "INSERT INTO suppliers (supplier_name, contact_person, contact_number, email, address) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $name, $contact, $number, $email, $address);
        
        if ($stmt->execute()) {
            $success_message = "Supplier added successfully!";
        }
    }
}

// Fetch all suppliers
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier Management</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .form-group { margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <button onclick="location.href='welcome.php'">Home</button>
        <h1>Supplier Management</h1>

        <?php if (isset($success_message)): ?>
            <div style="color: green;"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Add Supplier Form -->
        <h2>Add New Supplier</h2>
        <form method="POST">
            <div class="form-group">
                <label>Supplier Name:</label>
                <input type="text" name="supplier_name" required>
            </div>
            <div class="form-group">
                <label>Contact Person:</label>
                <input type="text" name="contact_person">
            </div>
            <div class="form-group">
                <label>Contact Number:</label>
                <input type="text" name="contact_number">
            </div>
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email">
            </div>
            <div class="form-group">
                <label>Address:</label>
                <textarea name="address"></textarea>
            </div>
            <button type="submit" name="add_supplier">Add Supplier</button>
        </form>

        <!-- Suppliers List -->
        <h2>Suppliers</h2>
        <table>
            <tr>
                <th>Supplier Name</th>
                <th>Contact Person</th>
                <th>Contact Number</th>
                <th>Email</th>
                <th>Address</th>
            </tr>
            <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $supplier['supplier_name']; ?></td>
                    <td><?php echo $supplier['contact_person']; ?></td>
                    <td><?php echo $supplier['contact_number']; ?></td>
                    <td><?php echo $supplier['email']; ?></td>
                    <td><?php echo $supplier['address']; ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html> 