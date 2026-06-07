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

$csrfToken = function_exists('csrf_token') ? csrf_token() : '';



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

}



$studentMainRsvped = false;
$mainRegCount = 0;
$mainMaxCap = null;
$mainEventOpenForRsvp = false;
if ($isStudent && $event) {
    $mainEventOpenForRsvp = eventify_event_is_upcoming($event);
    $regChk = $conn->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ? LIMIT 1');
    if ($regChk) {
        $regChk->bind_param('ii', $userId, $eventId);
        $regChk->execute();
        $studentMainRsvped = (bool) $regChk->get_result()->fetch_assoc();
        $regChk->close();
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
function eah_render_activity_row(array $s, int $eventId, string $todayYmd, bool $showDay = false): void
{
    $timeStr = eventify_format_session_time_range($s['start_time'] ?? null, $s['end_time'] ?? null);
    $dayShort = !empty($s['schedule_date']) ? date('M j', strtotime($s['schedule_date'])) : '';
    $live = eventify_session_is_live_now($s, $todayYmd);
    $status = (string) ($s['status'] ?? 'scheduled');
    $ended = !$live && $status !== 'cancelled' && !eventify_session_allows_rsvp($s);
    $href = eah_hub_link($eventId, ['activity' => (int) $s['id']]);
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
            </div>
        </div>
        <div class="eah-row-status">
            <?php if ($live): ?>
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

$todaySessionsSorted = eah_sort_sessions_by_time($todaySessions);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($isStudent): ?>
    <meta name="theme-color" content="#064e3b">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest-student.php">
    <?php endif; ?>

    <title>Activities — <?= eah_h($event['title']) ?> | EVENTIFY</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_activities_hub.css?v=6">

</head>

<body class="event-activities-hub">

<div class="eah-wrap">

    <header class="eah-topbar">
        <button type="button" class="eah-topbar__menu" id="eahNavOpen" aria-label="Open menu" aria-expanded="false" aria-controls="eahNavDrawer">
            <i class="fas fa-bars"></i>
        </button>
        <a class="eah-topbar__logo" href="<?= eah_h($hubUrl) ?>">EVENTIFY</a>
        <a class="eah-topbar__action" href="<?= eah_h($backUrl) ?>">Dashboard</a>
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
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a class="eah-nav-drawer__link<?= $view === 'hub' ? ' is-active' : '' ?>" href="<?= eah_h($hubUrl) ?>"<?= $view === 'hub' ? ' aria-current="page"' : '' ?>>
                        <i class="fas fa-th-large"></i>
                        <span>Activities hub</span>
                    </a>
                </li>
                <?php if ($isStudent): ?>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/my_tickets.php') ?>">
                            <i class="fas fa-ticket-alt"></i>
                            <span>My tickets</span>
                        </a>
                    </li>
                    <li>
                        <a class="eah-nav-drawer__link" href="<?= eah_h(BASE_URL . '/backend/auth/dashboard_student.php?open_modal=scan') ?>">
                            <i class="fas fa-qrcode"></i>
                            <span>Scan QR</span>
                        </a>
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

    <?php if ($view === 'hub' && $allSessions !== []): ?>
    <div class="eah-toolbar">
        <div class="eah-toolbar__filters">
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
    <?php endif; ?>

    <?php if ($view === 'hub'): ?>
    <div class="eah-event-bar">
        <h1 class="eah-event-bar__title"><?= eah_h($event['title']) ?></h1>
        <p class="eah-event-bar__meta"><?= count($allSessions) ?> activities · <?= count($byDate) ?> days<?php if ($liveSessions !== []): ?> · <span class="eah-text-live"><?= count($liveSessions) ?> live</span><?php endif; ?></p>
    </div>
    <?php endif; ?>

    <?php if ($view === 'hub' && $isStudent): ?>
        <?php $mainFull = $mainMaxCap !== null && $mainMaxCap > 0 && $mainRegCount >= $mainMaxCap; ?>
        <div class="eah-rsvp-strip" id="eahMainEventRsvpBar">
            <?php if ($studentMainRsvped): ?>
                <span class="eah-rsvp-strip__ok"><i class="fas fa-check-circle"></i> Registered</span>
                <?php if ($mainEventOpenForRsvp): ?>
                    <button type="button" class="eah-rsvp-strip__btn js-eah-main-cancel-rsvp" data-event-id="<?= (int) $eventId ?>">Cancel</button>
                <?php endif; ?>
            <?php elseif ($mainEventOpenForRsvp && !$mainFull): ?>
                <button type="button" class="eah-rsvp-strip__btn eah-rsvp-strip__btn--primary js-eah-main-rsvp" data-event-id="<?= (int) $eventId ?>">RSVP for event</button>
            <?php elseif ($mainFull): ?>
                <span class="eah-rsvp-strip__hint">Event full</span>
            <?php else: ?>
                <span class="eah-rsvp-strip__hint">Event RSVP closed</span>
            <?php endif; ?>
        </div>
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

            <div class="eah-stub-links">
                <?php if (!empty($ds['latitude']) && !empty($ds['longitude'])): ?>
                    <a href="https://www.openstreetmap.org/?mlat=<?= urlencode((string) $ds['latitude']) ?>&mlon=<?= urlencode((string) $ds['longitude']) ?>#map=17/<?= urlencode((string) $ds['latitude']) ?>/<?= urlencode((string) $ds['longitude']) ?>" target="_blank" rel="noopener"><i class="fas fa-map me-1"></i>View on map</a>
                <?php endif; ?>
                <a href="<?= eah_h($hubUrl) ?>"><i class="fas fa-arrow-left me-1"></i>All activities</a>
            </div>

        </article>

        <div class="eah-detail-sticky">
            <?php if ($isStudent && $status !== 'cancelled'): ?>
                <?php if (!empty($ds['user_rsvped'])): ?>
                    <button type="button" class="eah-btn eah-btn-primary eah-btn-block js-eah-cancel-rsvp" data-session-id="<?= (int) $ds['id'] ?>">
                        <i class="fas fa-check-circle"></i> You're RSVP'd — tap to cancel
                    </button>
                <?php elseif (eventify_session_allows_rsvp($ds)): ?>
                    <button type="button" class="eah-btn eah-btn-primary eah-btn-block js-eah-rsvp" data-session-id="<?= (int) $ds['id'] ?>">
                        <i class="fas fa-user-plus"></i> RSVP for this activity
                    </button>
                <?php else: ?>
                    <div class="eah-detail-sticky__muted"><i class="fas fa-clock me-1"></i> RSVP closed — activity ended</div>
                <?php endif; ?>
            <?php elseif ($isOrganizer): ?>
                <a class="eah-btn eah-btn-primary eah-btn-block" href="<?= eah_h(BASE_URL . '/activity_qr.php?id=' . (int) $ds['id']) ?>" target="_blank" rel="noopener">
                    <i class="fas fa-qrcode"></i> Open check-in QR
                </a>
            <?php endif; ?>
        </div>

        </div>



    <?php elseif ($view === 'category' || $view === 'day'): ?>

        <div class="eah-breadcrumb">

            <a href="<?= eah_h($hubUrl) ?>"><i class="fas fa-arrow-left me-1"></i>All activities</a>

        </div>

        <div class="eah-list-header">

            <h2 class="eah-page-title"><?= eah_h($listTitle) ?></h2>

        </div>

        <?php if ($listSessions === []): ?>

            <div class="eah-empty">
                <div class="eah-empty-icon"><i class="fas fa-calendar-xmark"></i></div>
                <div class="eah-empty-title">Nothing here yet</div>
                <p class="eah-empty-text">No activities in this section.</p>
            </div>

        <?php else: ?>

            <div class="eah-timeline-list">
                <?php foreach (eah_sort_sessions_by_time($listSessions) as $s): ?>
                    <?php eah_render_activity_row($s, $eventId, $todayYmd, $view === 'category'); ?>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>



    <?php else: ?>

        <?php if ($allSessions === []): ?>

            <div class="eah-empty">
                <div class="eah-empty-icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="eah-empty-title">No activities yet</div>
                <p class="eah-empty-text">
                    <?php if ($isOrganizer): ?>
                        Open the calendar, pick a day, and use <strong>Manage activities</strong> to add sessions.
                    <?php else: ?>
                        The organizer has not published a schedule for this event yet.
                    <?php endif; ?>
                </p>
            </div>

        <?php else: ?>

            <?php
            $featuredSession = null;
            if ($liveSessions !== []) {
                $featuredSession = eah_sort_sessions_by_time($liveSessions)[0];
            } elseif ($todaySessionsSorted !== []) {
                $featuredSession = $todaySessionsSorted[0];
            } elseif ($allSessions !== []) {
                $featuredSession = eah_sort_sessions_by_time($allSessions)[0];
            }
            $todayLeague = array_slice($todaySessionsSorted, 0, 5);
            $catList = array_keys($byCategory);
            $maxCats = 5;
            $visibleCats = array_slice($catList, 0, $maxCats);
            $hasMoreCats = count($catList) > $maxCats;
            ?>

            <h2 class="eah-sp-heading">Activities</h2>

            <div class="eah-sp-grid" id="eah-schedule">

                <div class="eah-sp-col eah-sp-col--cats" id="eah-sp-cats">
                    <?php foreach ($visibleCats as $catName): ?>
                        <?php
                        $items = $byCategory[$catName] ?? [];
                        $liveInCat = count(array_filter($items, static function ($s) use ($todayYmd) {
                            return eventify_session_is_live_now($s, $todayYmd);
                        }));
                        ?>
                        <a class="eah-sp-card eah-sp-card--cat"
                           href="<?= eah_h(eah_hub_link($eventId, ['category' => $catName])) ?>"
                           data-search="<?= eah_h(strtolower($catName)) ?>"
                           data-cat="<?= eah_h($catName) ?>">
                            <span class="eah-sp-card__count"><?= eah_h(eah_count_label(count($items))) ?></span>
                            <?php if ($liveInCat > 0): ?><span class="eah-sp-badge eah-sp-badge--live">LIVE</span><?php endif; ?>
                            <span class="eah-sp-card__icon"><?= eventify_activity_icon($catName, $catName) ?></span>
                            <span class="eah-sp-card__label"><?= eah_h($catName) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($hasMoreCats): ?>
                        <a class="eah-sp-card eah-sp-card--cat eah-sp-card--more" href="#eah-sp-cats-extra">
                            <span class="eah-sp-card__icon">⋯</span>
                            <span class="eah-sp-card__label">More</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($byCategory === []): ?>
                        <div class="eah-sp-card eah-sp-card--cat eah-sp-card--static">
                            <span class="eah-sp-card__icon">📋</span>
                            <span class="eah-sp-card__label">General</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="eah-sp-col eah-sp-col--feat">

                    <?php if ($liveSessions !== []): ?>
                        <a class="eah-sp-card eah-sp-card--live" id="eah-sp-live"
                           href="<?= eah_h(eah_hub_link($eventId, ['activity' => (int) $liveSessions[0]['id']])) ?>"
                           data-search="live">
                            <span class="eah-sp-badge eah-sp-badge--live">LIVE</span>
                            <span class="eah-sp-card__icon">🔴</span>
                            <span class="eah-sp-card__label">Live now</span>
                            <span class="eah-sp-card__count eah-sp-card__count--br"><?= count($liveSessions) ?></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($todaySessionsSorted !== []): ?>
                        <a class="eah-sp-card eah-sp-card--day" id="eah-sp-today"
                           href="<?= eah_h(eah_hub_link($eventId, ['day' => $todayYmd])) ?>"
                           data-search="today <?= eah_h(strtolower(date('l F j'))) ?>">
                            <span class="eah-sp-card__icon">📆</span>
                            <span class="eah-sp-card__label">Today</span>
                            <span class="eah-sp-card__sub"><?= eah_h(date('M j')) ?></span>
                            <span class="eah-sp-card__count eah-sp-card__count--br"><?= count($todaySessionsSorted) ?></span>
                        </a>
                    <?php endif; ?>

                    <div class="eah-sp-days" id="eah-sp-days">
                        <?php foreach ($byDate as $ymd => $items): ?>
                            <?php if ($ymd === $todayYmd && $todaySessionsSorted !== []) {
                                continue;
                            } ?>
                            <a class="eah-sp-card eah-sp-card--day eah-sp-card--day-sm"
                               href="<?= eah_h(eah_hub_link($eventId, ['day' => $ymd])) ?>"
                               data-search="<?= eah_h(strtolower(date('l F j', strtotime($ymd)))) ?>">
                                <span class="eah-sp-card__icon">🗓️</span>
                                <span class="eah-sp-card__label"><?= eah_h(date('D, M j', strtotime($ymd))) ?></span>
                                <span class="eah-sp-card__count eah-sp-card__count--br"><?= count($items) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($featuredSession): ?>
                        <a class="eah-sp-card eah-sp-card--featured"
                           href="<?= eah_h(eah_hub_link($eventId, ['activity' => (int) $featuredSession['id']])) ?>"
                           data-search="<?= eah_h(strtolower(($featuredSession['title'] ?? '') . ' ' . ($featuredSession['category'] ?? ''))) ?>">
                            <?php if ($liveSessions !== []): ?>
                                <span class="eah-sp-badge eah-sp-badge--hot">HOT</span>
                            <?php endif; ?>
                            <span class="eah-sp-card__icon"><?= eventify_activity_icon($featuredSession['title'] ?? '', $featuredSession['category'] ?? null) ?></span>
                            <span class="eah-sp-card__label eah-sp-card__label--lg"><?= eah_h($featuredSession['title'] ?? 'Featured') ?></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($todayLeague !== []): ?>
                        <div class="eah-sp-league" id="eah-sp-league">
                            <?php foreach ($todayLeague as $s): ?>
                                <a class="eah-sp-league-row"
                                   href="<?= eah_h(eah_hub_link($eventId, ['activity' => (int) $s['id']])) ?>"
                                   data-search="<?= eah_h(strtolower(($s['title'] ?? '') . ' ' . ($s['category'] ?? '') . ' ' . ($s['location'] ?? ''))) ?>">
                                    <span class="eah-sp-league-row__icon"><?= eventify_activity_icon($s['title'] ?? '', $s['category'] ?? null) ?></span>
                                    <span class="eah-sp-league-row__title"><?= eah_h($s['title'] ?? '') ?></span>
                                    <?php if (eventify_session_is_live_now($s, $todayYmd)): ?>
                                        <span class="eah-sp-badge eah-sp-badge--live eah-sp-badge--xs">LIVE</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
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

</div>

<?php include __DIR__ . '/views/partials/logout_confirm_modal.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<script src="<?= BASE_URL ?>/assets/js/event_activities_hub_nav.js"></script>
<script>
(function () {
    var search = document.getElementById('eahHubSearch');
    if (!search) return;
    search.addEventListener('input', function () {
        var q = (search.value || '').toLowerCase().trim();
        document.querySelectorAll('[data-search]').forEach(function (el) {
            var hay = (el.getAttribute('data-search') || '').toLowerCase();
            el.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
</script>

<?php if ($isStudent): ?>

<script>
window.csrfToken = <?= json_encode($csrfToken) ?>;
window.__eahMainEventId = <?= (int) $eventId ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_pwa.js"></script>
<script src="<?= BASE_URL ?>/assets/js/event_day_sessions.js"></script>
<script src="<?= BASE_URL ?>/assets/js/event_activities_main_rsvp.js"></script>

<?php endif; ?>

</body>

</html>


