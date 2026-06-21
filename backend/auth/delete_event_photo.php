<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_photos.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'multimedia') {
    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));
    exit();
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['photo_id']) || empty($_POST['event_id'])) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Invalid delete request.'));
    exit();
}
if (!csrf_validate()) {
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Invalid request. Please try again.'));
    exit();
}

$photo_id = (int) $_POST['photo_id'];
$event_id = (int) $_POST['event_id'];
$session_id = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;

$stmt = $conn->prepare('SELECT file_path, uploaded_by, session_id FROM event_photos WHERE id = ? AND event_id = ?');
$stmt->bind_param('ii', $photo_id, $event_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $conn->close();
    eventify_multimedia_photo_redirect($event_id, $session_id, 'Photo not found.');
}

if ((int) ($row['uploaded_by'] ?? 0) !== $user_id) {
    $conn->close();
    eventify_multimedia_photo_redirect($event_id, $session_id, 'You can only delete your own photos.');
}

$redirectSessionId = $session_id > 0 ? $session_id : (int) ($row['session_id'] ?? 0);

$base_dir = dirname(__DIR__, 2);
$full_path = $base_dir . '/' . ($row['file_path'] ?? '');
if (is_file($full_path)) {
    @unlink($full_path);
}

$del = $conn->prepare('DELETE FROM event_photos WHERE id = ?');
$del->bind_param('i', $photo_id);
$del->execute();
$del->close();
$conn->close();

eventify_multimedia_photo_redirect($event_id, $redirectSessionId, 'Photo deleted.');
