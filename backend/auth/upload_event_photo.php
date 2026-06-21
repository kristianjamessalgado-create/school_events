<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_photos.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'multimedia') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$base_dir = dirname(__DIR__, 2);

const MAX_FILE_SIZE = 5 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;

$redirect = static function (string $msg) use ($event_id, $session_id): void {
    eventify_multimedia_photo_redirect($event_id, $session_id, $msg);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $event_id <= 0) {
    $redirect('Invalid request.');
}
if (!csrf_validate()) {
    $redirect('Invalid request. Please try again.');
}

$stmt = $conn->prepare('SELECT id, title, department, status FROM events WHERE id = ?');
$stmt->bind_param('i', $event_id);
$stmt->execute();
$eventRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$eventRow) {
    $conn->close();
    $redirect('Event not found.');
}

if (!eventify_event_allows_multimedia_photo_upload($eventRow['status'] ?? '')) {
    $conn->close();
    $redirect('Photos cannot be uploaded while this event is pending, rejected, or cancelled.');
}

$ev_department = $eventRow['department'] ?? null;

if (!eventify_event_photos_ensure_session_column($conn)) {
    $conn->close();
    $redirect('Activity photos need a database update. Run migrations/event_photos_session_id.sql in phpMyAdmin, then try again.');
}

$hasSessionCol = eventify_event_photos_has_session_column($conn);

if ($session_id > 0) {
    if (!$hasSessionCol) {
        $conn->close();
        $redirect('Activity photos need a database update. Run migrations/event_photos_session_id.sql in phpMyAdmin, then try again.');
    }
    if (!eventify_validate_activity_for_event($conn, $session_id, $event_id)) {
        $conn->close();
        $redirect('Activity not found for this event.');
    }
}

$dept = is_string($ev_department) ? trim($ev_department) : '';
$deptFolder = ($dept === '' || strtoupper($dept) === 'ALL') ? 'all' : $dept;
$deptFolder = strtolower($deptFolder);
$deptFolder = preg_replace('/[^a-z0-9]+/', '_', $deptFolder);
$deptFolder = trim($deptFolder, '_');
if ($deptFolder === '') {
    $deptFolder = 'all';
}

$uploads_base = $base_dir . '/uploads/events/' . $deptFolder;
$relative_base = 'uploads/events/' . $deptFolder . '/';

$files = [];
$error = '';
if (!empty($_FILES['photos']['name'])) {
    $names = $_FILES['photos']['name'];
    $tmp = $_FILES['photos']['tmp_name'];
    $errors = $_FILES['photos']['error'];
    $sizes = $_FILES['photos']['size'];
    if (!is_array($names)) {
        $names = [$names];
        $tmp = [$tmp];
        $errors = [$errors];
        $sizes = [$sizes];
    }
    foreach ($names as $i => $name) {
        if (empty($name)) {
            continue;
        }
        if ($errors[$i] !== UPLOAD_ERR_OK) {
            if ($errors[$i] === UPLOAD_ERR_INI_SIZE || $errors[$i] === UPLOAD_ERR_FORM_SIZE) {
                $error = 'File too large. Max 5MB per image.';
            } elseif ($errors[$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            } else {
                $error = 'Upload failed for ' . htmlspecialchars($name) . '. Try again or pick a smaller file.';
            }
            break;
        }
        if ($sizes[$i] > MAX_FILE_SIZE) {
            $error = 'File too large: ' . htmlspecialchars($name) . ' (max 5MB).';
            break;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmp[$i]) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!in_array($mime, ALLOWED_TYPES, true)) {
            $error = 'Invalid file type: ' . htmlspecialchars($name) . ' (use JPG, PNG, GIF, or WebP).';
            break;
        }
        $files[] = ['name' => $name, 'tmp_name' => $tmp[$i]];
    }
} elseif (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = 'Upload too large for the server. Try fewer or smaller images.';
}

if ($error !== '') {
    $conn->close();
    $redirect($error);
}
if ($files === []) {
    $conn->close();
    $redirect('Please select at least one image.');
}
if (!is_dir($uploads_base) && !@mkdir($uploads_base, 0755, true)) {
    $conn->close();
    $redirect('Could not create upload folder.');
}

$photoStatusEnabled = eventify_event_photos_has_status($conn);

if ($hasSessionCol && $session_id > 0) {
    if ($photoStatusEnabled) {
        $insert = $conn->prepare(
            "INSERT INTO event_photos (event_id, session_id, uploaded_by, file_path, status, published_at) VALUES (?, ?, ?, ?, 'draft', NULL)"
        );
    } else {
        $insert = $conn->prepare(
            'INSERT INTO event_photos (event_id, session_id, uploaded_by, file_path) VALUES (?, ?, ?, ?)'
        );
    }
} elseif ($photoStatusEnabled) {
    $insert = $conn->prepare(
        "INSERT INTO event_photos (event_id, uploaded_by, file_path, status, published_at) VALUES (?, ?, ?, 'draft', NULL)"
    );
} else {
    $insert = $conn->prepare(
        'INSERT INTO event_photos (event_id, uploaded_by, file_path) VALUES (?, ?, ?)'
    );
}

if (!$insert) {
    $conn->close();
    $redirect('Database error: could not save photo record.');
}

$uploaded = 0;
$lastDbError = '';
foreach ($files as $f) {
    $ext = pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $f['name']);
    $filename = date('Ymd_His') . '_' . uniqid() . '_' . $safe_name;
    if (!pathinfo($filename, PATHINFO_EXTENSION)) {
        $filename .= '.' . (strtolower($ext) ?: 'jpg');
    }
    $dest = $uploads_base . '/' . $filename;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        $lastDbError = 'Could not save file on server.';
        continue;
    }
    $file_path = $relative_base . $filename;
    if ($hasSessionCol && $session_id > 0) {
        $insert->bind_param('iiis', $event_id, $session_id, $user_id, $file_path);
    } else {
        $insert->bind_param('iis', $event_id, $user_id, $file_path);
    }
    if ($insert->execute()) {
        $uploaded++;
    } else {
        $lastDbError = $insert->error ?: 'Database insert failed.';
        @unlink($dest);
    }
}

$insert->close();

if ($uploaded > 0) {
    $eventTitle = trim((string) ($eventRow['title'] ?? ''));
    $sessionTitle = '';
    if ($session_id > 0) {
        $sessStmt = $conn->prepare('SELECT title FROM event_day_sessions WHERE id = ? AND event_id = ? LIMIT 1');
        if ($sessStmt) {
            $sessStmt->bind_param('ii', $session_id, $event_id);
            $sessStmt->execute();
            $sessRow = $sessStmt->get_result()->fetch_assoc();
            $sessStmt->close();
            $sessionTitle = trim((string) ($sessRow['title'] ?? ''));
        }
    }
    $uploaderLabel = eventify_moderator_display_name($conn, $user_id);
    $details = $uploaderLabel . ' uploaded ' . $uploaded . ' photo(s)'
        . eventify_photo_activity_context_suffix($eventTitle, $sessionTitle)
        . ' — pending moderator review';
    eventify_log_photo_activity($conn, $user_id, 'multimedia', 'photo_uploaded', 'event', $event_id, $details);
}

$conn->close();

if ($uploaded > 0) {
    $msg = $session_id > 0
        ? $uploaded . ' photo(s) submitted. Your moderator will review them before they appear in the Activities hub.'
        : $uploaded . ' photo(s) submitted. Your moderator will review them before they are published.';
} else {
    $msg = $lastDbError !== '' ? $lastDbError : 'Upload failed. Please try again.';
}
$redirect($msg);
