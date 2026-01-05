<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';

// Only super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

// Fetch users
$stmt = $conn->prepare("SELECT id, name, email, role, status FROM users");
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Success message
$success = $_GET['success'] ?? '';

// Load view
include __DIR__ . '/../../super_admin/dashboardsuperadmin.php';
