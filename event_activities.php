<?php

/**

 * Activities hub — browse sub-activities inside a parent event.

 */

session_start();

if (!defined('BASE_URL')) {

    define('BASE_URL', '/school_events');

}

include __DIR__ . '/config/db.php';

include __DIR__ . '/config/config.php';

include __DIR__ . '/config/csrf.php';

require_once __DIR__ . '/backend/lib/event_day_sessions.php';

require_once __DIR__ . '/backend/lib/event_calendar.php';

require_once __DIR__ . '/backend/lib/event_ticketing.php';

require_once __DIR__ . '/backend/lib/event_checkin_security.php';

require_once __DIR__ . '/backend/lib/event_photos.php';

require_once __DIR__ . '/backend/lib/multimedia_moderator.php';

if (!isset($_SESSION['user_id'])) {

    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode(

        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/event_activities.php?' . http_build_query($_GET)

    ));

    exit();

}



$userId = (int) $_SESSION['user_id'];

$role = $_SESSION['role'] ?? '';
$menuUserName = trim((string) ($_SESSION['name'] ?? ''));

$eventId = (int) ($_GET['id'] ?? 0);

$categoryFilter = trim((string) ($_GET['category'] ?? ''));

$dayFilter = substr(trim((string) ($_GET['day'] ?? '')), 0, 10);

$activityId = (int) ($_GET['activity'] ?? 0);

$todayYmd = eventify_today_ymd();



if ($eventId < 1) {

    header('Location: ' . BASE_URL . '?error=' . urlencode('Invalid event'));

    exit();

}



eventify_event_day_sessions_ensure_enhanced($conn);

$event = eventify_load_event_for_activities_hub($conn, $eventId);

if (!$event) {

    header('Location: ' . BASE_URL . '?error=' . urlencode('Event not found'));

    exit();

}



$studentDept = null;

if ($role === 'student') {

    $du = $conn->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');

    if ($du) {

        $du->bind_param('i', $userId);

        $du->execute();

        $dr = $du->get_result()->fetch_assoc();

        $du->close();

        $studentDept = $dr['department'] ?? null;

    }

}



if (!eventify_user_can_view_event_activities($conn, $event, $userId, $role, $studentDept)) {

    header('Location: ' . BASE_URL . '/views/login.php?error=' . urlencode('Access denied'));

    exit();

}

$_SESSION['eventify_main_hub_event_id'] = $eventId;



$viewerId = in_array($role, ['student', 'organizer'], true) ? $userId : null;

$allSessions = eventify_load_event_day_sessions($conn, $eventId, null, $viewerId);

$byCategory = eventify_group_sessions_by_category($allSessions);

$byDate = eventify_group_sessions_by_date($allSessions);



$liveSessions = array_values(array_filter($allSessions, static function ($s) use ($todayYmd) {

    return eventify_session_is_live_now($s, $todayYmd);

}));



$todaySessions = array_values(array_filter($allSessions, static function ($s) use ($todayYmd) {

    return substr((string) ($s['schedule_date'] ?? ''), 0, 10) === $todayYmd;

}));



$hubUrl = BASE_URL . '/event_activities.php?id=' . $eventId;

$isOrganizer = $role === 'organizer' && (int) ($event['organizer_id'] ?? 0) === $userId;

$isStudent = $role === 'student';

$isMultimedia = $role === 'multimedia';
$isMultimediaModerator = $isMultimedia && eventify_user_is_multimedia_moderator($conn, $userId);

$hubMsg = trim((string) ($_GET['msg'] ?? ''));

$mySessions = [];
if ($isStudent) {
    $mySessions = eah_sort_sessions_by_time(array_values(array_filter($allSessions, static function ($s) {
        return !empty($s['user_rsvped']);
    })));
}

$studentEventTickets = [];
$eventUsesPaidTickets = false;
$eventTicketSalesOpen = false;
$activityTicketSalesOpen = false;
if ($isStudent && $event) {
    eventify_ticketing_ensure_schema($conn);
    $studentEventTickets = eventify_load_user_tickets($conn, $userId, $eventId);
    $eventUsesPaidTickets = eventify_event_uses_paid_ticketing($event);
    $eventTicketSalesOpen = $eventUsesPaidTickets && eventify_event_is_live($event);
    $activityTicketSalesOpen = eventify_event_is_live($event) && eventify_event_allows_ticket_shop($conn, $event);
}

$csrfToken = function_exists('csrf_token') ? csrf_token() : '';

$daySessionsHaveGeo = false;
$eventScheduleDates = [];
$defaultActivityDay = '';
$openAddActivity = isset($_GET['open_add_activity']) && (string) $_GET['open_add_activity'] === '1';

if ($isOrganizer) {
    $daySessionsHaveGeo = eventify_day_sessions_have_geo_columns($conn);
    $eventsForAttach = [$event];
    eventify_events_attach_schedule_dates($conn, $eventsForAttach);
    $event = $eventsForAttach[0];
    $eventScheduleDates = eventify_event_calendar_display_dates($event);
    if ($eventScheduleDates === []) {
        $d = substr((string) ($event['date'] ?? ''), 0, 10);
        if ($d !== '') {
            $eventScheduleDates = [$d];
        }
    }
    $defaultActivityDay = $dayFilter !== '' && in_array($dayFilter, $eventScheduleDates, true)
        ? $dayFilter
        : (in_array($todayYmd, $eventScheduleDates, true) ? $todayYmd : ($eventScheduleDates[0] ?? ''));
}

$eventScheduleEditable = true;
$eventHasEditableScheduleDay = true;
$scheduleLockMessage = '';
$eventCanEndEarly = false;
if ($isOrganizer) {
    $dayLock = eventify_organizer_event_has_editable_schedule_day($conn, $eventId, $eventScheduleDates);
    $eventHasEditableScheduleDay = $dayLock['ok'];
    if (!$eventHasEditableScheduleDay) {
        $scheduleLockMessage = $dayLock['error'];
    }
    if ($defaultActivityDay !== '') {
        $defaultDayLock = eventify_organizer_can_edit_event_schedule($conn, $eventId, $defaultActivityDay);
        $eventScheduleEditable = $defaultDayLock['ok'];
        if (!$eventScheduleEditable && $scheduleLockMessage === '') {
            $scheduleLockMessage = $defaultDayLock['error'];
        }
    } else {
        $eventScheduleEditable = $eventHasEditableScheduleDay;
    }
    $eventCanEndEarly = strtolower(trim((string) ($event['status'] ?? ''))) === 'active';
}


$backUrl = BASE_URL . '/backend/auth/dashboard_student.php';

if ($role === 'organizer') {

    $backUrl = BASE_URL . '/backend/auth/dashboardorganizer.php';

} elseif ($role === 'admin') {

    $backUrl = BASE_URL . '/backend/admin/dashboard.php';

} elseif ($role === 'super_admin') {

    $backUrl = BASE_URL . '/backend/super_admin/dashboardsuperadmin.php';

} elseif ($role === 'multimedia') {

    $backUrl = BASE_URL . '/backend/auth/dashboard_multimedia.php';

}

$activities_hub_events = [];
try {
    $activities_hub_events = eventify_load_activities_hub_picker_events($conn, $userId, $role, $studentDept);
} catch (Throwable $e) {
    $activities_hub_events = [];
}
$activities_hub_current_in_list = false;
foreach ($activities_hub_events as $hubEv) {
    if ((int) ($hubEv['id'] ?? 0) === $eventId) {
        $activities_hub_current_in_list = true;
        break;
    }
}
if (!$activities_hub_current_in_list) {
    array_unshift($activities_hub_events, [
        'id' => $eventId,
        'title' => $event['title'] ?? 'Untitled',
        'date' => $event['date'] ?? null,
        'location' => $event['location'] ?? null,
        'department' => $event['department'] ?? 'ALL',
        'status' => $event['status'] ?? '',
        'organizer_id' => $event['organizer_id'] ?? 0,
        'activity_count' => count($allSessions),
    ]);
}
$activities_hub_count = count($activities_hub_events);
$activities_hub_current_event_id = $eventId;
$activitiesHubListUrl = BASE_URL . '/activities_hub.php';

$view = 'hub';

$detailSession = null;

$listSessions = [];

$listTitle = '';



if ($activityId > 0) {

    $detailSession = eventify_load_activity_session($conn, $activityId, $eventId, $viewerId);

    $view = $detailSession ? 'activity' : 'hub';

} elseif ($categoryFilter !== '') {

    $view = 'category';

    $listTitle = $categoryFilter;

    $listSessions = $byCategory[$categoryFilter] ?? [];

} elseif ($dayFilter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayFilter)) {

    $view = 'day';

    $listTitle = date('l, F j, Y', strtotime($dayFilter));

    $listSessions = $byDate[$dayFilter] ?? [];

} elseif ($isStudent && ($_GET['view'] ?? '') === 'mine') {

    $view = 'mine';

    $listTitle = 'My schedule';

    $listSessions = $mySessions;

} elseif ($isStudent && ($_GET['view'] ?? '') === 'tickets') {

    $view = 'tickets';

    $listTitle = 'My tickets';

}



$mainHubUrl = eventify_activities_hub_main_url(
    $conn,
    $userId,
    $role,
    $studentDept,
    $eventId,
    count($allSessions)
);
$mainHubActive = ($view === 'hub');



$studentMainRsvped = false;
$studentHasMainAccess = false;
$mainEventRegMode = $event ? eventify_event_registration_mode($event) : 'rsvp';
$mainRegCount = 0;
$mainMaxCap = null;
$mainEventOpenForRsvp = false;
if ($isStudent && $event) {
    $mainEventOpenForRsvp = eventify_event_is_upcoming($event);
    $studentHasMainAccess = eventify_student_has_main_event_access($conn, $userId, $eventId, $event);
    $studentMainRsvped = $studentHasMainAccess;
    if ($mainEventRegMode === 'rsvp') {
        $regChk = $conn->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
        if ($regChk) {
            $regChk->bind_param('ii', $userId, $eventId);
            $regChk->execute();
            $studentMainRsvped = (bool) $regChk->get_result()->fetch_assoc();
            $regChk->close();
        }
    }
    $cntStmt = $conn->prepare('SELECT COUNT(*) AS c FROM registrations WHERE event_id = ?');
    if ($cntStmt) {
        $cntStmt->bind_param('i', $eventId);
        $cntStmt->execute();
        $cntRow = $cntStmt->get_result()->fetch_assoc();
        $cntStmt->close();
        $mainRegCount = (int) ($cntRow['c'] ?? 0);
    }
    if (array_key_exists('max_capacity', $event) && $event['max_capacity'] !== null && $event['max_capacity'] !== '') {
        $mainMaxCap = (int) $event['max_capacity'];
    }
}

eventify_event_photos_ensure_session_column($conn);
$photoStatusEnabled = eventify_event_photos_has_status($conn);
$sessionPhotoStats = [];
$detailActivityPhotos = [];
$detailPhotoDraftCount = 0;

if ($isMultimedia) {
    $sessionPhotoStats = eventify_load_event_session_photo_stats($conn, $eventId, $userId);
    $mmPhotoSummary = eventify_load_event_multimedia_photo_summary($conn, $eventId, $userId);
} else {
    $mmPhotoSummary = [
        'my_pending' => 0,
        'my_published' => 0,
        'my_rejected' => 0,
        'team_pending' => 0,
    ];
}

if ($view === 'activity' && $detailSession) {
    $detailActivityPhotos = eventify_load_activity_photos(
        $conn,
        $eventId,
        (int) ($detailSession['id'] ?? 0),
        $role,
        $userId,
        $isMultimediaModerator
    );
    if ($isMultimedia && $photoStatusEnabled) {
        foreach ($detailActivityPhotos as $ph) {
            if (($ph['status'] ?? '') !== 'draft') {
                continue;
            }
            if ($isMultimediaModerator || (int) ($ph['uploaded_by'] ?? 0) === $userId) {
                $detailPhotoDraftCount++;
            }
        }
    }
}

$conn->close();



function eah_h(string $s): string

{

    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

}



function eah_count_label(int $n): string

{

    return $n > 99 ? '99+' : (string) $n;

}



function eah_hub_link(int $eventId, array $params = []): string

{

    $params['id'] = $eventId;

    return BASE_URL . '/event_activities.php?' . http_build_query($params);

}

/** @param array<string, mixed> $session */
function eah_session_is_upcoming_or_live(array $session, ?string $todayYmd = null): bool
{
    if (($session['status'] ?? '') === 'cancelled') {
        return false;
    }
    if (eventify_session_is_live_now($session, $todayYmd)) {
        return true;
    }
    return eventify_session_allows_rsvp($session);
}

/** @param list<array<string, mixed>> $sessions */
function eah_sort_sessions_by_time(array $sessions): array
{
    usort($sessions, static function ($a, $b) {
        $da = substr((string) ($a['schedule_date'] ?? ''), 0, 10);
        $db = substr((string) ($b['schedule_date'] ?? ''), 0, 10);
        if ($da !== $db) {
            return strcmp($da, $db);
        }
        return strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));
    });
    return $sessions;
}

function eah_session_start_label(array $s): string
{
    $raw = trim((string) ($s['start_time'] ?? ''));
    if ($raw === '') {
        return 'TBA';
    }
    $ts = strtotime('1970-01-01 ' . substr($raw, 0, 8));
    return $ts ? date('g:i A', $ts) : 'TBA';
}

/** @param array<string, mixed> $s */
/** @param array<string, array{published: int, my_draft: int, pending_draft: int, my_total: int}> $sessionPhotoStats */
function eah_render_activity_row(array $s, int $eventId, string $todayYmd, bool $showDay = false, bool $showRsvpState = false, array $sessionPhotoStats = [], bool $showPhotoStats = false, bool $isPhotoModerator = false): void
{
    $timeStr = eventify_format_session_time_range($s['start_time'] ?? null, $s['end_time'] ?? null);
    $dayShort = !empty($s['schedule_date']) ? date('M j', strtotime($s['schedule_date'])) : '';
    $live = eventify_session_is_live_now($s, $todayYmd);
    $status = (string) ($s['status'] ?? 'scheduled');
    $ended = !$live && $status !== 'cancelled' && !eventify_session_allows_rsvp($s);
    $href = eah_hub_link($eventId, ['activity' => (int) $s['id']]);
    $photoStat = $sessionPhotoStats[(string) ($s['id'] ?? '')] ?? null;
    $rowClass = 'eah-activity-row eah-activity-row--timeline';
    if ($ended) {
        $rowClass .= ' eah-activity-row--ended';
    }
    if ($live) {
        $rowClass .= ' eah-activity-row--live';
    }
    ?>
    <a class="<?= $rowClass ?>" href="<?= eah_h($href) ?>">
        <div class="eah-row-time">
            <span class="eah-row-time__value"><?= eah_h(eah_session_start_label($s)) ?></span>
        </div>
        <div class="eah-row-main">
            <div class="eah-row-title"><?= eah_h($s['title'] ?? 'Activity') ?></div>
            <div class="eah-row-meta">
                <?php if ($timeStr !== ''): ?>
                    <span><i class="fas fa-clock"></i> <?= eah_h($timeStr) ?></span>
                <?php endif; ?>
                <?php if ($showDay && $dayShort !== ''): ?>
                    <span><i class="fas fa-calendar-day"></i> <?= eah_h($dayShort) ?></span>
                <?php endif; ?>
                <?php if (!empty($s['location'])): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?= eah_h($s['location']) ?></span>
                <?php endif; ?>
                <?php if (!empty($s['category'])): ?>
                    <span class="eah-row-cat"><?= eah_h($s['category']) ?></span>
                <?php endif; ?>
                <?php if ($showPhotoStats && $photoStat): ?>
                    <?php if ((int) ($photoStat['published'] ?? 0) > 0): ?>
                        <span><i class="fas fa-camera"></i> <?= (int) $photoStat['published'] ?> photo<?= (int) $photoStat['published'] === 1 ? '' : 's' ?></span>
                    <?php elseif ($isPhotoModerator && (int) ($photoStat['pending_draft'] ?? 0) > 0): ?>
                        <span><i class="fas fa-hourglass-half"></i> <?= (int) $photoStat['pending_draft'] ?> pending</span>
                    <?php elseif ((int) ($photoStat['my_draft'] ?? 0) > 0): ?>
                        <span><i class="fas fa-hourglass-half"></i> <?= (int) $photoStat['my_draft'] ?> pending</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="eah-row-status">
            <?php if ($showRsvpState && !empty($s['user_rsvped'])): ?>
                <span class="eah-badge eah-badge-rsvp">RSVP'd</span>
            <?php elseif ($showRsvpState && eventify_session_requires_ticket($s)): ?>
                <span class="eah-badge eah-badge-ticket">Ticket</span>
            <?php elseif ($live): ?>
                <span class="eah-badge eah-badge-live">Live</span>
            <?php elseif ($status === 'cancelled'): ?>
                <span class="eah-badge eah-badge-cancelled">Off</span>
            <?php elseif ($status === 'delayed'): ?>
                <span class="eah-badge eah-badge-delayed">Delayed</span>
            <?php elseif ($ended): ?>
                <span class="eah-badge eah-badge-ended">Ended</span>
            <?php else: ?>
                <i class="fas fa-chevron-right eah-row-chevron"></i>
            <?php endif; ?>
        </div>
    </a>
    <?php
}

/** @param list<array<string, mixed>> $photos */
function eah_render_activity_photos_section(
    array $photos,
    int $eventId,
    int $sessionId,
    bool $isMultimedia,
    int $userId,
    string $csrfToken,
    bool $photoStatusEnabled,
    bool $isMultimediaModerator = false,
    string $activityTitle = ''
): void {
    if ($photos === [] && !$isMultimedia) {
        return;
    }
    ?>
    <section class="eah-activity-photos">
        <div class="eah-activity-photos__head">
            <h2 class="eah-section-title"><i class="fas fa-camera me-2"></i>Activity photos</h2>
            <?php if ($isMultimedia): ?>
                <?php if ($isMultimediaModerator): ?>
                    <span class="eah-activity-photos__hint">Review uploads from your team and approve or reject before students see them.</span>
                <?php else: ?>
                    <span class="eah-activity-photos__hint">Upload here. Photos stay pending until your moderator approves them.</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($photos === []): ?>
            <p class="eah-activity-photos__empty">No photos for this activity yet.</p>
        <?php else: ?>
            <div class="eah-photo-grid">
                <?php foreach ($photos as $ph): ?>
                    <?php
                        $path = (string) ($ph['file_path'] ?? '');
                        $status = (string) ($ph['status'] ?? '');
                        $isDraft = $photoStatusEnabled && $status === 'draft';
                        $isRejected = $photoStatusEnabled && $status === 'rejected';
                        $canDelete = $isMultimedia && (int) ($ph['uploaded_by'] ?? 0) === $userId;
                        $canModerate = $isMultimediaModerator && $isDraft;
                        $photoLabel = $activityTitle !== '' ? $activityTitle : 'this activity photo';
                    ?>
                    <figure class="eah-photo-grid__item<?= $isDraft ? ' is-draft' : '' ?><?= $isRejected ? ' is-rejected' : '' ?>">
                        <a href="<?= eah_h(BASE_URL . '/' . ltrim($path, '/')) ?>" target="_blank" rel="noopener">
                            <img src="<?= eah_h(BASE_URL . '/' . ltrim($path, '/')) ?>" alt="Activity photo" loading="lazy" decoding="async">
                        </a>
                        <?php if ($isDraft): ?>
                            <span class="eah-photo-grid__badge">Pending</span>
                        <?php elseif ($isRejected): ?>
                            <span class="eah-photo-grid__badge eah-photo-grid__badge--rejected">Rejected</span>
                        <?php endif; ?>
                        <?php if ($canModerate): ?>
                            <div class="eah-photo-grid__moderate">
                                <form method="post" action="<?= eah_h(BASE_URL . '/backend/auth/moderate_event_photo.php') ?>" class="d-inline js-photo-moderate-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="photo_id" value="<?= (int) ($ph['id'] ?? 0) ?>">
                                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                    <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                    <input type="hidden" name="redirect_to" value="hub">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="button" class="eah-photo-grid__moderate-btn eah-photo-grid__moderate-btn--approve js-photo-moderate-trigger" title="Approve" data-action="approve" data-photo-label="<?= eah_h($photoLabel) ?>"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="<?= eah_h(BASE_URL . '/backend/auth/moderate_event_photo.php') ?>" class="d-inline js-photo-moderate-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="photo_id" value="<?= (int) ($ph['id'] ?? 0) ?>">
                                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                    <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                    <input type="hidden" name="redirect_to" value="hub">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="button" class="eah-photo-grid__moderate-btn eah-photo-grid__moderate-btn--reject js-photo-moderate-trigger" title="Reject" data-action="reject" data-photo-label="<?= eah_h($photoLabel) ?>"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= eah_h(BASE_URL . '/backend/auth/delete_event_photo.php') ?>" class="eah-photo-grid__delete" onsubmit="return confirm('Delete this photo?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="photo_id" value="<?= (int) ($ph['id'] ?? 0) ?>">
                                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                <button type="submit" class="eah-photo-grid__delete-btn" aria-label="Delete photo"><i class="fas fa-times"></i></button>
                            </form>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

/** @param list<array<string, mixed>> $tickets */
function eah_render_hub_tickets_timeline(array $tickets): void
{
    if ($tickets === []) {
        return;
    }
    usort($tickets, static function ($a, $b) {
        return strcmp(
            substr((string) ($a['event_date'] ?? ''), 0, 10),
            substr((string) ($b['event_date'] ?? ''), 0, 10)
        );
    });
    $byDate = [];
    foreach ($tickets as $t) {
        $ymd = substr((string) ($t['event_date'] ?? ''), 0, 10);
        if ($ymd === '') {
            $ymd = 'unknown';
        }
        if (!isset($byDate[$ymd])) {
            $byDate[$ymd] = [];
        }
        $byDate[$ymd][] = $t;
    }
    echo '<div class="eah-timeline-list eah-timeline-list--grouped">';
    foreach ($byDate as $ymd => $dayTickets) {
        if ($ymd !== 'unknown') {
            echo '<div class="eah-day-group"><h3 class="eah-day-group__title">' . eah_h(date('l, F j', strtotime($ymd))) . '</h3>';
        } else {
            echo '<div class="eah-day-group">';
        }
        foreach ($dayTickets as $t) {
            eah_render_ticket_row($t);
        }
        echo '</div>';
    }
    echo '</div>';
}

/** @param array<string, mixed> $t */
function eah_render_ticket_row(array $t): void
{
    $code = (string) ($t['ticket_code'] ?? '');
    $passUrl = BASE_URL . '/ticket_pass.php?code=' . urlencode($code);
    $typeName = (string) ($t['type_name'] ?? 'Ticket');
    $dateYmd = substr((string) ($t['event_date'] ?? ''), 0, 10);
    $timeRaw = trim((string) ($t['event_start_time'] ?? ''));
    $timeLabel = 'Pass';
    if ($timeRaw !== '') {
        $ts = strtotime('1970-01-01 ' . substr($timeRaw, 0, 8));
        if ($ts) {
            $timeLabel = date('g:i A', $ts);
        }
    }
    ?>
    <a class="eah-activity-row eah-activity-row--timeline eah-ticket-row" href="<?= eah_h($passUrl) ?>" target="_blank" rel="noopener">
        <div class="eah-row-time">
            <span class="eah-row-time__value"><?= eah_h($timeLabel) ?></span>
        </div>
        <div class="eah-row-main">
            <div class="eah-row-title"><?= eah_h($typeName) ?></div>
            <div class="eah-row-meta">
                <span><i class="fas fa-ticket-alt"></i> <?= eah_h($code) ?></span>
                <?php if (!empty($t['event_location'])): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?= eah_h((string) $t['event_location']) ?></span>
                <?php endif; ?>
                <?php if ($dateYmd !== ''): ?>
                    <span><i class="fas fa-calendar-day"></i> <?= eah_h(date('M j', strtotime($dateYmd))) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="eah-row-status">
            <span class="eah-badge eah-badge-ticket">QR pass</span>
            <i class="fas fa-chevron-right eah-row-chevron"></i>
        </div>
    </a>
    <?php
}

$todaySessionsSorted = eah_sort_sessions_by_time($todaySessions);
$todayUpcomingSorted = array_values(array_filter(
    $todaySessionsSorted,
    static fn($s) => eah_session_is_upcoming_or_live($s, $todayYmd)
));
$upcomingSessionsSorted = eah_sort_sessions_by_time(array_values(array_filter(
    $allSessions,
    static fn($s) => eah_session_is_upcoming_or_live($s, $todayYmd)
)));

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($isStudent): ?>
    <meta name="theme-color" content="#153313">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest-student.php">
    <?php endif; ?>

    <title>Activities — <?= eah_h($event['title']) ?> | EVENTIFY</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_activities_hub.css?v=36">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_modal.css?v=2">
    <?php if ($isOrganizer): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_day_sessions.css?v=4">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/create_event_modal.css?v=1">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php endif; ?>

</head>

<body class="event-activities-hub<?= $isOrganizer ? ' event-activities-hub--organizer' : '' ?>">

<div class="eah-wrap">

    <header class="eah-topbar">
        <button type="button" class="eah-topbar__menu" id="eahNavOpen" aria-label="Open menu" aria-expanded="false" aria-controls="eahNavDrawer">
            <i class="fas fa-bars"></i>
        </button>
        <a class="eah-topbar__logo" href="<?= eah_h($hubUrl) ?>"><i class="fas fa-calendar-alt" aria-hidden="true"></i><span>EVENTIFY</span></a>
        <div class="eah-topbar__actions">
            <?php if ($isOrganizer): ?>
            <button type="button" class="eah-topbar__action eah-topbar__action--create<?= $eventHasEditableScheduleDay ? '' : ' d-none' ?>" id="eahTopbarAddActivity" title="Add activity">
                <i class="fas fa-plus me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Add activity</span>
            </button>
            <?php endif; ?>
            <a class="eah-topbar__action" href="<?= eah_h($activitiesHubListUrl) ?>" aria-label="Browse all activity events">
                <i class="fas fa-th-large me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Activities hub</span>
            </a>
            <a class="eah-topbar__action<?= $mainHubActive ? ' is-active' : '' ?>" href="<?= eah_h($mainHubUrl) ?>"<?= $mainHubActive ? ' aria-current="page"' : '' ?> aria-label="Main hub">
                <i class="fas fa-calendar-day me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Main hub</span>
            </a>
            <a class="eah-topbar__action" href="<?= eah_h($backUrl) ?>">
                <i class="fas fa-gauge-high me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Dashboard</span>
            </a>
        </div>
    </header>

    <div class="eah-nav-drawer" id="eahNavDrawer" aria-hidden="true">
        <div class="eah-nav-drawer__backdrop" id="eahNavBackdrop" tabindex="-1"></div>
        <nav class="eah-nav-drawer__panel" id="eahNavPanel" role="navigation" aria-label="Activities hub menu">
            <div class="eah-nav-drawer__head">
                <div>
                    <div class="eah-nav-drawer__brand">EVENTIFY</div>
                    <?php if ($menuUserName !== ''): ?>
                        <div class="eah-nav-drawer__user"><?= eah_h($menuUserName) ?></div>
                    <?php endif; ?>
                </div>
                <button type="button" class="eah-nav-drawer__close" id="eahNavClose" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="eah-nav-drawer__event"><?= eah_h($event['title'] ?? 'Event') ?></p>
            <ul class="eah-nav-drawer__list">
                <li>
                    <a class="eah-nav-drawer__link" href="<?= eah_h($backUrl) ?>">
                        <i class="fas fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a class="eah-nav-drawer__link" href="<?= eah_h($activitiesHubListUrl) ?>">
                        <i class="fas fa-th-large"></i>
                        <span>Browse all events<?php if ($activities_hub_count > 0): ?> <span class="eah-nav-count"><?= $activities_hub_count > 99 ? '99+' : $activities_hub_count ?></span><?php endif; ?></span>
                    </a>
                </li>
                <li>
                    <a class="eah-nav-drawer__link<?= $mainHubActive ? ' is-active' : '' ?>" href="<?= eah_h($mainHubUrl) ?>"<?= $mainHubActive ? ' aria-current="page"' : '' ?>>
                        <i class="fas fa-calendar-day"></i>
                        <span>Main hub</span>
                    </a>
                </li>
                <?php if ($isMultimedia && $photoStatusEnabled): ?>
                    <?php
                        $mm_photo_status_context = 'drawer';
                        include __DIR__ . '/views/partials/activities_hub_mm_photo_status.php';
                    ?>
                <?php endif; ?>
                <?php if ($isStudent): ?>
                    <li>
                        <a class="eah-nav-drawer__link<?= $view === 'mine' ? ' is-active' : '' ?>" href="<?= eah_h(eah_hub_link($eventId, ['view' => 'mine'])) ?>"<?= $view === 'mine' ? ' aria-current="page"' : '' ?>>
                            <i class="fas fa-bookmark"></i>
                            <span>My schedule<?php if ($mySessions !== []): ?> <span class="eah-nav-count"><?= count($mySessions) ?></span><?php endif; ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="eah-nav-drawer__link<?= $view === 'tickets' ? ' is-active' : '' ?>" href="<?= eah_h(eah_hub_link($eventId, ['view' => 'tickets'])) ?>"<?= $view === 'tickets' ? ' aria-current="page"' : '' ?>>
                            <i class="fas fa-ticket-alt"></i>
                            <span>My tickets<?php if ($studentEventTickets !== []): ?> <span class="eah-nav-count eah-nav-count--ticket"><?= count($studentEventTickets) ?></span><?php endif; ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/attendance_history.php') ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Attendance history</span>
                        </a>
                    </li>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/backend/auth/dashboard_student.php?open_modal=scan') ?>">
                            <i class="fas fa-qrcode"></i>
                            <span>Scan QR</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($isOrganizer && $eventHasEditableScheduleDay): ?>
                    <li>
                        <button type="button" class="eah-nav-drawer__link eah-nav-drawer__link--btn" id="eahDrawerAddActivity">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add activity</span>
                        </button>
                    </li>
                <?php endif; ?>
                <?php if ($isOrganizer): ?>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/backend/auth/dashboardorganizer.php') ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Organizer calendar</span>
                        </a>
                    </li>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/activity_schedule.php?event_id=' . $eventId . '&date=' . urlencode($todayYmd)) ?>" target="_blank" rel="noopener">
                            <i class="fas fa-print"></i>
                            <span>Print schedule</span>
                        </a>
                    </li>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/event_qr.php?id=' . $eventId) ?>" target="_blank" rel="noopener">
                            <i class="fas fa-qrcode"></i>
                            <span>Event check-in QR</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (in_array($role, ['admin', 'super_admin'], true)): ?>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/event_qr.php?id=' . $eventId) ?>" target="_blank" rel="noopener">
                            <i class="fas fa-qrcode"></i>
                            <span>Event QR</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="eah-nav-drawer__footer">
                <a class="eah-nav-drawer__link eah-nav-drawer__link--danger" href="<?= eah_h(BASE_URL . '/backend/auth/logout.php') ?>" data-logout-confirm>
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log out</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="eah-hub-panel">

    <?php if ($hubMsg !== ''): ?>
        <?php
            $hubFlashClass = 'eah-flash';
            if (stripos($hubMsg, 'fail') !== false || stripos($hubMsg, 'invalid') !== false || stripos($hubMsg, 'error') !== false || stripos($hubMsg, 'could not') !== false) {
                $hubFlashClass .= ' eah-flash--error';
            } elseif (stripos($hubMsg, 'upload') !== false || stripos($hubMsg, 'publish') !== false || stripos($hubMsg, 'deleted') !== false || stripos($hubMsg, 'submitted') !== false || stripos($hubMsg, 'success') !== false) {
                $hubFlashClass .= ' eah-flash--ok';
            }
        ?>
        <div class="<?= eah_h($hubFlashClass) ?>" role="status"><?= eah_h($hubMsg) ?></div>
    <?php endif; ?>

    <?php if ($view === 'hub' && ($allSessions !== [] || $isStudent)): ?>
    <div class="eah-toolbar">
        <div class="eah-toolbar__filters">
            <?php if ($isStudent): ?>
                <a class="eah-tb-btn<?= $mySessions !== [] ? '' : ' eah-tb-btn--disabled' ?>" href="<?= eah_h(eah_hub_link($eventId, ['view' => 'mine'])) ?>" title="My schedule" aria-label="My schedule"><i class="fas fa-bookmark"></i></a>
                <a class="eah-tb-btn" href="<?= eah_h(eah_hub_link($eventId, ['view' => 'tickets'])) ?>" title="My tickets" aria-label="My tickets"><i class="fas fa-ticket-alt"></i></a>
                <button type="button" class="eah-tb-btn eah-tb-btn--toggle" id="eahMyOnlyToggle" title="Show my activities only" aria-pressed="false" aria-label="Show my activities only"><i class="fas fa-user-check" aria-hidden="true"></i><span class="eah-tb-btn__label">Mine</span></button>
            <?php endif; ?>
            <?php if ($liveSessions !== []): ?>
                <a class="eah-tb-btn" href="#eah-sp-live" title="Live"><i class="fas fa-circle"></i></a>
            <?php endif; ?>
            <a class="eah-tb-btn" href="#eah-sp-today" title="Today"><i class="fas fa-sun"></i></a>
            <a class="eah-tb-btn" href="#eah-sp-days" title="Days"><i class="fas fa-calendar-week"></i></a>
            <a class="eah-tb-btn" href="#eah-sp-cats" title="Categories"><i class="fas fa-th-large"></i></a>
        </div>
        <label class="eah-toolbar__search-wrap">
            <i class="fas fa-search"></i>
            <input type="search" class="eah-toolbar__search" id="eahHubSearch" placeholder="Search" autocomplete="off">
        </label>
    </div>
    <div class="eah-filter-banner" id="eahMyOnlyBanner" hidden>
        <span class="eah-filter-banner__text"><i class="fas fa-user-check" aria-hidden="true"></i> Showing your activities only</span>
        <button type="button" class="eah-filter-banner__clear" id="eahMyOnlyClear">Show all</button>
    </div>
    <?php endif; ?>

    <?php if ($view === 'hub'): ?>
    <div class="eah-event-hero">
        <div class="eah-event-hero__body">
            <nav class="eah-hub-breadcrumb" aria-label="Breadcrumb">
                <a class="eah-hub-breadcrumb__link" href="<?= eah_h($activitiesHubListUrl) ?>">
                    <i class="fas fa-th-large" aria-hidden="true"></i> Activities hub
                </a>
                <span class="eah-hub-breadcrumb__sep" aria-hidden="true">/</span>
                <span class="eah-hub-breadcrumb__current"><?= eah_h($event['title']) ?></span>
            </nav>
            <h1 class="eah-event-hero__title"><?= eah_h($event['title']) ?></h1>
            <p class="eah-event-hero__meta">
                <span><i class="fas fa-layer-group" aria-hidden="true"></i> <?= count($allSessions) ?> activities</span>
                <span><i class="fas fa-calendar-day" aria-hidden="true"></i> <?= count($byDate) ?> days</span>
                <?php if ($liveSessions !== []): ?>
                    <span class="eah-event-hero__live"><i class="fas fa-circle" aria-hidden="true"></i> <?= count($liveSessions) ?> live</span>
                <?php endif; ?>
            </p>
            <?php if ($isOrganizer): ?>
            <?php if ($scheduleLockMessage !== '' && !$eventHasEditableScheduleDay): ?>
            <p class="eah-schedule-lock-note mb-2" role="status">
                <i class="fas fa-lock me-1" aria-hidden="true"></i><?= eah_h($scheduleLockMessage) ?>
            </p>
            <?php endif; ?>
            <div class="eah-organizer-actions">
                <?php if (count($eventScheduleDates) > 1): ?>
                    <label class="eah-organizer-actions__day" for="eahAddActivityDay">
                        <span>Day</span>
                        <select class="form-select form-select-sm" id="eahAddActivityDay">
                            <?php foreach ($eventScheduleDates as $schedYmd): ?>
                                <option value="<?= eah_h($schedYmd) ?>"<?= $schedYmd === $defaultActivityDay ? ' selected' : '' ?>>
                                    <?= eah_h(date('M j, Y', strtotime($schedYmd))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <input type="hidden" id="eahAddActivityDay" value="<?= eah_h($defaultActivityDay) ?>">
                <?php endif; ?>
                <?php if ($eventHasEditableScheduleDay): ?>
                <button type="button" class="eah-hero-create-btn" id="eahHeroAddActivity">
                    <i class="fas fa-plus" aria-hidden="true"></i> Add activity
                </button>
                <?php endif; ?>
                <?php if ($eventCanEndEarly): ?>
                <button type="button" class="eah-hero-end-btn js-eah-end-event" id="eahHeroEndEvent">
                    End early
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($isStudent): ?>
            <?php
            $mainFull = $mainMaxCap !== null && $mainMaxCap > 0 && $mainRegCount >= $mainMaxCap;
            $showMainRsvpStrip = $mainEventRegMode !== 'open' && ($studentHasMainAccess || $mainEventOpenForRsvp);
            ?>
            <?php if ($showMainRsvpStrip): ?>
            <div class="eah-rsvp-strip eah-rsvp-strip--hero" id="eahMainEventRsvpBar">
                <?php if ($mainEventRegMode === 'paid_ticket'): ?>
                    <?php if ($studentHasMainAccess): ?>
                        <span class="eah-rsvp-strip__ok"><i class="fas fa-ticket-alt"></i> Ticket confirmed</span>
                        <a class="eah-rsvp-strip__btn" href="<?= eah_h(BASE_URL . '/event_activities.php?id=' . $eventId . '&view=tickets') ?>">My tickets</a>
                    <?php elseif ($eventTicketSalesOpen): ?>
                        <a class="eah-rsvp-strip__btn eah-rsvp-strip__btn--primary" href="<?= eah_h(BASE_URL . '/event_tickets.php?id=' . $eventId) ?>">
                            <i class="fas fa-shopping-cart me-1"></i> Buy ticket to join activities
                        </a>
                    <?php else: ?>
                        <span class="eah-rsvp-strip__hint">Ticket sales closed</span>
                    <?php endif; ?>
                <?php elseif ($studentMainRsvped): ?>
                    <span class="eah-rsvp-strip__ok"><i class="fas fa-check-circle"></i> Registered</span>
                    <?php if ($mainEventOpenForRsvp): ?>
                        <button type="button" class="eah-rsvp-strip__btn js-eah-main-cancel-rsvp" data-event-id="<?= (int) $eventId ?>">Cancel</button>
                    <?php endif; ?>
                <?php elseif (!$mainFull): ?>
                    <button type="button" class="eah-rsvp-strip__btn eah-rsvp-strip__btn--primary js-eah-main-rsvp" data-event-id="<?= (int) $eventId ?>">RSVP for event</button>
                <?php else: ?>
                    <span class="eah-rsvp-strip__hint">Event full</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if ($isMultimedia && $photoStatusEnabled): ?>
        <?php
            $mm_photo_status_context = 'hub';
            include __DIR__ . '/views/partials/activities_hub_mm_photo_status.php';
        ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($view !== 'hub'): ?>
    <div class="eah-event-bar eah-event-bar--sub">
        <a href="<?= eah_h($hubUrl) ?>" class="eah-sub-back"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="eah-event-bar__title"><?= eah_h($event['title']) ?></h1>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'activity' && $detailSession): ?>

        <?php

        $ds = $detailSession;

        $icon = eventify_activity_icon($ds['title'] ?? '', $ds['category'] ?? null);

        $timeStr = eventify_format_session_time_range($ds['start_time'] ?? null, $ds['end_time'] ?? null);

        $dayLabel = !empty($ds['schedule_date']) ? date('l, F j, Y', strtotime($ds['schedule_date'])) : '';

        $status = (string) ($ds['status'] ?? 'scheduled');

        $isLive = eventify_session_is_live_now($ds, $todayYmd);

        ?>

        <div class="eah-breadcrumb">

            <a href="<?= eah_h($hubUrl) ?>"><i class="fas fa-th-large me-1"></i>All activities</a>

            <?php if (!empty($ds['category'])): ?>

                <span>/</span>

                <a href="<?= eah_h(eah_hub_link($eventId, ['category' => $ds['category']])) ?>"><?= eah_h($ds['category']) ?></a>

            <?php endif; ?>

        </div>

        <div class="eah-detail-page">

        <article class="eah-detail-stub">

            <div class="eah-detail-stub__head">

                <?php if (!empty($ds['category'])): ?>
                    <span class="eah-detail-stub__cat"><?= eah_h($ds['category']) ?></span>
                <?php endif; ?>

                <h2 class="eah-detail-stub__title"><?= eah_h($ds['title']) ?></h2>

                <div class="eah-detail-stub__badges">
                    <?php if ($isLive): ?><span class="eah-badge eah-badge-live">Live now</span><?php endif; ?>
                    <?php if ($status === 'delayed'): ?><span class="eah-badge eah-badge-delayed">Delayed</span><?php endif; ?>
                    <?php if ($status === 'cancelled'): ?><span class="eah-badge eah-badge-cancelled">Cancelled</span><?php endif; ?>
                </div>

            </div>

            <div class="eah-stub-card">

            <div class="eah-info-grid eah-info-grid--stub">

                <?php if ($dayLabel !== ''): ?>

                    <div class="eah-info-item">

                        <i class="fas fa-calendar-day"></i>

                        <div>

                            <div class="eah-info-item-label">Date</div>

                            <div class="eah-info-item-value"><?= eah_h($dayLabel) ?></div>

                        </div>

                    </div>

                <?php endif; ?>

                <?php if ($timeStr !== ''): ?>

                    <div class="eah-info-item">

                        <i class="fas fa-clock"></i>

                        <div>

                            <div class="eah-info-item-label">Time</div>

                            <div class="eah-info-item-value"><?= eah_h($timeStr) ?></div>

                        </div>

                    </div>

                <?php endif; ?>

                <?php if (!empty($ds['location'])): ?>

                    <div class="eah-info-item">

                        <i class="fas fa-map-marker-alt"></i>

                        <div>

                            <div class="eah-info-item-label">Venue</div>

                            <div class="eah-info-item-value"><?= eah_h($ds['location']) ?></div>

                        </div>

                    </div>

                <?php endif; ?>

                <?php if (!empty($ds['contact_name']) || !empty($ds['contact_phone'])): ?>

                    <div class="eah-info-item">

                        <i class="fas fa-address-card"></i>

                        <div>

                            <div class="eah-info-item-label">Contact</div>

                            <div class="eah-info-item-value"><?= eah_h(trim(($ds['contact_name'] ?? '') . ' · ' . ($ds['contact_phone'] ?? ''), ' ·')) ?></div>

                        </div>

                    </div>

                <?php endif; ?>

                <?php if (!empty($ds['max_capacity'])): ?>

                    <div class="eah-info-item">

                        <i class="fas fa-users"></i>

                        <div>

                            <div class="eah-info-item-label">RSVP</div>

                            <div class="eah-info-item-value"><?= (int) ($ds['rsvp_count'] ?? 0) ?> / <?= (int) $ds['max_capacity'] ?></div>

                        </div>

                    </div>

                <?php elseif (($ds['rsvp_count'] ?? 0) > 0): ?>

                    <div class="eah-info-item">

                        <i class="fas fa-users"></i>

                        <div>

                            <div class="eah-info-item-label">RSVP</div>

                            <div class="eah-info-item-value"><?= (int) $ds['rsvp_count'] ?> registered</div>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

            </div>

            <?php if (!empty($ds['notes'])): ?>
                <div class="eah-notes-box">
                    <strong>Notes</strong>
                    <?= nl2br(eah_h($ds['notes'])) ?>
                </div>
            <?php endif; ?>

            <?php
            eah_render_activity_photos_section(
                $detailActivityPhotos,
                $eventId,
                (int) ($ds['id'] ?? 0),
                $isMultimedia,
                $userId,
                $csrfToken,
                $photoStatusEnabled,
                $isMultimediaModerator,
                (string) ($ds['title'] ?? '')
            );
            ?>

            <div class="eah-stub-links">
                <?php if ($isStudent): ?>
                    <a href="<?= eah_h(BASE_URL . '/event_activities_ics.php?id=' . $eventId . '&activity=' . (int) $ds['id']) ?>"><i class="fas fa-calendar-plus me-1"></i>Add to calendar</a>
                <?php endif; ?>
                <?php if (!empty($ds['latitude']) && !empty($ds['longitude'])): ?>
                    <a href="https://www.openstreetmap.org/?mlat=<?= urlencode((string) $ds['latitude']) ?>&mlon=<?= urlencode((string) $ds['longitude']) ?>#map=17/<?= urlencode((string) $ds['latitude']) ?>/<?= urlencode((string) $ds['longitude']) ?>" target="_blank" rel="noopener"><i class="fas fa-map me-1"></i>View on map</a>
                <?php endif; ?>
                <a href="<?= eah_h($hubUrl) ?>"><i class="fas fa-arrow-left me-1"></i>All activities</a>
            </div>

        </article>

        <?php
            $detailStickyStatusOnly = $isStudent
                && $status !== 'cancelled'
                && (
                    !empty($ds['user_checked_in'])
                    || (!empty($ds['user_rsvped']) && !eventify_session_allows_cancel_rsvp($ds))
                );
        ?>
        <div class="eah-detail-sticky<?= $detailStickyStatusOnly ? ' eah-detail-sticky--status-only' : '' ?>">
            <?php if ($isStudent && $status !== 'cancelled'): ?>
                <div id="eahDetailRsvpActions" data-session-id="<?= (int) ($ds['id'] ?? 0) ?>">
                <?php
                $studentReadyForActivities = ($mainEventRegMode === 'open')
                    || ($mainEventRegMode === 'rsvp' && $studentMainRsvped)
                    || ($mainEventRegMode === 'paid_ticket' && $studentHasMainAccess);
                $sessionRequiresTicket = eventify_session_requires_ticket($ds);
                $sessionHasTicket = !empty($ds['user_has_activity_ticket']);
                $sessionTicketTypeId = (int) ($ds['ticket_type_id'] ?? 0);
                $activityTicketUrl = BASE_URL . '/event_tickets.php?id=' . $eventId
                    . ($sessionTicketTypeId > 0 ? '&type=' . $sessionTicketTypeId : '')
                    . '&activity=' . (int) ($ds['id'] ?? 0);
                ?>
                <?php if (!empty($ds['user_checked_in'])): ?>
                    <div class="eah-attendance-status eah-attendance-status--checked-in" role="status">
                        <div class="eah-attendance-status__icon" aria-hidden="true">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="eah-attendance-status__body">
                            <span class="eah-attendance-status__label">You're checked in</span>
                            <?php if (!empty($ds['checked_in_at'])): ?>
                                <span class="eah-attendance-status__meta">
                                    <i class="fas fa-clock" aria-hidden="true"></i>
                                    <?= eah_h(date('M j, Y · g:i A', strtotime((string) $ds['checked_in_at']))) ?>
                                </span>
                            <?php else: ?>
                                <span class="eah-attendance-status__meta">Attendance recorded for this activity</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (!empty($ds['user_rsvped'])): ?>
                    <div class="eah-attendance-status eah-attendance-status--rsvp" role="status">
                        <div class="eah-attendance-status__icon" aria-hidden="true">
                            <i class="fas fa-bookmark"></i>
                        </div>
                        <div class="eah-attendance-status__body">
                            <span class="eah-attendance-status__label">
                                <?= eventify_session_allows_cancel_rsvp($ds) ? 'RSVP confirmed' : 'RSVP saved' ?>
                            </span>
                            <span class="eah-attendance-status__meta">
                                <?= eventify_session_allows_cancel_rsvp($ds)
                                    ? 'Show your QR at the venue to check in'
                                    : 'This activity has ended' ?>
                            </span>
                        </div>
                    </div>
                    <?php if (eventify_session_allows_cancel_rsvp($ds)): ?>
                    <button type="button" class="eah-btn eah-btn-outline eah-btn-block js-eah-cancel-rsvp" data-session-id="<?= (int) $ds['id'] ?>">
                        <i class="fas fa-times"></i> Cancel RSVP
                    </button>
                    <?php endif; ?>
                <?php elseif (!$studentReadyForActivities): ?>
                    <div class="eah-detail-sticky__muted mb-2">
                        <?php if ($mainEventRegMode === 'paid_ticket'): ?>
                            <i class="fas fa-ticket-alt me-1"></i> Buy a ticket for <strong><?= eah_h($event['title'] ?? 'this event') ?></strong> first, then you can join activities.
                        <?php else: ?>
                            <i class="fas fa-user-plus me-1"></i> RSVP for the main event first (top of the hub), then come back to join this activity.
                        <?php endif; ?>
                    </div>
                    <?php if ($mainEventRegMode === 'paid_ticket' && $eventTicketSalesOpen): ?>
                        <a class="eah-btn eah-btn-primary eah-btn-block" href="<?= eah_h(BASE_URL . '/event_tickets.php?id=' . $eventId) ?>">
                            <i class="fas fa-shopping-cart"></i> Buy ticket
                        </a>
                    <?php elseif ($mainEventRegMode === 'rsvp' && $mainEventOpenForRsvp): ?>
                        <button type="button" class="eah-btn eah-btn-primary eah-btn-block js-eah-main-rsvp" data-event-id="<?= (int) $eventId ?>">
                            <i class="fas fa-user-plus"></i> RSVP for main event
                        </button>
                    <?php endif; ?>
                <?php elseif ($sessionRequiresTicket && !$sessionHasTicket): ?>
                    <div class="eah-detail-sticky__muted mb-2">
                        <i class="fas fa-ticket-alt me-1"></i> This is a <strong>paid activity</strong>. Buy a ticket below, then RSVP to save your spot.
                    </div>
                    <?php if ($activityTicketSalesOpen): ?>
                        <a class="eah-btn eah-btn-primary eah-btn-block" href="<?= eah_h($activityTicketUrl) ?>">
                            <i class="fas fa-shopping-cart"></i> Buy ticket for this activity
                        </a>
                    <?php else: ?>
                        <div class="eah-detail-sticky__muted">Ticket sales are closed.</div>
                    <?php endif; ?>
                <?php elseif (eventify_session_allows_rsvp($ds)): ?>
                    <button type="button" class="eah-btn eah-btn-primary eah-btn-block js-eah-rsvp" data-session-id="<?= (int) $ds['id'] ?>">
                        <i class="fas fa-user-plus"></i> RSVP for this activity
                    </button>
                <?php else: ?>
                    <div class="eah-detail-sticky__muted"><i class="fas fa-clock me-1"></i> RSVP closed — activity ended or not open yet</div>
                <?php endif; ?>
                </div>
            <?php elseif ($isOrganizer): ?>
                <div class="eah-detail-sticky__organizer">
                    <div class="eah-organizer-bar">
                        <a class="eah-organizer-bar__btn eah-organizer-bar__btn--primary" href="<?= eah_h(BASE_URL . '/activity_qr.php?id=' . (int) $ds['id']) ?>" target="_blank" rel="noopener">
                            <i class="fas fa-qrcode" aria-hidden="true"></i>
                            <span>Check-in QR</span>
                        </a>
                        <a class="eah-organizer-bar__btn eah-organizer-bar__btn--secondary" href="<?= eah_h(BASE_URL . '/activity_attendance.php?id=' . (int) $ds['id']) ?>">
                            <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                            <span>Attendance</span>
                        </a>
                    </div>
                    <?php if ($eventCanEndEarly): ?>
                    <button type="button" class="eah-organizer-bar__end js-eah-end-event" id="eahDetailEndEvent">
                        End event early
                    </button>
                    <?php endif; ?>
                </div>
            <?php elseif ($isMultimedia): ?>
                <button type="button" class="eah-btn eah-btn-primary eah-btn-block" data-bs-toggle="modal" data-bs-target="#eahUploadPhotosModal">
                    <i class="fas fa-cloud-upload-alt"></i> Upload activity photos
                </button>
                <?php if ($isMultimediaModerator && $photoStatusEnabled && $detailPhotoDraftCount > 0): ?>
                    <form method="post" action="<?= eah_h(BASE_URL . '/backend/auth/publish_event_photos.php') ?>" class="eah-detail-sticky__secondary">
                        <?= csrf_field() ?>
                        <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                        <input type="hidden" name="session_id" value="<?= (int) ($ds['id'] ?? 0) ?>">
                        <button type="submit" class="eah-btn eah-btn-outline eah-btn-block">
                            <i class="fas fa-check-double"></i> Approve all <?= (int) $detailPhotoDraftCount ?> pending photo<?= $detailPhotoDraftCount === 1 ? '' : 's' ?>
                        </button>
                    </form>
                <?php elseif (!$isMultimediaModerator && $photoStatusEnabled && $detailPhotoDraftCount > 0): ?>
                    <p class="eah-detail-sticky__muted small mb-0 mt-2"><i class="fas fa-hourglass-half me-1"></i> <?= (int) $detailPhotoDraftCount ?> photo<?= $detailPhotoDraftCount === 1 ? '' : 's' ?> waiting for moderator approval.</p>
                <?php elseif (!$photoStatusEnabled): ?>
                    <p class="eah-detail-sticky__muted small mb-0 mt-2">Photos appear in the hub after upload (publish workflow not enabled).</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        </div>



    <?php elseif ($view === 'category' || $view === 'day' || $view === 'mine' || $view === 'tickets'): ?>

        <div class="eah-breadcrumb">

            <a href="<?= eah_h($hubUrl) ?>"><i class="fas fa-arrow-left me-1"></i>All activities</a>

        </div>

        <div class="eah-list-header eah-list-header--mine">

            <h2 class="eah-page-title"><?= eah_h($listTitle) ?></h2>

            <?php if ($view === 'mine' && $isStudent && $listSessions !== []): ?>
                <a class="eah-btn eah-btn-outline eah-btn-sm" href="<?= eah_h(BASE_URL . '/event_activities_ics.php?id=' . $eventId . '&scope=mine') ?>">
                    <i class="fas fa-calendar-plus"></i> Add all to calendar
                </a>
            <?php elseif ($view === 'tickets' && $isStudent && $eventTicketSalesOpen): ?>
                <a class="eah-btn eah-btn-outline eah-btn-sm" href="<?= eah_h(BASE_URL . '/event_tickets.php?id=' . $eventId) ?>">
                    <i class="fas fa-shopping-cart"></i> Buy tickets
                </a>
            <?php endif; ?>

        </div>

        <?php if ($view === 'mine' && $listSessions === []): ?>

            <div class="eah-empty">
                <div class="eah-empty-icon"><i class="fas fa-bookmark"></i></div>
                <div class="eah-empty-title">Nothing on your schedule yet</div>
                <p class="eah-empty-text">RSVP to activities from the hub and they will show up here with times and venues.</p>
                <a class="eah-btn eah-btn-primary" href="<?= eah_h($hubUrl) ?>">Browse activities</a>
            </div>

        <?php elseif ($view === 'tickets' && $studentEventTickets === []): ?>

            <div class="eah-empty">
                <div class="eah-empty-icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="eah-empty-title">No tickets for this event yet</div>
                <p class="eah-empty-text">
                    <?php if ($eventTicketSalesOpen): ?>
                        Buy tickets for this event to see your digital passes here.
                    <?php else: ?>
                        Your valid tickets for this event will appear here after purchase.
                    <?php endif; ?>
                </p>
                <?php if ($eventTicketSalesOpen): ?>
                    <a class="eah-btn eah-btn-primary" href="<?= eah_h(BASE_URL . '/event_tickets.php?id=' . $eventId) ?>">
                        <i class="fas fa-shopping-cart"></i> Buy tickets
                    </a>
                <?php else: ?>
                    <a class="eah-btn eah-btn-primary" href="<?= eah_h($hubUrl) ?>">Back to hub</a>
                <?php endif; ?>
            </div>

        <?php elseif ($view === 'tickets'): ?>

            <?php eah_render_hub_tickets_timeline($studentEventTickets); ?>

        <?php elseif ($listSessions === []): ?>

            <div class="eah-empty">
                <div class="eah-empty-icon"><i class="fas fa-calendar-xmark"></i></div>
                <div class="eah-empty-title">Nothing here yet</div>
                <p class="eah-empty-text">No activities in this section.</p>
            </div>

        <?php else: ?>

            <?php if ($view === 'mine'): ?>
                <?php
                $mineByDate = [];
                foreach ($listSessions as $s) {
                    $ymd = substr((string) ($s['schedule_date'] ?? ''), 0, 10);
                    if ($ymd === '') {
                        continue;
                    }
                    if (!isset($mineByDate[$ymd])) {
                        $mineByDate[$ymd] = [];
                    }
                    $mineByDate[$ymd][] = $s;
                }
                ?>
                <div class="eah-timeline-list eah-timeline-list--grouped">
                    <?php foreach ($mineByDate as $ymd => $dayItems): ?>
                        <div class="eah-day-group">
                            <h3 class="eah-day-group__title"><?= eah_h(date('l, F j', strtotime($ymd))) ?></h3>
                            <?php foreach ($dayItems as $s): ?>
                                <?php eah_render_activity_row($s, $eventId, $todayYmd, false, true, $sessionPhotoStats, $isMultimedia, $isMultimediaModerator); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
            <div class="eah-timeline-list">
                <?php foreach (eah_sort_sessions_by_time($listSessions) as $s): ?>
                    <?php eah_render_activity_row($s, $eventId, $todayYmd, $view === 'category', $isStudent, $sessionPhotoStats, $isMultimedia, $isMultimediaModerator); ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>



    <?php else: ?>

        <?php if ($allSessions === []): ?>

            <div class="eah-empty">
                <div class="eah-empty-icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="eah-empty-title">No activities yet</div>
                <p class="eah-empty-text">
                    <?php if ($isOrganizer): ?>
                        Add sub-activities for this event — for example <strong>Badminton</strong> under ms intrams.
                    <?php else: ?>
                        The organizer has not published a schedule for this event yet.
                    <?php endif; ?>
                </p>
                <div class="eah-empty-actions">
                    <a class="eah-btn eah-btn-outline" href="<?= eah_h($activitiesHubListUrl) ?>">
                        <i class="fas fa-th-large me-1" aria-hidden="true"></i> Browse all events
                    </a>
                    <?php if ($isOrganizer && $eventHasEditableScheduleDay): ?>
                        <button type="button" class="eah-btn eah-btn-primary" id="eahEmptyAddActivity">
                            <i class="fas fa-plus me-1" aria-hidden="true"></i> Add activity
                        </button>
                    <?php elseif ($isOrganizer && $scheduleLockMessage !== ''): ?>
                        <p class="eah-schedule-lock-note mb-0"><i class="fas fa-lock me-1" aria-hidden="true"></i><?= eah_h($scheduleLockMessage) ?></p>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>

            <?php
            $featuredSession = null;
            if ($liveSessions !== []) {
                $featuredSession = eah_sort_sessions_by_time($liveSessions)[0];
            } elseif ($upcomingSessionsSorted !== []) {
                $featuredSession = $upcomingSessionsSorted[0];
            }
            $todayLeague = array_slice($todayUpcomingSorted, 0, 5);
            $featuredIsLive = $featuredSession !== null && eventify_session_is_live_now($featuredSession, $todayYmd);
            $catList = array_keys($byCategory);
            $maxCats = 5;
            $visibleCats = array_slice($catList, 0, $maxCats);
            $hasMoreCats = count($catList) > $maxCats;
            ?>

            <div class="eah-hub-home" id="eah-schedule">

                <?php if ($featuredSession): ?>
                    <a class="eah-featured-banner"
                       href="<?= eah_h(eah_hub_link($eventId, ['activity' => (int) $featuredSession['id']])) ?>"
                       data-search="<?= eah_h(strtolower(($featuredSession['title'] ?? '') . ' ' . ($featuredSession['category'] ?? ''))) ?>">
                        <div class="eah-featured-banner__badge">
                            <?php if ($featuredIsLive): ?>
                                <span class="eah-pill eah-pill--live">LIVE</span>
                            <?php else: ?>
                                <span class="eah-pill eah-pill--gold">Up next</span>
                            <?php endif; ?>
                        </div>
                        <div class="eah-featured-banner__icon"><?= eventify_activity_icon($featuredSession['title'] ?? '', $featuredSession['category'] ?? null) ?></div>
                        <div class="eah-featured-banner__text">
                            <span class="eah-featured-banner__label">Featured activity</span>
                            <span class="eah-featured-banner__title"><?= eah_h($featuredSession['title'] ?? 'Featured') ?></span>
                        </div>
                        <i class="fas fa-chevron-right eah-featured-banner__chev" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>

                <?php if ($isStudent || $liveSessions !== [] || $todayUpcomingSorted !== []): ?>
                <div class="eah-quick-scroll">
                    <?php if ($isStudent): ?>
                        <a class="eah-quick-tile eah-quick-tile--mine<?= $mySessions === [] ? ' is-empty' : '' ?>"
                           id="eah-sp-mine"
                           href="<?= eah_h(eah_hub_link($eventId, ['view' => 'mine'])) ?>"
                           data-search="my schedule bookmark">
                            <span class="eah-quick-tile__icon"><i class="fas fa-bookmark"></i></span>
                            <span class="eah-quick-tile__label">My schedule</span>
                            <span class="eah-quick-tile__meta"><?= $mySessions === [] ? 'RSVP to save' : count($mySessions) . ' saved' ?></span>
                        </a>
                        <a class="eah-quick-tile eah-quick-tile--tickets<?= $studentEventTickets === [] ? ' is-empty' : '' ?>"
                           id="eah-sp-tickets"
                           href="<?= eah_h(eah_hub_link($eventId, ['view' => 'tickets'])) ?>"
                           data-search="my tickets pass">
                            <span class="eah-quick-tile__icon"><i class="fas fa-ticket-alt"></i></span>
                            <span class="eah-quick-tile__label">My tickets</span>
                            <span class="eah-quick-tile__meta"><?= $studentEventTickets === [] ? 'Buy passes' : count($studentEventTickets) . ' saved' ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($liveSessions !== []): ?>
                        <a class="eah-quick-tile eah-quick-tile--live" id="eah-sp-live"
                           href="<?= eah_h(eah_hub_link($eventId, ['activity' => (int) $liveSessions[0]['id']])) ?>"
                           data-search="live">
                            <span class="eah-quick-tile__icon"><i class="fas fa-circle"></i></span>
                            <span class="eah-quick-tile__label">Live now</span>
                            <span class="eah-quick-tile__meta"><?= count($liveSessions) ?> active</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($todayUpcomingSorted !== []): ?>
                        <a class="eah-quick-tile eah-quick-tile--today" id="eah-sp-today"
                           href="<?= eah_h(eah_hub_link($eventId, ['day' => $todayYmd])) ?>"
                           data-search="today <?= eah_h(strtolower(date('l F j'))) ?>">
                            <span class="eah-quick-tile__icon"><i class="fas fa-sun"></i></span>
                            <span class="eah-quick-tile__label">Today</span>
                            <span class="eah-quick-tile__meta"><?= eah_h(date('M j')) ?> · <?= count($todayUpcomingSorted) ?> left</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <section class="eah-section">
                    <h2 class="eah-section__title"><i class="fas fa-th-large" aria-hidden="true"></i> Categories</h2>
                    <div class="eah-cat-grid" id="eah-sp-cats">
                        <?php foreach ($visibleCats as $catName): ?>
                            <?php
                            $items = $byCategory[$catName] ?? [];
                            $liveInCat = count(array_filter($items, static function ($s) use ($todayYmd) {
                                return eventify_session_is_live_now($s, $todayYmd);
                            }));
                            ?>
                            <a class="eah-cat-card"
                               href="<?= eah_h(eah_hub_link($eventId, ['category' => $catName])) ?>"
                               data-search="<?= eah_h(strtolower($catName)) ?>"
                               data-cat="<?= eah_h($catName) ?>">
                                <span class="eah-cat-card__icon"><?= eventify_activity_icon($catName, $catName) ?></span>
                                <span class="eah-cat-card__body">
                                    <span class="eah-cat-card__label"><?= eah_h($catName) ?></span>
                                    <span class="eah-cat-card__count"><?= eah_h(eah_count_label(count($items))) ?></span>
                                </span>
                                <?php if ($liveInCat > 0): ?><span class="eah-pill eah-pill--live eah-cat-card__live">LIVE</span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($hasMoreCats): ?>
                            <a class="eah-cat-card eah-cat-card--more" href="#eah-sp-cats-extra">
                                <span class="eah-cat-card__icon"><i class="fas fa-ellipsis-h"></i></span>
                                <span class="eah-cat-card__body">
                                    <span class="eah-cat-card__label">More</span>
                                    <span class="eah-cat-card__count">All categories</span>
                                </span>
                            </a>
                        <?php endif; ?>
                        <?php if ($byCategory === []): ?>
                            <div class="eah-cat-card is-static">
                                <span class="eah-cat-card__icon"><i class="fas fa-list"></i></span>
                                <span class="eah-cat-card__body">
                                    <span class="eah-cat-card__label">General</span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($todayLeague !== []): ?>
                <section class="eah-section">
                    <h2 class="eah-section__title"><i class="fas fa-bolt" aria-hidden="true"></i> Still on today&apos;s schedule</h2>
                    <div class="eah-activity-list" id="eah-sp-league">
                        <?php foreach ($todayLeague as $s): ?>
                            <a class="eah-activity-list__item"
                               href="<?= eah_h(eah_hub_link($eventId, ['activity' => (int) $s['id']])) ?>"
                               data-search="<?= eah_h(strtolower(($s['title'] ?? '') . ' ' . ($s['category'] ?? '') . ' ' . ($s['location'] ?? ''))) ?>"
                               data-user-rsvp="<?= !empty($s['user_rsvped']) ? '1' : '0' ?>">
                                <span class="eah-activity-list__icon"><?= eventify_activity_icon($s['title'] ?? '', $s['category'] ?? null) ?></span>
                                <span class="eah-activity-list__body">
                                    <span class="eah-activity-list__title"><?= eah_h($s['title'] ?? '') ?></span>
                                    <span class="eah-activity-list__meta"><?= eah_h(eventify_format_session_time_range($s['start_time'] ?? null, $s['end_time'] ?? null)) ?><?php if (!empty($s['category'])): ?> · <?= eah_h($s['category']) ?><?php endif; ?></span>
                                </span>
                                <?php if (eventify_session_is_live_now($s, $todayYmd)): ?>
                                    <span class="eah-pill eah-pill--live">LIVE</span>
                                <?php else: ?>
                                    <i class="fas fa-chevron-right eah-activity-list__chev" aria-hidden="true"></i>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php
                $otherDays = [];
                foreach ($byDate as $ymd => $items) {
                    if ($ymd === $todayYmd && $todaySessionsSorted !== []) {
                        continue;
                    }
                    $otherDays[$ymd] = $items;
                }
                ?>
                <?php if ($otherDays !== []): ?>
                <section class="eah-section">
                    <h2 class="eah-section__title"><i class="fas fa-calendar-week" aria-hidden="true"></i> Other days</h2>
                    <div class="eah-days-scroll" id="eah-sp-days">
                        <?php foreach ($otherDays as $ymd => $items): ?>
                            <a class="eah-day-chip"
                               href="<?= eah_h(eah_hub_link($eventId, ['day' => $ymd])) ?>"
                               data-search="<?= eah_h(strtolower(date('l F j', strtotime($ymd)))) ?>">
                                <span class="eah-day-chip__dow"><?= eah_h(date('D', strtotime($ymd))) ?></span>
                                <span class="eah-day-chip__date"><?= eah_h(date('M j', strtotime($ymd))) ?></span>
                                <span class="eah-day-chip__count"><?= count($items) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

            </div>

            <?php if ($hasMoreCats): ?>
                <div class="eah-sp-extra" id="eah-sp-cats-extra">
                    <h3 class="eah-sp-extra__title">All categories</h3>
                    <div class="eah-sp-extra-chips">
                        <?php foreach ($byCategory as $catName => $items): ?>
                            <a class="eah-sp-extra-chip" href="<?= eah_h(eah_hub_link($eventId, ['category' => $catName])) ?>"><?= eah_h($catName) ?> <span><?= count($items) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>



        <?php endif; ?>

    <?php endif; ?>

    </div><!-- .eah-hub-panel -->

</div>

<?php include __DIR__ . '/views/partials/logout_confirm_modal.php'; ?>

<?php if ($isMultimedia && $view === 'activity' && $detailSession): ?>
<div class="modal fade" id="eahUploadPhotosModal" tabindex="-1" aria-labelledby="eahUploadPhotosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content eah-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="eahUploadPhotosModalLabel"><i class="fas fa-cloud-upload-alt me-2"></i>Upload activity photos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= eah_h(BASE_URL . '/backend/auth/upload_event_photo.php') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                <input type="hidden" name="session_id" value="<?= (int) ($detailSession['id'] ?? 0) ?>">
                <div class="modal-body">
                    <label class="form-label eah-modal__label" for="eahPhotosInput">Select images</label>
                    <input type="file" name="photos[]" id="eahPhotosInput" class="form-control eah-modal__file" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                </div>
                <div class="modal-footer eah-modal__footer">
                    <button type="button" class="eah-btn eah-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="eah-btn eah-btn-primary"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isMultimediaModerator): ?>
<?php include __DIR__ . '/views/partials/photo_moderation_confirm_modal.php'; ?>
<?php endif; ?>

<?php if ($isOrganizer): ?>
<?php include __DIR__ . '/views/partials/event_day_sessions_modal.php'; ?>
<?php if ($eventCanEndEarly): ?>
<div class="modal fade" id="eahEndEventModal" tabindex="-1" aria-labelledby="eahEndEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content efy-modal efy-modal--compact">
            <div class="modal-header efy-modal__header">
                <div>
                    <span class="efy-modal__eyebrow">Organizer</span>
                    <h5 class="modal-title efy-modal__title efy-modal__title--sm" id="eahEndEventModalLabel">
                        End event early?
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body efy-modal__body efy-modal__body--compact">
                <p class="efy-confirm-message mb-2">Use this when the event finishes before the last scheduled day.</p>
                <ul class="eah-end-modal__effects mb-0">
                    <li>Students can no longer check in or RSVP</li>
                    <li>Ticket sales stop immediately</li>
                    <li>You can still view attendance and reports</li>
                </ul>
            </div>
            <div class="modal-footer efy-modal__footer">
                <button type="button" class="btn efy-btn-muted" data-bs-dismiss="modal">Keep running</button>
                <button type="button" class="btn efy-btn-danger" id="eahEndEventConfirm">End event</button>
            </div>
        </div>
    </div>
</div>
<form id="eahEndEventForm" method="POST" action="<?= eah_h(BASE_URL . '/backend/auth/update_organizer_event_status.php') ?>" style="display:none" aria-hidden="true">
    <?= csrf_field() ?>
    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
    <input type="hidden" name="action" value="close">
    <input type="hidden" name="redirect_to" value="<?= eah_h(BASE_URL . '/event_activities.php?id=' . $eventId) ?>">
</form>
<?php endif; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<script src="<?= BASE_URL ?>/assets/js/photo_moderation_confirm.js?v=1"></script>
<script src="<?= BASE_URL ?>/assets/js/event_activities_hub_nav.js"></script>
<?php if ($isOrganizer): ?>
<script>
window.csrfToken = <?= json_encode($csrfToken) ?>;
window.currentRole = 'organizer';
window.EVENTIFY_GEOCODE_URL = <?= json_encode(BASE_URL . '/backend/auth/geocode_proxy.php') ?>;
window.EVENTIFY_SESSIONS_HAVE_GEO = <?= $daySessionsHaveGeo ? 'true' : 'false' ?>;
window.EVENTIFY_RELOAD_HUB_ON_SESSION_SAVE = true;
window.__eahEventId = <?= (int) $eventId ?>;
window.__eahEventScheduleDates = <?= json_encode(array_values($eventScheduleDates), JSON_UNESCAPED_UNICODE) ?>;
window.__eahOpenAddActivity = <?= $openAddActivity ? 'true' : 'false' ?>;
window.__eahScheduleEditable = <?= $eventScheduleEditable ? 'true' : 'false' ?>;
window.__eahHasEditableScheduleDay = <?= $eventHasEditableScheduleDay ? 'true' : 'false' ?>;
window.__eahScheduleLockMessage = <?= json_encode($scheduleLockMessage) ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= BASE_URL ?>/assets/js/event_location_picker.js"></script>
<script src="<?= BASE_URL ?>/assets/js/event_day_sessions.js?v=11"></script>
<script>
(function () {
    function getActivityDay() {
        var el = document.getElementById('eahAddActivityDay');
        return el ? String(el.value || '').slice(0, 10) : '';
    }
    function openAddActivity() {
        if (typeof window.eventifyOpenDaySessionsManage !== 'function') {
            return;
        }
        if (window.__eahHasEditableScheduleDay === false) {
            return;
        }
        window.eventifyOpenDaySessionsManage(window.__eahEventId, getActivityDay());
    }
    ['eahTopbarAddActivity', 'eahDrawerAddActivity', 'eahHeroAddActivity', 'eahEmptyAddActivity'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (btn) {
            btn.addEventListener('click', openAddActivity);
        }
    });
    if (window.__eahOpenAddActivity && window.__eahHasEditableScheduleDay !== false) {
        document.addEventListener('DOMContentLoaded', openAddActivity);
    }
    var endModalEl = document.getElementById('eahEndEventModal');
    var endConfirm = document.getElementById('eahEndEventConfirm');
    var endForm = document.getElementById('eahEndEventForm');
    if (endModalEl && endConfirm && endForm && typeof bootstrap !== 'undefined') {
        var endModal = bootstrap.Modal.getOrCreateInstance(endModalEl);
        document.querySelectorAll('.js-eah-end-event').forEach(function (btn) {
            btn.addEventListener('click', function () {
                endModal.show();
            });
        });
        endConfirm.addEventListener('click', function () {
            endModal.hide();
            endForm.submit();
        });
    }
})();
</script>
<?php endif; ?>
<script>
(function () {
    var search = document.getElementById('eahHubSearch');
    var myOnlyBtn = document.getElementById('eahMyOnlyToggle');
    var myOnlyBanner = document.getElementById('eahMyOnlyBanner');
    var myOnlyClear = document.getElementById('eahMyOnlyClear');
    var myOnly = false;

    function setMyOnlyFilter(on) {
        myOnly = !!on;
        applyHubFilters();
    }

    function applyHubFilters() {
        var q = search ? (search.value || '').toLowerCase().trim() : '';
        document.querySelectorAll('[data-search]').forEach(function (el) {
            var hay = (el.getAttribute('data-search') || '').toLowerCase();
            var matchSearch = !q || hay.indexOf(q) !== -1;
            var matchMine = !myOnly || el.getAttribute('data-user-rsvp') === '1' || el.id === 'eah-sp-mine' || el.id === 'eah-sp-tickets';
            el.style.display = matchSearch && matchMine ? '' : 'none';
        });
        if (myOnlyBtn) {
            myOnlyBtn.classList.toggle('is-active', myOnly);
            myOnlyBtn.setAttribute('aria-pressed', myOnly ? 'true' : 'false');
            myOnlyBtn.setAttribute('title', myOnly ? 'Showing your activities only (click to show all)' : 'Show my activities only');
            myOnlyBtn.setAttribute('aria-label', myOnly ? 'Showing your activities only. Click to show all.' : 'Show my activities only');
        }
        if (myOnlyBanner) {
            myOnlyBanner.hidden = !myOnly;
        }
    }

    if (search) {
        search.addEventListener('input', applyHubFilters);
    }
    if (myOnlyBtn) {
        myOnlyBtn.addEventListener('click', function () {
            setMyOnlyFilter(!myOnly);
        });
    }
    if (myOnlyClear) {
        myOnlyClear.addEventListener('click', function () {
            setMyOnlyFilter(false);
        });
    }
})();
</script>

<?php if ($isStudent): ?>

<script>
window.csrfToken = <?= json_encode($csrfToken) ?>;
window.__eahMainEventId = <?= (int) $eventId ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_pwa.js"></script>
<script src="<?= BASE_URL ?>/assets/js/event_day_sessions.js?v=11"></script>
<script src="<?= BASE_URL ?>/assets/js/event_activities_main_rsvp.js"></script>

<?php endif; ?>

</body>

</html>


