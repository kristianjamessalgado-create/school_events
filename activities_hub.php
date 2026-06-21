<?php
/**
 * Activities hub landing — choose an event to browse day activities, schedules, and check-ins.
 */
session_start();

if (!defined('BASE_URL')) {
    define('BASE_URL', '/school_events');
}

include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
require_once __DIR__ . '/backend/lib/event_day_sessions.php';
require_once __DIR__ . '/backend/lib/event_calendar.php';
require_once __DIR__ . '/backend/lib/event_checkin_security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode(
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . '/activities_hub.php'
    ));
    exit();
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$isStudent = $role === 'student';
$menuUserName = trim((string) ($_SESSION['name'] ?? ''));
$todayYmd = eventify_today_ymd();

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

$hubUrl = BASE_URL . '/activities_hub.php';
$activities_hub_events = [];
$studentTodayActivities = [];
$studentRegisteredEvents = [];
$studentAttendanceCounts = ['events' => 0, 'activities' => 0, 'total' => 0];

try {
    eventify_event_day_sessions_ensure_enhanced($conn);
    $activities_hub_events = eventify_load_activities_hub_picker_events($conn, $userId, $role, $studentDept);
    if ($role === 'student') {
        $studentTodayActivities = eventify_load_student_today_activities($conn, $userId, $studentDept, $todayYmd);
        $studentRegisteredEvents = eventify_load_student_registered_hub_events($conn, $userId, $studentDept);
        $activities_hub_events = eventify_merge_registered_events_into_hub_picker($activities_hub_events, $studentRegisteredEvents);
        $studentAttendanceCounts = eventify_student_attendance_counts($conn, $userId);
    }
} catch (Throwable $e) {
    $activities_hub_events = [];
    $studentTodayActivities = [];
    $studentRegisteredEvents = [];
    $studentAttendanceCounts = ['events' => 0, 'activities' => 0, 'total' => 0];
}

$activities_hub_count = count($activities_hub_events);
$showHubStatusFilter = ($role !== 'student');
$hubStatusOptions = ['active', 'pending', 'closed', 'rejected'];
$hubStatusDefault = ($role === 'organizer')
    ? $hubStatusOptions
    : ['active'];
$hubStatusSelected = $showHubStatusFilter ? $hubStatusDefault : ['active'];
$statusParam = strtolower(trim((string) ($_GET['status'] ?? '')));
if ($showHubStatusFilter && $statusParam !== '') {
    $parsed = array_values(array_filter(array_map('trim', explode(',', $statusParam)), static function ($s) use ($hubStatusOptions) {
        return in_array($s, $hubStatusOptions, true);
    }));
    if ($parsed !== []) {
        $hubStatusSelected = $parsed;
    }
}
$activities_hub_visible_count = $activities_hub_count;
if (!$showHubStatusFilter) {
    $activities_hub_visible_count = 0;
    foreach ($activities_hub_events as $hubEv) {
        if (ah_event_status_filter_bucket((string) ($hubEv['status'] ?? '')) === 'active') {
            $activities_hub_visible_count += 1;
        }
    }
}
try {
    $mainHubUrl = eventify_activities_hub_main_url($conn, $userId, $role, $studentDept);
} catch (Throwable $e) {
    $mainHubUrl = $hubUrl;
}
$showMainHubNav = rtrim((string) $mainHubUrl, '/') !== rtrim((string) $hubUrl, '/');
$conn->close();

function ah_event_status_filter_bucket(string $status): string
{
    $st = strtolower(trim($status));
    if ($st === 'completed') {
        return 'closed';
    }
    if (in_array($st, ['active', 'pending', 'closed', 'rejected'], true)) {
        return $st;
    }
    return 'closed';
}

function ah_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** @return array{class: string, label: string, card_class: string} */
function ah_event_status_display(string $status): array
{
    $st = strtolower(trim($status));
    $label = $st !== '' ? ucfirst($st) : 'Unknown';
    if ($st === 'completed') {
        $label = 'Closed';
    }
    $map = [
        'active' => ['eah-status-pill--active', 'eah-picker-card--status-active'],
        'pending' => ['eah-status-pill--pending', 'eah-picker-card--status-pending'],
        'rejected' => ['eah-status-pill--rejected', 'eah-picker-card--status-rejected'],
        'closed' => ['eah-status-pill--closed', 'eah-picker-card--status-closed'],
        'completed' => ['eah-status-pill--closed', 'eah-picker-card--status-closed'],
    ];
    [$pill, $card] = $map[$st] ?? ['eah-status-pill--closed', 'eah-picker-card--status-closed'];

    return [
        'class' => 'eah-status-pill ' . $pill,
        'label' => $label,
        'card_class' => $card,
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($isStudent): ?>
    <meta name="theme-color" content="#153313">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="EVENTIFY">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest-student.php">
    <?php endif; ?>
    <title>Activities hub | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_activities_hub.css?v=31">
    <?php if ($showHubStatusFilter): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/activities_hub_filter.css?v=2">
    <?php endif; ?>
</head>
<body class="event-activities-hub event-activities-hub--index<?= $isStudent ? ' event-activities-hub--student' : '' ?>">
<div class="eah-wrap">
    <header class="eah-topbar">
        <button type="button" class="eah-topbar__menu" id="eahNavOpen" aria-label="Open menu" aria-expanded="false" aria-controls="eahNavDrawer">
            <i class="fas fa-bars"></i>
        </button>
        <a class="eah-topbar__logo" href="<?= ah_h($hubUrl) ?>"><i class="fas fa-calendar-alt" aria-hidden="true"></i><span>EVENTIFY</span></a>
        <div class="eah-topbar__actions">
            <a class="eah-topbar__action is-active" href="<?= ah_h($hubUrl) ?>" aria-current="page">
                <i class="fas fa-th-large me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Activities hub</span>
            </a>
            <?php if ($showMainHubNav): ?>
            <a class="eah-topbar__action" href="<?= ah_h($mainHubUrl) ?>" aria-label="Main hub">
                <i class="fas fa-calendar-day me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Main hub</span>
            </a>
            <?php endif; ?>
            <a class="eah-topbar__action" href="<?= ah_h($backUrl) ?>">
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
                        <div class="eah-nav-drawer__user"><?= ah_h($menuUserName) ?></div>
                    <?php endif; ?>
                </div>
                <button type="button" class="eah-nav-drawer__close" id="eahNavClose" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="eah-nav-drawer__list">
                <li>
                    <a class="eah-nav-drawer__link" href="<?= ah_h($backUrl) ?>">
                        <i class="fas fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php if ($showMainHubNav): ?>
                <li>
                    <a class="eah-nav-drawer__link" href="<?= ah_h($mainHubUrl) ?>">
                        <i class="fas fa-calendar-day"></i>
                        <span>Main hub</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a class="eah-nav-drawer__link is-active" href="<?= ah_h($hubUrl) ?>" aria-current="page">
                        <i class="fas fa-th-large"></i>
                        <span>Activities hub<?php if ($activities_hub_count > 0): ?> <span class="eah-nav-count"><?= $activities_hub_count > 99 ? '99+' : $activities_hub_count ?></span><?php endif; ?></span>
                    </a>
                </li>
                <?php if ($isStudent): ?>
                <li>
                    <a class="eah-nav-drawer__link" href="<?= ah_h(BASE_URL . '/attendance_history.php') ?>">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Attendance history<?php if ($studentAttendanceCounts['total'] > 0): ?> <span class="eah-nav-count"><?= $studentAttendanceCounts['total'] > 99 ? '99+' : (int) $studentAttendanceCounts['total'] ?></span><?php endif; ?></span>
                    </a>
                </li>
                <li>
                    <a class="eah-nav-drawer__link" href="<?= ah_h(BASE_URL . '/my_tickets.php') ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>My tickets</span>
                    </a>
                </li>
                <li>
                    <a class="eah-nav-drawer__link" href="<?= ah_h(BASE_URL . '/backend/auth/dashboard_student.php?open_modal=scan') ?>">
                        <i class="fas fa-qrcode"></i>
                        <span>Scan QR</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="eah-nav-drawer__footer">
                <a class="eah-nav-drawer__link eah-nav-drawer__link--danger" href="<?= ah_h(BASE_URL . '/backend/auth/logout.php') ?>" data-logout-confirm>
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log out</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="eah-hub-panel">
        <div class="eah-event-hero">
            <div class="eah-event-hero__body">
                <p class="eah-event-hero__eyebrow">Activities hub</p>
                <h1 class="eah-event-hero__title"><?= $isStudent ? 'Your events &amp; activities' : 'Browse events' ?></h1>
                <p class="eah-event-hero__meta">
                    <?php if ($isStudent && $studentTodayActivities !== []): ?>
                        <span class="eah-event-hero__live"><i class="fas fa-bolt" aria-hidden="true"></i> <?= count($studentTodayActivities) ?> today</span>
                    <?php endif; ?>
                    <span id="eahHubEventCount"><i class="fas fa-th-large" aria-hidden="true"></i> <?= $showHubStatusFilter ? $activities_hub_count : $activities_hub_visible_count ?> event<?= ($showHubStatusFilter ? $activities_hub_count : $activities_hub_visible_count) === 1 ? '' : 's' ?></span>
                </p>
            </div>
        </div>

        <?php if ($showHubStatusFilter && $activities_hub_count > 0): ?>
        <div class="eah-hub-toolbar">
            <div class="eah-hub-filter">
                <div class="eah-hub-filter__head">
                    <span class="eah-hub-filter__label">Show status</span>
                    <span class="eah-hub-filter__hint">Tap to show or hide</span>
                </div>
                <div class="eah-hub-filter__chips" role="group" aria-label="Filter by event status">
                    <?php foreach ($hubStatusOptions as $statusKey): ?>
                        <?php
                            $chipLabels = [
                                'active' => 'Active',
                                'pending' => 'Pending',
                                'closed' => 'Closed',
                                'rejected' => 'Rejected',
                            ];
                            $isSelected = in_array($statusKey, $hubStatusSelected, true);
                        ?>
                        <button type="button"
                            class="eah-hub-filter__chip eah-hub-filter__chip--<?= ah_h($statusKey) ?><?= $isSelected ? ' is-selected' : '' ?>"
                            data-eah-status="<?= ah_h($statusKey) ?>"
                            aria-pressed="<?= $isSelected ? 'true' : 'false' ?>">
                            <?= ah_h($chipLabels[$statusKey] ?? ucfirst($statusKey)) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="eah-hub-filter__quick">
                    <button type="button" class="eah-hub-filter__quick-btn" data-eah-filter-all>Select all</button>
                    <span class="eah-hub-filter__quick-sep" aria-hidden="true">·</span>
                    <button type="button" class="eah-hub-filter__quick-btn" data-eah-filter-none>Clear all</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isStudent && $studentTodayActivities !== []): ?>
        <section class="eah-landing-section" aria-labelledby="eahTodayHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahTodayHeading"><i class="fas fa-bolt" aria-hidden="true"></i> Today's activities</h2>
            </div>
            <div class="eah-landing-scroll">
                <?php foreach ($studentTodayActivities as $idx => $act): ?>
                    <?php
                        $eventId = (int) ($act['event_id'] ?? 0);
                        $activityId = (int) ($act['id'] ?? 0);
                        $actHref = BASE_URL . '/event_activities.php?id=' . $eventId . ($activityId > 0 ? '&activity=' . $activityId : '');
                        $timeStr = eventify_format_session_time_range($act['start_time'] ?? null, $act['end_time'] ?? null);
                        $isLive = eventify_session_is_live_now($act, $todayYmd);
                        $accentClass = ($idx % 2 === 0) ? 'eah-landing-chip--warm' : 'eah-landing-chip--cool';
                    ?>
                    <a class="eah-landing-chip <?= ah_h($accentClass) ?>" href="<?= ah_h($actHref) ?>">
                        <span class="eah-landing-chip__icon"><?= eventify_activity_icon((string) ($act['title'] ?? ''), $act['category'] ?? null) ?></span>
                        <span class="eah-landing-chip__body">
                            <span class="eah-landing-chip__title"><?= ah_h((string) ($act['title'] ?? 'Activity')) ?></span>
                            <span class="eah-landing-chip__meta">
                                <?= ah_h($timeStr) ?>
                                <?php if (!empty($act['event_title'])): ?> · <?= ah_h((string) $act['event_title']) ?><?php endif; ?>
                            </span>
                        </span>
                        <?php if ($isLive): ?><span class="eah-pill eah-pill--live">Live</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($isStudent && $studentRegisteredEvents !== []): ?>
        <section class="eah-landing-section" aria-labelledby="eahMyEventsHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahMyEventsHeading"><i class="fas fa-id-card-alt" aria-hidden="true"></i> My registered events</h2>
            </div>
            <div class="eah-landing-grid">
                <?php foreach (array_slice($studentRegisteredEvents, 0, 6) as $mev): ?>
                    <?php
                        $meid = (int) ($mev['id'] ?? 0);
                        $meEnd = function_exists('eventify_event_resolve_end_date') ? eventify_event_resolve_end_date($mev) : ($mev['date'] ?? '');
                        $actCount = (int) ($mev['activity_count'] ?? 0);
                    ?>
                    <a class="eah-landing-my-card" href="<?= ah_h(BASE_URL . '/event_activities.php?id=' . $meid) ?>">
                        <span class="eah-landing-my-card__title"><?= ah_h((string) ($mev['title'] ?? 'Event')) ?></span>
                        <span class="eah-landing-my-card__meta">
                            <i class="fas fa-calendar-day" aria-hidden="true"></i> <?= ah_h((string) ($meEnd ?: ($mev['date'] ?? ''))) ?>
                            · <?= $actCount ?> activit<?= $actCount === 1 ? 'y' : 'ies' ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($isStudent && $studentAttendanceCounts['total'] > 0): ?>
        <section class="eah-landing-section" aria-labelledby="eahAttendedHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahAttendedHeading"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Where you've been</h2>
                <a class="eah-landing-section__link" href="<?= ah_h(BASE_URL . '/attendance_history.php') ?>">View all</a>
            </div>
            <a class="eah-landing-attendance-card" href="<?= ah_h(BASE_URL . '/attendance_history.php') ?>">
                <span class="eah-landing-attendance-card__icon"><i class="fas fa-history"></i></span>
                <span class="eah-landing-attendance-card__body">
                    <span class="eah-landing-attendance-card__title">Attendance history</span>
                    <span class="eah-landing-attendance-card__meta">
                        <?= (int) $studentAttendanceCounts['total'] ?> check-in<?= $studentAttendanceCounts['total'] === 1 ? '' : 's' ?>
                        <?php if ($studentAttendanceCounts['events'] > 0 && $studentAttendanceCounts['activities'] > 0): ?>
                            · <?= (int) $studentAttendanceCounts['events'] ?> event<?= $studentAttendanceCounts['events'] === 1 ? '' : 's' ?>, <?= (int) $studentAttendanceCounts['activities'] ?> activit<?= $studentAttendanceCounts['activities'] === 1 ? 'y' : 'ies' ?>
                        <?php endif; ?>
                    </span>
                </span>
                <span class="eah-landing-attendance-card__chev" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
            </a>
        </section>
        <?php endif; ?>

        <?php if ($activities_hub_count > 0): ?>
        <section class="eah-landing-section" aria-labelledby="eahAllEventsHeading">
            <div class="eah-landing-section__head">
                <h2 class="eah-landing-section__title" id="eahAllEventsHeading">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                    <?= $isStudent ? 'Upcoming events' : ($role === 'organizer' ? 'Your events' : 'Events with activities') ?>
                </h2>
            </div>
            <div class="eah-picker-list" id="eahHubEventList">
                <?php foreach ($activities_hub_events as $ev): ?>
                    <?php
                        $eid = (int) ($ev['id'] ?? 0);
                        $st = strtolower((string) ($ev['status'] ?? ''));
                        $statusUi = ah_event_status_display($st);
                        $actCount = (int) ($ev['activity_count'] ?? 0);
                        $eventHref = BASE_URL . '/event_activities.php?id=' . $eid;
                        $filterBucket = ah_event_status_filter_bucket($st);
                        $isVisible = in_array($filterBucket, $hubStatusSelected, true);
                    ?>
                    <a class="eah-picker-card <?= ah_h($statusUi['card_class']) ?>" href="<?= ah_h($eventHref) ?>" data-filter-status="<?= ah_h($filterBucket) ?>"<?= !$isVisible ? ' hidden' : '' ?>>
                        <div class="eah-picker-card__main">
                            <h3 class="eah-picker-card__title">
                                <i class="fas fa-calendar-day eah-picker-card__title-icon" aria-hidden="true"></i>
                                <?= ah_h((string) ($ev['title'] ?? 'Untitled')) ?>
                            </h3>
                            <p class="eah-picker-card__meta">
                                <?php if (!empty($ev['date'])): ?>
                                    <span><i class="fas fa-clock" aria-hidden="true"></i> <?= ah_h(date('M j, Y', strtotime((string) $ev['date']))) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($ev['location'])): ?>
                                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= ah_h(mb_strimwidth((string) $ev['location'], 0, 56, '…')) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="eah-picker-card__aside">
                            <?php if ($actCount > 0): ?>
                                <span class="eah-picker-card__badge"><?= $actCount ?> activit<?= $actCount === 1 ? 'y' : 'ies' ?></span>
                            <?php else: ?>
                                <span class="eah-picker-card__badge eah-picker-card__badge--muted">Schedule coming</span>
                            <?php endif; ?>
                            <?php if ($st !== '' && $showHubStatusFilter): ?>
                                <span class="<?= ah_h($statusUi['class']) ?>"><?= ah_h($statusUi['label']) ?></span>
                            <?php endif; ?>
                            <span class="eah-picker-card__chev-wrap" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="eah-hub-filter-empty" id="eahHubFilterEmpty"<?= ($showHubStatusFilter || $activities_hub_visible_count > 0) ? ' hidden' : '' ?>>
                <div class="eah-empty-icon"><i class="fas fa-filter"></i></div>
                <div class="eah-empty-title"><?= $showHubStatusFilter ? 'No events match' : 'No active events' ?></div>
                <p class="eah-empty-text mb-0" id="eahHubFilterEmptyText"><?= $showHubStatusFilter ? 'Select at least one status above, or tap <strong>Select all</strong>.' : 'Check back when new events are approved on your dashboard.' ?></p>
            </div>
        </section>
        <?php else: ?>
            <div class="eah-empty">
                <div class="eah-empty-icon"><i class="fas fa-calendar-plus"></i></div>
                <div class="eah-empty-title"><?= $isStudent ? 'No events yet' : 'No events with activities yet' ?></div>
                <p class="eah-empty-text">
                    <?php if ($isStudent): ?>
                        RSVP for an event on your dashboard calendar — it will show up here when activities are published.
                    <?php elseif ($role === 'organizer'): ?>
                        Create an event on your dashboard, then open it here to add day activities.
                    <?php else: ?>
                        When organizers publish day activities for an event, it will appear here.
                    <?php endif; ?>
                </p>
                <div class="eah-empty-actions">
                    <a class="eah-btn eah-btn-primary" href="<?= ah_h($backUrl) ?>">
                        <i class="fas fa-home me-1" aria-hidden="true"></i> Back to dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<?php if ($showHubStatusFilter): ?>
<script>
window.__eahHubStatusDefault = <?= json_encode(array_values($hubStatusDefault), JSON_UNESCAPED_UNICODE) ?>;
window.__eahHubStatusInitial = <?= json_encode(array_values($hubStatusSelected), JSON_UNESCAPED_UNICODE) ?>;
window.__eahHubStatusOptions = <?= json_encode($hubStatusOptions, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/activities_hub_filter.js?v=2"></script>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/event_activities_hub_nav.js"></script>
</body>
</html>
