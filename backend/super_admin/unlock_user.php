<?php
session_start();

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';

// Only super admin can unlock accounts
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

// Validate user ID
$id = $_GET['id'] ?? '';

if (!ctype_digit($id)) {
    header("Location: dashboardsuperadmin.php?error=Invalid user ID");
    exit();
}

// Unlock account
$stmt = $conn->prepare("
    UPDATE users 
    SET status = 'active', failed_attempts = 0 
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: dashboardsuperadmin.php?success=Account unlocked successfully");
exit();
