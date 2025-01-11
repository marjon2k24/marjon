<?php
session_start();
include 'connection.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'superuser'])) {
    header("location: login.php");
    exit();
}

// Generate PO Number
function generatePONumber() {
    return 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_po'])) {
        $supplier_id = $_POST['supplier_id'];
        $po_number = generatePONumber();
        $created_by = $_SESSION['user_id'];
        
        // Insert PO header
        $sql = "INSERT INTO purchase_orders (po_number, supplier_id, created_by, status) 
                VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $po_number, $supplier_id, $created_by);
        
        if ($stmt->execute()) {
            $po_id = $conn->insert_id;
            
            // Insert PO items
            $items = $_POST['items'];
            $quantities = $_POST['quantities'];
            $prices = $_POST['prices'];
            
            $total_amount = 0;
            
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i])) {
                    $total_price = $quantities[$i] * $prices[$i];
                    $total_amount += $total_price;
                    
                    $sql = "INSERT INTO po_items (po_id, item_name, quantity, unit_price, total_price) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isids", $po_id, $items[$i], $quantities[$i], $prices[$i], $total_price);
                    $stmt->execute();
                }
            }
            
            // Update total amount
            $conn->query("UPDATE purchase_orders SET total_amount = $total_amount WHERE po_id = $po_id");
            
            $success_message = "Purchase Order created successfully!";
        }
    }
    
    // Handle approve/reject actions
    if (isset($_POST['action']) && isset($_POST['po_id'])) {
        $po_id = $_POST['po_id'];
        $status = $_POST['action'] == 'approve' ? 'approved' : 'rejected';
        $approved_by = $_SESSION['user_id'];
        
        $sql = "UPDATE purchase_orders SET status = ?, approved_by = ? WHERE po_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $status, $approved_by, $po_id);
        $stmt->execute();
    }
}

// Fetch all POs
$pos = $conn->query("
    SELECT po.*, s.supplier_name, u.username as created_by_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN users u ON po.created_by = u.id
    ORDER BY po.created_at DESC
");

// Fetch suppliers for dropdown
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Order Management</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .form-group { margin-bottom: 15px; }
        .btn { padding: 8px 15px; cursor: pointer; }
        .item-row { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <button onclick="location.href='welcome.php'">Home</button>
        <h1>Purchase Order Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div style="color: green;"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Create PO Form -->
        <h2>Create New Purchase Order</h2>
        <form method="POST">
            <div class="form-group">
                <label>Supplier:</label>
                <select name="supplier_id" required>
                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                        <option value="<?php echo $supplier['supplier_id']; ?>">
                            <?php echo $supplier['supplier_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div id="items-container">
                <div class="item-row">
                    <input type="text" name="items[]" placeholder="Item Name" required>
                    <input type="number" name="quantities[]" placeholder="Quantity" required>
                    <input type="number" step="0.01" name="prices[]" placeholder="Unit Price" required>
                </div>
            </div>

            <button type="button" onclick="addItem()">Add Item</button>
            <button type="submit" name="create_po">Create PO</button>
        </form>

        <!-- PO List -->
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
                    <td><?php echo $po['po_number']; ?></td>
                    <td><?php echo $po['supplier_name']; ?></td>
                    <td><?php echo number_format($po['total_amount'], 2); ?></td>
                    <td><?php echo $po['status']; ?></td>
                    <td><?php echo $po['created_by_name']; ?></td>
                    <td><?php echo $po['created_at']; ?></td>
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
    </div>

    <script>
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <input type="text" name="items[]" placeholder="Item Name" required>
                <input type="number" name="quantities[]" placeholder="Quantity" required>
                <input type="number" step="0.01" name="prices[]" placeholder="Unit Price" required>
            `;
            container.appendChild(newRow);
        }
    </script>
</body>
</html> 