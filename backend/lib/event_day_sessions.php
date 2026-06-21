<?php

/**
 * Day activities (sub-events) within a parent event — e.g. badminton @ Gym A on Jun 3.
 */

require_once __DIR__ . '/../../config/departments.php';

function eventify_event_day_sessions_table_exists(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_day_sessions'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_event_day_sessions_ensure_table(mysqli $conn): bool
{
    if (eventify_event_day_sessions_table_exists($conn)) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_event_day_sessions.sql');
    if ($sql === false || trim($sql) === '') {
        return false;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            @$conn->query($stmt);
        }
    }
    return eventify_event_day_sessions_table_exists($conn);
}

function eventify_day_sessions_have_geo_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return false;
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_sessions WHERE Field = 'latitude'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_day_sessions_have_enhanced_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    if (!eventify_event_day_sessions_table_exists($conn)) {
        return false;
    }
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_day_sessions WHERE Field = 'category'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_event_day_sessions_ensure_enhanced(mysqli $conn): bool
{
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return false;
    }
    if (eventify_day_sessions_have_enhanced_columns($conn)) {
        eventify_event_day_sessions_ensure_rsvp_tables($conn);
        eventify_event_day_sessions_ensure_access_columns($conn);
        return true;
    }
    $alters = [
        "ALTER TABLE event_day_sessions ADD COLUMN category varchar(80) DEFAULT NULL AFTER title",
        "ALTER TABLE event_day_sessions ADD COLUMN notes text DEFAULT NULL AFTER location",
        "ALTER TABLE event_day_sessions ADD COLUMN status enum('scheduled','cancelled','delayed') NOT NULL DEFAULT 'scheduled' AFTER notes",
        "ALTER TABLE event_day_sessions ADD COLUMN max_capacity int(11) DEFAULT NULL AFTER end_time",
        "ALTER TABLE event_day_sessions ADD COLUMN contact_name varchar(100) DEFAULT NULL AFTER max_capacity",
        "ALTER TABLE event_day_sessions ADD COLUMN contact_phone varchar(50) DEFAULT NULL AFTER contact_name",
        "ALTER TABLE event_day_sessions ADD COLUMN checkin_token varchar(64) DEFAULT NULL AFTER contact_phone",
    ];
    foreach ($alters as $sql) {
        try {
            @$conn->query($sql);
        } catch (Throwable $e) {
            /* column may already exist */
        }
    }
    eventify_event_day_sessions_ensure_rsvp_tables($conn);
    eventify_event_day_sessions_ensure_access_columns($conn);
    return eventify_day_sessions_have_enhanced_columns($conn);
}

function eventify_day_sessions_have_access_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW COLUMNS FROM event_day_sessions LIKE 'access_mode'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function eventify_event_day_sessions_ensure_access_columns(mysqli $conn): bool
{
    if (eventify_day_sessions_have_access_columns($conn)) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_session_access_mode.sql');
    if ($sql !== false && trim($sql) !== '') {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') {
                try {
                    @$conn->query($stmt);
                } catch (Throwable $e) {
                    /* ignore */
                }
            }
        }
    }
    try {
        @$conn->query("ALTER TABLE event_day_sessions ADD COLUMN access_mode VARCHAR(20) NOT NULL DEFAULT 'free' AFTER status");
        @$conn->query("ALTER TABLE event_day_sessions ADD COLUMN ticket_type_id INT NULL DEFAULT NULL AFTER access_mode");
    } catch (Throwable $e) {
        /* ignore */
    }
    return eventify_day_sessions_have_access_columns($conn);
}

function eventify_event_day_sessions_ensure_rsvp_tables(mysqli $conn): bool
{
    static $done = false;
    if ($done) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_event_day_sessions_phase2.sql');
    if ($sql === false || trim($sql) === '') {
        return false;
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            try {
                @$conn->query($stmt);
            } catch (Throwable $e) {
                /* ignore */
            }
        }
    }
    $done = true;
    return true;
}

function eventify_session_rsvps_table_exists(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_day_session_rsvps'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/** @return list<string> */
function eventify_day_session_category_options(): array
{
    return ['Sports', 'Ceremony', 'Workshop', 'Competition', 'Social', 'Other'];
}

/** @return list<string> */
function eventify_day_session_status_options(): array
{
    return ['scheduled', 'cancelled', 'delayed'];
}

function eventify_day_sessions_select_columns(mysqli $conn): string
{
    $cols = 'id, event_id, schedule_date, title, location, start_time, end_time, sort_order';
    if (eventify_day_sessions_have_geo_columns($conn)) {
        $cols = 'id, event_id, schedule_date, title, location, latitude, longitude, start_time, end_time, sort_order';
    }
    if (eventify_day_sessions_have_enhanced_columns($conn)) {
        $base = 'id, event_id, schedule_date, title, category, location, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, checkin_token, sort_order';
        if (eventify_day_sessions_have_geo_columns($conn)) {
            $base = 'id, event_id, schedule_date, title, category, location, latitude, longitude, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, checkin_token, sort_order';
        }
        if (eventify_day_sessions_have_access_columns($conn)) {
            $base = str_replace(', status, start_time,', ', status, access_mode, ticket_type_id, start_time,', $base);
        }
        return $base;
    }
    return $cols;
}

function eventify_ensure_session_checkin_token(mysqli $conn, int $sessionId): ?string
{
    if ($sessionId < 1 || !eventify_day_sessions_have_enhanced_columns($conn)) {
        return null;
    }
    $stmt = $conn->prepare('SELECT checkin_token FROM event_day_sessions WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $token = trim((string) ($row['checkin_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }
    $token = bin2hex(random_bytes(16));
    $up = $conn->prepare('UPDATE event_day_sessions SET checkin_token = ? WHERE id = ?');
    if (!$up) {
        return null;
    }
    $up->bind_param('si', $token, $sessionId);
    $up->execute();
    $up->close();
    return $token;
}

/** @return array<string, mixed> */
function eventify_map_day_session_row(array $row): array
{
    $lat = isset($row['latitude']) && $row['latitude'] !== '' && $row['latitude'] !== null
        ? (float) $row['latitude'] : null;
    $lng = isset($row['longitude']) && $row['longitude'] !== '' && $row['longitude'] !== null
        ? (float) $row['longitude'] : null;
    $maxCap = isset($row['max_capacity']) && $row['max_capacity'] !== '' && $row['max_capacity'] !== null
        ? (int) $row['max_capacity'] : null;

    return [
        'id' => (int) ($row['id'] ?? 0),
        'event_id' => (int) ($row['event_id'] ?? 0),
        'schedule_date' => substr(trim((string) ($row['schedule_date'] ?? '')), 0, 10),
        'title' => trim((string) ($row['title'] ?? '')),
        'category' => trim((string) ($row['category'] ?? '')) ?: null,
        'location' => trim((string) ($row['location'] ?? '')),
        'latitude' => $lat,
        'longitude' => $lng,
        'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
        'status' => trim((string) ($row['status'] ?? 'scheduled')) ?: 'scheduled',
        'start_time' => trim((string) ($row['start_time'] ?? '')) ?: null,
        'end_time' => trim((string) ($row['end_time'] ?? '')) ?: null,
        'max_capacity' => $maxCap !== null && $maxCap > 0 ? $maxCap : null,
        'contact_name' => trim((string) ($row['contact_name'] ?? '')) ?: null,
        'contact_phone' => trim((string) ($row['contact_phone'] ?? '')) ?: null,
        'checkin_token' => trim((string) ($row['checkin_token'] ?? '')) ?: null,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'access_mode' => eventify_session_access_mode($row),
        'ticket_type_id' => isset($row['ticket_type_id']) && $row['ticket_type_id'] !== '' && $row['ticket_type_id'] !== null
            ? (int) $row['ticket_type_id'] : null,
        'rsvp_count' => isset($row['rsvp_count']) ? (int) $row['rsvp_count'] : null,
        'user_rsvped' => isset($row['user_rsvped']) ? (bool) $row['user_rsvped'] : null,
        'user_checked_in' => isset($row['user_checked_in']) ? (bool) $row['user_checked_in'] : null,
        'checked_in_at' => $row['checked_in_at'] ?? null,
    ];
}

/** @param array<string, mixed> $session */
function eventify_session_access_mode(array $session): string
{
    $mode = strtolower(trim((string) ($session['access_mode'] ?? 'free')));
    return in_array($mode, ['free', 'ticket_required'], true) ? $mode : 'free';
}

/** @param array<string, mixed> $session */
function eventify_session_requires_ticket(array $session): bool
{
    return eventify_session_access_mode($session) === 'ticket_required';
}

/** @param array<string, mixed> $session */
function eventify_student_has_session_ticket(mysqli $conn, int $userId, array $session): bool
{
    if ($userId < 1 || !eventify_session_requires_ticket($session)) {
        return true;
    }
    require_once __DIR__ . '/event_ticketing.php';
    if (!eventify_ticketing_ensure_schema($conn)) {
        return false;
    }
    $eventId = (int) ($session['event_id'] ?? 0);
    $typeId = (int) ($session['ticket_type_id'] ?? 0);
    if ($eventId < 1) {
        return false;
    }
    if ($typeId > 0) {
        $stmt = $conn->prepare(
            "SELECT id FROM event_tickets WHERE user_id = ? AND event_id = ? AND ticket_type_id = ? AND status = 'valid' LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $userId, $eventId, $typeId);
    } else {
        $stmt = $conn->prepare(
            "SELECT id FROM event_tickets WHERE user_id = ? AND event_id = ? AND status = 'valid' LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $userId, $eventId);
    }
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_ticket_meta(mysqli $conn, array &$sessions, ?int $viewerUserId): void
{
    foreach ($sessions as &$s) {
        $s['requires_ticket'] = eventify_session_requires_ticket($s);
        $s['user_has_activity_ticket'] = ($viewerUserId !== null && $viewerUserId > 0)
            ? eventify_student_has_session_ticket($conn, $viewerUserId, $s)
            : false;
    }
    unset($s);
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_save_session_access_meta(
    mysqli $conn,
    int $sessionId,
    int $eventId,
    string $accessMode,
    ?int $ticketTypeId
): array {
    if ($sessionId < 1 || !eventify_event_day_sessions_ensure_access_columns($conn)) {
        return ['ok' => true];
    }
    $accessMode = strtolower(trim($accessMode));
    if (!in_array($accessMode, ['free', 'ticket_required'], true)) {
        $accessMode = 'free';
    }
    if ($accessMode === 'ticket_required') {
        if ($ticketTypeId === null || $ticketTypeId < 1) {
            return ['ok' => false, 'error' => 'Select a ticket type for paid activities.'];
        }
        require_once __DIR__ . '/event_ticketing.php';
        $type = eventify_load_ticket_type_for_event($conn, $ticketTypeId, $eventId);
        if (!$type) {
            return ['ok' => false, 'error' => 'Invalid ticket type for this event.'];
        }
    } else {
        $ticketTypeId = null;
    }
    if ($ticketTypeId === null || $ticketTypeId < 1) {
        $stmt = $conn->prepare('UPDATE event_day_sessions SET access_mode = ?, ticket_type_id = NULL WHERE id = ? AND event_id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity access settings.'];
        }
        $stmt->bind_param('sii', $accessMode, $sessionId, $eventId);
    } else {
        $stmt = $conn->prepare('UPDATE event_day_sessions SET access_mode = ?, ticket_type_id = ? WHERE id = ? AND event_id = ?');
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity access settings.'];
        }
        $stmt->bind_param('siii', $accessMode, $ticketTypeId, $sessionId, $eventId);
    }
    $stmt->execute();
    $stmt->close();
    return ['ok' => true];
}

/**
 * @return list<array<string, mixed>>
 */
function eventify_load_event_day_sessions(mysqli $conn, int $eventId, ?string $scheduleDate = null, ?int $viewerUserId = null): array
{
    if ($eventId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    $cols = eventify_day_sessions_select_columns($conn);
    if ($scheduleDate !== null && $scheduleDate !== '') {
        $scheduleDate = substr(trim($scheduleDate), 0, 10);
        $stmt = $conn->prepare(
            "SELECT {$cols} FROM event_day_sessions WHERE event_id = ? AND schedule_date = ?
             ORDER BY sort_order ASC, start_time ASC, id ASC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('is', $eventId, $scheduleDate);
    } else {
        $stmt = $conn->prepare(
            "SELECT {$cols} FROM event_day_sessions WHERE event_id = ?
             ORDER BY schedule_date ASC, sort_order ASC, start_time ASC, id ASC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $eventId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = eventify_map_day_session_row($row);
    }
    $stmt->close();
    if ($out !== [] && eventify_session_rsvps_table_exists($conn)) {
        eventify_attach_session_rsvp_meta($conn, $out, $viewerUserId);
    }
    if ($out !== [] && $viewerUserId !== null && $viewerUserId > 0) {
        eventify_attach_session_checkin_meta($conn, $out, $viewerUserId);
    }
    if ($out !== []) {
        eventify_attach_session_student_flags($out);
        eventify_attach_session_ticket_meta($conn, $out, $viewerUserId);
    }
    return $out;
}

/** @param list<int> $eventIds @return array<int, list<array<string, mixed>>> */
function eventify_load_day_sessions_map(mysqli $conn, array $eventIds): array
{
    $map = [];
    $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static function ($id) {
        return $id > 0;
    })));
    if ($eventIds === [] || !eventify_event_day_sessions_ensure_table($conn)) {
        return $map;
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    $cols = eventify_day_sessions_select_columns($conn);
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $types = str_repeat('i', count($eventIds));
    $sql = "SELECT {$cols} FROM event_day_sessions WHERE event_id IN ($placeholders)
            ORDER BY schedule_date ASC, sort_order ASC, start_time ASC, id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $map;
    }
    $stmt->bind_param($types, ...$eventIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $eid = (int) ($row['event_id'] ?? 0);
        if ($eid < 1) {
            continue;
        }
        if (!isset($map[$eid])) {
            $map[$eid] = [];
        }
        $mapped = eventify_map_day_session_row($row);
        unset($mapped['event_id']);
        $map[$eid][] = $mapped;
    }
    $stmt->close();
    return $map;
}

function eventify_organizer_owns_event(mysqli $conn, int $eventId, int $organizerId): bool
{
    if ($eventId < 1 || $organizerId < 1) {
        return false;
    }
    $stmt = $conn->prepare('SELECT id FROM events WHERE id = ? AND organizer_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $eventId, $organizerId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/**
 * Whether an organizer may add/edit/delete activities (optionally for one schedule day).
 *
 * @return array{ok: bool, error: string, locked: bool}
 */
function eventify_organizer_can_edit_event_schedule(mysqli $conn, int $eventId, ?string $scheduleDate = null): array
{
    $locked = static function (string $message): array {
        return ['ok' => false, 'error' => $message, 'locked' => true];
    };

    if ($eventId < 1) {
        return $locked('Invalid event.');
    }

    $event = eventify_load_event_for_activities_hub($conn, $eventId);
    if (!$event) {
        return $locked('Event not found.');
    }

    $status = strtolower(trim((string) ($event['status'] ?? '')));
    if (in_array($status, ['closed', 'completed', 'rejected'], true)) {
        return $locked('This event has ended. The schedule is read-only.');
    }

    if ($scheduleDate !== null && trim($scheduleDate) !== '') {
        $scheduleDate = substr(trim($scheduleDate), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate)) {
            return $locked('Invalid date.');
        }
        if ($scheduleDate < eventify_today_ymd()) {
            return $locked('This day has passed. The schedule is read-only.');
        }
    }

    return ['ok' => true, 'error' => '', 'locked' => false];
}

/** @return array{ok: bool, error: string, locked: bool} */
function eventify_organizer_can_edit_event_schedule_by_session(mysqli $conn, int $sessionId, int $eventId): array
{
    if ($sessionId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid activity.', 'locked' => true];
    }
    $stmt = $conn->prepare('SELECT schedule_date FROM event_day_sessions WHERE id = ? AND event_id = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not verify activity.', 'locked' => true];
    }
    $stmt->bind_param('ii', $sessionId, $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return ['ok' => false, 'error' => 'Activity not found.', 'locked' => true];
    }

    return eventify_organizer_can_edit_event_schedule(
        $conn,
        $eventId,
        (string) ($row['schedule_date'] ?? '')
    );
}

/** @return array{ok: bool, error: string, locked: bool} */
function eventify_organizer_event_has_editable_schedule_day(mysqli $conn, int $eventId, array $scheduleDates): array
{
    $eventLock = eventify_organizer_can_edit_event_schedule($conn, $eventId, null);
    if (!$eventLock['ok']) {
        return $eventLock;
    }

    $today = eventify_today_ymd();
    foreach ($scheduleDates as $ymd) {
        $ymd = substr(trim((string) $ymd), 0, 10);
        if ($ymd !== '' && $ymd >= $today) {
            return ['ok' => true, 'error' => '', 'locked' => false];
        }
    }

    return [
        'ok' => false,
        'error' => 'All event days have passed. The schedule is read-only.',
        'locked' => true,
    ];
}

/** @return array{ok: bool, error: string, locked: bool} */
function eventify_save_event_day_session(mysqli $conn, int $eventId, string $scheduleDate, array $data, ?int $sessionId = null): array
{
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return ['ok' => false, 'error' => 'Activities feature is not available. Run the database migration.'];
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    $scheduleDate = substr(trim($scheduleDate), 0, 10);
    $dt = DateTime::createFromFormat('Y-m-d', $scheduleDate);
    if (!$dt || $dt->format('Y-m-d') !== $scheduleDate) {
        return ['ok' => false, 'error' => 'Invalid date.'];
    }
    $scheduleLock = eventify_organizer_can_edit_event_schedule($conn, $eventId, $scheduleDate);
    if (!$scheduleLock['ok']) {
        return ['ok' => false, 'error' => $scheduleLock['error']];
    }
    $title = trim((string) ($data['title'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    $startTime = trim((string) ($data['start_time'] ?? ''));
    $endTime = trim((string) ($data['end_time'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $notes = trim((string) ($data['notes'] ?? ''));
    $status = strtolower(trim((string) ($data['status'] ?? 'scheduled')));
    $contactName = trim((string) ($data['contact_name'] ?? ''));
    $contactPhone = trim((string) ($data['contact_phone'] ?? ''));
    $maxCapRaw = trim((string) ($data['max_capacity'] ?? ''));
    $maxCapacity = null;
    if ($maxCapRaw !== '' && is_numeric($maxCapRaw)) {
        $v = (int) $maxCapRaw;
        if ($v > 0) {
            $maxCapacity = $v;
        }
    }
    $maxCapacityBind = $maxCapacity !== null ? (string) $maxCapacity : null;
    if ($title === '') {
        return ['ok' => false, 'error' => 'Activity title is required.'];
    }
    if (strlen($title) > 150) {
        return ['ok' => false, 'error' => 'Title must be 150 characters or less.'];
    }
    if (strlen($location) > 255) {
        return ['ok' => false, 'error' => 'Location must be 255 characters or less.'];
    }
    if ($location === '') {
        return ['ok' => false, 'error' => 'Location is required.'];
    }
    if ($category !== '' && strlen($category) > 80) {
        return ['ok' => false, 'error' => 'Category must be 80 characters or less.'];
    }
    if ($category === '') {
        $category = null;
    }
    if ($notes !== '' && strlen($notes) > 5000) {
        return ['ok' => false, 'error' => 'Notes are too long.'];
    }
    if ($notes === '') {
        $notes = null;
    }
    if (!in_array($status, eventify_day_session_status_options(), true)) {
        $status = 'scheduled';
    }
    if ($contactName !== '' && strlen($contactName) > 100) {
        return ['ok' => false, 'error' => 'Contact name must be 100 characters or less.'];
    }
    if ($contactName === '') {
        $contactName = null;
    }
    if ($contactPhone !== '' && strlen($contactPhone) > 50) {
        return ['ok' => false, 'error' => 'Contact phone must be 50 characters or less.'];
    }
    if ($contactPhone === '') {
        $contactPhone = null;
    }
    if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        return ['ok' => false, 'error' => 'Invalid start time.'];
    }
    if ($endTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        return ['ok' => false, 'error' => 'Invalid end time.'];
    }
    if ($startTime !== '' && $endTime !== '' && $endTime <= $startTime) {
        return ['ok' => false, 'error' => 'End time must be after start time.'];
    }
    $stParam = $startTime !== '' ? $startTime : null;
    $etParam = $endTime !== '' ? $endTime : null;
    $hasGeo = eventify_day_sessions_have_geo_columns($conn);
    $hasEnhanced = eventify_day_sessions_have_enhanced_columns($conn);
    $latParam = null;
    $lngParam = null;
    if ($hasGeo) {
        $latRaw = trim((string) ($data['latitude'] ?? ''));
        $lngRaw = trim((string) ($data['longitude'] ?? ''));
        if ($latRaw === '' || $lngRaw === '') {
            return ['ok' => false, 'error' => 'Please set the venue on the map, search for a place, or use your location.'];
        }
        if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
            return ['ok' => false, 'error' => 'Invalid map coordinates.'];
        }
        $latParam = (float) $latRaw;
        $lngParam = (float) $lngRaw;
        if (abs($latParam) > 90 || abs($lngParam) > 180) {
            return ['ok' => false, 'error' => 'Invalid map coordinates.'];
        }
    }

    $oldSession = null;
    if ($sessionId !== null && $sessionId > 0) {
        $existing = eventify_load_event_day_sessions($conn, $eventId, $scheduleDate);
        foreach ($existing as $ex) {
            if ((int) ($ex['id'] ?? 0) === $sessionId) {
                $oldSession = $ex;
                break;
            }
        }
    }

    if ($sessionId !== null && $sessionId > 0) {
        if ($hasEnhanced && $hasGeo) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, category = ?, location = ?, latitude = ?, longitude = ?, notes = ?, status = ?,
                 start_time = ?, end_time = ?, max_capacity = ?, contact_name = ?, contact_phone = ?, sort_order = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $sortOrder = (int) ($data['sort_order'] ?? ($oldSession['sort_order'] ?? 0));
            $stmt->bind_param(
                'sssddsssssssiiis',
                $title,
                $category,
                $location,
                $latParam,
                $lngParam,
                $notes,
                $status,
                $stParam,
                $etParam,
                $maxCapacityBind,
                $contactName,
                $contactPhone,
                $sortOrder,
                $sessionId,
                $eventId,
                $scheduleDate
            );
        } elseif ($hasEnhanced) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, category = ?, location = ?, notes = ?, status = ?,
                 start_time = ?, end_time = ?, max_capacity = ?, contact_name = ?, contact_phone = ?, sort_order = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $sortOrder = (int) ($data['sort_order'] ?? ($oldSession['sort_order'] ?? 0));
            $stmt->bind_param(
                'ssssssssssiiis',
                $title,
                $category,
                $location,
                $notes,
                $status,
                $stParam,
                $etParam,
                $maxCapacityBind,
                $contactName,
                $contactPhone,
                $sortOrder,
                $sessionId,
                $eventId,
                $scheduleDate
            );
        } elseif ($hasGeo) {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, location = ?, latitude = ?, longitude = ?, start_time = ?, end_time = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $stmt->bind_param('ssddssiis', $title, $location, $latParam, $lngParam, $stParam, $etParam, $sessionId, $eventId, $scheduleDate);
        } else {
            $stmt = $conn->prepare(
                'UPDATE event_day_sessions SET title = ?, location = ?, start_time = ?, end_time = ?
                 WHERE id = ? AND event_id = ? AND schedule_date = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not update activity.'];
            }
            $stmt->bind_param('ssssiis', $title, $location, $stParam, $etParam, $sessionId, $eventId, $scheduleDate);
        }
        $ok = $stmt->execute();
        if (!$ok && $stmt->errno) {
            $err = $stmt->error ?: 'Database error.';
            $stmt->close();
            return ['ok' => false, 'error' => 'Could not update activity. ' . $err];
        }
        $stmt->close();
        if ($ok && $oldSession !== null) {
            eventify_notify_session_changes($conn, $eventId, $oldSession, [
                'title' => $title,
                'status' => $status,
                'start_time' => $stParam,
                'end_time' => $etParam,
                'schedule_date' => $scheduleDate,
            ]);
        }
        if ($ok && $hasEnhanced) {
            eventify_ensure_session_checkin_token($conn, $sessionId);
        }
        if ($ok) {
            $accessResult = eventify_apply_session_access_from_data($conn, $sessionId, $eventId, $data);
            if (!$accessResult['ok']) {
                return $accessResult;
            }
        }
        return $ok ? ['ok' => true, 'id' => $sessionId] : ['ok' => false, 'error' => 'Activity not found.'];
    }

    $sortOrder = (int) ($data['sort_order'] ?? 0);
    if ($hasEnhanced && $hasGeo) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, category, location, latitude, longitude, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param(
            'issssddsssssssi',
            $eventId,
            $scheduleDate,
            $title,
            $category,
            $location,
            $latParam,
            $lngParam,
            $notes,
            $status,
            $stParam,
            $etParam,
            $maxCapacityBind,
            $contactName,
            $contactPhone,
            $sortOrder
        );
    } elseif ($hasEnhanced) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, category, location, notes, status, start_time, end_time, max_capacity, contact_name, contact_phone, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param(
            'isssssssssssi',
            $eventId,
            $scheduleDate,
            $title,
            $category,
            $location,
            $notes,
            $status,
            $stParam,
            $etParam,
            $maxCapacityBind,
            $contactName,
            $contactPhone,
            $sortOrder
        );
    } elseif ($hasGeo) {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, location, latitude, longitude, start_time, end_time, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param('issddsssi', $eventId, $scheduleDate, $title, $location, $latParam, $lngParam, $stParam, $etParam, $sortOrder);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO event_day_sessions (event_id, schedule_date, title, location, start_time, end_time, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not save activity.'];
        }
        $stmt->bind_param('isssssi', $eventId, $scheduleDate, $title, $location, $stParam, $etParam, $sortOrder);
    }
    $stmt->execute();
    if ($stmt->errno) {
        $err = $stmt->error ?: 'Database error.';
        $stmt->close();
        return ['ok' => false, 'error' => 'Could not save activity. ' . $err];
    }
    $newId = (int) $conn->insert_id;
    $stmt->close();
    if ($newId > 0 && $hasEnhanced) {
        eventify_ensure_session_checkin_token($conn, $newId);
    }
    if ($newId > 0) {
        $accessResult = eventify_apply_session_access_from_data($conn, $newId, $eventId, $data);
        if (!$accessResult['ok']) {
            return $accessResult;
        }
    }
    return $newId > 0 ? ['ok' => true, 'id' => $newId] : ['ok' => false, 'error' => 'Could not save activity.'];
}

/**
 * @param array<string, mixed> $data
 * @return array{ok: bool, error?: string}
 */
function eventify_apply_session_access_from_data(mysqli $conn, int $sessionId, int $eventId, array $data): array
{
    if (!array_key_exists('access_mode', $data)) {
        return ['ok' => true];
    }
    $accessMode = strtolower(trim((string) ($data['access_mode'] ?? 'free')));
    $ticketTypeId = null;
    $rawType = trim((string) ($data['ticket_type_id'] ?? ''));
    if ($rawType !== '' && ctype_digit($rawType)) {
        $ticketTypeId = (int) $rawType;
    }
    return eventify_save_session_access_meta($conn, $sessionId, $eventId, $accessMode, $ticketTypeId);
}

/** @return array{ok: bool, error?: string} */
function eventify_delete_event_day_session(mysqli $conn, int $sessionId, int $eventId): array
{
    if ($sessionId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }
    $scheduleLock = eventify_organizer_can_edit_event_schedule_by_session($conn, $sessionId, $eventId);
    if (!$scheduleLock['ok']) {
        return ['ok' => false, 'error' => $scheduleLock['error']];
    }
    $stmt = $conn->prepare('DELETE FROM event_day_sessions WHERE id = ? AND event_id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not delete activity.'];
    }
    $stmt->bind_param('ii', $sessionId, $eventId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Activity not found.'];
}

function eventify_format_session_time_range(?string $start, ?string $end): string
{
    $fmt = static function (?string $t): string {
        $t = trim((string) $t);
        if ($t === '') {
            return '';
        }
        $ts = strtotime($t);
        return $ts ? date('g:i A', $ts) : $t;
    };
    $a = $fmt($start);
    $b = $fmt($end);
    if ($a !== '' && $b !== '') {
        return $a . ' – ' . $b;
    }
    if ($a !== '') {
        return $a;
    }
    return '';
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_rsvp_meta(mysqli $conn, array &$sessions, ?int $viewerUserId): void
{
    if ($sessions === [] || !eventify_session_rsvps_table_exists($conn)) {
        return;
    }
    $ids = array_values(array_filter(array_map(static function ($s) {
        return (int) ($s['id'] ?? 0);
    }, $sessions), static function ($id) {
        return $id > 0;
    }));
    if ($ids === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $counts = [];
    $sql = "SELECT session_id, COUNT(*) AS c FROM event_day_session_rsvps WHERE session_id IN ($placeholders) GROUP BY session_id";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $counts[(int) $row['session_id']] = (int) $row['c'];
        }
        $stmt->close();
    }
    $userRsvped = [];
    if ($viewerUserId !== null && $viewerUserId > 0) {
        $types2 = 'i' . str_repeat('i', count($ids));
        $sql2 = "SELECT session_id FROM event_day_session_rsvps WHERE user_id = ? AND session_id IN ($placeholders)";
        $stmt2 = $conn->prepare($sql2);
        if ($stmt2) {
            $params = array_merge([$viewerUserId], $ids);
            $stmt2->bind_param($types2, ...$params);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($row = $res2->fetch_assoc()) {
                $userRsvped[(int) $row['session_id']] = true;
            }
            $stmt2->close();
        }
    }
    foreach ($sessions as &$s) {
        $sid = (int) ($s['id'] ?? 0);
        $s['rsvp_count'] = $counts[$sid] ?? 0;
        $s['user_rsvped'] = !empty($userRsvped[$sid]);
    }
    unset($s);
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_checkin_meta(mysqli $conn, array &$sessions, int $viewerUserId): void
{
    if ($sessions === [] || $viewerUserId < 1) {
        return;
    }
    try {
        $chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
        if (!$chk || $chk->num_rows < 1) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    $ids = array_values(array_filter(array_map(static function ($s) {
        return (int) ($s['id'] ?? 0);
    }, $sessions), static function ($id) {
        return $id > 0;
    }));
    if ($ids === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i' . str_repeat('i', count($ids));
    $checkedIn = [];
    $sql = "SELECT session_id, checked_in_at FROM event_day_session_attendance WHERE user_id = ? AND session_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $params = array_merge([$viewerUserId], $ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $checkedIn[(int) $row['session_id']] = (string) ($row['checked_in_at'] ?? '');
        }
        $stmt->close();
    }
    foreach ($sessions as &$s) {
        $sid = (int) ($s['id'] ?? 0);
        $s['user_checked_in'] = isset($checkedIn[$sid]);
        $s['checked_in_at'] = $checkedIn[$sid] ?? null;
    }
    unset($s);
}

/** @param array<string, mixed> $session */
function eventify_session_allows_cancel_rsvp(array $session): bool
{
    if (empty($session['user_rsvped'])) {
        return false;
    }
    if (!empty($session['user_checked_in'])) {
        return false;
    }
    return eventify_session_allows_rsvp($session);
}

/** @return array{ok: bool, error?: string} */
function eventify_register_session_rsvp(mysqli $conn, int $sessionId, int $userId): array
{
    if ($sessionId < 1 || $userId < 1 || !eventify_session_rsvps_table_exists($conn)) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }
    $accessCols = eventify_day_sessions_have_access_columns($conn)
        ? 's.access_mode, s.ticket_type_id,'
        : '';
    $stmt = $conn->prepare(
        "SELECT s.id, s.event_id, s.title, s.status, {$accessCols} s.max_capacity, s.schedule_date, s.start_time, s.end_time,
                e.status AS event_status, e.department, u.department AS student_department
         FROM event_day_sessions s
         JOIN events e ON e.id = s.event_id
         JOIN users u ON u.id = ?
         WHERE s.id = ? LIMIT 1"
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Server error.'];
    }
    $stmt->bind_param('ii', $userId, $sessionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return ['ok' => false, 'error' => 'Activity not found.'];
    }
    if (($row['event_status'] ?? '') !== 'active') {
        return ['ok' => false, 'error' => 'This event is not open for registration.'];
    }
    if (($row['status'] ?? '') === 'cancelled') {
        return ['ok' => false, 'error' => 'This activity has been cancelled.'];
    }
    if (!eventify_student_sees_event_department((string) ($row['department'] ?? 'ALL'), $row['student_department'] ?? null)) {
        return ['ok' => false, 'error' => 'This activity is not available for your department.'];
    }
    if (!eventify_session_allows_rsvp($row)) {
        return ['ok' => false, 'error' => 'This activity has ended. RSVP is no longer available.'];
    }
    $eventId = (int) ($row['event_id'] ?? 0);
    require_once __DIR__ . '/event_checkin_security.php';
    require_once __DIR__ . '/event_ticketing.php';
    if (!eventify_student_has_main_event_access($conn, $userId, $eventId)) {
        $evStmt = $conn->prepare('SELECT registration_mode FROM events WHERE id = ? LIMIT 1');
        $regMode = 'rsvp';
        if ($evStmt) {
            $evStmt->bind_param('i', $eventId);
            $evStmt->execute();
            $evRow = $evStmt->get_result()->fetch_assoc();
            $evStmt->close();
            if ($evRow) {
                $regMode = eventify_event_registration_mode($evRow);
            }
        }
        if ($regMode === 'paid_ticket') {
            return ['ok' => false, 'error' => 'Buy a ticket for this event first, then RSVP for activities in the hub.'];
        }
        return ['ok' => false, 'error' => 'RSVP for the main event first, then you can join activities in the hub.'];
    }
    if (eventify_session_requires_ticket($row) && !eventify_student_has_session_ticket($conn, $userId, $row)) {
        return ['ok' => false, 'error' => 'Buy a ticket for this paid activity first, then RSVP here.'];
    }
    $chk = $conn->prepare('SELECT id FROM event_day_session_rsvps WHERE session_id = ? AND user_id = ? LIMIT 1');
    if (!$chk) {
        return ['ok' => false, 'error' => 'Server error.'];
    }
    $chk->bind_param('ii', $sessionId, $userId);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        return ['ok' => false, 'error' => 'You already RSVP\'d for this activity.'];
    }
    $chk->close();
    $maxCap = isset($row['max_capacity']) && $row['max_capacity'] !== null && $row['max_capacity'] !== ''
        ? (int) $row['max_capacity'] : null;
    if ($maxCap !== null && $maxCap > 0) {
        $cStmt = $conn->prepare('SELECT COUNT(*) AS c FROM event_day_session_rsvps WHERE session_id = ?');
        if ($cStmt) {
            $cStmt->bind_param('i', $sessionId);
            $cStmt->execute();
            $cStmt->bind_result($cnt);
            $cStmt->fetch();
            $cStmt->close();
            if ((int) $cnt >= $maxCap) {
                return ['ok' => false, 'error' => 'This activity is full.'];
            }
        }
    }
    $ins = $conn->prepare('INSERT INTO event_day_session_rsvps (session_id, user_id) VALUES (?, ?)');
    if (!$ins) {
        return ['ok' => false, 'error' => 'Could not save RSVP.'];
    }
    $ins->bind_param('ii', $sessionId, $userId);
    $ok = $ins->execute();
    $ins->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not save RSVP.'];
}

/** @return array{ok: bool, error?: string} */
function eventify_cancel_session_rsvp(mysqli $conn, int $sessionId, int $userId): array
{
    if ($sessionId < 1 || $userId < 1 || !eventify_session_rsvps_table_exists($conn)) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }
    $st = $conn->prepare(
        'SELECT s.id, s.status, s.schedule_date, s.start_time, s.end_time
         FROM event_day_sessions s WHERE s.id = ? LIMIT 1'
    );
    if ($st) {
        $st->bind_param('i', $sessionId);
        $st->execute();
        $sessionRow = $st->get_result()->fetch_assoc();
        $st->close();
        if ($sessionRow) {
            $session = eventify_map_day_session_row($sessionRow);
            $session['user_rsvped'] = true;
            $session['user_checked_in'] = false;
            try {
                $chk = $conn->query("SHOW TABLES LIKE 'event_day_session_attendance'");
                if ($chk && $chk->num_rows > 0) {
                    $att = $conn->prepare('SELECT 1 FROM event_day_session_attendance WHERE session_id = ? AND user_id = ? LIMIT 1');
                    if ($att) {
                        $att->bind_param('ii', $sessionId, $userId);
                        $att->execute();
                        $session['user_checked_in'] = (bool) $att->get_result()->fetch_assoc();
                        $att->close();
                    }
                }
            } catch (Throwable $e) {
                /* ignore */
            }
            if (!empty($session['user_checked_in'])) {
                return ['ok' => false, 'error' => 'You already checked in — RSVP cannot be cancelled.'];
            }
            if (!eventify_session_allows_rsvp($session)) {
                return ['ok' => false, 'error' => 'This activity has ended — RSVP can no longer be cancelled.'];
            }
        }
    }
    $stmt = $conn->prepare('DELETE FROM event_day_session_rsvps WHERE session_id = ? AND user_id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not cancel RSVP.'];
    }
    $stmt->bind_param('ii', $sessionId, $userId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'You are not RSVP\'d for this activity.'];
}

/**
 * Remove all activity RSVPs for a user under one parent event (e.g. when main event RSVP is cancelled).
 *
 * @return int Number of activity RSVPs removed
 */
function eventify_cancel_all_session_rsvps_for_event(mysqli $conn, int $eventId, int $userId): int
{
    if ($eventId < 1 || $userId < 1 || !eventify_session_rsvps_table_exists($conn)) {
        return 0;
    }
    $stmt = $conn->prepare(
        'DELETE r FROM event_day_session_rsvps r
         INNER JOIN event_day_sessions s ON s.id = r.session_id
         WHERE r.user_id = ? AND s.event_id = ?'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ii', $userId, $eventId);
    $stmt->execute();
    $removed = $stmt->affected_rows;
    $stmt->close();
    return max(0, (int) $removed);
}

/**
 * @return list<array<string, mixed>>
 */
function eventify_load_student_today_activities(mysqli $conn, int $userId, ?string $studentDepartment, string $todayYmd): array
{
    if ($userId < 1 || !eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }
    eventify_event_day_sessions_ensure_enhanced($conn);
    $todayYmd = substr(trim($todayYmd), 0, 10);
    $rawCols = explode(', ', eventify_day_sessions_select_columns($conn));
    $colList = implode(', ', array_map(static function ($c) {
        return 's.' . trim($c);
    }, $rawCols));
    $sql = "SELECT {$colList}, e.title AS event_title, e.department AS event_department, e.status AS event_status
            FROM event_day_sessions s
            INNER JOIN events e ON e.id = s.event_id
            WHERE s.schedule_date = ? AND e.status = 'active'
            ORDER BY s.start_time ASC, s.sort_order ASC, s.id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $todayYmd);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        if (!eventify_student_sees_event_department((string) ($row['event_department'] ?? 'ALL'), $studentDepartment)) {
            continue;
        }
        $mapped = eventify_map_day_session_row($row);
        $mapped['event_title'] = trim((string) ($row['event_title'] ?? ''));
        $mapped['event_status'] = trim((string) ($row['event_status'] ?? ''));
        $out[] = $mapped;
    }
    $stmt->close();
    if ($out !== [] && eventify_session_rsvps_table_exists($conn)) {
        eventify_attach_session_rsvp_meta($conn, $out, $userId);
    }
    return $out;
}

/** @param array<string, mixed> $oldSession @param array<string, mixed> $newData */
function eventify_notify_session_changes(mysqli $conn, int $eventId, array $oldSession, array $newData): void
{
    $sessionId = (int) ($oldSession['id'] ?? 0);
    if ($sessionId < 1) {
        return;
    }
    $oldStatus = (string) ($oldSession['status'] ?? 'scheduled');
    $newStatus = (string) ($newData['status'] ?? 'scheduled');
    $oldStart = trim((string) ($oldSession['start_time'] ?? ''));
    $newStart = trim((string) ($newData['start_time'] ?? ''));
    $oldEnd = trim((string) ($oldSession['end_time'] ?? ''));
    $newEnd = trim((string) ($newData['end_time'] ?? ''));
    $title = trim((string) ($newData['title'] ?? $oldSession['title'] ?? 'Activity'));
    $dateLabel = trim((string) ($newData['schedule_date'] ?? $oldSession['schedule_date'] ?? ''));

    $notify = false;
    $msg = '';
    if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
        $notify = true;
        $msg = '"' . $title . '" on ' . $dateLabel . ' has been cancelled.';
    } elseif ($newStatus === 'delayed' && $oldStatus !== 'delayed') {
        $notify = true;
        $msg = '"' . $title . '" on ' . $dateLabel . ' has been delayed.';
    } elseif ($oldStart !== $newStart || $oldEnd !== $newEnd) {
        $notify = true;
        $timeStr = eventify_format_session_time_range($newStart ?: null, $newEnd ?: null);
        $msg = '"' . $title . '" on ' . $dateLabel . ' schedule updated' . ($timeStr !== '' ? ' to ' . $timeStr : '') . '.';
    }
    if (!$notify || $msg === '') {
        return;
    }
    $userIds = [];
    if (eventify_session_rsvps_table_exists($conn)) {
        $rs = $conn->prepare('SELECT user_id FROM event_day_session_rsvps WHERE session_id = ?');
        if ($rs) {
            $rs->bind_param('i', $sessionId);
            $rs->execute();
            $rRes = $rs->get_result();
            while ($r = $rRes->fetch_assoc()) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid > 0) {
                    $userIds[$uid] = true;
                }
            }
            $rs->close();
        }
    }
    $reg = $conn->prepare('SELECT user_id FROM registrations WHERE event_id = ?');
    if ($reg) {
        $reg->bind_param('i', $eventId);
        $reg->execute();
        $regRes = $reg->get_result();
        while ($r = $regRes->fetch_assoc()) {
            $uid = (int) ($r['user_id'] ?? 0);
            if ($uid > 0) {
                $userIds[$uid] = true;
            }
        }
        $reg->close();
    }
    if ($userIds === []) {
        return;
    }
    $notifTitle = 'Activity update';
    try {
        $ins = $conn->prepare('INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)');
        if (!$ins) {
            return;
        }
        $type = 'activity_update';
        foreach (array_keys($userIds) as $uid) {
            $ins->bind_param('isssi', $uid, $type, $notifTitle, $msg, $eventId);
            $ins->execute();
        }
        $ins->close();
    } catch (Throwable $e) {
        /* ignore */
    }
}

/** @return array<string, mixed>|null */
function eventify_load_session_by_checkin_token(mysqli $conn, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !eventify_event_day_sessions_ensure_enhanced($conn)) {
        return null;
    }
    $rawCols = explode(', ', eventify_day_sessions_select_columns($conn));
    $colList = implode(', ', array_map(static function ($c) {
        return 's.' . trim($c);
    }, $rawCols));
    $eventGeoCols = '';
    try {
        $geoColCheck = $conn->query("SHOW COLUMNS FROM events WHERE Field IN ('latitude','longitude')");
        if ($geoColCheck && $geoColCheck->num_rows >= 2) {
            $eventGeoCols = ', e.latitude AS event_latitude, e.longitude AS event_longitude';
        }
    } catch (Throwable $e) {
        /* events table may not have map columns yet */
    }
    $stmt = $conn->prepare("SELECT {$colList}, e.title AS event_title, e.status AS event_status, e.organizer_id{$eventGeoCols} FROM event_day_sessions s JOIN events e ON e.id = s.event_id WHERE s.checkin_token = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $mapped = eventify_map_day_session_row($row);
    $mapped['event_title'] = trim((string) ($row['event_title'] ?? ''));
    $mapped['event_status'] = trim((string) ($row['event_status'] ?? ''));
    $mapped['organizer_id'] = (int) ($row['organizer_id'] ?? 0);
    $mapped['event_latitude'] = $row['event_latitude'] ?? null;
    $mapped['event_longitude'] = $row['event_longitude'] ?? null;
    return $mapped;
}

/** @return array<string, mixed>|null */
function eventify_load_event_for_activities_hub(mysqli $conn, int $eventId): ?array
{
    if ($eventId < 1) {
        return null;
    }
    $cols = 'id, title, description, date, end_date, location, status, department, organizer_id';
    try {
        $mcCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'max_capacity'");
        if ($mcCol && $mcCol->num_rows >= 1) {
            $cols .= ', max_capacity';
        }
        $rmCol = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'registration_mode'");
        if ($rmCol && $rmCol->num_rows >= 1) {
            $cols .= ', registration_mode';
        }
    } catch (Throwable $e) {
        /* ignore */
    }
    $stmt = $conn->prepare('SELECT ' . $cols . ' FROM events WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function eventify_user_can_view_event_activities(mysqli $conn, array $event, int $userId, string $role, ?string $studentDepartment = null): bool
{
    if ($userId < 1) {
        return false;
    }
    if (in_array($role, ['super_admin', 'admin'], true)) {
        return true;
    }
    if ($role === 'organizer') {
        return (int) ($event['organizer_id'] ?? 0) === $userId;
    }
    if ($role === 'student') {
        $st = strtolower((string) ($event['status'] ?? ''));
        if (!in_array($st, ['active', 'closed', 'completed'], true)) {
            return false;
        }
        if ($studentDepartment === null && $userId > 0) {
            $u = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
            if ($u) {
                $u->bind_param('i', $userId);
                $u->execute();
                $ur = $u->get_result()->fetch_assoc();
                $u->close();
                $studentDepartment = $ur['department'] ?? null;
            }
        }
        return eventify_student_sees_event_department((string) ($event['department'] ?? 'ALL'), $studentDepartment);
    }
    if ($role === 'multimedia') {
        $st = strtolower((string) ($event['status'] ?? ''));
        if (!in_array($st, ['active', 'closed', 'completed'], true)) {
            return false;
        }
        if ($studentDepartment === null && $userId > 0) {
            $u = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
            if ($u) {
                $u->bind_param('i', $userId);
                $u->execute();
                $ur = $u->get_result()->fetch_assoc();
                $u->close();
                $studentDepartment = $ur['department'] ?? null;
            }
        }
        if ($studentDepartment === null || trim((string) $studentDepartment) === '') {
            return true;
        }
        return eventify_student_sees_event_department((string) ($event['department'] ?? 'ALL'), $studentDepartment);
    }
    return false;
}

/**
 * Events the user can open in the activities hub (must have at least one day session).
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_activities_hub_picker_events(mysqli $conn, int $userId, string $role, ?string $userDepartment = null): array
{
    if ($userId < 1 || !in_array($role, ['super_admin', 'admin', 'organizer', 'student', 'multimedia'], true)) {
        return [];
    }
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }

    if ($userDepartment === null && in_array($role, ['student', 'multimedia'], true)) {
        $u = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
        if ($u) {
            $u->bind_param('i', $userId);
            $u->execute();
            $ur = $u->get_result()->fetch_assoc();
            $u->close();
            $userDepartment = $ur['department'] ?? null;
        }
    }

    $sessionCountSql = '(SELECT COUNT(*) FROM event_day_sessions s WHERE s.event_id = e.id)';
    $isOrganizer = $role === 'organizer';
    $sql = "SELECT e.id, e.title, e.date, e.location, e.department, e.status, e.organizer_id,
                   {$sessionCountSql} AS activity_count
            FROM events e
            WHERE 1=1";

    if (!$isOrganizer) {
        $sql .= " AND e.title NOT LIKE 'sample%' AND {$sessionCountSql} > 0";
    }

    if ($isOrganizer) {
        $sql .= ' AND e.organizer_id = ?';
    } elseif (!in_array($role, ['super_admin', 'admin'], true)) {
        $sql .= " AND e.status IN ('active', 'closed', 'completed')";
    } else {
        $sql .= " AND e.status IN ('active', 'closed', 'completed')";
    }

    $sql .= ' ORDER BY e.date DESC, e.id DESC LIMIT 80';

    $res = null;
    $stmt = null;
    if ($isOrganizer) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (!eventify_user_can_view_event_activities($conn, $row, $userId, $role, $userDepartment)) {
                continue;
            }
            $out[] = $row;
        }
    }
    if ($stmt) {
        $stmt->close();
    }

    return array_slice($out, 0, 50);
}

/**
 * Student's RSVP'd events for the activities hub landing (includes events without sessions yet).
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_student_registered_hub_events(mysqli $conn, int $userId, ?string $studentDepartment): array
{
    if ($userId < 1) {
        return [];
    }
    if (!eventify_event_day_sessions_ensure_table($conn)) {
        return [];
    }
    $sessionCountSql = '(SELECT COUNT(*) FROM event_day_sessions s WHERE s.event_id = e.id)';
    $sql = "SELECT e.id, e.title, e.date, e.location, e.department, e.status, e.end_date,
                   {$sessionCountSql} AS activity_count
            FROM events e
            INNER JOIN registrations r ON r.event_id = e.id AND r.user_id = ?
            WHERE e.status = 'active'
            ORDER BY e.date ASC, e.id ASC
            LIMIT 24";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        if (!eventify_user_can_view_event_activities($conn, $row, $userId, 'student', $studentDepartment)) {
            continue;
        }
        $out[] = $row;
    }
    $stmt->close();

    return $out;
}

/**
 * Merge registered events into hub picker list (student landing).
 *
 * @param list<array<string, mixed>> $pickerEvents
 * @param list<array<string, mixed>> $registeredEvents
 * @return list<array<string, mixed>>
 */
function eventify_merge_registered_events_into_hub_picker(array $pickerEvents, array $registeredEvents): array
{
    $byId = [];
    foreach ($pickerEvents as $ev) {
        $id = (int) ($ev['id'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $ev;
        }
    }
    foreach ($registeredEvents as $ev) {
        $id = (int) ($ev['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        if (!isset($byId[$id])) {
            $byId[$id] = $ev;
        }
    }
    $merged = array_values($byId);
    usort($merged, static function ($a, $b) {
        $da = substr(trim((string) ($a['date'] ?? '')), 0, 10);
        $db = substr(trim((string) ($b['date'] ?? '')), 0, 10);
        if ($da === $db) {
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        }
        return strcmp($db, $da);
    });

    return array_slice($merged, 0, 50);
}

/**
 * Merged picker + registered events for a student (hub navigation).
 *
 * @return list<array<string, mixed>>
 */
function eventify_load_student_merged_hub_events(mysqli $conn, int $userId, ?string $studentDepartment): array
{
    if ($userId < 1) {
        return [];
    }
    $picker = eventify_load_activities_hub_picker_events($conn, $userId, 'student', $studentDepartment);
    $registered = eventify_load_student_registered_hub_events($conn, $userId, $studentDepartment);

    return eventify_merge_registered_events_into_hub_picker($picker, $registered);
}

/**
 * Pick the primary event hub from a list (richest active schedule wins).
 */
function eventify_pick_primary_hub_event_id(array $events, int $preferEventId = 0, int $preferActivityCount = 0): int
{
    if ($preferEventId > 0) {
        foreach ($events as $ev) {
            if ((int) ($ev['id'] ?? 0) === $preferEventId) {
                return $preferEventId;
            }
        }
        if ($preferActivityCount > 0) {
            return $preferEventId;
        }
    }

    if ($events === []) {
        return $preferEventId > 0 ? $preferEventId : 0;
    }

    $candidates = [];
    foreach ($events as $ev) {
        if ((int) ($ev['activity_count'] ?? 0) < 1) {
            continue;
        }
        if (strtolower((string) ($ev['status'] ?? '')) !== 'active') {
            continue;
        }
        $candidates[] = $ev;
    }
    if ($candidates === []) {
        foreach ($events as $ev) {
            if ((int) ($ev['activity_count'] ?? 0) > 0) {
                $candidates[] = $ev;
            }
        }
    }
    if ($candidates === []) {
        return (int) ($events[0]['id'] ?? 0) ?: ($preferEventId > 0 ? $preferEventId : 0);
    }

    usort($candidates, static function ($a, $b) {
        $ca = (int) ($a['activity_count'] ?? 0);
        $cb = (int) ($b['activity_count'] ?? 0);
        if ($ca !== $cb) {
            return $cb <=> $ca;
        }
        $da = substr(trim((string) ($a['date'] ?? '')), 0, 10);
        $db = substr(trim((string) ($b['date'] ?? '')), 0, 10);

        return strcmp($db, $da);
    });

    return (int) ($candidates[0]['id'] ?? 0);
}

/**
 * Primary event hub for a student (registered active event with the richest schedule).
 */
function eventify_resolve_student_hub_home_event_id(
    mysqli $conn,
    int $userId,
    ?string $studentDepartment,
    int $currentEventId = 0,
    int $currentActivityCount = 0
): int {
    $merged = eventify_load_student_merged_hub_events($conn, $userId, $studentDepartment);
    if ($merged === []) {
        return $currentEventId > 0 ? $currentEventId : 0;
    }

    return eventify_pick_primary_hub_event_id($merged, $currentEventId, $currentActivityCount);
}

/**
 * Main hub URL for activities navigation (event hub page, never dashboard or index).
 */
function eventify_activities_hub_main_url(
    mysqli $conn,
    int $userId,
    string $role,
    ?string $userDepartment = null,
    int $preferEventId = 0,
    int $preferActivityCount = 0
): string {
    $base = defined('BASE_URL') ? BASE_URL : '/school_events';
    $listUrl = $base . '/activities_hub.php';
    if ($userId < 1) {
        return $listUrl;
    }

    $role = strtolower(trim($role));

    if ($preferEventId < 1) {
        $sessionPrefer = (int) ($_SESSION['eventify_main_hub_event_id'] ?? 0);
        if ($sessionPrefer > 0) {
            $preferEventId = $sessionPrefer;
        }
    }

    if ($role === 'student') {
        return eventify_student_activities_hub_home_url(
            $conn,
            $userId,
            $userDepartment,
            $preferEventId,
            $preferActivityCount,
            $preferEventId > 0 ? 'hub' : null
        );
    }

    if ($preferEventId > 0) {
        $event = eventify_load_event_for_activities_hub($conn, $preferEventId);
        if ($event && eventify_user_can_view_event_activities($conn, $event, $userId, $role, $userDepartment)) {
            return $base . '/event_activities.php?id=' . $preferEventId;
        }
    }

    $events = eventify_load_activities_hub_picker_events($conn, $userId, $role, $userDepartment);
    $eventId = eventify_pick_primary_hub_event_id($events, $preferEventId, $preferActivityCount);
    if ($eventId > 0) {
        return $base . '/event_activities.php?id=' . $eventId;
    }

    return $listUrl;
}

/**
 * Student top-bar home target: main event hub (e.g. sample intrams), not dashboard or bare picker.
 */
function eventify_student_activities_hub_home_url(
    mysqli $conn,
    int $userId,
    ?string $studentDepartment,
    int $currentEventId = 0,
    int $currentActivityCount = 0,
    ?string $view = null
): string {
    $base = defined('BASE_URL') ? BASE_URL : '/school_events';
    $listUrl = $base . '/activities_hub.php';
    if ($userId < 1) {
        return $listUrl;
    }

    if ($currentEventId > 0 && $view !== null && $view !== 'hub') {
        return $base . '/event_activities.php?id=' . $currentEventId;
    }

    $homeEventId = eventify_resolve_student_hub_home_event_id(
        $conn,
        $userId,
        $studentDepartment,
        $currentEventId,
        $currentActivityCount
    );
    if ($homeEventId > 0) {
        return $base . '/event_activities.php?id=' . $homeEventId;
    }

    return $listUrl;
}

function eventify_activity_icon(string $title, ?string $category = null): string
{
    $t = strtolower($title);
    $map = [
        'badminton' => '🏸', 'basketball' => '🏀', 'volleyball' => '🏐', 'soccer' => '⚽', 'football' => '🏈',
        'tennis' => '🎾', 'table tennis' => '🏓', 'chess' => '♟️', 'swimming' => '🏊', 'track' => '🏃',
        'boxing' => '🥊', 'dance' => '💃', 'singing' => '🎤', 'debate' => '🗣️', 'quiz' => '❓',
    ];
    foreach ($map as $key => $icon) {
        if (strpos($t, $key) !== false) {
            return $icon;
        }
    }
    $cat = strtolower(trim((string) $category));
    $catIcons = [
        'sports' => '🏅', 'ceremony' => '🎓', 'workshop' => '🛠️',
        'competition' => '🏆', 'social' => '🎉', 'other' => '📋',
    ];
    return $catIcons[$cat] ?? '📅';
}

/** Font Awesome 6 icon class for activity cards (no fa- prefix). */
function eventify_activity_fa_icon(string $title, ?string $category = null): string
{
    $t = strtolower($title);
    $map = [
        'badminton' => 'shuttlecock',
        'basketball' => 'basketball',
        'volleyball' => 'volleyball',
        'soccer' => 'futbol',
        'football' => 'football-ball',
        'tennis' => 'table-tennis-paddle-ball',
        'chess' => 'chess',
        'swimming' => 'person-swimming',
        'track' => 'person-running',
        'dance' => 'music',
        'singing' => 'microphone',
        'debate' => 'comments',
        'quiz' => 'circle-question',
    ];
    foreach ($map as $key => $icon) {
        if (strpos($t, $key) !== false) {
            return $icon;
        }
    }
    $cat = strtolower(trim((string) $category));
    $catIcons = [
        'sports' => 'medal',
        'ceremony' => 'graduation-cap',
        'workshop' => 'screwdriver-wrench',
        'competition' => 'trophy',
        'social' => 'champagne-glasses',
        'other' => 'clipboard-list',
    ];
    return $catIcons[$cat] ?? 'calendar-day';
}

function eventify_short_activity_location(?string $location): string
{
    $loc = trim((string) $location);
    if ($loc === '' || $loc === '0' || !preg_match('/[a-zA-Z\x{00C0}-\x{024F}]/u', $loc)) {
        return '';
    }
    if (strlen($loc) > 52) {
        return substr($loc, 0, 49) . '…';
    }
    return $loc;
}

function eventify_app_timezone(): DateTimeZone
{
    static $tz = null;
    if ($tz instanceof DateTimeZone) {
        return $tz;
    }
    $name = defined('EVENTIFY_APP_TIMEZONE') ? (string) EVENTIFY_APP_TIMEZONE : 'Asia/Manila';
    try {
        $tz = new DateTimeZone($name);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    return $tz;
}

/** Normalize MySQL TIME to H:i:s for parsing. */
function eventify_normalize_session_time(string $time): string
{
    $time = trim($time);
    if ($time === '') {
        return '';
    }
    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        return $time . ':00';
    }
    return $time;
}

/**
 * Parse schedule date + time in the app timezone.
 */
function eventify_session_datetime(string $dayYmd, string $time): ?DateTimeImmutable
{
    $dayYmd = substr(trim($dayYmd), 0, 10);
    $time = eventify_normalize_session_time($time);
    if ($dayYmd === '' || $time === '') {
        return null;
    }
    $tz = eventify_app_timezone();
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $dayYmd . ' ' . $time, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }
    return null;
}

function eventify_today_ymd(): string
{
    return (new DateTimeImmutable('now', eventify_app_timezone()))->format('Y-m-d');
}

/** @param array<string, mixed> $session */
function eventify_session_effective_end_datetime(array $session): ?DateTimeImmutable
{
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day === '') {
        return null;
    }
    $end = trim((string) ($session['end_time'] ?? ''));
    if ($end !== '') {
        $et = eventify_session_datetime($day, $end);
        if ($et instanceof DateTimeImmutable) {
            return $et;
        }
    }
    $tz = eventify_app_timezone();
    $dayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);
    return $dayOnly ? $dayOnly->setTime(23, 59, 59) : null;
}

/** Whether students may RSVP for this activity (not cancelled, day not passed, not past end time). */
function eventify_session_allows_rsvp(array $session, ?DateTimeInterface $now = null): bool
{
    if (($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day === '' || $day < $todayYmd) {
        return false;
    }
    $endDt = eventify_session_effective_end_datetime($session);
    if ($endDt instanceof DateTimeInterface && $now > $endDt) {
        return false;
    }
    return true;
}

/** Whether QR / manual activity check-in is open (activity day, within time window + early grace). */
function eventify_session_allows_checkin(array $session, ?DateTimeInterface $now = null): bool
{
    return eventify_session_is_checkin_window_open($session, $now);
}

/**
 * Human-readable reason when activity check-in is closed, or null when open.
 *
 * @param array<string, mixed> $session
 */
function eventify_session_checkin_unavailable_reason(array $session, ?DateTimeInterface $now = null): ?string
{
    if (eventify_session_is_checkin_window_open($session, $now)) {
        return null;
    }
    if (($session['status'] ?? '') === 'cancelled') {
        return 'This activity has been cancelled.';
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day === '') {
        return 'Check-in is not available for this activity yet.';
    }
    if ($day !== $todayYmd) {
        $dayLabel = DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);
        $prettyDay = $dayLabel instanceof DateTimeImmutable ? $dayLabel->format('M j, Y') : $day;
        if ($day < $todayYmd) {
            return 'Check-in closed — this activity was scheduled for ' . $prettyDay . '.';
        }
        return 'Check-in opens on ' . $prettyDay . ' during the scheduled activity time.';
    }
    $start = trim((string) ($session['start_time'] ?? ''));
    $end = trim((string) ($session['end_time'] ?? ''));
    if ($start !== '') {
        $st = eventify_session_datetime($day, $start);
        if ($st instanceof DateTimeImmutable) {
            $early = eventify_checkin_early_minutes();
            $openAt = $early > 0 ? $st->modify('-' . $early . ' minutes') : $st;
            if ($now < $openAt) {
                return 'Check-in opens at ' . $openAt->format('g:i A') . ' (about ' . $early . ' min before start).';
            }
        }
    }
    if ($end !== '') {
        $et = eventify_session_datetime($day, $end);
        if ($et instanceof DateTimeImmutable && $now > $et) {
            return 'Check-in closed — this activity ended at ' . $et->format('g:i A') . '.';
        }
    }
    return 'Check-in is not open right now (' . $now->format('g:i A') . '). Please scan again during the scheduled activity time.';
}

/**
 * Student-facing context when activity check-in is closed.
 *
 * @param array<string, mixed> $session
 * @return array{reason:string,activity_date_label:string,time_window:string,now_label:string,today_date_label:string,timezone_short:string}
 */
function eventify_session_checkin_student_details(array $session, ?DateTimeInterface $now = null): array
{
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    $activityDateLabel = '';
    if ($day !== '') {
        $dayDt = DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);
        $activityDateLabel = $dayDt instanceof DateTimeImmutable ? $dayDt->format('l, M j, Y') : $day;
    }

    return [
        'reason' => eventify_session_checkin_unavailable_reason($session, $now)
            ?? 'Check-in is not available right now.',
        'activity_date_label' => $activityDateLabel,
        'time_window' => eventify_format_session_time_range($session['start_time'] ?? null, $session['end_time'] ?? null),
        'now_label' => $now->format('g:i A'),
        'today_date_label' => $now->format('l, M j, Y'),
        'timezone_short' => $now->format('T'),
    ];
}

/** @param array<string, mixed> $session */
function eventify_session_is_checkin_window_open(array $session, ?DateTimeInterface $now = null): bool
{
    if (!function_exists('eventify_checkin_early_minutes')) {
        require_once __DIR__ . '/event_checkin_security.php';
    }
    if (($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day !== $todayYmd) {
        return false;
    }
    $start = trim((string) ($session['start_time'] ?? ''));
    $end = trim((string) ($session['end_time'] ?? ''));
    if ($start === '' && $end === '') {
        return true;
    }
    if ($start !== '') {
        $st = eventify_session_datetime($day, $start);
        if ($st instanceof DateTimeImmutable) {
            $early = eventify_checkin_early_minutes();
            if ($early > 0) {
                $st = $st->modify('-' . $early . ' minutes');
            }
            if ($now < $st) {
                return false;
            }
        }
    }
    if ($end !== '') {
        $et = eventify_session_datetime($day, $end);
        if ($et instanceof DateTimeImmutable && $now > $et) {
            return false;
        }
    }
    return true;
}

/** @param list<array<string, mixed>> $sessions */
function eventify_attach_session_student_flags(array &$sessions): void
{
    foreach ($sessions as &$s) {
        $s['allows_rsvp'] = eventify_session_allows_rsvp($s);
        $s['allows_checkin'] = eventify_session_allows_checkin($s);
        $s['allows_cancel_rsvp'] = eventify_session_allows_cancel_rsvp($s);
    }
    unset($s);
}

/** @param array<string, mixed> $session */
function eventify_session_is_live_now(array $session, ?string $todayYmd = null, ?DateTimeInterface $now = null): bool
{
    if (($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    $tz = eventify_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $todayYmd ?? $now->format('Y-m-d');
    $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
    if ($day !== $todayYmd) {
        return false;
    }
    $start = trim((string) ($session['start_time'] ?? ''));
    $end = trim((string) ($session['end_time'] ?? ''));
    if ($start === '' && $end === '') {
        return true;
    }
    if ($start !== '') {
        $st = eventify_session_datetime($day, $start);
        if ($st !== null && $now < $st) {
            return false;
        }
    }
    if ($end !== '') {
        $et = eventify_session_datetime($day, $end);
        if ($et !== null && $now > $et) {
            return false;
        }
    }
    return true;
}

/**
 * @param list<array<string, mixed>> $sessions
 * @return array<string, list<array<string, mixed>>>
 */
function eventify_group_sessions_by_category(array $sessions): array
{
    $groups = [];
    foreach ($sessions as $s) {
        $cat = trim((string) ($s['category'] ?? ''));
        if ($cat === '') {
            $cat = 'Other';
        }
        if (!isset($groups[$cat])) {
            $groups[$cat] = [];
        }
        $groups[$cat][] = $s;
    }
    uksort($groups, static function ($a, $b) {
        if ($a === 'Other') {
            return 1;
        }
        if ($b === 'Other') {
            return -1;
        }
        return strcasecmp($a, $b);
    });
    return $groups;
}

/**
 * @param list<array<string, mixed>> $sessions
 * @return array<string, list<array<string, mixed>>>
 */
function eventify_group_sessions_by_date(array $sessions): array
{
    $groups = [];
    foreach ($sessions as $s) {
        $day = substr(trim((string) ($s['schedule_date'] ?? '')), 0, 10);
        if ($day === '') {
            continue;
        }
        if (!isset($groups[$day])) {
            $groups[$day] = [];
        }
        $groups[$day][] = $s;
    }
    ksort($groups);
    return $groups;
}

/** @return array<string, mixed>|null */
function eventify_load_activity_session(mysqli $conn, int $sessionId, int $eventId, ?int $viewerUserId = null): ?array
{
    if ($sessionId < 1 || $eventId < 1) {
        return null;
    }
    $sessions = eventify_load_event_day_sessions($conn, $eventId, null, $viewerUserId);
    foreach ($sessions as $s) {
        if ((int) ($s['id'] ?? 0) === $sessionId) {
            return $s;
        }
    }
    return null;
}

function eventify_ics_escape(string $text): string
{
    $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);
    return str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $text);
}

/**
 * Build an iCalendar (.ics) document for hub activities.
 *
 * @param list<array<string, mixed>> $sessions
 */
function eventify_build_ics_for_sessions(array $sessions, string $calendarName, string $eventTitle = ''): string
{
    $tz = eventify_app_timezone();
    $tzName = $tz->getName();
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//EVENTIFY//Activities Hub//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . eventify_ics_escape($calendarName),
    ];
    if ($eventTitle !== '') {
        $lines[] = 'X-WR-CALDESC:' . eventify_ics_escape($eventTitle);
    }

    $stamp = (new DateTimeImmutable('now', $tz))->format('Ymd\THis');
    foreach ($sessions as $session) {
        $sid = (int) ($session['id'] ?? 0);
        $title = trim((string) ($session['title'] ?? 'Activity'));
        if ($title === '') {
            $title = 'Activity';
        }
        $day = substr(trim((string) ($session['schedule_date'] ?? '')), 0, 10);
        if ($day === '') {
            continue;
        }
        $startTime = trim((string) ($session['start_time'] ?? ''));
        $endDt = eventify_session_effective_end_datetime($session);
        $startDt = $startTime !== ''
            ? eventify_session_datetime($day, $startTime)
            : DateTimeImmutable::createFromFormat('Y-m-d', $day, $tz);

        if (!$startDt instanceof DateTimeImmutable) {
            continue;
        }
        if (!$endDt instanceof DateTimeImmutable || $endDt <= $startDt) {
            $endDt = $startDt->modify('+1 hour');
        }

        $host = preg_replace('/[^a-z0-9.-]+/i', '-', $_SERVER['HTTP_HOST'] ?? 'eventify.local');
        $uid = 'eventify-activity-' . $sid . '@' . $host;
        $loc = trim((string) ($session['location'] ?? ''));
        $notes = trim((string) ($session['notes'] ?? ''));
        $descParts = [];
        if ($eventTitle !== '') {
            $descParts[] = $eventTitle;
        }
        if ($notes !== '') {
            $descParts[] = $notes;
        }
        $description = implode("\n", $descParts);

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . $stamp;
        $lines[] = 'DTSTART;TZID=' . $tzName . ':' . $startDt->format('Ymd\THis');
        $lines[] = 'DTEND;TZID=' . $tzName . ':' . $endDt->format('Ymd\THis');
        $lines[] = 'SUMMARY:' . eventify_ics_escape($title);
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . eventify_ics_escape($description);
        }
        if ($loc !== '') {
            $lines[] = 'LOCATION:' . eventify_ics_escape($loc);
        }
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}
