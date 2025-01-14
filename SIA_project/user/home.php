<?php
session_start();

// Check if the user is logged in and has the 'user' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header("location: ../login.php");
    exit();
}

// Include database connection
include '../connection.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get user_id
    $username = $_SESSION['username'];
    $sql = "SELECT id FROM users WHERE username='$username'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $user_id = $row['id'];

    // Get the number of items
    $num_items = count($_POST['item_name']);

    // Store requisition details for the "receipt"
    $requisition_items = []; 

    // Loop through each item
    for ($i = 0; $i < $num_items; $i++) {
        $item_name = $conn->real_escape_string($_POST['item_name'][$i]);
        $quantity = (int)$_POST['quantity'][$i];
        $unit = "Pack"; // Automatically set the unit to "Pack"
        $class = isset($_POST['class'][$i]) ? $conn->real_escape_string($_POST['class'][$i]) : null;
        $request_date = date("Y-m-d H:i:s");

        // Insert requisition into database with 'pending' status
        $sql = "INSERT INTO requisitions (req_user_id, req_item_name, req_quantity, req_unit, req_class, req_date, req_status) 
                VALUES ('$user_id', '$item_name', $quantity, '$unit', '$class', '$request_date', 'pending')";

        if ($conn->query($sql) !== TRUE) {
            $error_message = "Error submitting requisition: " . $conn->error;
            break;
        } else {
            // Store requisition details
            $requisition_items[] = [
                'req_item_name' => $item_name, 
                'req_quantity' => $quantity,
                'req_unit' => $unit,
                'req_class' => $class,
                'req_date' => $request_date
            ];
        }
    }

    if (!isset($error_message)) {
        // Display the "receipt"
        echo "<div class='receipt'>";
        echo "<h2>Requisition Receipt</h2>";
        echo "<p>Request Date: " . date("Y-m-d H:i:s") . "</p>";
        echo "<table>";
        echo "<tr><th>Item Name</th><th>Quantity</th><th>Unit</th><th>Class</th></tr>";
        foreach ($requisition_items as $item) {
            echo "<tr>";
            echo "<td>" . $item['req_item_name'] . "</td>";
            echo "<td>" . $item['req_quantity'] . "</td>";
            echo "<td>" . $item['req_unit'] . "</td>";
            echo "<td>" . ($item['req_class'] ? $item['req_class'] : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>User Home</title>
<style>
    body { font-family: Arial; background: #f4f4f4; color: #333; }
    .container { width: 600px; margin: 50px auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    h2 { text-align: center; color: #333; }
    .item { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; }
    input, textarea { width: calc(100% - 22px); padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
    select { width: calc(100% - 22px); padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
    input[type="submit"] { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    .error { color: red; }
    .success { color: green; }
    .receipt { margin-top: 20px; background: white; padding: 20px; border: 1px solid #ddd; }
    .receipt table { width: 100%; border-collapse: collapse; }
    .receipt th, .receipt td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    .logout-btn {
      display: block;
      margin-top: 20px;
      padding: 10px 20px;
      background-color: #dc3545; 
      color: white;
      border: none;
      text-decoration: none; 
      text-align: center;}
</style>
</head>
<body>
    <div class="container">
        <h2>Requisition Form</h2>

        <?php if (isset($success_message)): ?>
            <p class="success"><?= $success_message ?></p>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <p class="error"><?= $error_message ?></p>
        <?php endif; ?>

        <form method="post">
            <div id="items-container">
                <div class="item">
                    <label for="item_name[]">Item Name:</label>
                    <select id="item_name[]" name="item_name[]" onchange="toggleClass(this)">
                        <option value="rubberbond">Rubberbond</option>
                        <option value="tobacco">Tobacco</option>
                        <option value="lomboy">Lomboy</option>
                    </select>

                    <label for="quantity[]">Quantity:</label>
                    <input type="number" id="quantity[]" name="quantity[]" min="1" required>

                    <div class="class-container" style="display: none;"> 
                        <label for="class[]">Class:</label>
                        <select id="class[]" name="class[]">
                            <option value="class_a">Class A</option>
                            <option value="class_b">Class B</option>
                            <option value="class_c">Class C</option> 
                        </select>
                    </div>
                </div>
            </div>

            <button type="button" onclick="addItem()">Add Item</button>
            <input type="submit" value="Submit Requisition">
        </form>
    </div>

    <script>
    function addItem() {
        const itemsContainer = document.getElementById('items-container');
        const newItem = document.createElement('div');
        newItem.classList.add('item');
        newItem.innerHTML = `
            <label for="item_name[]">Item Name:</label>
            <select id="item_name[]" name="item_name[]" onchange="toggleClass(this)">
                <option value="rubberbond">Rubberbond</option>    
                <option value="tobacco">Tobacco</option>
                <option value="lomboy">Lomboy</option>
            </select>

            <label for="quantity[]">Quantity:</label>
            <input type="number" id="quantity[]" name="quantity[]" min="1" required>

            <div class="class-container" style="display: none;"> 
                <label for="class[]">Class:</label>
                <select id="class[]" name="class[]">
                    <option value="class_a">Class A</option>
                    <option value="class_b">Class B</option>
                    <option value="class_c">Class C</option>
                </select>
            </div>
        `;
        itemsContainer.appendChild(newItem);
    }

    function toggleClass(selectElement) {
        const classContainer = selectElement.parentNode.querySelector('.class-container');
        if (selectElement.value === 'tobacco' || selectElement.value === 'lomboy') {
            classContainer.style.display = 'block';
        } else {
            classContainer.style.display = 'none';
        }
    }
    </script>
      <a href="../logout.php" class="logout-btn">Logout</a> 
      <a href="user_history.php" class="logout-btn">history</a>
</body>
</html>
