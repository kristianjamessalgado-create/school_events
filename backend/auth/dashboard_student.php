<?php
session_start();

// Include DB, config, and CSRF
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../config/config.php'; // for BASE_URL
include __DIR__ . '/../../config/csrf.php';
include __DIR__ . '/../../config/departments.php';
require_once __DIR__ . '/../../config/student_profile_fields.php';
require_once __DIR__ . '/../lib/event_status_auto.php';
require_once __DIR__ . '/../lib/event_calendar.php';
require_once __DIR__ . '/../lib/event_ticketing.php';
require_once __DIR__ . '/../lib/event_evaluation.php';

eventify_ticketing_ensure_schema($conn);

// Only allow logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: " . BASE_URL . "/views/login.php?error=Access denied");
    exit();
}

eventify_run_dashboard_maintenance($conn);
eventify_events_department_ensure_varchar($conn);
eventify_users_ensure_student_profile_fields($conn);

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
            header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode(BASE_URL . "/backend/auth/dashboard_student.php"));
            exit();
        }
    }
}

// Logged-in user's ID
$session_user_id = $_SESSION['user_id'];

// Fetch user info (including department and profile picture)
$stmt = $conn->prepare("SELECT id, user_id, name, department, profile_picture, student_course, student_year_level, student_academic_year FROM users WHERE id = ?");
$stmt->bind_param("i", $session_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Safe defaults
$user_name  = $user['name'] ?? 'Student';
$department = $user['department'] ?? null;
$events     = [];
$msg        = $_GET['msg'] ?? '';
$error      = $_GET['error'] ?? '';

// Student settings (lazy migration + defaults for backward compatibility)
$studentSettings = [
    'event_reminders' => 1,
    'rsvp_updates' => 1,
    'announcement_notifications' => 1,
    'notif_channel_email' => 1,
    'default_calendar_view' => 'dayGridMonth',
    'show_calendar_legend' => 1,
    'auto_add_rsvp_calendar' => 1,
    'reminder_timing' => '1_day',
    'hide_past_rsvped' => 0,
    'share_profile_with_organizers' => 1,
    'allow_photo_tagging' => 1,
];
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS student_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            event_reminders TINYINT(1) NOT NULL DEFAULT 1,
            rsvp_updates TINYINT(1) NOT NULL DEFAULT 1,
            announcement_notifications TINYINT(1) NOT NULL DEFAULT 1,
            notif_channel_email TINYINT(1) NOT NULL DEFAULT 1,
            default_calendar_view VARCHAR(20) NOT NULL DEFAULT 'dayGridMonth',
            show_calendar_legend TINYINT(1) NOT NULL DEFAULT 1,
            auto_add_rsvp_calendar TINYINT(1) NOT NULL DEFAULT 1,
            reminder_timing VARCHAR(20) NOT NULL DEFAULT '1_day',
            hide_past_rsvped TINYINT(1) NOT NULL DEFAULT 0,
            share_profile_with_organizers TINYINT(1) NOT NULL DEFAULT 1,
            allow_photo_tagging TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_student_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $stmtSettings = $conn->prepare("
        SELECT event_reminders, rsvp_updates, announcement_notifications, notif_channel_email,
               default_calendar_view, show_calendar_legend, auto_add_rsvp_calendar, reminder_timing,
               hide_past_rsvped, share_profile_with_organizers, allow_photo_tagging
        FROM student_settings
        WHERE user_id = ?
        LIMIT 1
    ");
    if ($stmtSettings) {
        $stmtSettings->bind_param('i', $session_user_id);
        if ($stmtSettings->execute()) {
            $resSettings = $stmtSettings->get_result();
            if ($resSettings && ($settingsRow = $resSettings->fetch_assoc())) {
                $studentSettings = array_merge($studentSettings, $settingsRow);
            }
        }
        $stmtSettings->close();
    }
} catch (Throwable $e) {
    // Keep dashboard available when settings table is unavailable.
}

// Fetch events filtered by student's department (supports multi-audience JSON in events.department)
$deptSql = eventify_department_match_sql('department');
if ($department) {
    $stmt2 = $conn->prepare("SELECT * FROM events WHERE status IN ('active','completed','closed') AND {$deptSql} ORDER BY date ASC");
    if ($stmt2) {
        $stmt2->bind_param('ss', $department, $department);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($result2) {
            $events = $result2->fetch_all(MYSQLI_ASSOC);
        }
        $stmt2->close();
    }
} else {
    $stmt2 = $conn->prepare("SELECT * FROM events WHERE status IN ('active','completed','closed') ORDER BY date ASC");
    if ($stmt2 && $stmt2->execute()) {
        $result2 = $stmt2->get_result();
        if ($result2) {
            $events = $result2->fetch_all(MYSQLI_ASSOC);
        }
        $stmt2->close();
    }
}
eventify_events_attach_schedule_dates($conn, $events);

// Fetch this student's attendance records (events they checked into via QR)
$attendance_records = [];
$stmt_att = $conn->prepare("
    SELECT r.id, r.event_id, r.status, r.time_in, r.time_out,
           e.title AS event_title, e.date AS event_date, e.location AS event_location,
           e.status AS event_status
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    WHERE r.user_id = ? AND r.status = 'present' AND r.time_in IS NOT NULL
    ORDER BY r.time_in DESC
");
$stmt_att->bind_param("i", $session_user_id);
if ($stmt_att->execute()) {
    $res_att = $stmt_att->get_result();
    if ($res_att) {
        $attendance_records = $res_att->fetch_all(MYSQLI_ASSOC);
    }
    $stmt_att->close();
}

$attended_event_ids = [];
foreach ($attendance_records as $rec) {
    $eid = (int) ($rec['event_id'] ?? 0);
    if ($eid > 0) {
        $attended_event_ids[$eid] = true;
    }
}
$attended_event_ids = array_keys($attended_event_ids);

// Past attended events may be auto-marked completed/closed — merge them into the calendar list for feedback/history
if (!empty($attended_event_ids)) {
    $existing_event_ids = [];
    foreach ($events as $evRow) {
        $existing_event_ids[(int) ($evRow['id'] ?? 0)] = true;
    }
    $missingForCalendar = [];
    foreach ($attended_event_ids as $eid) {
        if ($eid > 0 && empty($existing_event_ids[$eid])) {
            $missingForCalendar[] = $eid;
        }
    }
    if (!empty($missingForCalendar)) {
        $placeholders = implode(',', array_fill(0, count($missingForCalendar), '?'));
        $types = str_repeat('i', count($missingForCalendar));
        $params = $missingForCalendar;
        $deptFrag = eventify_department_match_sql('department');
        if ($department) {
            $sqlEx = "SELECT * FROM events WHERE id IN ($placeholders) AND status IN ('completed','closed') AND {$deptFrag} ORDER BY date DESC";
            $types .= 'ss';
            $params[] = $department;
            $params[] = $department;
        } else {
            $sqlEx = "SELECT * FROM events WHERE id IN ($placeholders) AND status IN ('completed','closed') ORDER BY date DESC";
        }
        $stEx = $conn->prepare($sqlEx);
        if ($stEx) {
            $stEx->bind_param($types, ...$params);
            if ($stEx->execute()) {
                $resEx = $stEx->get_result();
                if ($resEx) {
                    while ($rowEx = $resEx->fetch_assoc()) {
                        $events[] = $rowEx;
                    }
                }
            }
            $stEx->close();
        }
    }
}

// Students should not see rejected events on the calendar (defensive filter after merges)
$events = array_values(array_filter($events, static function ($row) {
    return strtolower(trim((string) ($row['status'] ?? ''))) !== 'rejected';
}));

// RSVP: which events this student is registered for
$registered_event_ids = [];
$stmtReg = $conn->prepare("SELECT event_id FROM registrations WHERE user_id = ?");
$stmtReg->bind_param("i", $session_user_id);
if ($stmtReg->execute()) {
    $rr = $stmtReg->get_result();
    if ($rr) {
        while ($row = $rr->fetch_assoc()) {
            $registered_event_ids[] = (int) $row['event_id'];
        }
    }
    $stmtReg->close();
}

// RSVP counts per event (for capacity display)
$reg_count_by_event = [];
$rc = $conn->query("SELECT event_id, COUNT(*) AS cnt FROM registrations GROUP BY event_id");
if ($rc) {
    while ($row = $rc->fetch_assoc()) {
        $reg_count_by_event[(int) $row['event_id']] = (int) $row['cnt'];
    }
}

// Feedback already submitted (event_feedback table may not exist yet)
$feedback_submitted_ids = [];
$feedback_lookup_ok = true;
try {
    $stmtFb = $conn->prepare("SELECT event_id FROM event_feedback WHERE user_id = ?");
    if ($stmtFb) {
        $stmtFb->bind_param("i", $session_user_id);
        if ($stmtFb->execute()) {
            $rf = $stmtFb->get_result();
            if ($rf) {
                while ($row = $rf->fetch_assoc()) {
                    $feedback_submitted_ids[] = (int) $row['event_id'];
                }
            }
        }
        $stmtFb->close();
    }
} catch (Throwable $e) {
    $feedback_submitted_ids = [];
    $feedback_lookup_ok = false;
}

// Events where the student checked in, the event is finished (by date or status), and feedback not submitted — used for urgent dashboard prompt
$pending_urgent_feedback_events = [];
$today_feedback = date('Y-m-d');
$seen_urgent_fb = [];
$feedback_ack_session = $_SESSION['eventify_feedback_ack'] ?? [];
if (!is_array($feedback_ack_session)) {
    $feedback_ack_session = [];
}
if ($feedback_lookup_ok) {
foreach ($attendance_records as $rec) {
    $eid = (int) ($rec['event_id'] ?? 0);
    if ($eid < 1 || !empty($seen_urgent_fb[$eid])) {
        continue;
    }
    if (in_array($eid, $feedback_submitted_ids, true)) {
        continue;
    }
    if (in_array($eid, $feedback_ack_session, true)) {
        continue;
    }
    $evDate = trim((string) ($rec['event_date'] ?? ''));
    $st = strtolower((string) ($rec['event_status'] ?? ''));
    $endedForFeedback = ($evDate !== '' && $evDate < $today_feedback) || $st === 'closed' || $st === 'completed';
    if (!$endedForFeedback) {
        continue;
    }
    $seen_urgent_fb[$eid] = true;
    $pending_urgent_feedback_events[] = [
        'id'     => $eid,
        'title'  => (string) ($rec['event_title'] ?? 'Event'),
        'date'   => $evDate,
        'status' => (string) ($rec['event_status'] ?? ''),
    ];
}
}

require_once __DIR__ . '/../lib/event_day_sessions.php';

// In-app notifications
$student_notifications = [];
$unread_notif_count = 0;
try {
    $stmtN = $conn->prepare("SELECT id, type, title, message, event_id, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 40");
    if ($stmtN) {
        $stmtN->bind_param("i", $session_user_id);
        if ($stmtN->execute()) {
            $rn = $stmtN->get_result();
            if ($rn) {
                $student_notifications = $rn->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmtN->close();
    }
    foreach ($student_notifications as $n) {
        if (empty($n['read_at'])) {
            $unread_notif_count++;
        }
    }
} catch (Throwable $e) {
    $student_notifications = [];
    $unread_notif_count = 0;
}

$today_activities = [];
try {
    eventify_event_day_sessions_ensure_enhanced($conn);
    $today_activities = eventify_load_student_today_activities(
        $conn,
        (int) $session_user_id,
        $department,
        date('Y-m-d')
    );
} catch (Throwable $e) {
    $today_activities = [];
}

$upcoming_events = array_values(array_filter($events, static function ($row) {
    return eventify_event_is_upcoming($row);
}));

$my_registered_events = array_values(array_filter($events, static function ($row) use ($registered_event_ids) {
    $eid = (int) ($row['id'] ?? 0);
    return $eid > 0 && in_array($eid, $registered_event_ids, true) && eventify_event_is_upcoming($row);
}));

$activity_attendance_records = [];
try {
    eventify_event_day_sessions_ensure_enhanced($conn);
    $stmtActAtt = $conn->prepare(
        'SELECT a.checked_in_at, s.id AS session_id, s.title AS activity_title, s.schedule_date, s.start_time, s.end_time,
                e.id AS event_id, e.title AS event_title
         FROM event_day_session_attendance a
         INNER JOIN event_day_sessions s ON s.id = a.session_id
         INNER JOIN events e ON e.id = s.event_id
         WHERE a.user_id = ?
         ORDER BY a.checked_in_at DESC
         LIMIT 30'
    );
    if ($stmtActAtt) {
        $stmtActAtt->bind_param('i', $session_user_id);
        if ($stmtActAtt->execute()) {
            $resAct = $stmtActAtt->get_result();
            if ($resAct) {
                $activity_attendance_records = $resAct->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmtActAtt->close();
    }
} catch (Throwable $e) {
    $activity_attendance_records = [];
}

$activities_hub_events = [];
try {
    $activities_hub_events = eventify_load_activities_hub_picker_events(
        $conn,
        (int) $session_user_id,
        'student',
        $department
    );
} catch (Throwable $e) {
    $activities_hub_events = [];
}

$event_evaluation_sections = eventify_evaluation_sections();

// Include the view
include __DIR__ . '/../../views/dashboard_student.php';
