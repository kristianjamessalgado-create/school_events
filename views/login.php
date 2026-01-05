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
    <title>Login / Signup</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/school_events/assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>

<div class="container">

    <!-- LOGIN FORM -->
    <div class="form-box login">
        <form action="/school_events/backend/auth/auth.php" method="POST">
            <h1>Login</h1>

            <?php if ($error && isset($_GET['form']) && $_GET['form'] === 'login'): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <input type="hidden" name="action" value="login">

            <div class="input-box">
                <input type="email" name="email" placeholder="Email" required>
                <i class="fa-solid fa-envelope"></i>
            </div>

            <div class="input-box">
                <input type="password" name="password" placeholder="Password" required>
                <i class="fa-solid fa-lock"></i>
            </div>

            <div class="forgot-link">
                <a href="#">Forgot Password?</a>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>
    </div>

    <!-- REGISTER FORM -->
    <div class="form-box register">
        <form id="registerForm" action="/school_events/backend/auth/auth.php" method="POST">

            <h1>Register</h1>

            <!-- Display server-side errors -->
            <?php if ($error && isset($_GET['form']) && $_GET['form'] === 'register'): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Display success message -->
            <?php if ($success && isset($_GET['form']) && $_GET['form'] === 'register'): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <input type="hidden" name="action" value="register">

            <div class="input-box">
                <input type="text" name="name" placeholder="Username" required>
            </div>

            <div class="input-box">
                <input type="email" name="email" placeholder="Email" required>
                <i class="fa-solid fa-envelope"></i>
            </div>

            <div class="input-box">
    <input
        type="password"
        name="password"
        id="registerPassword"
        placeholder="Password"
        required
    >
    <i
        class="fa-solid fa-eye"
        id="togglePassword"
        style="cursor:pointer;"
    ></i>
</div>


            <div class="input-box">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required> 
                <i class="fa-solid fa-lock"></i>
            </div>

  
<p id="passwordError" class="text-danger" style="font-size:14px; display:none;">
    Passwords do not match
</p>


            <div class="input-box">
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="organizer">Organizer</option>
                </select>
            </div>

            <!-- OPEN MODAL -->
            <button type="button" class="btn btn-primary" onclick="openRegisterModal()">
                Register
            </button>

        </form>
    </div>

    <!-- MODAL CONFIRMATION -->
    <div class="modal fade" id="registerConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Confirm Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    Are you sure you want to submit your registration?
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitRegister()">
                        Yes, Submit
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- TOGGLE BOX -->
    <div class="toggle-box">

        <div class="toggle-panel toggle-left">
            <h1>Hello, Welcome!</h1>
            <p>Don't have an account?</p>
            <button type="button" class="btn register-btn">Register</button>
        </div>

        <div class="toggle-panel toggle-right">
            <h1>Welcome Back!</h1>
            <p>Already have an account?</p>
            <button type="button" class="btn login-btn">Login</button>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


<script src="/school_events/assets/js/login.js"></script>
</body>
</html>
