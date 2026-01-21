<?php
session_start();
if (!defined('BASE_URL')) define('BASE_URL','/school_events');

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
<title>EVENTIFY - Landing Page</title>
<link rel="stylesheet" href="/school_events/assets/css/index.css?v=2">
</head>
<body>

<!-- LOGIN MODAL -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeLoginModal()">&times;</span>
        <iframe id="loginFrame" src="<?= BASE_URL ?>/views/login.php"></iframe>
    </div>
</div>

<video autoplay muted loop id="bgVideo">
    <source src="/school_events/assets/video/hero.mp4" type="video/mp4">
</video>

<header>
    <h2>EVENTIFY</h2>
    <nav>
        <a onclick="goToSection('hero')">Home</a>
        <a onclick="goToSection('features')">Features</a>
        <a onclick="goToSection('roles')">Roles</a>
        <a onclick="openLoginModal()" class="btn">Login</a>
    </nav>
</header>

<section id="hero" class="active">
    <h1>Welcome to EVENTIFY</h1>
    <p>Web & App-Based School Events Monitoring System</p>
    <a onclick="openLoginModal()" class="btn">Login</a>
    <a onclick="openRegisterModal()" class="btn">Register</a>
</section>

<section id="features">
    <h1>Features</h1>
    <div class="grid">
        <div class="card"><h3>Event Creation</h3><p>Create and schedule school events easily.</p></div>
        <div class="card"><h3>Attendance Monitoring</h3><p>Track student participation in real-time.</p></div>
        <div class="card"><h3>Notifications</h3><p>Send announcements instantly to users.</p></div>
        <div class="card"><h3>Reports & Analytics</h3><p>Generate detailed event reports and stats.</p></div>
    </div>
</section>

<section id="roles">
    <h1>Who Can Use EVENTIFY?</h1>
    <div class="grid">
        <div class="card"><h3>Administrators</h3><p>Manage users, events, and system settings.</p></div>
        <div class="card"><h3>Organizers</h3><p>Create and monitor events efficiently.</p></div>
        <div class="card"><h3>Students</h3><p>View events, register, and check attendance.</p></div>
    </div>
</section>

<section id="cta">
    <h1>Get Started Today</h1>
    <a onclick="openLoginModal()" class="btn">Login</a>
    <a onclick="openRegisterModal()" class="btn">Register</a>
</section>

<script src="/school_events/assets/js/index.js?v=1.1"></script>
</body>
</html>
