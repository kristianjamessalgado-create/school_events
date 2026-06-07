<?php

session_start();



header('Content-Type: application/json; charset=utf-8');



require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../config/csrf.php';

require_once __DIR__ . '/../../config/departments.php';

require_once __DIR__ . '/../lib/event_day_sessions.php';

require_once __DIR__ . '/../lib/event_calendar.php';



if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);

    exit;

}



$role = $_SESSION['role'] ?? '';

$userId = (int) $_SESSION['user_id'];

$canEdit = $role === 'organizer';

$isStudent = $role === 'student';



eventify_event_day_sessions_ensure_enhanced($conn);



if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $eventId = (int) ($_GET['event_id'] ?? 0);

    $scheduleDate = trim((string) ($_GET['schedule_date'] ?? ''));

    if ($eventId < 1) {

        http_response_code(400);

        echo json_encode(['ok' => false, 'error' => 'Event id required.']);

        exit;

    }

    if ($canEdit && !eventify_organizer_owns_event($conn, $eventId, $userId)) {

        http_response_code(403);

        echo json_encode(['ok' => false, 'error' => 'Access denied.']);

        exit;

    }

    if (!$canEdit) {

        $stmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND status IN ('active','closed','completed') LIMIT 1");

        if ($stmt) {

            $stmt->bind_param('i', $eventId);

            $stmt->execute();

            $allowed = (bool) $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$allowed) {

                http_response_code(403);

                echo json_encode(['ok' => false, 'error' => 'Event not available.']);

                exit;

            }

        }

    }

    $viewerId = ($isStudent || $canEdit) ? $userId : null;

    $sessions = eventify_load_event_day_sessions($conn, $eventId, $scheduleDate !== '' ? $scheduleDate : null, $viewerId);

    echo json_encode(['ok' => true, 'sessions' => $sessions]);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);

    exit;

}



if (!csrf_validate()) {

    http_response_code(403);

    echo json_encode(['ok' => false, 'error' => 'Invalid request token.']);

    exit;

}



$action = strtolower(trim((string) ($_POST['action'] ?? '')));



if ($action === 'rsvp' && $isStudent) {

    $sessionId = (int) ($_POST['session_id'] ?? 0);

    $result = eventify_register_session_rsvp($conn, $sessionId, $userId);

    if (!empty($result['ok'])) {

        $stmt = $conn->prepare('SELECT event_id, schedule_date FROM event_day_sessions WHERE id = ? LIMIT 1');

        if ($stmt) {

            $stmt->bind_param('i', $sessionId);

            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($row) {

                $result['sessions'] = eventify_load_event_day_sessions(

                    $conn,

                    (int) $row['event_id'],

                    (string) ($row['schedule_date'] ?? ''),

                    $userId

                );

            }

        }

    }

    echo json_encode($result);

    exit;

}



if ($action === 'cancel_rsvp' && $isStudent) {

    $sessionId = (int) ($_POST['session_id'] ?? 0);

    $result = eventify_cancel_session_rsvp($conn, $sessionId, $userId);

    if (!empty($result['ok'])) {

        $stmt = $conn->prepare('SELECT event_id, schedule_date FROM event_day_sessions WHERE id = ? LIMIT 1');

        if ($stmt) {

            $stmt->bind_param('i', $sessionId);

            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if ($row) {

                $result['sessions'] = eventify_load_event_day_sessions(

                    $conn,

                    (int) $row['event_id'],

                    (string) ($row['schedule_date'] ?? ''),

                    $userId

                );

            }

        }

    }

    echo json_encode($result);

    exit;

}



if (!$canEdit) {

    http_response_code(403);

    echo json_encode(['ok' => false, 'error' => 'Access denied.']);

    exit;

}



$eventId = (int) ($_POST['event_id'] ?? 0);

$scheduleDate = trim((string) ($_POST['schedule_date'] ?? ''));



if ($eventId < 1 || !eventify_organizer_owns_event($conn, $eventId, $userId)) {

    http_response_code(403);

    echo json_encode(['ok' => false, 'error' => 'Access denied.']);

    exit;

}



if ($action === 'delete') {

    $sessionId = (int) ($_POST['session_id'] ?? 0);

    $result = eventify_delete_event_day_session($conn, $sessionId, $eventId);

    echo json_encode($result);

    exit;

}



if ($action === 'save') {

    $sessionId = (int) ($_POST['session_id'] ?? 0);

    $data = [

        'title' => $_POST['title'] ?? '',

        'category' => $_POST['category'] ?? '',

        'location' => $_POST['location'] ?? '',

        'latitude' => $_POST['latitude'] ?? '',

        'longitude' => $_POST['longitude'] ?? '',

        'notes' => $_POST['notes'] ?? '',

        'status' => $_POST['status'] ?? 'scheduled',

        'start_time' => $_POST['start_time'] ?? '',

        'end_time' => $_POST['end_time'] ?? '',

        'max_capacity' => $_POST['max_capacity'] ?? '',

        'contact_name' => $_POST['contact_name'] ?? '',

        'contact_phone' => $_POST['contact_phone'] ?? '',

        'sort_order' => (int) ($_POST['sort_order'] ?? 0),

    ];

    $result = eventify_save_event_day_session(
        $conn,
        $eventId,
        $scheduleDate,
        $data,
        $sessionId > 0 ? $sessionId : null
    );
    if (!empty($result['ok'])) {
        $result['sessions'] = eventify_load_event_day_sessions($conn, $eventId, $scheduleDate, $userId);
    }
    echo json_encode($result);
    exit;
}



http_response_code(400);

echo json_encode(['ok' => false, 'error' => 'Unknown action.']);

