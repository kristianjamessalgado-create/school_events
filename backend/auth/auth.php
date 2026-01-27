<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include DB and config
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php'; // For BASE_URL

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        // --- REGISTRATION ---
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role     = $_POST['role'] ?? '';
        $department = $_POST['department'] ?? null;

        // Validation sige rag validate
      
if ($password !== $confirm_password) {
    $error = "Passwords do not match.";
}
elseif (!preg_match('/[A-Z]/', $password)
    || !preg_match('/[\W_]/', $password)
    || strlen($password) < 8) {

    $error = "Password must contain at least 1 uppercase letter, 1 special character, and be at least 8 characters long.";
}
elseif (!in_array($role, ['student', 'organizer'])) {
    $error = "Invalid role selected.";
}
elseif ($role === 'student' && empty($department)) {
    $error = "Department is required for students.";
}
else {
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Email already registered.";
    } else {
        $hashed_password = hash('sha256', $password);
        $prefix = strtoupper(substr($role, 0, 3));
        $user_id = $prefix . '-' . rand(100, 999);

        $insert = $conn->prepare(
            "INSERT INTO users (user_id, name, email, password, role, department, status)
             VALUES (?, ?, ?, ?, ?, ?, 'active')"
        );
        $insert->bind_param("ssssss", $user_id, $name, $email, $hashed_password, $role, $department);

        if ($insert->execute()) {
            $success = "Registration successful! You can now login.";
            header("Location: " . BASE_URL . "/views/login.php?success=" . urlencode($success) . "&form=register");
            exit();
        } else {
            $error = "Registration failed. Please try again.";
        }

        $insert->close();
    }

    $stmt->close();
}


        
        if (!empty($error)) {
            header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode($error) . "&form=register");
            exit();
        }

    } elseif ($action === 'login') {
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $error = "Invalid email or password";
        } else {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                $error = "Account is inactive. Contact admin.";
            } else {
                $hashed_password = hash('sha256', $password);

                if ($hashed_password !== $user['password']) {
                    // Handle failed attempts
                    $attempts = $user['failed_attempts'] + 1;

                    if ($attempts >= 5) {
                        $update = $conn->prepare("UPDATE users SET failed_attempts=?, status='inactive' WHERE id=?");
                        $update->bind_param("ii", $attempts, $user['id']);
                        $update->execute();

                        $error = "Account locked due to multiple failed attempts.";
                    } else {
                        $update = $conn->prepare("UPDATE users SET failed_attempts=? WHERE id=?");
                        $update->bind_param("ii", $attempts, $user['id']);
                        $update->execute();

                        $remaining = 5 - $attempts;
                        $error = "Incorrect password. $remaining attempts left.";
                    }
                } else {
                    // Success: reset failed attempts
                    $update = $conn->prepare("UPDATE users SET failed_attempts=0 WHERE id=?");
                    $update->bind_param("i", $user['id']);
                    $update->execute();

                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    // Decide redirect URL based on role
                    $redirectUrl = '';
                    switch ($user['role']) {
                        case 'super_admin':
                            $redirectUrl = BASE_URL . "/backend/super_admin/dashboardsuperadmin.php";
                            break;
                        case 'admin':
                            $redirectUrl = BASE_URL . "/backend/admin/dashboard.php";
                            break;
                        case 'organizer':
                            $redirectUrl = BASE_URL . "/backend/auth/dashboardorganizer.php";
                            break;
                        case 'student':
                            $redirectUrl = BASE_URL . "/backend/auth/dashboard_student.php";
                            break;
                        default:
                            $error = "Invalid role";
                            break;
                    }

                    if (!empty($error)) {
                        header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode($error) . "&form=login");
                        exit();
                    }

                    // Break out of iframe (modal) and redirect full page
                    // Works whether called inside iframe or directly
                    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body>';
                    echo '<script>';
                    echo 'window.top.location.href = ' . json_encode($redirectUrl) . ';';
                    echo '</script>';
                    echo '</body></html>';
                    exit();
                }
            }
        }

        // Redirect with error if login failed
        if (!empty($error)) {
            header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode($error) . "&form=login");
            exit();
        }
    }
} else {
    // Redirect if accessed directly
    header("Location: " . BASE_URL . "/views/login.php");
    include __DIR__ . '/../../views/login.php';

    exit();
}
