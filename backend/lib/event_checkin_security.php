<?php

/**
 * Shared check-in security: geofence, device lock, RSVP requirement.
 */

function eventify_checkin_config_require_rsvp(): bool
{
    return !defined('EVENTIFY_CHECKIN_REQUIRE_RSVP') || EVENTIFY_CHECKIN_REQUIRE_RSVP;
}

function eventify_checkin_config_geo_when_pinned(): bool
{
    return !defined('EVENTIFY_CHECKIN_GEO_WHEN_PINNED') || EVENTIFY_CHECKIN_GEO_WHEN_PINNED;
}

function eventify_checkin_geo_radius_m(): float
{
    if (defined('EVENTIFY_CHECKIN_GEO_RADIUS_M')) {
        return max(50.0, (float) EVENTIFY_CHECKIN_GEO_RADIUS_M);
    }
    return 300.0;
}

function eventify_haversine_distance_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth * $c;
}

/** @return array{lat: ?float, lng: ?float} */
function eventify_checkin_coords_from_row(array $row): array
{
    $lat = isset($row['latitude']) && $row['latitude'] !== '' && $row['latitude'] !== null
        ? (float) $row['latitude'] : null;
    $lng = isset($row['longitude']) && $row['longitude'] !== '' && $row['longitude'] !== null
        ? (float) $row['longitude'] : null;
    if ($lat === null || $lng === null) {
        return ['lat' => null, 'lng' => null];
    }
    return ['lat' => $lat, 'lng' => $lng];
}

/** @param array<string, mixed> $event */
function eventify_event_checkin_geo_required(array $event): bool
{
    if (array_key_exists('checkin_require_geo', $event) && $event['checkin_require_geo'] !== null && $event['checkin_require_geo'] !== '') {
        return (int) $event['checkin_require_geo'] === 1;
    }
    if (!eventify_checkin_config_geo_when_pinned()) {
        return false;
    }
    $coords = eventify_checkin_coords_from_row($event);
    return $coords['lat'] !== null && $coords['lng'] !== null;
}

/** @param array<string, mixed> $event */
function eventify_event_checkin_rsvp_required(array $event): bool
{
    if (array_key_exists('checkin_require_rsvp', $event) && $event['checkin_require_rsvp'] !== null && $event['checkin_require_rsvp'] !== '') {
        return (int) $event['checkin_require_rsvp'] === 1;
    }
    return eventify_checkin_config_require_rsvp();
}

function eventify_activity_checkin_require_session_rsvp(): bool
{
    return !defined('EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP') || EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP;
}

function eventify_student_has_session_rsvp(mysqli $conn, int $userId, int $sessionId): bool
{
    if ($userId < 1 || $sessionId < 1) {
        return false;
    }
    if (!function_exists('eventify_session_rsvps_table_exists')) {
        require_once __DIR__ . '/event_day_sessions.php';
    }
    if (!eventify_session_rsvps_table_exists($conn)) {
        return true;
    }
    $stmt = $conn->prepare('SELECT id FROM event_day_session_rsvps WHERE session_id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $sessionId, $userId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function eventify_student_has_event_registration(mysqli $conn, int $userId, int $eventId): bool
{
    if ($userId < 1 || $eventId < 1) {
        return false;
    }
    $stmt = $conn->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $userId, $eventId);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

/**
 * @param array<string, mixed> $post
 * @return array{ok: bool, error?: string, geo_lat: ?float, geo_lng: ?float, geo_accuracy: ?float, device_hash: string}
 */
function eventify_checkin_parse_security_post(array $post, bool $geoRequired): array
{
    $device_hash = trim((string) ($post['device_hash'] ?? ''));
    $geo_lat_raw = trim((string) ($post['geo_lat'] ?? ''));
    $geo_lng_raw = trim((string) ($post['geo_lng'] ?? ''));
    $geo_accuracy_raw = trim((string) ($post['geo_accuracy'] ?? ''));
    $geo_ts_raw = trim((string) ($post['geo_ts'] ?? ''));

    $geo_lat = is_numeric($geo_lat_raw) ? (float) $geo_lat_raw : null;
    $geo_lng = is_numeric($geo_lng_raw) ? (float) $geo_lng_raw : null;
    $geo_accuracy = is_numeric($geo_accuracy_raw) ? (float) $geo_accuracy_raw : null;
    $geo_ts = ctype_digit($geo_ts_raw) ? (int) $geo_ts_raw : 0;
    $server_now_ms = (int) round(microtime(true) * 1000);

    if ($device_hash === '' || strlen($device_hash) < 16) {
        return ['ok' => false, 'error' => 'Device verification failed. Please use a modern browser and try again.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => ''];
    }
    if ($geoRequired && ($geo_lat === null || $geo_lng === null)) {
        return ['ok' => false, 'error' => 'Live location is required to check in. Please allow location access and try again.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
    }
    if ($geoRequired && ($geo_ts <= 0 || abs($server_now_ms - $geo_ts) > 120000)) {
        return ['ok' => false, 'error' => 'Location check expired. Please refresh your location and try again.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
    }
    if ($geoRequired && $geo_accuracy !== null && $geo_accuracy > 2000) {
        return ['ok' => false, 'error' => 'Location accuracy is too low. Move to a better signal and try again.', 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
    }

    return ['ok' => true, 'geo_lat' => $geo_lat, 'geo_lng' => $geo_lng, 'geo_accuracy' => $geo_accuracy, 'device_hash' => $device_hash];
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_checkin_validate_geofence(?float $geo_lat, ?float $geo_lng, ?float $venue_lat, ?float $venue_lng, bool $geoRequired): array
{
    if (!$geoRequired || $venue_lat === null || $venue_lng === null) {
        return ['ok' => true];
    }
    if ($geo_lat === null || $geo_lng === null) {
        return ['ok' => false, 'error' => 'Live location is required to check in. Please allow location access and try again.'];
    }
    $distance_m = eventify_haversine_distance_m($geo_lat, $geo_lng, $venue_lat, $venue_lng);
    if ($distance_m > eventify_checkin_geo_radius_m()) {
        return ['ok' => false, 'error' => 'You are too far from the venue to check in. Please move closer and try again.'];
    }
    return ['ok' => true];
}

function eventify_checkin_ensure_main_device_locks_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS event_checkin_device_locks (
          id INT AUTO_INCREMENT PRIMARY KEY,
          event_id INT NOT NULL,
          user_id INT NOT NULL,
          device_hash VARCHAR(128) NOT NULL,
          first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          last_lat DECIMAL(10,7) NULL DEFAULT NULL,
          last_lng DECIMAL(10,7) NULL DEFAULT NULL,
          last_accuracy FLOAT NULL DEFAULT NULL,
          last_geo_at DATETIME NULL DEFAULT NULL,
          UNIQUE KEY uniq_event_device (event_id, device_hash),
          KEY idx_event_user (event_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function eventify_checkin_ensure_session_device_locks_table(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS event_session_checkin_device_locks (
          id INT AUTO_INCREMENT PRIMARY KEY,
          session_id INT NOT NULL,
          user_id INT NOT NULL,
          device_hash VARCHAR(128) NOT NULL,
          first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          last_lat DECIMAL(10,7) NULL DEFAULT NULL,
          last_lng DECIMAL(10,7) NULL DEFAULT NULL,
          last_accuracy FLOAT NULL DEFAULT NULL,
          last_geo_at DATETIME NULL DEFAULT NULL,
          UNIQUE KEY uniq_session_device (session_id, device_hash),
          KEY idx_session_user (session_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_checkin_apply_main_device_lock(
    mysqli $conn,
    int $eventId,
    int $userId,
    string $deviceHash,
    ?float $geoLat,
    ?float $geoLng,
    ?float $geoAccuracy
): array {
    eventify_checkin_ensure_main_device_locks_table($conn);
    $lockSel = $conn->prepare('SELECT user_id FROM event_checkin_device_locks WHERE event_id = ? AND device_hash = ? LIMIT 1 FOR UPDATE');
    if (!$lockSel) {
        return ['ok' => false, 'error' => 'Security lock check failed.'];
    }
    $lockSel->bind_param('is', $eventId, $deviceHash);
    $lockSel->execute();
    $lockRow = $lockSel->get_result()->fetch_assoc();
    $lockSel->close();

    if ($lockRow && (int) $lockRow['user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'This device already checked in another account for this event.'];
    }

    if ($lockRow) {
        $updLock = $conn->prepare('UPDATE event_checkin_device_locks SET last_seen_at = NOW(), last_lat = ?, last_lng = ?, last_accuracy = ?, last_geo_at = NOW() WHERE event_id = ? AND device_hash = ? AND user_id = ?');
        if (!$updLock) {
            return ['ok' => false, 'error' => 'Could not update device lock.'];
        }
        $updLock->bind_param('dddisi', $geoLat, $geoLng, $geoAccuracy, $eventId, $deviceHash, $userId);
        $updLock->execute();
        $updLock->close();
    } else {
        $insLock = $conn->prepare('INSERT INTO event_checkin_device_locks (event_id, user_id, device_hash, last_lat, last_lng, last_accuracy, last_geo_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        if (!$insLock) {
            return ['ok' => false, 'error' => 'Could not create device lock.'];
        }
        $insLock->bind_param('iisddd', $eventId, $userId, $deviceHash, $geoLat, $geoLng, $geoAccuracy);
        $insLock->execute();
        $insLock->close();
    }
    return ['ok' => true];
}

/**
 * @return array{ok: bool, error?: string}
 */
function eventify_checkin_apply_session_device_lock(
    mysqli $conn,
    int $sessionId,
    int $userId,
    string $deviceHash,
    ?float $geoLat,
    ?float $geoLng,
    ?float $geoAccuracy
): array {
    eventify_checkin_ensure_session_device_locks_table($conn);
    $lockSel = $conn->prepare('SELECT user_id FROM event_session_checkin_device_locks WHERE session_id = ? AND device_hash = ? LIMIT 1 FOR UPDATE');
    if (!$lockSel) {
        return ['ok' => false, 'error' => 'Security lock check failed.'];
    }
    $lockSel->bind_param('is', $sessionId, $deviceHash);
    $lockSel->execute();
    $lockRow = $lockSel->get_result()->fetch_assoc();
    $lockSel->close();

    if ($lockRow && (int) $lockRow['user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'This device already checked in another account for this activity.'];
    }

    if ($lockRow) {
        $updLock = $conn->prepare('UPDATE event_session_checkin_device_locks SET last_seen_at = NOW(), last_lat = ?, last_lng = ?, last_accuracy = ?, last_geo_at = NOW() WHERE session_id = ? AND device_hash = ? AND user_id = ?');
        if (!$updLock) {
            return ['ok' => false, 'error' => 'Could not update device lock.'];
        }
        $updLock->bind_param('dddisi', $geoLat, $geoLng, $geoAccuracy, $sessionId, $deviceHash, $userId);
        $updLock->execute();
        $updLock->close();
    } else {
        $insLock = $conn->prepare('INSERT INTO event_session_checkin_device_locks (session_id, user_id, device_hash, last_lat, last_lng, last_accuracy, last_geo_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        if (!$insLock) {
            return ['ok' => false, 'error' => 'Could not create device lock.'];
        }
        $insLock->bind_param('iisddd', $sessionId, $userId, $deviceHash, $geoLat, $geoLng, $geoAccuracy);
        $insLock->execute();
        $insLock->close();
    }
    return ['ok' => true];
}

/**
 * @param array<string, mixed> $session Row with latitude/longitude; optional event_latitude/event_longitude
 */
function eventify_session_checkin_geo_required(array $session): bool
{
    $coords = eventify_checkin_coords_from_row($session);
    if ($coords['lat'] !== null && $coords['lng'] !== null) {
        return eventify_checkin_config_geo_when_pinned();
    }
    if (isset($session['event_latitude'], $session['event_longitude'])) {
        $parent = eventify_checkin_coords_from_row([
            'latitude' => $session['event_latitude'],
            'longitude' => $session['event_longitude'],
        ]);
        if ($parent['lat'] !== null && $parent['lng'] !== null) {
            return eventify_checkin_config_geo_when_pinned();
        }
    }
    return false;
}

/** @return array{lat: ?float, lng: ?float} */
function eventify_session_checkin_venue_coords(array $session): array
{
    $coords = eventify_checkin_coords_from_row($session);
    if ($coords['lat'] !== null && $coords['lng'] !== null) {
        return $coords;
    }
    return eventify_checkin_coords_from_row([
        'latitude' => $session['event_latitude'] ?? null,
        'longitude' => $session['event_longitude'] ?? null,
    ]);
}

/**
 * Process main event check-in POST (device lock + present status).
 *
 * @param array<string, mixed> $event
 * @param array<string, mixed> $post
 * @return array{ok: bool, error?: string}
 */
function eventify_process_main_event_checkin(mysqli $conn, array $event, int $userId, array $post): array
{
    $eventId = (int) ($event['id'] ?? 0);
    if ($eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid event.'];
    }

    if (eventify_event_checkin_rsvp_required($event) && !eventify_student_has_event_registration($conn, $userId, $eventId)) {
        return ['ok' => false, 'error' => 'RSVP for this event first, then check in at the venue.'];
    }

    $geoRequired = eventify_event_checkin_geo_required($event);
    $parsed = eventify_checkin_parse_security_post($post, $geoRequired);
    if (!$parsed['ok']) {
        return ['ok' => false, 'error' => $parsed['error'] ?? 'Check-in failed.'];
    }

    $venue = eventify_checkin_coords_from_row($event);
    $geoVal = eventify_checkin_validate_geofence(
        $parsed['geo_lat'],
        $parsed['geo_lng'],
        $venue['lat'],
        $venue['lng'],
        $geoRequired
    );
    if (!$geoVal['ok']) {
        return $geoVal;
    }

    try {
        $conn->begin_transaction();
        $lock = eventify_checkin_apply_main_device_lock(
            $conn,
            $eventId,
            $userId,
            $parsed['device_hash'],
            $parsed['geo_lat'],
            $parsed['geo_lng'],
            $parsed['geo_accuracy']
        );
        if (!$lock['ok']) {
            throw new RuntimeException($lock['error'] ?? 'Device lock failed.');
        }

        $stmt = $conn->prepare("INSERT INTO registrations (user_id, event_id, status, time_in) VALUES (?, ?, 'present', NOW()) ON DUPLICATE KEY UPDATE status = 'present', time_in = NOW()");
        if (!$stmt) {
            throw new RuntimeException('Could not record attendance.');
        }
        $stmt->bind_param('ii', $userId, $eventId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => $e->getMessage() !== 'Could not record attendance.' ? $e->getMessage() : 'Could not record attendance. Please try again.'];
    }
}

/**
 * @param array<string, mixed> $session
 * @return array{ok: bool, error?: string}
 */
function eventify_process_activity_checkin(mysqli $conn, array $session, int $userId, array $post): array
{
    $sessionId = (int) ($session['id'] ?? 0);
    $eventId = (int) ($session['event_id'] ?? 0);
    if ($sessionId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid activity.'];
    }

    if (!eventify_student_has_event_registration($conn, $userId, $eventId)) {
        return ['ok' => false, 'error' => 'Register for the main event before checking in to this activity.'];
    }

    if (eventify_activity_checkin_require_session_rsvp() && !eventify_student_has_session_rsvp($conn, $userId, $sessionId)) {
        return ['ok' => false, 'error' => 'RSVP for this activity in the Activities hub first, then check in at the venue.'];
    }

    $geoRequired = eventify_session_checkin_geo_required($session);
    $parsed = eventify_checkin_parse_security_post($post, $geoRequired);
    if (!$parsed['ok']) {
        return ['ok' => false, 'error' => $parsed['error'] ?? 'Check-in failed.'];
    }

    $venue = eventify_session_checkin_venue_coords($session);
    $geoVal = eventify_checkin_validate_geofence(
        $parsed['geo_lat'],
        $parsed['geo_lng'],
        $venue['lat'],
        $venue['lng'],
        $geoRequired
    );
    if (!$geoVal['ok']) {
        return $geoVal;
    }

    try {
        $conn->begin_transaction();
        $lock = eventify_checkin_apply_session_device_lock(
            $conn,
            $sessionId,
            $userId,
            $parsed['device_hash'],
            $parsed['geo_lat'],
            $parsed['geo_lng'],
            $parsed['geo_accuracy']
        );
        if (!$lock['ok']) {
            throw new RuntimeException($lock['error'] ?? 'Device lock failed.');
        }

        $ins = $conn->prepare('INSERT INTO event_day_session_attendance (session_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE checked_in_at = CURRENT_TIMESTAMP');
        if (!$ins) {
            throw new RuntimeException('Could not record check-in.');
        }
        $ins->bind_param('ii', $sessionId, $userId);
        $ins->execute();
        $ins->close();
        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => $e->getMessage() !== 'Could not record check-in.' ? $e->getMessage() : 'Could not record check-in. Please try again.'];
    }
}

/**
 * Process paid ticket check-in with device lock + optional geofence.
 *
 * @param array<string, mixed> $ticket Row from eventify_load_ticket_by_checkin_token
 * @param array<string, mixed> $post
 * @return array{ok: bool, error?: string}
 */
function eventify_process_ticket_checkin(mysqli $conn, array $ticket, int $userId, array $post): array
{
    $ticketId = (int) ($ticket['id'] ?? 0);
    $eventId = (int) ($ticket['event_id'] ?? 0);
    if ($ticketId < 1 || $eventId < 1) {
        return ['ok' => false, 'error' => 'Invalid ticket.'];
    }
    if ($userId !== (int) ($ticket['user_id'] ?? 0)) {
        return ['ok' => false, 'error' => 'This ticket belongs to another student.'];
    }

    $event = [
        'id' => $eventId,
        'latitude' => $ticket['latitude'] ?? $ticket['event_latitude'] ?? null,
        'longitude' => $ticket['longitude'] ?? $ticket['event_longitude'] ?? null,
        'checkin_require_geo' => $ticket['checkin_require_geo'] ?? null,
    ];
    $geoRequired = eventify_event_checkin_geo_required($event);
    $parsed = eventify_checkin_parse_security_post($post, $geoRequired);
    if (!$parsed['ok']) {
        return ['ok' => false, 'error' => $parsed['error'] ?? 'Check-in failed.'];
    }

    $venue = eventify_checkin_coords_from_row($event);
    $geoVal = eventify_checkin_validate_geofence(
        $parsed['geo_lat'],
        $parsed['geo_lng'],
        $venue['lat'],
        $venue['lng'],
        $geoRequired
    );
    if (!$geoVal['ok']) {
        return $geoVal;
    }

    try {
        $conn->begin_transaction();
        $lock = eventify_checkin_apply_main_device_lock(
            $conn,
            $eventId,
            $userId,
            $parsed['device_hash'],
            $parsed['geo_lat'],
            $parsed['geo_lng'],
            $parsed['geo_accuracy']
        );
        if (!$lock['ok']) {
            throw new RuntimeException($lock['error'] ?? 'Device lock failed.');
        }

        if (!function_exists('eventify_mark_ticket_used')) {
            require_once __DIR__ . '/event_ticketing.php';
        }
        $result = eventify_mark_ticket_used($conn, $ticketId, $userId);
        if (!$result['ok']) {
            throw new RuntimeException($result['error'] ?? 'Check-in failed.');
        }
        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $e2) {
        }
        return ['ok' => false, 'error' => $e->getMessage() !== 'Check-in failed.' ? $e->getMessage() : 'Check-in failed. Please try again.'];
    }
}
