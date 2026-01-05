<?php
session_start();

include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','super_admin'])) {
    header("Location: " . BASE_URL . "/views/login.php");
    exit();
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: " . BASE_URL . "/admin/dashboard.php?error=invalid_id");
    exit();
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("
    UPDATE users 
    SET status='active', failed_attempts=0 
    WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: " . BASE_URL . "/admin/dashboard.php?message=user_reactivated");
exit();
