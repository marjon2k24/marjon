<?php
$conn = new mysqli("localhost", "root", "", "user_auth");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Function to prevent SQL injection (prepared statements)
function executeQuery($conn, $sql, $types = null, ...$params) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute() === false) {
        die("Error executing statement: " . $stmt->error);
    }

    return $stmt;
}
?>