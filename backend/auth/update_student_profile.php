<?php
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/config.php';

// Only allow logged-in students
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'student') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

$studentId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $department = $_POST['department'] ?? null;

    $error = '';

    if ($name === '') {
        $error = "Full name is required.";
    }

    $allowedDepartments = ['BSIT','BSHM','CONAHS','Senior High'];
    if ($department === null || $department === '') {
        $error = "Department is required.";
    } elseif (!in_array($department, $allowedDepartments, true)) {
        $error = "Invalid department selected.";
    }

    if ($error !== '') {
        header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode($error));
        exit();
    }

    // Update student profile
    $stmt = $conn->prepare("UPDATE users SET name = ?, department = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssi", $name, $department, $studentId);
        $stmt->execute();
        $stmt->close();

        // Keep session name in sync
        $_SESSION['name'] = $name;

        header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Profile updated successfully."));
        exit();
    } else {
        header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php?msg=" . urlencode("Failed to update profile."));
        exit();
    }
}

// Fallback for non-POST access
header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php");
exit();

