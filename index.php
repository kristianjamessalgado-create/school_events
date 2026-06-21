<?php
require_once __DIR__ . '/config/session.php';
if (!defined('BASE_URL')) define('BASE_URL','/school_events');

$checkin_token = trim($_GET['t'] ?? '');
$session_checkin_token = trim($_GET['st'] ?? '');
$ticket_checkin_token = trim($_GET['tk'] ?? '');
$auth_modal = trim((string)($_GET['auth_modal'] ?? ''));
$auth_error = trim((string)($_GET['auth_error'] ?? ''));
$auth_success = trim((string)($_GET['auth_success'] ?? ''));
$auth_redirect = trim((string)($_GET['redirect'] ?? ''));
$verify_purpose = (($_GET['verify_purpose'] ?? $_GET['purpose'] ?? 'register') === 'reactivate') ? 'reactivate' : 'register';
$verify_email = strtolower(trim((string)($_GET['verify_email'] ?? $_GET['email'] ?? '')));
if ($auth_modal === 'verify' && $verify_email !== '') {
    require_once __DIR__ . '/backend/lib/account_email_otp.php';
    $verifyFlash = eventify_consume_verify_otp_flash($verify_purpose, $verify_email);
    if ($verifyFlash['success'] !== '') {
        $auth_success = $verifyFlash['success'];
        $auth_error = '';
    } elseif ($verifyFlash['error'] !== '') {
        $auth_error = $verifyFlash['error'];
        $auth_success = '';
    } else {
        // Do not keep stale success text from an old bookmarked/refreshed URL.
        $auth_success = '';
    }
}
$studentCourseOptions = [];
$studentYearLevelOptions = [];
try {
    require_once __DIR__ . '/config/student_profile_fields.php';
    if (function_exists('eventify_student_course_program_options')) {
        $studentCourseOptions = eventify_student_course_program_options();
    }
    if (function_exists('eventify_student_year_level_options')) {
        $studentYearLevelOptions = eventify_student_year_level_options();
    }
} catch (Throwable $e) {
    $studentCourseOptions = [];
    $studentYearLevelOptions = [];
}

// Public landing: show upcoming active events on a calendar (login required to view details/RSVP)
$publicCalendarEvents = [];
$publicUpcomingList = [];
$publicPastList = [];
$publicAllList = [];
$publicPhotoPreviewList = [];
try {
    include __DIR__ . '/config/db.php';
    include __DIR__ . '/config/config.php';
    include __DIR__ . '/config/csrf.php';
    require_once __DIR__ . '/backend/lib/event_calendar.php';
    $today = date('Y-m-d');
    $stmtPub = $conn->prepare("
        SELECT id, title, description, date, end_date, start_time, end_time, location, department, status
        FROM events
        WHERE status IN ('active','completed','closed')
        ORDER BY date DESC, start_time DESC, id DESC
        LIMIT 400
    ");
    $pubRows = [];
    if ($stmtPub) {
        if ($stmtPub->execute()) {
            $res = $stmtPub->get_result();
            while ($row = $res->fetch_assoc()) {
                if (trim($row['date'] ?? '') === '') {
                    continue;
                }
                $pubRows[] = $row;
            }
        }
        $stmtPub->close();
    }
    eventify_events_attach_schedule_dates($conn, $pubRows);
    foreach ($pubRows as $row) {
        $date = trim($row['date'] ?? '');
        $publicAllList[] = [
            'id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? 'Untitled'),
            'description' => (string)($row['description'] ?? ''),
            'date' => $date,
            'end_date' => trim((string)($row['end_date'] ?? '')),
            'schedule_dates' => $row['schedule_dates'] ?? [],
            'start_time' => trim((string)($row['start_time'] ?? '')),
            'end_time' => trim((string)($row['end_time'] ?? '')),
            'location' => (string)($row['location'] ?? ''),
            'department' => (string)($row['department'] ?? 'ALL'),
            'status' => (string)($row['status'] ?? 'active'),
        ];
    }
    $publicCalendarEvents = eventify_events_to_fullcalendar_list($pubRows, static function ($row) {
        return [
            'location' => $row['location'] ?? '',
            'department' => $row['department'] ?? 'ALL',
            'status' => $row['status'] ?? '',
        ];
    });
    // Landing photo preview rail (prefer published-only if migration exists)
    $photoStatusEnabled = false;
    try {
        $colRes = $conn->query("SHOW COLUMNS FROM event_photos LIKE 'status'");
        $photoStatusEnabled = (bool)($colRes && $colRes->num_rows > 0);
    } catch (Throwable $e) {
        $photoStatusEnabled = false;
    }

    $photoSql = "
        SELECT p.file_path, p.created_at, e.id AS event_id, e.title, e.date
        FROM event_photos p
        INNER JOIN events e ON e.id = p.event_id
        WHERE e.title NOT LIKE 'sample%'
    ";
    if ($photoStatusEnabled) {
        $photoSql .= " AND p.status = 'published' ";
    }
    // Pull a wider pool first, then group in PHP (1 card per event, rotating photos per event).
    $photoSql .= " ORDER BY p.created_at DESC, p.id DESC LIMIT 220 ";

    $stmtPhotos = $conn->prepare($photoSql);
    if ($stmtPhotos && $stmtPhotos->execute()) {
        $resPhotos = $stmtPhotos->get_result();
        $photoGroupsByEvent = [];
        $eventOrder = [];
        while ($photo = $resPhotos->fetch_assoc()) {
            $path = trim((string)($photo['file_path'] ?? ''));
            if ($path === '') continue;
            $eventId = (int)($photo['event_id'] ?? 0);
            if ($eventId < 1) continue;

            if (!isset($photoGroupsByEvent[$eventId])) {
                $photoGroupsByEvent[$eventId] = [
                    'event_title' => (string)($photo['title'] ?? 'School event'),
                    'event_date' => (string)($photo['date'] ?? ''),
                    'photo_paths' => [],
                ];
                $eventOrder[] = $eventId;
            }
            // Keep a small set per event for fade rotation.
            if (count($photoGroupsByEvent[$eventId]['photo_paths']) < 6) {
                $photoGroupsByEvent[$eventId]['photo_paths'][] = $path;
            }
        }
        foreach ($eventOrder as $eid) {
            if (count($publicPhotoPreviewList) >= 24) break;
            $group = $photoGroupsByEvent[$eid] ?? null;
            if (!$group || empty($group['photo_paths'])) continue;
            $publicPhotoPreviewList[] = [
                'file_path' => (string)$group['photo_paths'][0],
                'event_title' => (string)($group['event_title'] ?? 'School event'),
                'event_date' => (string)($group['event_date'] ?? ''),
                'photo_paths' => array_values($group['photo_paths']),
            ];
        }
        $stmtPhotos->close();
    }

    if (isset($conn) && $conn) {
        $conn->close();
    }
} catch (Throwable $e) {
    $publicCalendarEvents = [];
    $publicUpcomingList = [];
    $publicPastList = [];
    $publicAllList = [];
    $publicPhotoPreviewList = [];
}

// Split for display
try {
    $publicUpcomingList = array_values(array_filter($publicAllList, function ($e) use ($today) {
        $d = (string)($e['date'] ?? '');
        return $d !== '' && $d >= $today;
    }));
    $publicPastList = array_values(array_filter($publicAllList, function ($e) use ($today) {
        $d = (string)($e['date'] ?? '');
        return $d !== '' && $d < $today;
    }));
} catch (Throwable $e) {
    $publicPastList = [];
}

if (!function_exists('eventify_qr_checkin_route')) {
    function eventify_qr_checkin_route(string $relativePath): void
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $targetPath = BASE_URL . $relativePath;
        $absolute = $scheme . '://' . $host . $targetPath;
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtolower((string) ($_SESSION['role'] ?? ''));

        if ($uid > 0 && $role === 'student') {
            header('Location: ' . $targetPath);
            exit();
        }
        if ($uid > 0 && $role !== 'student') {
            header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($absolute) . '&error=' . urlencode('Please sign in as a student to check in.'));
            exit();
        }
        header('Location: ' . BASE_URL . '/views/login.php?redirect=' . urlencode($absolute));
        exit();
    }
}

if ($session_checkin_token !== '') {
    eventify_qr_checkin_route('/activity_checkin.php?st=' . urlencode($session_checkin_token));
}

if ($ticket_checkin_token !== '') {
    eventify_qr_checkin_route('/ticket_checkin.php?tk=' . urlencode($ticket_checkin_token));
}

if ($checkin_token !== '') {
    eventify_qr_checkin_route('/checkin.php?t=' . urlencode($checkin_token));
}

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Enforce password change before dashboard access if flagged.
    try {
        include __DIR__ . '/config/db.php';
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid > 0) {
            $cp = $conn->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
            if ($cp) {
                $cp->bind_param("i", $uid);
                $cp->execute();
                $cpRow = $cp->get_result()->fetch_assoc();
                $cp->close();
                if ((int)($cpRow['must_change_password'] ?? 0) === 1) {
                    $next = BASE_URL . "/index.php";
                    if ($_SESSION['role'] === 'super_admin') $next = BASE_URL . "/backend/super_admin/dashboardsuperadmin.php";
                    elseif ($_SESSION['role'] === 'admin') $next = BASE_URL . "/backend/admin/dashboard.php";
                    elseif ($_SESSION['role'] === 'organizer') $next = BASE_URL . "/backend/auth/dashboardorganizer.php";
                    elseif ($_SESSION['role'] === 'student') $next = BASE_URL . "/backend/auth/dashboard_student.php";
                    elseif ($_SESSION['role'] === 'multimedia') $next = BASE_URL . "/backend/auth/dashboard_multimedia.php";
                    header("Location: " . BASE_URL . "/views/change_password.php?from=required&next=" . urlencode($next));
                    exit();
                }
            }
        }
    } catch (Throwable $e) {
        // ignore and continue default routing
    }
    switch ($_SESSION['role']) {
        case 'super_admin':
            header("Location: " . BASE_URL . "/backend/super_admin/dashboardsuperadmin.php");
            exit();
        case 'admin':
            header("Location: " . BASE_URL . "/backend/admin/dashboard.php");
            exit();
        case 'organizer':
            header("Location: " . BASE_URL . "/backend/auth/dashboardorganizer.php");
            exit();
        case 'student':
            header("Location: " . BASE_URL . "/backend/auth/dashboard_student.php");
            exit();
        case 'multimedia':
            header("Location: " . BASE_URL . "/backend/auth/dashboard_multimedia.php");
            exit();
    }
}

// Build login iframe URL: if we have a check-in token, pass redirect so after login they go to check-in
$login_src = BASE_URL . '/views/login.php';
if ($checkin_token !== '') {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $checkin_redirect = $scheme . '://' . $host . BASE_URL . '/checkin.php?t=' . urlencode($checkin_token);
    $login_src .= '?redirect=' . urlencode($checkin_redirect);
}

$landing_upcoming_n = count($publicUpcomingList);
$landing_past_n = count($publicPastList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>EVENTIFY</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css?v=4">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/calendar_legend.css">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
</head>
<body>

<!-- Background layers -->
<img src="<?= BASE_URL ?>/assets/img/gradient.png" alt="Background" class="bg-image">
<div class="layer-blur"></div>
<div class="noise-overlay" aria-hidden="true"></div>

<!-- Header -->
<header class="site-header">
    <div class="header-main">
        <h2 class="brand-wordmark">EVENTIFY</h2>
        <button type="button" class="hamburger" id="hamburgerBtn" aria-label="Open menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav class="desktop-nav">
            <a onclick="goToSection('public-calendar')">Home</a>
            <a onclick="goToSection('how-it-works')">How it works</a>
            <a onclick="goToSection('features')">Features</a>
            <a onclick="goToSection('roles')">Roles</a>
            <a onclick="goToSection('faq')">FAQ</a>
            <span class="magnetic-wrap">
                <a href="javascript:void(0)" class="btn btn-shimmer login-trigger" id="navGetStarted" data-login-url="<?= htmlspecialchars($login_src) ?>">Get Started</a>
            </span>
        </nav>
        <div class="mobile-nav-overlay" id="mobileNavOverlay" onclick="closeMobileNav()" aria-hidden="true"></div>
        <nav class="mobile-nav" id="mobileNav">
            <a onclick="goToSection('public-calendar'); closeMobileNav();">Home</a>
            <a onclick="goToSection('how-it-works'); closeMobileNav();">How it works</a>
            <a onclick="goToSection('features'); closeMobileNav();">Features</a>
            <a onclick="goToSection('roles'); closeMobileNav();">Roles</a>
            <a onclick="goToSection('faq'); closeMobileNav();">FAQ</a>
            <a href="javascript:void(0)" onclick="closeMobileNav()" class="btn btn-shimmer login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">Get Started</a>
        </nav>
    </div>
    <div class="header-progress-track" aria-hidden="true">
        <span class="header-progress-fill" id="landingScrollProgress"></span>
    </div>
</header>

<!-- Sections -->
<section id="public-calendar" class="active reveal-scope in-view">
    <h1 class="reveal-item" style="--reveal-d: 0ms">Upcoming events calendar</h1>
    <p class="reveal-item" style="--reveal-d: 70ms">Browse what’s coming up. To view full details and RSVP, you’ll be asked to log in.</p>

    <div class="landing-stat-strip reveal-item" style="--reveal-d: 120ms">
        <span class="stat-pill" title="Posted events (active or ended) on or after today."><strong><?= (int) $landing_upcoming_n ?></strong> <span class="stat-pill-label">from today</span></span>
        <span class="stat-pill stat-pill-muted" title="Posted events (active or ended) before today."><strong><?= (int) $landing_past_n ?></strong> <span class="stat-pill-label">earlier</span></span>
        <span class="stat-pill stat-pill-hint"><i class="fas fa-lock" aria-hidden="true"></i> Log in for details &amp; RSVP</span>
    </div>

    <div class="public-upcoming-wrap reveal-item" style="--reveal-d: 140ms">
        <div class="landing-photo-header">
            <h3 class="public-upcoming-title">Trending now</h3>
            <span class="landing-photo-subtitle">Published moments from recent events</span>
        </div>
        <?php if (!empty($publicPhotoPreviewList)): ?>
            <div class="landing-photo-rail-wrap">
                <button type="button" class="landing-rail-nav landing-rail-prev" aria-label="Scroll previews left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="landing-photo-rail" id="landingPhotoRail">
                    <?php foreach ($publicPhotoPreviewList as $idx => $photo): ?>
                        <?php
                            $photoSrc = BASE_URL . '/' . ltrim((string)($photo['file_path'] ?? ''), '/');
                            $photoTitle = (string)($photo['event_title'] ?? 'School event');
                            $photoDate = '';
                            try { $photoDate = date('M d, Y', strtotime((string)($photo['event_date'] ?? ''))); } catch (Throwable $e) { $photoDate = ''; }
                            $rank = (int)$idx + 1;
                            $photoPaths = array_values(array_filter((array)($photo['photo_paths'] ?? []), function ($p) {
                                return is_string($p) && trim($p) !== '';
                            }));
                            $photoUrls = [];
                            foreach ($photoPaths as $pp) {
                                $photoUrls[] = BASE_URL . '/' . ltrim($pp, '/');
                            }
                        ?>
                        <a class="landing-photo-card login-trigger" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>" data-photo-urls="<?= htmlspecialchars(json_encode($photoUrls, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)) ?>" aria-label="Log in to view photos for <?= htmlspecialchars($photoTitle) ?>">
                            <span class="landing-photo-rank"><?= $rank ?></span>
                            <img class="landing-photo-img" src="<?= htmlspecialchars($photoSrc) ?>" alt="<?= htmlspecialchars($photoTitle) ?>" loading="lazy" decoding="async">
                            <span class="landing-photo-overlay"></span>
                            <span class="landing-photo-meta">
                                <strong><?= htmlspecialchars($photoTitle) ?></strong>
                                <?php if ($photoDate !== ''): ?><small><?= htmlspecialchars($photoDate) ?></small><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="landing-rail-nav landing-rail-next" aria-label="Scroll previews right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="public-empty">No published photos yet.</div>
        <?php endif; ?>
    </div>

    <div class="public-upcoming-wrap reveal-item" style="--reveal-d: 160ms">
        <h3 class="public-upcoming-title">Upcoming events</h3>
        <?php if (!empty($publicUpcomingList)): ?>
            <div class="public-upcoming-grid">
                <?php foreach (array_slice($publicUpcomingList, 0, 6) as $ev): ?>
                    <?php
                      $dateLabel = '';
                      try { $dateLabel = date('M d, Y', strtotime((string)($ev['date'] ?? ''))); } catch (Throwable $e) { $dateLabel = (string)($ev['date'] ?? ''); }
                      $timeLabel = '';
                      $st = trim((string)($ev['start_time'] ?? ''));
                      $et = trim((string)($ev['end_time'] ?? ''));
                      if ($st !== '' && $et !== '') $timeLabel = $st . ' - ' . $et;
                      elseif ($st !== '') $timeLabel = $st;
                      $deptLabel = trim((string)($ev['department'] ?? ''));
                      $locLabel = trim((string)($ev['location'] ?? ''));
                      $evSt = strtolower((string)($ev['status'] ?? ''));
                      $isEndedCard = ($evSt === 'closed' || $evSt === 'completed');
                    ?>
                    <a class="public-upcoming-card login-trigger<?= $isEndedCard ? ' public-upcoming-card-ended' : '' ?>" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>" aria-label="Log in to view <?= htmlspecialchars((string)($ev['title'] ?? 'event')) ?>">
                        <div class="public-upcoming-card-top">
                            <div class="public-upcoming-card-title"><?= htmlspecialchars((string)($ev['title'] ?? 'Untitled')) ?></div>
                            <div class="public-upcoming-card-meta">
                                <span><?= htmlspecialchars($dateLabel) ?></span>
                                <?php if ($timeLabel !== ''): ?><span class="dot">•</span><span><?= htmlspecialchars($timeLabel) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="public-upcoming-card-bottom">
                            <?php if ($isEndedCard): ?><span class="chip chip-ended">Ended</span><?php endif; ?>
                            <?php if ($deptLabel !== ''): ?><span class="chip"><?= htmlspecialchars($deptLabel) ?></span><?php endif; ?>
                            <?php if ($locLabel !== ''): ?><span class="chip chip-muted"><?= htmlspecialchars($locLabel) ?></span><?php endif; ?>
                            <span class="chip chip-cta">Log in to view</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($publicUpcomingList) > 6): ?>
                <div class="public-upcoming-more">
                    <button type="button" class="btn btn-outline login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">View all events (login)</button>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="public-empty">
                No upcoming events posted yet.
            </div>
        <?php endif; ?>
    </div>

    <div class="public-upcoming-wrap reveal-item" style="--reveal-d: 200ms">
        <h3 class="public-upcoming-title">Existing events</h3>
        <?php if (!empty($publicPastList)): ?>
            <div class="public-upcoming-grid">
                <?php foreach (array_slice($publicPastList, 0, 6) as $ev): ?>
                    <?php
                      $dateLabel = '';
                      try { $dateLabel = date('M d, Y', strtotime((string)($ev['date'] ?? ''))); } catch (Throwable $e) { $dateLabel = (string)($ev['date'] ?? ''); }
                      $timeLabel = '';
                      $st = trim((string)($ev['start_time'] ?? ''));
                      $et = trim((string)($ev['end_time'] ?? ''));
                      if ($st !== '' && $et !== '') $timeLabel = $st . ' - ' . $et;
                      elseif ($st !== '') $timeLabel = $st;
                      $deptLabel = trim((string)($ev['department'] ?? ''));
                      $locLabel = trim((string)($ev['location'] ?? ''));
                      $evStPast = strtolower((string)($ev['status'] ?? ''));
                      $isEndedPastCard = ($evStPast === 'closed' || $evStPast === 'completed');
                    ?>
                    <a class="public-upcoming-card login-trigger<?= $isEndedPastCard ? ' public-upcoming-card-ended' : '' ?>" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>" aria-label="Log in to view <?= htmlspecialchars((string)($ev['title'] ?? 'event')) ?>">
                        <div class="public-upcoming-card-top">
                            <div class="public-upcoming-card-title"><?= htmlspecialchars((string)($ev['title'] ?? 'Untitled')) ?></div>
                            <div class="public-upcoming-card-meta">
                                <span><?= htmlspecialchars($dateLabel) ?></span>
                                <?php if ($timeLabel !== ''): ?><span class="dot">•</span><span><?= htmlspecialchars($timeLabel) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="public-upcoming-card-bottom">
                            <?php if ($isEndedPastCard): ?><span class="chip chip-ended">Ended</span><?php endif; ?>
                            <?php if ($deptLabel !== ''): ?><span class="chip"><?= htmlspecialchars($deptLabel) ?></span><?php endif; ?>
                            <?php if ($locLabel !== ''): ?><span class="chip chip-muted"><?= htmlspecialchars($locLabel) ?></span><?php endif; ?>
                            <span class="chip chip-cta">Log in to view</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($publicPastList) > 6): ?>
                <div class="public-upcoming-more">
                    <button type="button" class="btn btn-outline login-trigger" data-login-url="<?= htmlspecialchars($login_src) ?>">View all existing events (login)</button>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="public-empty">
                No existing events yet.
            </div>
        <?php endif; ?>
    </div>

    <div class="public-calendar-card reveal-item" style="--reveal-d: 240ms">
        <div class="public-calendar-toolbar">
            <div class="public-calendar-note">
                <span class="pill">Public view</span>
                <span class="muted">Tap an event to log in</span>
                <span class="calendar-month-display" id="publicCalendarMonth"><?= date('F Y') ?></span>
            </div>
            <a class="btn btn-sm login-trigger" href="javascript:void(0)" data-login-url="<?= htmlspecialchars($login_src) ?>">Log in</a>
        </div>
        <div id="publicCalendar"></div>
    </div>
    <script>
      window.EVENTIFY_BASE_URL = <?= json_encode(BASE_URL) ?>;
      window.PUBLIC_LOGIN_URL = <?= json_encode($login_src) ?>;
      window.PUBLIC_CALENDAR_EVENTS = <?= json_encode($publicCalendarEvents, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
      window.AUTH_MODAL = <?= json_encode($auth_modal) ?>;
      window.AUTH_ERROR = <?= json_encode($auth_error) ?>;
      window.AUTH_SUCCESS = <?= json_encode($auth_success) ?>;
      window.VERIFY_PURPOSE = <?= json_encode($verify_purpose) ?>;
      window.VERIFY_EMAIL = <?= json_encode($verify_email) ?>;
    </script>
</section>

<section id="hero" class="reveal-scope">
    <?php if ($checkin_token !== ''): ?>
    <p class="hero-checkin-notice reveal-item" style="--reveal-d: 0ms; background: rgba(5, 150, 105, 0.22); color: #ecfdf5; border: 1px solid rgba(253, 224, 71, 0.35); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.95rem;">
        <strong>Event check-in.</strong> Log in with your student account to confirm your attendance.
    </p>
    <?php endif; ?>
    <h1 class="reveal-item" style="--reveal-d: 0ms">Plan and track every school event in one place.</h1>
    <p class="hero-lead reveal-item" style="--reveal-d: 60ms">One calendar for admins, organizers, and students—stay aligned without the spreadsheet chaos.</p>
    <p class="reveal-item" style="--reveal-d: 110ms">
        EVENTIFY is a web & app-based school events monitoring system that helps
        administrators, organizers, and students create, announce, and follow every
        activity without missing a thing.
    </p>
    <div class="hero-buttons reveal-item" style="--reveal-d: 170ms">
        <span class="magnetic-wrap">
            <a class="btn btn-shimmer login-trigger" href="javascript:void(0)" id="heroGetStarted" data-login-url="<?= htmlspecialchars($login_src) ?>"><?= $checkin_token !== '' ? 'Log in to check in' : 'Get Started' ?></a>
        </span>
        <a class="btn btn-outline" onclick="goToSection('how-it-works')">See how it works</a>
    </div>
</section>

<!-- Auth Modals on landing page (login, register, verify OTP) -->
<div id="loginModal" class="modal auth-modal">
    <div class="modal-content auth-modal-content">
        <span class="close" onclick="closeLoginModal()" aria-label="Close">&times;</span>
        <div class="auth-form-box">
            <h3 class="auth-title">Login</h3>
            <p class="auth-subtitle">Welcome back. Sign in to continue.</p>
            <div class="auth-inline-message" id="loginModalMessage" style="display:none;"></div>
            <?php if ($auth_modal === 'login' && $auth_error !== ''): ?>
                <div class="auth-inline-message error" id="loginModalMessageServer"><?= htmlspecialchars($auth_error) ?></div>
            <?php elseif ($auth_modal === 'login' && $auth_success !== ''): ?>
                <div class="auth-inline-message success" id="loginModalMessageServer"><?= htmlspecialchars($auth_success) ?></div>
            <?php endif; ?>
            <form id="loginModalForm" action="<?= BASE_URL ?>/backend/auth/auth.php" method="POST" class="auth-form-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <?php if ($auth_redirect !== ''): ?>
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($auth_redirect, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <div class="auth-input-wrap">
                    <label class="auth-label" for="loginModalEmail">Email</label>
                    <input type="email" name="email" id="loginModalEmail" class="auth-input" placeholder="you@example.com" required autocomplete="username email">
                </div>
                <div class="auth-input-wrap auth-password-wrap">
                    <label class="auth-label" for="loginModalPassword">Password</label>
                    <div class="auth-password-input-row">
                        <input type="password" name="password" id="loginModalPassword" class="auth-input" placeholder="Enter password" required>
                        <button type="button" class="auth-eye-btn" id="toggleLoginModalPassword" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="login-modal-action-btn primary auth-submit-btn">Login</button>
            </form>
        </div>
        <div class="login-modal-action-row">
            <button type="button" class="login-modal-action-btn secondary" onclick="openRegisterModal()">Register</button>
            <button type="button" class="login-modal-action-btn ghost" onclick="openVerifyModal({ purpose: 'reactivate' })">Verify Reactivation OTP</button>
        </div>
    </div>
</div>

<div id="registerModal" class="modal auth-modal">
    <div class="modal-content auth-modal-content">
        <span class="close" onclick="closeRegisterModal()" aria-label="Close">&times;</span>
        <div class="auth-form-box">
            <h3 class="auth-title">Register</h3>
            <p class="auth-subtitle">Create your account to use EVENTIFY.</p>
            <div class="auth-inline-message" id="registerModalMessage" style="display:none;"></div>
            <?php if ($auth_modal === 'register' && $auth_error !== ''): ?>
                <div class="auth-inline-message error" id="registerModalMessageServer"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>
            <form id="registerModalForm" action="<?= BASE_URL ?>/backend/auth/auth.php" method="POST" class="auth-form-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
                <div class="auth-name-row">
                    <div class="auth-input-wrap">
                        <label class="auth-label" for="registerModalFirstName">First name</label>
                        <input type="text" name="first_name" id="registerModalFirstName" class="auth-input" placeholder="First name" required maxlength="50" autocomplete="given-name">
                    </div>
                    <div class="auth-input-wrap">
                        <label class="auth-label" for="registerModalLastName">Last name</label>
                        <input type="text" name="last_name" id="registerModalLastName" class="auth-input" placeholder="Last name" required maxlength="50" autocomplete="family-name">
                    </div>
                </div>
                <div class="auth-input-wrap">
                    <label class="auth-label" for="registerModalEmail">Email</label>
                    <input type="email" name="email" id="registerModalEmail" class="auth-input" placeholder="you@example.com" required autocomplete="email">
                    <p class="auth-field-hint">Use this email to sign in — it is your username.</p>
                </div>
                <div class="auth-input-wrap auth-password-wrap">
                    <label class="auth-label" for="registerModalPassword">Password</label>
                    <div class="auth-password-input-row">
                        <input type="password" name="password" id="registerModalPassword" class="auth-input" placeholder="Password" required>
                        <button type="button" class="auth-eye-btn" id="toggleRegisterModalPassword" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="auth-password-guide" id="registerPasswordGuide">
                        Password guide: at least 8 characters, with 1 uppercase letter and 1 special character.
                    </div>
                </div>
                <div class="auth-input-wrap auth-password-wrap">
                    <label class="auth-label" for="registerModalConfirmPassword">Confirm Password</label>
                    <div class="auth-password-input-row">
                        <input type="password" name="confirm_password" id="registerModalConfirmPassword" class="auth-input" placeholder="Confirm Password" required>
                        <button type="button" class="auth-eye-btn" id="toggleRegisterModalConfirmPassword" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="auth-password-match" id="registerPasswordMatchStatus" style="display:none;"></div>
                </div>
                <div class="auth-input-wrap">
                    <label class="auth-label" for="registerRoleSelectModal">Role</label>
                    <select name="role" id="registerRoleSelectModal" class="auth-input" required>
                        <option value="">Select Role</option>
                        <option value="student">Student</option>
                        <option value="organizer">Organizer</option>
                        <option value="multimedia">Multimedia</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="auth-input-wrap" id="registerDepartmentWrapModal" style="display:none;">
                    <label class="auth-label" for="registerDepartmentSelectModal">Department</label>
                    <select name="department" id="registerDepartmentSelectModal" class="auth-input">
                        <option value="">Select Department</option>
                        <option value="High school department">High School Department</option>
                        <option value="College of Communication, Information and Technology">College of Communication, Information and Technology</option>
                        <option value="College of Accountancy and Business">College of Accountancy and Business</option>
                        <option value="School of Law and Political Science">School of Law and Political Science</option>
                        <option value="College of Education">College of Education</option>
                        <option value="College of Nursing and Allied health sciences">College of Nursing and Allied health sciences</option>
                        <option value="College of Hospitality Management">College of Hospitality Management</option>
                    </select>
                </div>
                <div class="auth-input-wrap" id="registerCourseWrapModal" style="display:none;">
                    <label class="auth-label" for="registerCourseSelectModal">Course / Program</label>
                    <select name="student_course" id="registerCourseSelectModal" class="auth-input">
                        <?php if (!empty($studentCourseOptions)): ?>
                            <?php foreach ($studentCourseOptions as $cv => $clab): ?>
                                <?php $courseDept = function_exists('eventify_student_course_program_department') ? eventify_student_course_program_department((string)$cv) : ''; ?>
                                <option value="<?= htmlspecialchars((string)$cv) ?>"<?= $courseDept !== '' ? ' data-department="' . htmlspecialchars($courseDept) . '"' : '' ?>><?= htmlspecialchars((string)$clab) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">Select course / program</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="auth-input-wrap" id="registerYearLevelWrapModal" style="display:none;">
                    <label class="auth-label" for="registerYearLevelSelectModal">Year Level</label>
                    <select name="student_year_level" id="registerYearLevelSelectModal" class="auth-input">
                        <?php if (!empty($studentYearLevelOptions)): ?>
                            <?php foreach ($studentYearLevelOptions as $yv => $ylab): ?>
                                <option value="<?= htmlspecialchars((string)$yv) ?>"><?= htmlspecialchars((string)$ylab) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">— Select —</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="auth-input-wrap auth-consent-wrap">
                    <label class="auth-consent-check">
                        <input type="checkbox" name="accept_legal" value="1" required>
                        <span>
                            I have read and agree to the
                            <button type="button" class="legal-doc-link" data-legal-open="privacy" onclick="openLegalPrivacyModal(); return false;">Data Privacy Notice</button>
                            and
                            <button type="button" class="legal-doc-link" data-legal-open="terms" onclick="openLegalTermsModal(); return false;">Terms and Conditions</button>.
                        </span>
                    </label>
                </div>
                <button type="submit" class="login-modal-action-btn primary auth-submit-btn">Register</button>
                <button type="button" class="login-modal-action-btn secondary auth-back-login-btn" onclick="openLoginModal()">Back to Login</button>
            </form>
        </div>
    </div>
</div>

<!-- Legal documents (stacked above auth modals; no redirect from registration) -->
<div id="legalPrivacyModal" class="modal auth-modal legal-doc-modal" style="display:none;" aria-hidden="true">
    <div class="modal-content auth-modal-content legal-doc-panel">
        <span class="close" onclick="closeLegalPrivacyModal()" aria-label="Close">&times;</span>
        <div class="legal-doc-panel-head">
            <h3 class="auth-title">EVENTIFY Data Privacy Notice</h3>
            <p class="auth-subtitle legal-doc-sub">Republic Act No. 10173 (Data Privacy Act of 2012)</p>
        </div>
        <div class="legal-doc-scroll legal-doc-body">
            <?php include __DIR__ . '/views/partials/legal_privacy_inner.php'; ?>
        </div>
        <div class="legal-doc-actions">
            <button type="button" class="login-modal-action-btn primary" onclick="closeLegalPrivacyModal()">Close</button>
        </div>
    </div>
</div>
<div id="legalTermsModal" class="modal auth-modal legal-doc-modal" style="display:none;" aria-hidden="true">
    <div class="modal-content auth-modal-content legal-doc-panel">
        <span class="close" onclick="closeLegalTermsModal()" aria-label="Close">&times;</span>
        <div class="legal-doc-panel-head">
            <h3 class="auth-title">Terms and Conditions</h3>
            <p class="auth-subtitle legal-doc-sub">EVENTIFY - School Events Monitoring System</p>
        </div>
        <div class="legal-doc-scroll legal-doc-body">
            <?php $legal_terms_context = 'modal'; include __DIR__ . '/views/partials/legal_terms_inner.php'; ?>
        </div>
        <div class="legal-doc-actions">
            <button type="button" class="login-modal-action-btn primary" onclick="closeLegalTermsModal()">Close</button>
        </div>
    </div>
</div>

<div id="verifyModal" class="modal auth-modal">
    <div class="modal-content auth-modal-content">
        <span class="close" onclick="closeVerifyModal()" aria-label="Close">&times;</span>
        <div class="auth-form-box">
            <h3 class="auth-title" id="verifyModalTitle"><?= $verify_purpose === 'reactivate' ? 'Verify reactivation OTP' : 'Verify your email' ?></h3>
            <?php
            $verifyTopAlertType = '';
            $verifyTopAlertText = '';
            if ($auth_modal === 'verify' && $auth_success !== '') {
                $verifyTopAlertType = 'success';
                $verifyTopAlertText = $auth_success;
            } elseif ($auth_modal === 'verify' && $auth_error !== '') {
                $verifyTopAlertType = 'error';
                $verifyTopAlertText = $auth_error;
            }
            ?>
            <div id="verifyModalTopAlert" class="auth-inline-message auth-verify-top-alert<?= $verifyTopAlertType !== '' ? ' ' . $verifyTopAlertType : '' ?>" role="alert"<?= $verifyTopAlertText === '' ? ' style="display:none;"' : '' ?>><?= $verifyTopAlertText !== '' ? htmlspecialchars($verifyTopAlertText) : '' ?></div>
            <p class="auth-subtitle" id="verifyModalSubtitle"><?= $verify_purpose === 'reactivate'
                ? 'Enter the code sent to your registered email.'
                : 'Enter the 6-digit code we sent to your email. After verification, super admin will approve your account.' ?></p>
            <p class="auth-field-hint auth-verify-hint">Did not get the email? Check <strong>Spam/Junk</strong>. School inboxes may take 1–2 minutes.</p>
            <form id="verifyModalForm" action="<?= BASE_URL ?>/backend/auth/verify_account_otp.php" method="POST" class="auth-form-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="purpose" id="verifyModalPurpose" value="<?= htmlspecialchars($verify_purpose) ?>">
                <div class="auth-input-wrap">
                    <label class="auth-label" for="verifyModalEmail">Email</label>
                    <input type="email" name="email" id="verifyModalEmail" class="auth-input" placeholder="you@example.com" required value="<?= htmlspecialchars($verify_email) ?>" autocomplete="email"<?= ($verify_purpose === 'register' && $verify_email !== '') ? ' readonly' : '' ?>>
                </div>
                <div class="auth-input-wrap">
                    <label class="auth-label" for="verifyOtpCode">6-digit OTP</label>
                    <input type="text" name="otp_code" id="verifyOtpCode" class="auth-input" placeholder="Enter 6-digit code" required maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code">
                </div>
                <button type="submit" class="login-modal-action-btn primary auth-submit-btn">Verify</button>
            </form>
            <form id="verifyResendOtpForm" action="<?= BASE_URL ?>/backend/auth/resend_account_otp.php" method="POST" class="auth-verify-resend-form mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="purpose" id="verifyResendPurpose" value="<?= htmlspecialchars($verify_purpose) ?>">
                <input type="hidden" name="email" id="verifyResendEmail" value="<?= htmlspecialchars($verify_email) ?>">
                <button type="submit" class="login-modal-action-btn ghost auth-verify-resend-btn">Resend OTP</button>
            </form>
        </div>
        <div class="login-modal-action-row">
            <button type="button" class="login-modal-action-btn secondary" onclick="openLoginModal()">Back to login</button>
        </div>
    </div>
</div>

<section id="how-it-works" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">How EVENTIFY works</h1>
    <div class="grid reveal-item" style="--reveal-d: 80ms">
        <div class="card">
            <h3 style="margin-bottom: 8px; font-size: 1.2rem;">1. Plan</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Organizers create events, choose which departments can see them, and set dates
                and locations in a few clicks.
            </p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 8px; font-size: 1.2rem;">2. Announce & track</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Students see upcoming events on a Google-style calendar filtered to their
                department, while admins and organizers review and update events in one
                centralized place.
            </p>
        </div>
    </div>
</section>

<section id="features" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">Powerful features for your campus</h1>
    <div class="grid reveal-item" style="--reveal-d: 80ms">
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">📅 Smart event creation</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Create events in a few clicks, set dates and locations, and target the right
                departments or the whole school.
            </p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">🗓 Google-style calendars</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Organizer and student dashboards use a clean month/week/day calendar view so
                everyone can see what is happening at a glance.
            </p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">🎯 Department-based visibility</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Events can be exclusive to BSIT, BSHM, CONAHS, Senior High, or visible to all —
                students only see what is relevant to them.
            </p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">👤 Personalized dashboards</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Students can update their profile and view events in one place, while organizers
                manage and edit their events easily.
            </p>
        </div>
    </div>
</section>

<section id="roles" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">Built for your whole school</h1>
    <div class="grid reveal-item" style="--reveal-d: 80ms">
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">👨‍💼 Administrators</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Get a clear view of all upcoming events, departments involved, and organizers
                responsible for each activity.
            </p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">🎯 Organizers</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Create, edit, and manage events from a modern Google-like calendar, and see
                exactly which students you are targeting.
            </p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">📷 Multimedia</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                Capture and manage photos and visuals for school events so announcements and
                galleries stay complete and easy to find.
            </p>
        </div>
        <div class="card">
            <h3 style="margin-bottom: 10px; font-size: 1.3rem;">🎓 Students</h3>
            <p style="font-size: 0.95rem; color: var(--school-muted);">
                View upcoming events for your department, avoid schedule conflicts, and keep
                track of your participation throughout the school year.
            </p>
        </div>
    </div>
</section>

<section id="faq" class="reveal-scope">
    <h1 class="reveal-item" style="--reveal-d: 0ms">Frequently asked questions</h1>
    <div class="faq-accordion reveal-item" style="--reveal-d: 80ms">
        <div class="faq-item is-open">
            <button type="button" class="faq-trigger" id="faqTrigger1" aria-expanded="true" aria-controls="faqPanel1">
                <span>Who can create events?</span>
                <i class="fas fa-chevron-down faq-trigger-icon" aria-hidden="true"></i>
            </button>
            <div class="faq-panel" id="faqPanel1" role="region" aria-labelledby="faqTrigger1">
                <p>Only users with an organizer or admin account can create and manage events from their dashboard.</p>
            </div>
        </div>
        <div class="faq-item">
            <button type="button" class="faq-trigger" id="faqTrigger2" aria-expanded="false" aria-controls="faqPanel2">
                <span>What do students see?</span>
                <i class="fas fa-chevron-down faq-trigger-icon" aria-hidden="true"></i>
            </button>
            <div class="faq-panel" id="faqPanel2" role="region" aria-labelledby="faqTrigger2">
                <p>Students see events that are tagged for their department or for all departments, displayed in a clean calendar view.</p>
            </div>
        </div>
    </div>
</section>

<script type="module" src="https://unpkg.com/@splinetool/viewer@1.12.39/build/spline-viewer.js"></script>
<div class="spline-wrap">
    <spline-viewer url="https://prod.spline.design/QKWcuhuYDwcet-bm/scene.splinecode"></spline-viewer>
</div>

<!-- Footer -->
<footer>
    <div class="footer-inner">
        <div class="footer-left">
            <span class="footer-brand">EVENTIFY</span>
            <span class="footer-text">Web & App-Based School Events Monitoring System</span>
        </div>
        <div class="footer-links">
            <a href="javascript:void(0)" onclick="goToSection('features')">Features</a>
            <a href="javascript:void(0)" onclick="goToSection('roles')">Roles</a>
            <a class="legal-doc-link legal-footer-link" href="<?= BASE_URL ?>/privacy-notice.php" target="_blank" rel="noopener noreferrer">Privacy Notice</a>
            <a class="legal-doc-link legal-footer-link" href="<?= BASE_URL ?>/terms-and-conditions.php" target="_blank" rel="noopener noreferrer">Terms &amp; Conditions</a>
            <a href="mailto:youremail@example.com">Contact</a>
        </div>
    </div>
</footer>


<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_calendar_colors.js"></script>
<script src="<?= BASE_URL ?>/assets/js/index.js?v=5"></script>
</body>
</html>
