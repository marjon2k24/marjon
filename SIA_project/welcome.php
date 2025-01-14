<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit();
}

// Read users
$result = $conn->query("SELECT * FROM users WHERE approved=1");

// Handle user actions (rest of your PHP code remains the same) 
?>

<!DOCTYPE html>
<html>  
<head>
    <title>User Dashboard</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; color: #333; display: flex; }
        nav { 
            background: #007bff; 
            color: white; 
            width: 250px; 
            padding: 20px; 
            position: fixed; /* Keep sidebar in place */
            height: 100vh; /* Make sidebar full viewport height */
        }
        nav a {
            display: block;
            color: white;
            text-decoration: none;
            margin-bottom: 10px;
        }
        main { 
            margin-left: 250px; /* Adjust for sidebar width */
            padding: 20px;
            flex-grow: 1; /* Allow main content to take available space */
        }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #007bff; color: white; }
        form { width: 100%; padding: 20px; background: white; }
        input, label { display: block; margin-bottom: 10px; }
        input[type="submit"] { background: #007bff; color: white; }
    </style>
</head>
<body>
    <nav>
        <?php if (in_array($_SESSION['role'], ['superuser', 'admin'])): ?>
            <a href="welcome.php">Home</a>
            <a href="user_management.php">User Management</a> 
            <a href="requests.php">Request</a> 
            <a href="inventory.php">Inventory</a>
            <a href="purchasing.php">purchasing</a>
            <a href="supplier.php">Supplier</a>
            <a href="delivery.php">delivery</a>
            <a href="logout.php">Logout</a>
        <?php endif; ?>
    </nav>

    <main>
        <h1 style="text-align: center;">User Management Dashboard</h1>

    </main>
</body>
</html>