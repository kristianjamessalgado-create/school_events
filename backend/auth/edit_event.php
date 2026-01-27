<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';

// Require organizer login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'organizer') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

$session_user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

// Get event ID
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($event_id <= 0) {
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Invalid event ID."));
    exit();
}

// Fetch event and ensure it belongs to this organizer
$stmt = $conn->prepare("SELECT id, title, description, date, location, department FROM events WHERE id = ? AND organizer_id = ?");
$stmt->bind_param("ii", $event_id, $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$event  = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Event not found or you do not have permission to edit it."));
    exit();
}

// Handle form submission (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date        = $_POST['date'] ?? '';
    $location    = trim($_POST['location'] ?? '');
    $department  = $_POST['department'] ?? 'ALL';

    if (empty($title)) {
        $error = "Title is required.";
    } elseif (empty($date)) {
        $error = "Date is required.";
    } elseif (empty($location)) {
        $error = "Location is required.";
    } elseif (strlen($title) > 150) {
        $error = "Title must be 150 characters or less.";
    } elseif (strlen($location) > 100) {
        $error = "Location must be 100 characters or less.";
    } else {
        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $error = "Invalid date format.";
        } else {
            // Check if date is in the past
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $eventDate = new DateTime($date);
            $eventDate->setTime(0, 0, 0);

            if ($eventDate < $today) {
                $error = "Event date cannot be in the past.";
            } else {
                // Validate department value
                $allowedDepartments = ['ALL', 'BSIT', 'BSHM', 'CONAHS', 'Senior High'];
                if (!in_array($department, $allowedDepartments, true)) {
                    $department = 'ALL';
                }

                $stmt = $conn->prepare("UPDATE events SET title = ?, description = ?, date = ?, location = ?, department = ? WHERE id = ? AND organizer_id = ?");
                $stmt->bind_param("sssssii", $title, $description, $date, $location, $department, $event_id, $session_user_id);

                if ($stmt->execute()) {
                    $stmt->close();
                    $success = "Event updated successfully!";
                    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode($success));
                    exit();
                } else {
                    $error = "Failed to update event. Please try again.";
                    $stmt->close();
                }
            }
        }
    }

    // If there was an error, keep form values from POST
    $event['title']       = $title;
    $event['description'] = $description;
    $event['date']        = $date;
    $event['location']    = $location;
    $event['department']  = $department;
}

// Fetch organizer name for display
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$stmt->bind_result($db_name);
$stmt->fetch();
$user_name = $db_name ?? 'Organizer';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - EVENTIFY</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .edit-event-container {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .edit-event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .edit-event-header h1 {
            font-size: 28px;
            font-weight: 500;
            margin: 0;
        }

        .edit-event-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .edit-event-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #3c4043;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label .required {
            color: #ea4335;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Google Sans', sans-serif;
            transition: all 0.2s ease;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder {
            color: #9aa0a6;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #5f6368;
            border: 1px solid #dadce0;
        }

        .btn-secondary:hover {
            background: #e8eaed;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: #fce8e6;
            color: #c5221f;
            border: 1px solid #f28b82;
        }

        .alert-success {
            background: #e6f4ea;
            color: #137333;
            border: 1px solid #81c995;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: #764ba2;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }

        @media (max-width: 768px) {
            .edit-event-body {
                padding: 24px;
            }

            .edit-event-header {
                padding: 24px;
            }

            .edit-event-header h1 {
                font-size: 24px;
            }

            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="edit-event-container">
        <div class="edit-event-header">
            <h1><i class="fas fa-edit"></i> Edit Event</h1>
            <p>Update the details of your event</p>
        </div>

        <div class="edit-event-body">
            <a href="<?= BASE_URL ?>/backend/auth/dashboardorganizer.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="editEventForm">
                <div class="form-group">
                    <label for="title">
                        Event Title <span class="required">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-heading"></i>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-control"
                            placeholder="Enter event title"
                            value="<?= htmlspecialchars($event['title'] ?? '') ?>"
                            required
                            maxlength="150"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">
                        Description
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        placeholder="Enter event description (optional)"
                        maxlength="1000"
                    ><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="date">
                        Event Date <span class="required">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input
                            type="date"
                            id="date"
                            name="date"
                            class="form-control"
                            value="<?= htmlspecialchars($event['date'] ?? '') ?>"
                            required
                            min="<?= date('Y-m-d') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="location">
                        Location <span class="required">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input
                            type="text"
                            id="location"
                            name="location"
                            class="form-control"
                            placeholder="Enter event location"
                            value="<?= htmlspecialchars($event['location'] ?? '') ?>"
                            required
                            maxlength="100"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="department">
                        Department / Audience <span class="required">*</span>
                    </label>
                    <?php $selectedDept = $event['department'] ?? 'ALL'; ?>
                    <select id="department" name="department" class="form-control" required>
                        <option value="ALL" <?= $selectedDept === 'ALL' ? 'selected' : '' ?>>All Departments</option>
                        <option value="BSIT" <?= $selectedDept === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                        <option value="BSHM" <?= $selectedDept === 'BSHM' ? 'selected' : '' ?>>BSHM</option>
                        <option value="CONAHS" <?= $selectedDept === 'CONAHS' ? 'selected' : '' ?>>CONAHS</option>
                        <option value="Senior High" <?= $selectedDept === 'Senior High' ? 'selected' : '' ?>>Senior High</option>
                    </select>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Update Event
                    </button>
                    <a href="<?= BASE_URL ?>/backend/auth/dashboardorganizer.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('editEventForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const date  = document.getElementById('date').value;
            const loc   = document.getElementById('location').value.trim();

            if (!title) {
                e.preventDefault();
                alert('Please enter an event title.');
                document.getElementById('title').focus();
                return false;
            }

            if (!date) {
                e.preventDefault();
                alert('Please select an event date.');
                document.getElementById('date').focus();
                return false;
            }

            if (!loc) {
                e.preventDefault();
                alert('Please enter an event location.');
                document.getElementById('location').focus();
                return false;
            }

            const today    = new Date();
            today.setHours(0, 0, 0, 0);
            const eventDate = new Date(date);

            if (eventDate < today) {
                e.preventDefault();
                alert('Event date cannot be in the past.');
                document.getElementById('date').focus();
                return false;
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>

