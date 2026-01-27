<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/school_events/assets/css/login.css">
</head>
<body>

<!-- VIDEO BACKGROUND -->
<video autoplay muted loop id="bgVideo">
    <source src="/school_events/assets/video/adminvid.mov" type="video/mp4">
    Your browser does not support the video tag.
</video>

<div class="container">

    <!-- LOGIN FORM -->
    <div class="form-box login">
        <h1>Login</h1>

        <?php if ($error && isset($_GET['form']) && $_GET['form'] === 'login'): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="/school_events/backend/auth/auth.php" method="POST">
            <input type="hidden" name="action" value="login">

            <div class="input-box">
                <input type="email" name="email" placeholder="Email" required>
                <i class="fa-solid fa-envelope"></i>
            </div>

            <div class="input-box">
                <input type="password" name="password" placeholder="Password" id="loginPassword" required>
                <i class="fa-solid fa-eye" id="toggleLoginPassword" style="cursor:pointer;"></i>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <button class="switch-btn">Don't have an account? Register</button>
    </div>

    <!-- REGISTER FORM -->
    <div class="form-box register">
        <h1>Register</h1>

        <?php if ($error && isset($_GET['form']) && $_GET['form'] === 'register'): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success && isset($_GET['form']) && $_GET['form'] === 'register'): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form action="/school_events/backend/auth/auth.php" method="POST">
            <input type="hidden" name="action" value="register">

            <div class="input-box">
                <input type="text" name="name" placeholder="Username" required>
            </div>

            <div class="input-box">
                <input type="email" name="email" placeholder="Email" required>
                <i class="fa-solid fa-envelope"></i>
            </div>

            <div class="input-box">
                <input type="password" name="password" placeholder="Password" id="registerPassword" required>
                <i class="fa-solid fa-eye" id="toggleRegisterPassword" style="cursor:pointer;"></i>
            </div>

            <div class="input-box">
                <input type="password" name="confirm_password" placeholder="Confirm Password" id="confirmPassword" required>
                <i class="fa-solid fa-eye" id="toggleConfirmPassword" style="cursor:pointer;"></i>
            </div>

            <div class="input-box">
                <select name="role" id="roleSelect" required>
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="organizer">Organizer</option>
                </select>
            </div>

            <div class="input-box" id="departmentWrapper" style="display: none;">
                <select name="department" id="departmentSelect">
                    <option value="">Select Department</option>
                    <option value="BSIT">BSIT</option>
                    <option value="BSHM">BSHM</option>
                    <option value="CONAHS">CONAHS</option>
                    <option value="Senior High">Senior High</option>
                </select>
            </div>

            <button type="submit" class="btn">Register</button>
        </form>

        <button class="switch-btn">Already have an account? Login</button>
    </div>

</div>

<!-- JS Scripts -->
<script>
// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.container');
    const switchButtons = document.querySelectorAll('.switch-btn');

    // Toggle between login and register
    switchButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            container.classList.toggle('active');
        });
    });

    // Show/hide password function
    function togglePassword(inputId, toggleId) {
        const input = document.getElementById(inputId);
        const toggle = document.getElementById(toggleId);

        if (input && toggle) {
            toggle.addEventListener('click', () => {
                if(input.type === 'password') {
                    input.type = 'text';
                    toggle.classList.remove('fa-eye');
                    toggle.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    toggle.classList.remove('fa-eye-slash');
                    toggle.classList.add('fa-eye');
                }
            });
        }
    }

    // Apply to all password fields
    togglePassword('loginPassword', 'toggleLoginPassword');
    togglePassword('registerPassword', 'toggleRegisterPassword');
    togglePassword('confirmPassword', 'toggleConfirmPassword');

    // Show department field only for students
    const roleSelect = document.getElementById('roleSelect');
    const deptWrapper = document.getElementById('departmentWrapper');
    if (roleSelect && deptWrapper) {
        const toggleDept = () => {
            if (roleSelect.value === 'student') {
                deptWrapper.style.display = 'block';
            } else {
                deptWrapper.style.display = 'none';
            }
        };
        roleSelect.addEventListener('change', toggleDept);
        // Initialize on load
        toggleDept();
    }
});
</script>

</body>
</html>
