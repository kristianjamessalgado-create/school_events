<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../lib/event_status_auto.php';
include __DIR__ . '/../lib/activity_logger.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'organizer') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate()) {
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Invalid request."));
    exit();
}

$event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$action = $_POST['action'] ?? '';
$organizer_id = (int) $_SESSION['user_id'];

if ($event_id < 1 || !in_array($action, ['close', 'cancel'], true)) {
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Invalid event action."));
    exit();
}

$stmt = $conn->prepare("SELECT id, title, status, `date` AS event_date FROM events WHERE id = ? AND organizer_id = ? LIMIT 1");
$stmt->bind_param("ii", $event_id, $organizer_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Event not found."));
    exit();
}

$current = strtolower((string) ($event['status'] ?? ''));
if (!in_array($current, ['active', 'pending'], true)) {
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Only pending or active events can be closed."));
    exit();
}

if ($current === 'pending' && $action === 'close') {
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("For pending events, use Withdraw instead."));
    exit();
}

if ($current === 'active' && $action === 'cancel') {
    $conn->close();
    header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Use Mark as ended to finish an active event."));
    exit();
}

$newStatus = eventify_events_completed_or_closed_target($conn);
$up = $conn->prepare("UPDATE events SET status = ?, reject_reason = NULL WHERE id = ? AND organizer_id = ?");
$up->bind_param("sii", $newStatus, $event_id, $organizer_id);
$ok = $up->execute();
$up->close();

if ($ok) {
    $title = (string) ($event['title'] ?? 'Event');
    $verb = $action === 'cancel' ? 'cancelled' : 'closed';
    log_activity($conn, $organizer_id, 'organizer', 'event_' . $verb . '_by_organizer', 'event', $event_id, ucfirst($verb) . ' event: ' . $title);

    // Notify registered students (if notifications table exists)
    try {
        $rStmt = $conn->prepare("SELECT DISTINCT user_id FROM registrations WHERE event_id = ?");
        if ($rStmt) {
            $rStmt->bind_param("i", $event_id);
            $rStmt->execute();
            $res = $rStmt->get_result();
            $studentIds = [];
            while ($row = $res->fetch_assoc()) {
                $studentIds[] = (int) $row['user_id'];
            }
            $rStmt->close();

            if (!empty($studentIds)) {
                $notifTitle = 'Event update';
                $notifMsg = 'The event "' . $title . '" has been ' . $verb . ' by the organizer.';
                $ins = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'event_update', ?, ?, ?)");
                if ($ins) {
                    foreach ($studentIds as $sid) {
                        $ins->bind_param("issi", $sid, $notifTitle, $notifMsg, $event_id);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }
        }
    } catch (Throwable $e) {
        // keep status update successful even if notifications table isn't available
    }

    $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));
    $defaultRedirect = BASE_URL . '/backend/auth/dashboardorganizer.php';
    if ($redirectTo === '' || strpos($redirectTo, BASE_URL) !== 0) {
        $redirectTo = $defaultRedirect;
    }
    $conn->close();
    $doneMsg = $action === 'cancel'
        ? 'Submission withdrawn successfully.'
        : 'Event marked as ended successfully.';
    $sep = strpos($redirectTo, '?') !== false ? '&' : '?';
    header('Location: ' . $redirectTo . $sep . 'msg=' . urlencode($doneMsg));
    exit();
}

$conn->close();
header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php?msg=" . urlencode("Failed to update event status."));
exit();
