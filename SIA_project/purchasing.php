<?php
session_start();

// Check if the user is logged in and has the 'admin' or 'superuser' role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'superuser'])) {
    header("location: ../login.php");
    exit();
}

include 'connection.php';

// Generate PO Number
function generatePONumber() {
    return 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle supplier selection and PO creation
    if (isset($_POST['select_supplier'])) {
        $req_id = $_POST['req_id'];
        $supplier_id = $_POST['supplier_id']; 

        // Fetch requisition details
        $sql = "SELECT * FROM requisitions WHERE req_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        $req_result = $stmt->get_result();
        $req_row = $req_result->fetch_assoc();
        $stmt->close();

        // Fetch supplier details 
        $supplier_sql = "SELECT * FROM Suppliers WHERE supplier_id = ?";
        $supplier_stmt = $conn->prepare($supplier_sql);
        $supplier_stmt->bind_param("i", $supplier_id);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        $supplier = $supplier_result->fetch_assoc();
        $supplier_stmt->close();

        if ($supplier) {
            $po_number = generatePONumber();
            $created_by = $_SESSION['user_id'];

            // Check if the requisition is already fulfilled from the inventory
            $check_inventory_sql = "SELECT * 
                                   FROM Inventory 
                                   WHERE item_name = ? 
                                     AND class = ? 
                                     AND unit = ?";
            $check_inventory_stmt = $conn->prepare($check_inventory_sql);
            $check_inventory_stmt->bind_param("sss", $req_row['req_item_name'], $req_row['req_class'], $req_row['req_unit']);
            $check_inventory_stmt->execute();
            $inventory_result = $check_inventory_stmt->get_result();
            $inventory_item = $inventory_result->fetch_assoc();
            $check_inventory_stmt->close();

            if ($inventory_result->num_rows > 0 && $inventory_item['quantity'] >= $req_row['req_quantity']) {
                // Requisition is fulfilled from inventory, set price to 0
                $unit_price = 0;
                $total_price = 0; 
            } else {
                // Requisition needs to be purchased, use supplier price
                $unit_price = $supplier['price'];
                $total_price = $req_row['req_quantity'] * $unit_price;
            }

            // Insert PO header
            $insert_po_sql = "INSERT INTO purchase_orders (po_number, supplier_id, created_by, status, req_id, total_amount) 
                              VALUES (?, ?, ?, 'pending', ?, ?)"; 
            $insert_po_stmt = $conn->prepare($insert_po_sql);
            $insert_po_stmt->bind_param("siisd", $po_number, $supplier_id, $created_by, $req_id, $total_price); 

            if ($insert_po_stmt->execute()) {
                $po_id = $conn->insert_id;

                // Insert PO item (only if not fulfilled from inventory)
                if ($unit_price > 0) { 
                    $insert_item_sql = "INSERT INTO po_items (po_id, item_name, quantity, unit_price, total_price) 
                                        VALUES (?, ?, ?, ?, ?)";
                    $insert_item_stmt = $conn->prepare($insert_item_sql);
                    $insert_item_stmt->bind_param("isidd", $po_id, $req_row['req_item_name'], $req_row['req_quantity'], $unit_price, $total_price);
                    $insert_item_stmt->execute();
                    $insert_item_stmt->close();
                }

                // Update requisition status to 'ordered'
                $update_req_sql = "UPDATE requisitions SET req_status = 'ordered' WHERE req_id = ?";
                $update_req_stmt = $conn->prepare($update_req_sql);
                $update_req_stmt->bind_param("i", $req_id);
                $update_req_stmt->execute();

                $success_message = "Purchase Order created successfully!";
            } else {
                $error_message = "Error creating purchase order: " . $insert_po_stmt->error;
            }
            $insert_po_stmt->close();
        } else {
            $error_message = "No supplier found for this item and class.";
        }
    }

    // Handle approve/reject actions for existing POs
    if (isset($_POST['action']) && isset($_POST['po_id'])) {
        $po_id = $_POST['po_id'];
        $status = $_POST['action'] == 'approve' ? 'approved' : 'rejected';
        $approved_by = $_SESSION['user_id'];

        $sql = "UPDATE purchase_orders SET status = ?, approved_by = ? WHERE po_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $status, $approved_by, $po_id);
        $stmt->execute();

        // If approved, update inventory
        if ($status == 'approved') {
            // Get PO items
            $sql = "SELECT * FROM po_items WHERE po_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $po_id);
            $stmt->execute();
            $po_items_result = $stmt->get_result();

            while ($item = $po_items_result->fetch_assoc()) {
                $item_name = $item['item_name'];
                $quantity = $item['quantity'];

                // Update inventory (add the purchased quantity)
                $update_inventory_sql = "UPDATE Inventory 
                                         SET quantity = quantity + ? 
                                         WHERE item_name = ?";
                $update_stmt = $conn->prepare($update_inventory_sql);
                $update_stmt->bind_param("is", $quantity, $item_name);
                $update_stmt->execute(); 
            }
        }
    }
}

// Fetch requisitions with 'purchasing' status
$purchasing_reqs = $conn->query("SELECT * FROM requisitions WHERE req_status = 'purchasing'");

// Fetch all POs
$pos = $conn->query("
    SELECT po.*, s.supplier_name, u.username as created_by_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN users u ON po.created_by = u.id
    ORDER BY po.created_at DESC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Order Management</title>
    <style>
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        .btn {
            padding: 8px 15px;
            cursor: pointer;
            margin: 5px 0;
            border: none;
            border-radius: 4px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .success-message {
            color: green;
            font-weight: bold;
        }
        .error-message {
            color: red;
            font-weight: bold;
        }
        .delivery-btn {
            display: block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color:rgb(21, 174, 59); 
            color: white;
            border: none;
            text-decoration: none; 
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <button onclick="location.href='welcome.php'">Home</button>
        <h1>Purchase Order Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div style="color: green;"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div style="color: red;"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <h2>Purchasing</h2>
        <table>
            <tr>
                <th>Requisition ID</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Class</th>
                <th>Unit</th>
                <th>Available Suppliers</th>
                <th>Actions</th>
            </tr>
            <?php while ($req = $purchasing_reqs->fetch_assoc()): ?>
                <tr>
                    <td><?= $req['req_id'] ?></td>
                    <td><?= $req['req_item_name'] ?></td>
                    <td><?= $req['req_quantity'] ?></td>
                    <td><?= $req['req_class'] ?></td> 
                    <td><?= $req['req_unit'] ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="req_id" value="<?= $req['req_id'] ?>"> 
                            <select name="supplier_id">
                                <?php
                                // Fetch suppliers for this item and class
                                $supplier_options_sql = "SELECT * 
                                                        FROM Suppliers 
                                                        WHERE item_name = ? 
                                                          AND class = ?";
                                $supplier_options_stmt = $conn->prepare($supplier_options_sql);
                                $supplier_options_stmt->bind_param("ss", $req['req_item_name'], $req['req_class']);
                                $supplier_options_stmt->execute();
                                $supplier_options_result = $supplier_options_stmt->get_result();

                                while ($supplier_option = $supplier_options_result->fetch_assoc()) {
                                    echo "<option value='" . $supplier_option['supplier_id'] . "'>" 
                                         . $supplier_option['supplier_name'] . " - " 
                                         . $supplier_option['supplier_contact'] . " - $" 
                                         . $supplier_option['price'] . "</option>";
                                }
                                $supplier_options_stmt->close();
                                ?>
                            </select>
                            <button type="submit" name="select_supplier">Select Supplier</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <h2>Purchase Orders</h2>
        <table>
            <tr>
                <th>PO Number</th>
                <th>Supplier</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
            <?php while ($po = $pos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                    <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                    <td><?php echo number_format($po['total_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($po['status']); ?></td>
                    <td><?php echo htmlspecialchars($po['created_by_name']); ?></td>
                    <td><?php echo htmlspecialchars($po['created_at']); ?></td>
                    <td>
                        <?php if ($po['status'] == 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="po_id" value="<?php echo $po['po_id']; ?>">
                                <button type="submit" name="action" value="approve">Approve</button>
                                <button type="submit" name="action" value="reject">Reject</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
        <button onclick="location.href='delivery.php'" class="delivery-btn">Delivery</button>
    </div>
</body>
</html>