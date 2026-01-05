<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php?error=Access denied");
    exit();
}
include '../config/db.php';

$user_id = $_SESSION['user_id'];
$event_id = $_GET['event_id'] ?? null;

if ($event_id) {
    // Check if already registered
    $stmt = $conn->prepare("SELECT id FROM registrations WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO registrations (user_id, event_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $event_id);
        $stmt->execute();
        $msg = "Successfully registered!";
    } else {
        $msg = "You are already registered for this event.";
    }
} else {
    $msg = "Invalid event.";
}

// Redirect back to dashboard with message
header("Location: dashboard_student.php?msg=" . urlencode($msg));
exit();
