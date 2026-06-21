<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php';
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/../lib/event_status_auto.php';
require_once __DIR__ . '/../lib/event_day_sessions.php';
require_once __DIR__ . '/../lib/multimedia_moderator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'multimedia') {
    header("Location: " . BASE_URL . "/views/login.php?error=" . urlencode("Access denied"));
    exit();
}

eventify_run_dashboard_maintenance($conn);
eventify_events_department_ensure_varchar($conn);

$hasMustChangePasswordColumn = false;
try {
    $cpCol = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
    $hasMustChangePasswordColumn = (bool)($cpCol && $cpCol->num_rows > 0);
} catch (Throwable $e) {
    $hasMustChangePasswordColumn = false;
}
if ($hasMustChangePasswordColumn) {
    $forceCp = $conn->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
    if ($forceCp) {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $forceCp->bind_param("i", $uid);
        $forceCp->execute();
        $cpRow = $forceCp->get_result()->fetch_assoc();
        $forceCp->close();
        if ((int)($cpRow['must_change_password'] ?? 0) === 1) {
            header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode(BASE_URL . "/backend/auth/dashboard_multimedia.php"));
            exit();
        }
    }
}

$session_user_id = (int) $_SESSION['user_id'];

// Fetch user info (including department and profile picture)
$stmt = $conn->prepare("SELECT id, user_id, name, department, profile_picture, is_multimedia_moderator FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

$user_name  = $user['name'] ?? 'Multimedia';
$user_department = $user['department'] ?? null;
$is_multimedia_moderator = eventify_user_is_multimedia_moderator($conn, $session_user_id);

// Feature flag: photo publishing workflow (status column on event_photos)
$photoStatusEnabled = false;
$chkCol = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'status'");
if ($chkCol && $chkCol->num_rows > 0) {
    $photoStatusEnabled = true;
}

// Fetch all events (newest date first)
$events = [];
$uid = (int) $session_user_id;
$deptWhere = empty($user_department) ? '1=1' : eventify_department_match_sql('e.department');
$stEv = null;

if ($photoStatusEnabled) {
    $sqlEv = "
        SELECT e.id, e.title, e.date, e.location, e.department,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.status = 'published') AS photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.uploaded_by = {$uid}) AS my_photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.uploaded_by = {$uid} AND p.status = 'draft') AS my_draft_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.status = 'draft') AS pending_draft_count
        FROM events e
        WHERE e.title NOT LIKE 'sample%'
          AND ({$deptWhere})
        ORDER BY e.date DESC, e.id DESC
    ";
} else {
    $sqlEv = "
        SELECT e.id, e.title, e.date, e.location, e.department,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id) AS photo_count,
               (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id AND p.uploaded_by = {$uid}) AS my_photo_count,
               0 AS my_draft_count,
               0 AS pending_draft_count
        FROM events e
        WHERE e.title NOT LIKE 'sample%'
          AND ({$deptWhere})
        ORDER BY e.date DESC, e.id DESC
    ";
}

if (empty($user_department)) {
    $res = $conn->query($sqlEv);
} else {
    $stEv = $conn->prepare($sqlEv);
    if ($stEv) {
        $stEv->bind_param('ss', $user_department, $user_department);
        $stEv->execute();
        $res = $stEv->get_result();
    } else {
        $res = false;
    }
}
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $events[] = $row;
    }
}
if ($stEv) {
    $stEv->close();
}

// Fetch upcoming events (department-aware) for modal
$upcomingEvents = [];
$today = date('Y-m-d');
$deptUpSql = eventify_department_match_sql('department');
if (!empty($user_department)) {
    $stmtUp = $conn->prepare("SELECT id, title, description, date, location, department FROM events WHERE status = 'active' AND date >= ? AND {$deptUpSql} AND title NOT LIKE 'sample%' ORDER BY date ASC, id ASC LIMIT 12");
    if ($stmtUp) {
        $stmtUp->bind_param('sss', $today, $user_department, $user_department);
        if ($stmtUp->execute()) {
            $resUp = $stmtUp->get_result();
            if ($resUp) {
                $upcomingEvents = $resUp->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmtUp->close();
    }
} else {
    $stmtUp = $conn->prepare("SELECT id, title, description, date, location, department FROM events WHERE status = 'active' AND date >= ? AND title NOT LIKE 'sample%' ORDER BY date ASC, id ASC LIMIT 12");
    if ($stmtUp) {
        $stmtUp->bind_param("s", $today);
        if ($stmtUp->execute()) {
            $resUp = $stmtUp->get_result();
            if ($resUp) $upcomingEvents = $resUp->fetch_all(MYSQLI_ASSOC);
        }
        $stmtUp->close();
    }
}

// Fetch photos per event so we can show thumbnails / gallery
$photosByEvent = [];
if (!empty($events)) {
    $ids = array_column($events, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = "SELECT id, event_id, file_path, uploaded_by FROM event_photos WHERE event_id IN ($placeholders) ORDER BY created_at DESC, id DESC";
    $stmtPhotos = $conn->prepare($sql);
    if ($stmtPhotos) {
        $stmtPhotos->bind_param($types, ...$ids);
        $stmtPhotos->execute();
        $resultPhotos = $stmtPhotos->get_result();
        while ($row = $resultPhotos->fetch_assoc()) {
            $eid = (int)$row['event_id'];
            if (!isset($photosByEvent[$eid])) {
                $photosByEvent[$eid] = [];
            }
            // Store all photos (id + file path) for this event
            $photosByEvent[$eid][] = [
                'id' => (int)$row['id'],
                'file_path' => $row['file_path'],
                'uploaded_by' => (int)($row['uploaded_by'] ?? 0),
            ];
        }
        $stmtPhotos->close();
    }
}

$msg = $_GET['msg'] ?? '';

$multimedia_notifications = [];
$multimedia_unread_count = 0;
try {
    $stmtN = $conn->prepare("SELECT id, type, title, message, event_id, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 40");
    if ($stmtN) {
        $stmtN->bind_param('i', $session_user_id);
        if ($stmtN->execute()) {
            $rn = $stmtN->get_result();
            if ($rn) {
                $multimedia_notifications = $rn->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmtN->close();
    }
    foreach ($multimedia_notifications as $n) {
        if (empty($n['read_at'])) {
            $multimedia_unread_count++;
        }
    }
} catch (Throwable $e) {
    $multimedia_notifications = [];
    $multimedia_unread_count = 0;
}

$multimedia_notif_dropdown = array_values(array_filter($multimedia_notifications, static function ($n) {
    return empty($n['read_at']);
}));

$activities_hub_events = [];
try {
    $activities_hub_events = eventify_load_activities_hub_picker_events(
        $conn,
        $session_user_id,
        'multimedia',
        $user_department
    );
} catch (Throwable $e) {
    $activities_hub_events = [];
}

$pending_photo_count = $is_multimedia_moderator ? eventify_count_pending_photos($conn) : 0;
$pending_photos_queue = $is_multimedia_moderator ? eventify_load_pending_photos_for_moderator($conn, 60) : [];
$multimedia_photo_activity_logs = $is_multimedia_moderator ? eventify_load_multimedia_photo_activity_logs($conn, 50) : [];

$conn->close();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/school_events');
}

include __DIR__ . '/../../views/dashboard_multimedia.php';
