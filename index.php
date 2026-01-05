<?php
session_start();

// Define BASE_URL if not already defined (fallback)
if (!defined('BASE_URL')) {
    // Change this to match your project folder if different
    define('BASE_URL', '/school_events');
}

// Redirect logged-in users to their dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'super_admin':
            header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php");
            exit();
        case 'admin':
            header("Location: " . BASE_URL . "/backend/admin/dashboard.php");
            exit();
        case 'organizer':
            header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php");
            exit();
        case 'student':
            header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php");
            exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Events Monitoring System</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
        a { text-decoration: none; color: white; background-color: #007BFF; padding: 10px 20px; border-radius: 5px; margin: 5px; display: inline-block; }
        a:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <h1>Welcome to the School Events Monitoring System</h1>
    <p>Please login or register to continue.</p>
    <a href="<?= BASE_URL ?>/views/login.php">Login</a>
    <a href="<?= BASE_URL ?>/views/register.php">Register</a>
</body>
</html>
