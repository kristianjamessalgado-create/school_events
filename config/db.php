<?php
if (!defined('EVENTIFY_APP_TIMEZONE')) {
    define('EVENTIFY_APP_TIMEZONE', 'Asia/Manila');
}
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(EVENTIFY_APP_TIMEZONE);
}

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
