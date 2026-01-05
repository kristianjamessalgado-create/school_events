<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';

// Only allow logged-in organizers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

// Logged-in user's ID
$session_user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$stmt->bind_result($db_name);
$stmt->fetch();
$user_name = $db_name ?? 'Organizer';
$stmt->close();

// Fetch events
$events = [];
$stmt2 = $conn->prepare("SELECT * FROM events WHERE organizer_id = ?");
$stmt2->bind_param("i", $session_user_id);
$stmt2->execute();
$result = $stmt2->get_result();
if ($result) {
    $events = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt2->close();
$conn->close();

// Optional message
$msg = $_GET['msg'] ?? '';

// Include view (pass variables)
include __DIR__ . '/../../views/dashboardorganizer.php';
