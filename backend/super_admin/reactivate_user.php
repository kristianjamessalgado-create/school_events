<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';

// Only super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

// Validate ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: " . BASE_URL . "/backend/super_admin/superadmin_controller.php?success=Invalid user ID");
    exit();
}

$id = (int)$_GET['id'];

// update ka sa status
$stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// mo redirect dayun if success
header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php?success=User reactivated");
exit;
