<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "school_events_db";

// OOP MySQLi connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
