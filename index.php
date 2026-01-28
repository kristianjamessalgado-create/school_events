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
<title>EVENTIFY</title>
<link rel="stylesheet" href="/school_events/assets/css/index.css">
</head>
<body>

<!-- Background layers -->
<img src="/school_events/assets/img/gradient.png" alt="Background" class="bg-image">
<div class="layer-blur"></div>

<!-- Header -->
<header>
    <h2>EVENTIFY</h2>
    <nav>
        <a onclick="goToSection('hero')">Home</a>
        <a onclick="goToSection('features')">Features</a>
        <a onclick="goToSection('roles')">Roles</a>
        <a onclick="openLoginModal()" class="btn">Login</a>
    </nav>
</header>

<!-- Sections -->
<section id="hero" class="active">
    <h1>Welcome to EVENTIFY</h1>
    <p>Transform your school events management with our comprehensive Web & App-Based School Events Monitoring System. Streamline event creation, track attendance, and stay connected with real-time notifications.</p>
    <a class="btn" onclick="openLoginModal()">Get Started</a>
</section>

<section id="features">
    <h1>Powerful Features</h1>
    <div class="grid">
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">📅 Event Creation</h3>
            <p style="font-size: 0.95rem; color: #cbd5e1;">Create and manage events effortlessly</p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">✅ Attendance Monitoring</h3>
            <p style="font-size: 0.95rem; color: #cbd5e1;">Track attendance in real-time</p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">🔔 Notifications</h3>
            <p style="font-size: 0.95rem; color: #cbd5e1;">Stay updated with instant alerts</p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">📊 Reports & Analytics</h3>
            <p style="font-size: 0.95rem; color: #cbd5e1;">Get insights with detailed reports</p>
        </div>
    </div>
</section>

<section id="roles">
    <h1>Who Can Use EVENTIFY?</h1>
    <div class="grid">
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">👨‍💼 Administrators</h3>
            <p style="font-size: 0.95rem; color: #cbd5e1;">Full system control and oversight</p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">🎯 Organizers</h3>
            <p style="font-size: 0.95rem; color: #cbd5e1;">Create and manage your events</p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">🎓 Students</h3>
            <p style="font-size: 0.95rem; color: #cbd5e1;">Join events and track your participation</p>
        </div>
    </div>
</section>

<!-- Login Modal -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeLoginModal()">&times;</span>
        <iframe src="<?= BASE_URL ?>/views/login.php"></iframe>
    </div>
  
</div>

<script type="module" src="https://unpkg.com/@splinetool/viewer@1.12.39/build/spline-viewer.js"></script>
<spline-viewer url="https://prod.spline.design/QKWcuhuYDwcet-bm/scene.splinecode"></spline-viewer>

<!-- Footer -->
<footer>
    <div class="footer-inner">
        <div class="footer-left">
            <span class="footer-brand">EVENTIFY</span>
            <span class="footer-text">Web & App-Based School Events Monitoring System</span>
        </div>
        <div class="footer-links">
            <a href="javascript:void(0)" onclick="goToSection('features')">Features</a>
            <a href="javascript:void(0)" onclick="goToSection('roles')">Roles</a>
            <a href="mailto:youremail@example.com">Contact</a>
        </div>
    </div>
</footer>


<!-- JS -->
<script src="/school_events/assets/js/index.js"></script>
</body>
</html>
