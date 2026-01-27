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

// Fetch user info (including department)
$stmt = $conn->prepare("SELECT id, user_id, name, department FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Safe defaults
$user_name  = $user['name'] ?? 'Student';
$department = $user['department'] ?? null;
$events     = [];
$msg        = $_GET['msg'] ?? '';

// Fetch events filtered by student's department
if ($department) {
    $stmt2 = $conn->prepare("SELECT * FROM events WHERE status = 'active' AND (department = ? OR department = 'ALL') ORDER BY date ASC");
    $stmt2->bind_param("s", $department);
} else {
    // Fallback: if no department set, show all active events
    $stmt2 = $conn->prepare("SELECT * FROM events WHERE status = 'active' ORDER BY date ASC");
}

if ($stmt2 && $stmt2->execute()) {
    $result2 = $stmt2->get_result();
    if ($result2) {
        $events = $result2->fetch_all(MYSQLI_ASSOC);
    }
    $stmt2->close();
}

// Include the view
include __DIR__ . '/../../views/dashboard_student.php';
