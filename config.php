<?php
$servername = "localhost";
$username = "root";  // Default XAMPP user
$password = "";      // Default empty
$dbname = "complaint_center";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
session_start();  // For user sessions
?>