<?php

/**
 * Admin ↔ Organizer direct messages (staff_messages table).
 */

function eventify_staff_messages_ensure_table(mysqli $conn): bool
{
    static $done = false;
    if ($done) {
        return true;
    }
    try {
        $conn->query("
            CREATE TABLE IF NOT EXISTS staff_messages (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_id INT NOT NULL,
                body VARCHAR(8000) NOT NULL,
                attachment_path VARCHAR(512) NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME NULL DEFAULT NULL,
                KEY idx_pair_time (sender_id, recipient_id, created_at),
                KEY idx_inbox (recipient_id, read_at, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $col = $conn->query("SHOW COLUMNS FROM staff_messages LIKE 'attachment_path'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE staff_messages ADD COLUMN attachment_path VARCHAR(512) NULL DEFAULT NULL AFTER body");
        }
        if ($col) {
            $col->free();
        }
        $done = true;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return 'admin'|'organizer'|null
 */
function eventify_staff_user_role(mysqli $conn, int $userId): ?string
{
    if ($userId < 1) {
        return null;
    }
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $r = strtolower((string)($row['role'] ?? ''));
    if ($r === 'admin' || $r === 'organizer') {
        return $r;
    }
    return null;
}

/**
 * True if exactly one party is admin and the other is organizer.
 */
function eventify_staff_messaging_pair_allowed(mysqli $conn, int $userA, int $userB): bool
{
    if ($userA < 1 || $userB < 1 || $userA === $userB) {
        return false;
    }
    $ra = eventify_staff_user_role($conn, $userA);
    $rb = eventify_staff_user_role($conn, $userB);
    if ($ra === null || $rb === null) {
        return false;
    }
    return ($ra === 'admin' && $rb === 'organizer') || ($ra === 'organizer' && $rb === 'admin');
}
