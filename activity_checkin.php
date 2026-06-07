<?php
session_start();
if (!defined('BASE_URL')) {
    define('BASE_URL', '/school_events');
}
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

$token = trim($_GET['st'] ?? '');
$confirmed = false;
$already_done = false;
$error = '';
$session = null;
$geo_required = false;
$geo_radius_m = eventify_checkin_geo_radius_m();
$needs_rsvp_first = false;
$needs_session_rsvp_first = false;
$focus_confirm_mobile = false;

if ($token === '') {
    $error = 'Invalid or missing activity check-in link.';
} else {
    eventify_event_day_sessions_ensure_enhanced($conn);
    $session = eventify_load_session_by_checkin_token($conn, $token);
    if (!$session) {
        $error = 'This activity check-in link is invalid or has expired.';
    } elseif (($session['event_status'] ?? '') !== 'active') {
        $st = strtolower((string) ($session['event_status'] ?? ''));
        $error = in_array($st, ['closed', 'completed'], true)
            ? 'This event has ended. Activity check-in is no longer available.'
            : 'Check-in is only available for approved, active events.';
    } elseif (($session['status'] ?? '') === 'cancelled') {
        $error = 'This activity has been cancelled.';
    } elseif (!eventify_session_allows_checkin($session)) {
        $error = 'Check-in is not available. This activity day has ended or has not started yet.';
    } else {
        $geo_required = eventify_session_checkin_geo_required($session);
    }
}

if (!$error && $session && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/activity_checkin.php?st=' . urlencode($token);
        header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($returnUrl));
        exit();
    }
    if (($_SESSION['role'] ?? '') !== 'student') {
        $error = 'Only students can confirm activity attendance.';
        $session = null;
    } else {
        $uid = (int) $_SESSION['user_id'];
        $sid = (int) ($session['id'] ?? 0);
        $eventId = (int) ($session['event_id'] ?? 0);
        $chk = $conn->prepare('SELECT 1 FROM event_day_session_attendance WHERE session_id = ? AND user_id = ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('ii', $sid, $uid);
            $chk->execute();
            $chk->store_result();
            $already_done = $chk->num_rows > 0;
            $chk->close();
        }
        if (!$already_done && $eventId > 0 && !eventify_student_has_event_registration($conn, $uid, $eventId)) {
            $needs_rsvp_first = true;
        }
        if (!$already_done && !$needs_rsvp_first && eventify_activity_checkin_require_session_rsvp()
            && !eventify_student_has_session_rsvp($conn, $uid, $sid)) {
            $needs_session_rsvp_first = true;
        }
        $focus_confirm_mobile = !$already_done && !$needs_rsvp_first && !$needs_session_rsvp_first;
    }
}

if (!$error && $session && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    if (!csrf_validate()) {
        $error = 'Invalid request. Please try again.';
    } elseif (!eventify_session_allows_checkin($session)) {
        $error = 'Check-in is not available. This activity day has ended or has not started yet.';
    } else {
        $user_id = (int) $_SESSION['user_id'];
        $event_id = (int) ($session['event_id'] ?? 0);
        $result = eventify_process_activity_checkin($conn, $session, $user_id, $_POST);
        if ($result['ok']) {
            $confirmed = true;
            $already_done = true;
            try {
                $orgId = (int) ($session['organizer_id'] ?? 0);
                if ($orgId > 0) {
                    $studentName = (string) ($_SESSION['name'] ?? 'A student');
                    $actTitle = (string) ($session['title'] ?? 'activity');
                    $msg = $studentName . ' checked in for "' . $actTitle . '".';
                    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'activity_attendance', 'Activity check-in', ?, ?)");
                    if ($n) {
                        $n->bind_param('isi', $orgId, $msg, $event_id);
                        $n->execute();
                        $n->close();
                    }
                }
            } catch (Throwable $e) {
                /* ignore */
            }
        } else {
            $error = $result['error'] ?? 'Could not record check-in.';
        }
    }
}

$timeStr = $session ? eventify_format_session_time_range($session['start_time'] ?? null, $session['end_time'] ?? null) : '';
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Check-in | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f9f4; padding: 1rem; }
        .checkin-card { max-width: 420px; width: 100%; border-radius: 16px; box-shadow: 0 10px 30px rgba(6, 78, 59, 0.12); overflow: hidden; }
        .checkin-header { background: linear-gradient(120deg, #064e3b, #047857); color: #fff; padding: 1.25rem; text-align: center; }
        .checkin-body { padding: 1.5rem; background: #fff; }
    </style>
</head>
<body>
    <div class="checkin-card card border-0">
        <div class="checkin-header">
            <h1 class="h5 mb-0"><i class="fas fa-clipboard-check me-2"></i>Activity Check-in</h1>
        </div>
        <div class="checkin-body">
            <?php if ($error): ?>
                <div class="alert alert-danger mb-0"><?= htmlspecialchars($error) ?></div>
                <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-outline-primary btn-sm mt-3">Dashboard</a>
            <?php elseif ($confirmed || $already_done): ?>
                <div class="text-center">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5><?= $confirmed ? 'Check-in confirmed!' : 'Already checked in' ?></h5>
                    <?php if ($session): ?>
                        <p class="text-muted mb-0"><?= htmlspecialchars($session['title']) ?></p>
                        <?php if (!empty($session['event_title'])): ?>
                            <p class="small text-muted"><?= htmlspecialchars($session['event_title']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-primary btn-sm mt-3">Go to dashboard</a>
                </div>
            <?php elseif ($session): ?>
                <h5 class="mb-1"><?= htmlspecialchars($session['title']) ?></h5>
                <p class="text-muted small mb-2"><?= htmlspecialchars($session['event_title'] ?? '') ?></p>
                <?php if ($timeStr !== ''): ?>
                    <p class="small mb-1"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($timeStr) ?></p>
                <?php endif; ?>
                <?php if (!empty($session['location'])): ?>
                    <p class="small mb-3"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($session['location']) ?></p>
                <?php endif; ?>
                <?php if ($needs_rsvp_first): ?>
                    <div class="alert alert-warning small mb-3">
                        RSVP for the main event on your dashboard first, then scan this activity QR again.
                    </div>
                    <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-primary w-100">Go to dashboard</a>
                <?php elseif ($needs_session_rsvp_first): ?>
                    <div class="alert alert-warning small mb-3">
                        RSVP for this activity in the <strong>Activities hub</strong> first, then scan this QR again to check in.
                    </div>
                    <a href="<?= BASE_URL ?>/event_activities.php?event_id=<?= (int) ($session['event_id'] ?? 0) ?>" class="btn btn-success w-100 mb-2">Open Activities hub</a>
                    <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-outline-primary w-100">Dashboard</a>
                <?php else: ?>
                    <p class="small text-muted mb-3">One device per student for this activity<?= $geo_required ? '; GPS must be within ' . (int) $geo_radius_m . 'm of the venue' : '' ?>.</p>
                    <form method="POST" id="checkinForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="confirm" value="1">
                        <input type="hidden" name="geo_lat" id="geo_lat" value="">
                        <input type="hidden" name="geo_lng" id="geo_lng" value="">
                        <input type="hidden" name="geo_accuracy" id="geo_accuracy" value="">
                        <input type="hidden" name="geo_ts" id="geo_ts" value="">
                        <input type="hidden" name="device_hash" id="device_hash" value="">
                        <button type="submit" class="btn btn-success w-100" id="confirmBtn" disabled>
                            <i class="fas fa-check me-1"></i>Confirm check-in
                        </button>
                    </form>
                    <?php include __DIR__ . '/views/partials/checkin_security_script.php'; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
