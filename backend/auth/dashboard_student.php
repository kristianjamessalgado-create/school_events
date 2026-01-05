<?php
session_start();

// Include DB and config
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php'; // for BASE_URL

// Only allow logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

// Logged-in user's ID
$session_user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT id, user_id, name FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Safe defaults
$user_name = $user['name'] ?? 'Student';
$events    = []; // You can fetch student-specific events here if needed
$msg       = $_GET['msg'] ?? '';

// Include the view
include __DIR__ . '/../../views/dashboard_student.php';
