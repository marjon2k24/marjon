<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['username'])) {
    header("location: login.php");
    exit();
}

// Read users
$result = $conn->query("SELECT * FROM users WHERE approved=1");

// Handle user actions
if (isset($_GET['delete'], $_SESSION['role']) && in_array($_SESSION['role'], ['superuser', 'admin'])) {
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=$id") or die("Error: " . $conn->error);
}

if (isset($_POST['update'], $_SESSION['role']) && in_array($_SESSION['role'], ['superuser', 'admin'])) {
    $id = (int) $_POST['id'];
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $conn->query("UPDATE users SET username='$username', email='$email' WHERE id=$id") or die("Error: " . $conn->error);
}

if (isset($_GET['approve_user'], $_SESSION['role'])) {
    $id = (int) $_GET['approve_user'];
    $roleCheck = $_SESSION['role'] === 'superuser' ? "role='admin' OR role='superuser'" : "role='user'";
    $conn->query("UPDATE users SET approved=1 WHERE id=$id AND $roleCheck") or die("Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; color: #333; }
        nav, footer { background: #007bff; color: white; text-align: center; padding: 10px; }
        table { width: 90%; margin: 20px auto; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #007bff; color: white; }
        form { width: 90%; margin: 20px auto; padding: 20px; background: white; }
        input, label { display: block; margin-bottom: 10px; }
        input[type="submit"] { background: #007bff; color: white; }
    </style>
</head>
<body>
<button onclick="location.href='welcome.php'">Home</button>

    <h1 style="text-align: center;">User Management Dashboard</h1>
    

    <?php if ($_SESSION['role'] === 'superuser') echo "<p>Superuser privileges.</p>"; ?>

    <h2>Approved Users</h2>
    <table>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Actions</th></tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['username'] ?></td>
                <td><?= $row['email'] ?></td>
                <td><?= $row['role'] ?></td>
                <td>
                    <?php if (in_array($_SESSION['role'], ['superuser', 'admin'])): ?>
                        <a href="?delete=<?= $row['id'] ?>">Delete</a> |
                        <a href="?edit=<?= $row['id'] ?>">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <?php if (isset($_GET['edit']) && in_array($_SESSION['role'], ['superuser', 'admin'])): 
        $id = (int)$_GET['edit'];
        $editRow = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc(); ?>
        <form method="post">
            <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
            <label>Username:</label>
            <input type="text" name="username" value="<?= $editRow['username'] ?>">
            <label>Email:</label>
            <input type="email" name="email" value="<?= $editRow['email'] ?>">
            <input type="submit" name="update" value="Update">
        </form>
    <?php endif; ?>

    <?php if (in_array($_SESSION['role'], ['superuser', 'admin'])): 
        $pendingUsers = $conn->query("SELECT * FROM users WHERE approved=0"); ?>
        <h2>Pending Users</h2>
        <table>
            <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Actions</th></tr>
            <?php while ($row = $pendingUsers->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row['username'] ?></td>
                    <td><?= $row['email'] ?></td>
                    <td><?= $row['role'] ?></td>
                    <td><a href="?approve_user=<?= $row['id'] ?>">Approve</a></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>

    <nav><a href="logout.php">Logout</a></nav>
</body>
</html>
