<?php
session_start();
include 'connection.php';

// Check if the user is logged in and has the 'admin' or 'superuser' role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'superuser'])) {
    header("location: ../login.php");
    exit();
}

// Handle search form submission
if (isset($_GET['search_po'])) {
    $po_number_search = $_GET['po_number'];

    // Fetch PO details based on search
    $po_sql = "SELECT po.*, s.supplier_name 
               FROM purchase_orders po
               JOIN suppliers s ON po.supplier_id = s.supplier_id
               WHERE po.po_number LIKE ? AND po.status != 'pending'"; 
    $po_stmt = $conn->prepare($po_sql);
    $search_term = '%' . $po_number_search . '%'; 
    $po_stmt->bind_param("s", $search_term);
    $po_stmt->execute();
    $po_result = $po_stmt->get_result();

    if ($po_result->num_rows > 0) {
        $po = $po_result->fetch_assoc();
        $po_id = $po['po_id'];

        // Fetch PO items
        $items_sql = "SELECT * FROM po_items WHERE po_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $po_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $items_stmt->close();
    } else {
        $error_message = "No purchase order found with that PO number.";
    }
    $po_stmt->close();
} 

// If not a search, fetch the specific PO for display
if (!isset($_GET['search_po']) && isset($_GET['po_id'])) {
    $po_id = $_GET['po_id'];

    // Fetch PO details
    $po_sql = "SELECT po.*, s.supplier_name 
               FROM purchase_orders po
               JOIN suppliers s ON po.supplier_id = s.supplier_id
               WHERE po.po_id = ? AND po.status != 'pending'";
    $po_stmt = $conn->prepare($po_sql);
    $po_stmt->bind_param("i", $po_id);
    $po_stmt->execute();
    $po_result = $po_stmt->get_result();

    if ($po_result->num_rows > 0) {
        $po = $po_result->fetch_assoc();

        // Fetch PO items
        $items_sql = "SELECT * FROM po_items WHERE po_id = ?";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param("i", $po_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $items_stmt->close();
    } else {
        $error_message = "No purchase order found with that ID.";
    }
    $po_stmt->close();
}

// Fetch all POs for history display (excluding 'pending' status)
$pos_sql = "SELECT po.*, s.supplier_name 
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.supplier_id
            WHERE po.status != 'pending' 
            ORDER BY po.created_at DESC";
$pos_result = $conn->query($pos_sql); 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Receipt History</title>
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
            <h2>Purchase Order Details</h2>

            

            <?php if (isset($error_message)): ?>
                <p class="error"><?= $error_message ?></p>
            <?php endif; ?>

            <?php if ((isset($_GET['search_po']) && $po_result->num_rows > 0) || (isset($_GET['po_id']) && $po_result->num_rows > 0)): ?> 
                <h3>Purchase Order</h3>
                <p><strong>PO Number:</strong> <?php echo $po['po_number']; ?></p>
                <p><strong>Supplier:</strong> <?php echo $po['supplier_name']; ?></p>
                <p><strong>Date:</strong> <?php echo date('Y-m-d'); ?></p>

                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $items_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $item['item_name']; ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo $item['unit_price']; ?></td>
                                <td><?php echo $item['total_price']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <p><strong>Total Amount: $<?php echo number_format($po['total_amount'], 2); ?></strong></p>

                <p>Received by:</p>
                <p>_________________________</p>
                <p>Signature</p>
            <?php endif; ?> 
        </div> 

        

    </div>
    <form method="GET">
                <label for="po_number">Search by PO Number:</label>
                <input type="text" id="po_number" name="po_number">
                <button type="submit" name="search_po">Search</button>
            </form>

    <div class="history"> 
            <h3>Purchase Receipt History</h3>
            <table>
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($po = $pos_result->fetch_assoc()): ?> 
                        <tr>
                            <td><?php echo $po['po_number']; ?></td>
                            <td><?php echo $po['supplier_name']; ?></td>
                            <td>$<?php echo number_format($po['total_amount'], 2); ?></td>
                            <td><?php echo $po['status']; ?></td> 
                            <td><?php echo $po['created_at']; ?></td> 
                            <td>
                                <a href="?po_id=<?php echo $po['po_id']; ?>">View Receipt</a>
                            </td>
                        </tr>
                    <?php endwhile; ?> 
                </tbody>
            </table>
        </div> 
</body>
</html>