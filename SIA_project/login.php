<?php
include 'connection.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; // No need to escape password before hashing

    // Use a prepared statement for security
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            if ($row['approved'] == 1) {
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $row['role'];
                $_SESSION['user_id'] = $row['id']; // Store the user ID

                // Role-based redirection for approved users
                if ($row['role'] == 'user') {
                    header("location: user/home.php"); 
                    exit(); 
                } else {
                    header("location: welcome.php"); 
                    exit(); 
                }
            } else {
                echo "Your account is pending approval.";
            }
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "Username not found.";
    }
    $stmt->close(); // Close the statement
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f0f0;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        #create-account {
          display: block;
          margin-top: 10px;
          color: #4CAF50; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <label for="username">Username:</label>
            <input type="text" name="username" required><br><br>

            <label for="password">Password:</label>
            <input type="password" name="password" required><br><br>

            <input type="submit" value="Login"><br><br>

            <a id="create-account" href="register.php">Create Account</a>
        </form>
    </div>
</body>
</html>