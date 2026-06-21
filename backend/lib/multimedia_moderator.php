<?php

require_once __DIR__ . '/event_photos.php';
require_once __DIR__ . '/activity_logger.php';

function eventify_users_ensure_multimedia_moderator_column(mysqli $conn): bool
{
    try {
        $c = $conn->query("SHOW COLUMNS FROM users LIKE 'is_multimedia_moderator'");
        if ($c && $c->num_rows > 0) {
            return true;
        }
        return (bool) $conn->query(
            "ALTER TABLE users ADD COLUMN is_multimedia_moderator TINYINT(1) NOT NULL DEFAULT 0 AFTER role"
        );
    } catch (Throwable $e) {
        return false;
    }
}

function eventify_user_is_multimedia_moderator(mysqli $conn, int $userId): bool
{
    if ($userId < 1) {
        return false;
    }
    eventify_users_ensure_multimedia_moderator_column($conn);
    $stmt = $conn->prepare(
        "SELECT is_multimedia_moderator FROM users WHERE id = ? AND role = 'multimedia' AND status = 'active' LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) ((int) ($row['is_multimedia_moderator'] ?? 0) === 1);
}

/** @return list<array<string, mixed>> */
function eventify_load_pending_photos_for_moderator(mysqli $conn, int $limit = 80): array
{
    if (!eventify_event_photos_has_status($conn)) {
        return [];
    }
    eventify_event_photos_ensure_session_column($conn);
    $limit = max(1, min(200, $limit));
    $sql = "
        SELECT p.id, p.event_id, p.session_id, p.file_path, p.uploaded_by, p.created_at,
               e.title AS event_title,
               u.name AS uploader_name,
               s.title AS session_title
        FROM event_photos p
        INNER JOIN events e ON e.id = p.event_id
        INNER JOIN users u ON u.id = p.uploaded_by
        LEFT JOIN event_day_sessions s ON s.id = p.session_id
        WHERE p.status = 'draft'
        ORDER BY p.created_at ASC, p.id ASC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return is_array($rows) ? $rows : [];
}

function eventify_count_pending_photos(mysqli $conn): int
{
    if (!eventify_event_photos_has_status($conn)) {
        return 0;
    }
    $res = $conn->query("SELECT COUNT(*) AS c FROM event_photos WHERE status = 'draft'");
    if (!$res || !($row = $res->fetch_assoc())) {
        return 0;
    }
    return (int) ($row['c'] ?? 0);
}

/** @return list<string> */
function eventify_photo_activity_actions(): array
{
    return ['photo_uploaded', 'photo_approved', 'photo_rejected', 'photo_bulk_approved'];
}

function eventify_photo_activity_label(string $action): string
{
    $map = [
        'photo_uploaded' => 'Photo uploaded',
        'photo_approved' => 'Photo approved',
        'photo_rejected' => 'Photo rejected',
        'photo_bulk_approved' => 'Bulk approved',
    ];
    return $map[$action] ?? ucwords(str_replace('_', ' ', $action));
}

function eventify_photo_activity_context_suffix(string $eventTitle, string $sessionTitle = ''): string
{
    $eventLabel = $eventTitle !== '' ? '"' . $eventTitle . '"' : 'an event';
    if ($sessionTitle !== '') {
        return ' for ' . $eventLabel . ' · activity "' . $sessionTitle . '"';
    }
    return ' for ' . $eventLabel;
}

function eventify_moderator_display_name(mysqli $conn, int $userId): string
{
    if ($userId < 1) {
        return 'Moderator';
    }
    $stmt = $conn->prepare('SELECT name, user_id FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'Moderator';
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 'Moderator';
    }
    $name = trim((string) ($row['name'] ?? ''));
    $uid = trim((string) ($row['user_id'] ?? ''));
    if ($name !== '' && $uid !== '') {
        return $name . ' (' . $uid . ')';
    }
    return $name !== '' ? $name : 'Moderator';
}

function eventify_log_photo_activity(
    mysqli $conn,
    ?int $actorId,
    ?string $actorRole,
    string $action,
    string $targetType,
    ?int $targetId,
    string $details
): void {
    if (!in_array($action, eventify_photo_activity_actions(), true)) {
        return;
    }
    log_activity($conn, $actorId, $actorRole, $action, $targetType, $targetId, $details);
}

/** @return list<array<string, mixed>> */
function eventify_load_multimedia_photo_activity_logs(mysqli $conn, int $limit = 50): array
{
    $actions = eventify_photo_activity_actions();
    $limit = max(1, min(100, $limit));
    $escaped = array_map(static function ($action) use ($conn) {
        return "'" . $conn->real_escape_string($action) . "'";
    }, $actions);
    $in = implode(',', $escaped);
    $sql = "
        SELECT l.id, l.actor_id, l.actor_role, l.action, l.target_type, l.target_id, l.details, l.created_at,
               u.name AS actor_name, u.user_id AS actor_user_id
        FROM activity_logs l
        LEFT JOIN users u ON l.actor_id = u.id
        WHERE l.action IN ($in)
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT $limit
    ";
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    return is_array($rows) ? $rows : [];
}

/** @return array{uploader_id: int, event_id: int, event_title: string, session_title: string, uploader_name: string}|null */
function eventify_photo_moderation_context(mysqli $conn, int $photoId): ?array
{
    if ($photoId < 1) {
        return null;
    }
    eventify_event_photos_ensure_session_column($conn);
    $sql = "
        SELECT p.uploaded_by, p.event_id, e.title AS event_title, s.title AS session_title,
               u.name AS uploader_name
        FROM event_photos p
        INNER JOIN events e ON e.id = p.event_id
        INNER JOIN users u ON u.id = p.uploaded_by
        LEFT JOIN event_day_sessions s ON s.id = p.session_id
        WHERE p.id = ? AND p.status = 'draft'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    return [
        'uploader_id' => (int) ($row['uploaded_by'] ?? 0),
        'event_id' => (int) ($row['event_id'] ?? 0),
        'event_title' => trim((string) ($row['event_title'] ?? '')),
        'session_title' => trim((string) ($row['session_title'] ?? '')),
        'uploader_name' => trim((string) ($row['uploader_name'] ?? '')),
    ];
}

/** @return array{title: string, message: string, type: string} */
function eventify_photo_moderation_notification_copy(string $action, string $eventTitle, string $sessionTitle, int $count = 1): array
{
    $eventLabel = $eventTitle !== '' ? $eventTitle : 'an event';
    $activitySuffix = $sessionTitle !== '' ? ' for activity "' . $sessionTitle . '"' : '';
    $approved = $action === 'approved';

    if ($approved) {
        $title = $count > 1 ? 'Photos approved' : 'Photo approved';
        if ($count > 1) {
            $message = 'Your moderator approved ' . $count . ' of your photos for "' . $eventLabel . '"' . $activitySuffix . '. They are now visible to students.';
        } else {
            $message = 'Your photo for "' . $eventLabel . '"' . $activitySuffix . ' was approved and is now visible to students.';
        }
        return ['title' => $title, 'message' => $message, 'type' => 'photo_approved'];
    }

    $title = $count > 1 ? 'Photos rejected' : 'Photo rejected';
    if ($count > 1) {
        $message = 'Your moderator rejected ' . $count . ' of your photos for "' . $eventLabel . '"' . $activitySuffix . '. They will not appear for students.';
    } else {
        $message = 'Your photo for "' . $eventLabel . '"' . $activitySuffix . ' was rejected and will not appear for students.';
    }
    return ['title' => $title, 'message' => $message, 'type' => 'photo_rejected'];
}

function eventify_notify_multimedia_photo_moderation(
    mysqli $conn,
    int $uploaderId,
    int $eventId,
    string $action,
    string $eventTitle = '',
    string $sessionTitle = '',
    int $count = 1
): void {
    if ($uploaderId < 1 || $eventId < 1 || !in_array($action, ['approved', 'rejected'], true)) {
        return;
    }
    $count = max(1, $count);
    $copy = eventify_photo_moderation_notification_copy($action, $eventTitle, $sessionTitle, $count);
    try {
        $ins = $conn->prepare(
            'INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            return;
        }
        $ins->bind_param(
            'isssi',
            $uploaderId,
            $copy['type'],
            $copy['title'],
            $copy['message'],
            $eventId
        );
        $ins->execute();
        $ins->close();
    } catch (Throwable $e) {
        // ignore if notifications table is unavailable
    }
}

function eventify_moderator_approve_photo(mysqli $conn, int $photoId, int $moderatorId): bool
{
    if ($photoId < 1 || !eventify_event_photos_has_status($conn)) {
        return false;
    }
    $context = eventify_photo_moderation_context($conn, $photoId);
    $stmt = $conn->prepare(
        "UPDATE event_photos SET status = 'published', published_at = NOW() WHERE id = ? AND status = 'draft'"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if ($ok && $context) {
        eventify_notify_multimedia_photo_moderation(
            $conn,
            (int) $context['uploader_id'],
            (int) $context['event_id'],
            'approved',
            (string) $context['event_title'],
            (string) $context['session_title']
        );
        $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
        $uploaderName = (string) ($context['uploader_name'] ?? '') ?: 'Multimedia user';
        $details = $moderatorName . ' approved a photo uploaded by ' . $uploaderName
            . eventify_photo_activity_context_suffix((string) $context['event_title'], (string) $context['session_title']);
        eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_approved', 'event_photo', $photoId, $details);
    }
    return $ok;
}

function eventify_moderator_reject_photo(mysqli $conn, int $photoId, int $moderatorId = 0): bool
{
    if ($photoId < 1 || !eventify_event_photos_has_status($conn)) {
        return false;
    }
    $context = eventify_photo_moderation_context($conn, $photoId);
    $stmt = $conn->prepare(
        "UPDATE event_photos SET status = 'rejected', published_at = NULL WHERE id = ? AND status = 'draft'"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $photoId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    if ($ok && $context) {
        eventify_notify_multimedia_photo_moderation(
            $conn,
            (int) $context['uploader_id'],
            (int) $context['event_id'],
            'rejected',
            (string) $context['event_title'],
            (string) $context['session_title']
        );
        if ($moderatorId > 0) {
            $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
            $uploaderName = (string) ($context['uploader_name'] ?? '') ?: 'Multimedia user';
            $details = $moderatorName . ' rejected a photo uploaded by ' . $uploaderName
                . eventify_photo_activity_context_suffix((string) $context['event_title'], (string) $context['session_title']);
            eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_rejected', 'event_photo', $photoId, $details);
        }
    }
    return $ok;
}

/** @return list<array{uploader_id: int, event_id: int, event_title: string, session_title: string}> */
function eventify_load_draft_photos_for_moderation_batch(mysqli $conn, int $eventId, int $sessionId): array
{
    if ($eventId < 1 || !eventify_event_photos_has_status($conn)) {
        return [];
    }
    eventify_event_photos_ensure_session_column($conn);
    if ($sessionId > 0) {
        $sql = "
            SELECT p.uploaded_by, p.event_id, e.title AS event_title, s.title AS session_title
            FROM event_photos p
            INNER JOIN events e ON e.id = p.event_id
            LEFT JOIN event_day_sessions s ON s.id = p.session_id
            WHERE p.event_id = ? AND p.session_id = ? AND p.status = 'draft'
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $eventId, $sessionId);
    } else {
        $sql = "
            SELECT p.uploaded_by, p.event_id, e.title AS event_title, s.title AS session_title
            FROM event_photos p
            INNER JOIN events e ON e.id = p.event_id
            LEFT JOIN event_day_sessions s ON s.id = p.session_id
            WHERE p.event_id = ? AND p.session_id IS NULL AND p.status = 'draft'
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $eventId);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'uploader_id' => (int) ($row['uploaded_by'] ?? 0),
            'event_id' => (int) ($row['event_id'] ?? 0),
            'event_title' => trim((string) ($row['event_title'] ?? '')),
            'session_title' => trim((string) ($row['session_title'] ?? '')),
        ];
    }
    return $out;
}

function eventify_moderator_notify_bulk_photo_approvals(mysqli $conn, array $approvedRows): void
{
    if ($approvedRows === []) {
        return;
    }
    /** @var array<string, array{uploader_id: int, event_id: int, event_title: string, session_title: string, count: int}> $groups */
    $groups = [];
    foreach ($approvedRows as $row) {
        $uploaderId = (int) ($row['uploader_id'] ?? 0);
        $eventId = (int) ($row['event_id'] ?? 0);
        if ($uploaderId < 1 || $eventId < 1) {
            continue;
        }
        $sessionTitle = (string) ($row['session_title'] ?? '');
        $key = $uploaderId . ':' . $eventId . ':' . $sessionTitle;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'uploader_id' => $uploaderId,
                'event_id' => $eventId,
                'event_title' => (string) ($row['event_title'] ?? ''),
                'session_title' => $sessionTitle,
                'count' => 0,
            ];
        }
        $groups[$key]['count']++;
    }
    foreach ($groups as $group) {
        eventify_notify_multimedia_photo_moderation(
            $conn,
            (int) $group['uploader_id'],
            (int) $group['event_id'],
            'approved',
            (string) $group['event_title'],
            (string) $group['session_title'],
            (int) $group['count']
        );
    }
}

function eventify_moderator_approve_event_drafts(mysqli $conn, int $eventId, int $sessionId, int $moderatorId = 0): int
{
    if ($eventId < 1 || !eventify_event_photos_has_status($conn)) {
        return 0;
    }
    $pendingRows = eventify_load_draft_photos_for_moderation_batch($conn, $eventId, $sessionId);
    if ($pendingRows === []) {
        return 0;
    }
    eventify_event_photos_ensure_session_column($conn);
    if ($sessionId > 0) {
        $stmt = $conn->prepare(
            "UPDATE event_photos SET status = 'published', published_at = NOW() WHERE event_id = ? AND session_id = ? AND status = 'draft'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ii', $eventId, $sessionId);
    } else {
        $stmt = $conn->prepare(
            "UPDATE event_photos SET status = 'published', published_at = NOW() WHERE event_id = ? AND session_id IS NULL AND status = 'draft'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $eventId);
    }
    $stmt->execute();
    $n = (int) $stmt->affected_rows;
    $stmt->close();
    if ($n > 0) {
        eventify_moderator_notify_bulk_photo_approvals($conn, $pendingRows);
        if ($moderatorId > 0) {
            $eventTitle = (string) ($pendingRows[0]['event_title'] ?? '');
            $sessionTitle = $sessionId > 0 ? (string) ($pendingRows[0]['session_title'] ?? '') : '';
            $moderatorName = eventify_moderator_display_name($conn, $moderatorId);
            $details = $moderatorName . ' approved ' . $n . ' pending photo(s)'
                . eventify_photo_activity_context_suffix($eventTitle, $sessionTitle);
            eventify_log_photo_activity($conn, $moderatorId, 'multimedia', 'photo_bulk_approved', 'event', $eventId, $details);
        }
    }
    return $n;
}

function eventify_moderator_require(mysqli $conn, int $userId): void
{
    if (($_SESSION['role'] ?? '') !== 'multimedia' || !eventify_user_is_multimedia_moderator($conn, $userId)) {
        header('Location: ' . BASE_URL . '/backend/auth/dashboard_multimedia.php?msg=' . urlencode('Only a multimedia moderator can approve or reject photos.'));
        exit();
    }
}
