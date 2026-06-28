<?php
if (!isset($user_name)) $user_name = 'Multimedia';
if (!isset($events)) $events = [];
if (!isset($msg)) $msg = '';
$user = $user ?? ['name' => $user_name, 'user_id' => 'N/A', 'department' => null, 'profile_picture' => null];
$department = $user['department'] ?? null;
$upcomingEvents = $upcomingEvents ?? [];
$photoStatusEnabled = (bool) ($photoStatusEnabled ?? false);
$is_multimedia_moderator = (bool) ($is_multimedia_moderator ?? false);
$pending_photo_count = (int) ($pending_photo_count ?? 0);
$pending_photos_queue = is_array($pending_photos_queue ?? null) ? $pending_photos_queue : [];

$totalEvents = is_array($events) ? count($events) : 0;
$totalPhotos = 0;
if (is_array($events)) {
    foreach ($events as $e) {
        $totalPhotos += (int)($e['photo_count'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#121212">
    <title>Multimedia Dashboard - EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard_multimedia.css?v=8">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/eventify_dashboard_brand.css?v=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/notifications.css?v=4">
</head>
<body class="multimedia-dashboard" data-eventify-keyboard-scroll data-eventify-sidebar="mmSidebar" data-eventify-main=".multimedia-dashboard .main-content">
<input type="hidden" id="csrf_token_value" value="<?= htmlspecialchars(csrf_token()) ?>">

<nav class="top-navbar">
    <div class="navbar-left">
        <button type="button" class="nav-btn sidebar-toggle-mobile" id="mmSidebarToggle" aria-label="Toggle sidebar" title="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="brand-logo">
            <i class="fas fa-camera"></i>
            <span>EVENTIFY</span>
        </div>
    </div>
    <div class="navbar-right">
        <?php
            $mm_notifications = $multimedia_notifications ?? [];
            $mm_unread_count = (int) ($multimedia_unread_count ?? 0);
            $mm_notif_dropdown = $multimedia_notif_dropdown ?? [];
        ?>
        <div class="dropdown me-2">
            <button class="nav-btn eventify-notif-btn position-relative dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-eventify-notif-badge aria-expanded="false" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($mm_unread_count > 0): ?>
                    <span class="eventify-nav-badge badge rounded-pill bg-danger"><?= $mm_unread_count > 99 ? '99+' : $mm_unread_count ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end eventify-notif-dropdown">
                <li class="eventify-notif-dropdown__header">
                    <i class="fas fa-bell me-2"></i>Notifications
                    <?php if ($mm_unread_count > 0): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;"><?= $mm_unread_count ?> new</span>
                    <?php endif; ?>
                </li>
                <li class="eventify-notif-dropdown__scroll">
                    <div class="eventify-notif-scroll eventify-notif-scroll--dropdown">
                        <?php
                            $notifications = $mm_notif_dropdown;
                            $empty_title = 'All caught up';
                            $empty_text = 'No new notifications right now.';
                            $notif_interactive = true;
                            include __DIR__ . '/partials/notification_cards.php';
                        ?>
                    </div>
                </li>
                <?php if ($mm_unread_count > 0 || !empty($mm_notifications)): ?>
                    <li class="eventify-notif-dropdown__footer">
                        <?php
                            $notif_show_mark_all = $mm_unread_count > 0;
                            $notif_show_clear = !empty($mm_notifications);
                            $notif_clear_modal_id = 'multimediaClearNotifsModal';
                            $notif_context = 'dropdown';
                            include __DIR__ . '/partials/notification_footer_actions.php';
                        ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="dropdown">
            <button class="profile-avatar profile-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($user_name) ?>">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user_name) ?>" class="profile-avatar-img">
                <?php else: ?>
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end profile-menu">
                <li class="px-3 py-2">
                    <div class="small text-muted">Signed in as</div>
                    <div class="fw-semibold"><?= htmlspecialchars($user_name) ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="#" onclick="openMmProfileModal(); return false;">
                        <i class="fas fa-user me-2"></i> Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#upcomingEventsModal">
                        <i class="fas fa-calendar-check me-2"></i> Upcoming events
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Layout -->
<div class="dashboard-layout">
    <div class="sidebar-backdrop" id="mmSidebarBackdrop" aria-hidden="true"></div>

    <!-- Left Sidebar -->
    <aside class="sidebar eventify-kb-scroll-zone" id="mmSidebar" tabindex="0" aria-label="Multimedia sidebar — use arrow keys to scroll">
        <button type="button" class="sidebar-close-mobile" id="mmSidebarClose" aria-label="Close menu"><i class="fas fa-times"></i></button>

        <!-- Multimedia Profile Card -->
        <div class="mm-user-card">
            <div class="mm-user-avatar">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= htmlspecialchars($user_name) ?>">
                <?php else: ?>
                    <span><?= strtoupper(substr($user_name, 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <h3 class="mm-user-name"><?= htmlspecialchars($user_name) ?></h3>
            <p class="mm-user-id">ID: <?= htmlspecialchars($user['user_id'] ?? 'N/A') ?></p>
            <?php if ($department): ?>
                <span class="mm-user-dept"><?= htmlspecialchars($department) ?> Multimedia</span>
            <?php else: ?>
                <span class="mm-user-dept muted">Department not set</span>
            <?php endif; ?>
        </div>

        <!-- Your Role -->
        <div class="sidebar-role">
            <h3 class="section-title">YOUR ROLE</h3>
            <p class="role-desc">
                <?php if ($is_multimedia_moderator): ?>
                    You are the photo moderator for your multimedia team. Upload photos like other members, and approve or reject pending uploads before students can see them.
                <?php else: ?>
                    You are part of the multimedia team.
                    Upload photos for events and activities; your moderator will review them before they go live for students.
                <?php endif; ?>
            </p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 class="section-title">QUICK ACTIONS</h3>
            <button type="button" class="action-btn" onclick="openMmProfileModal()">
                <i class="fas fa-user-edit"></i>
                <span>Edit profile</span>
            </button>
            <a href="#" class="action-btn" data-bs-toggle="modal" data-bs-target="#upcomingEventsModal">
                <i class="fas fa-calendar-check"></i>
                <span>Upcoming events</span>
            </a>
            <?php
                $activities_hub_btn_class = '';
                include __DIR__ . '/partials/activities_hub_quick_action.php';
            ?>
            <?php if ($is_multimedia_moderator): ?>
            <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#photoApprovalsModal">
                <i class="fas fa-user-shield"></i>
                <span>Photo approvals</span>
                <?php if ($pending_photo_count > 0): ?>
                    <span class="badge bg-primary ms-1"><?= $pending_photo_count > 99 ? '99+' : $pending_photo_count ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#photoActivityLogModal">
                <i class="fas fa-clipboard-list"></i>
                <span>Photo activity log</span>
            </button>
            <?php endif; ?>
            <a href="#" class="action-btn logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content eventify-kb-scroll-zone" tabindex="0" aria-label="Multimedia events — use arrow keys to scroll">
        <div class="content-header">
            <div class="content-header-top">
                <div>
                    <div class="page-kicker">Multimedia</div>
                    <h1>Event photos</h1>
                    <p class="text-muted">
                        <?php if ($is_multimedia_moderator): ?>
                            Upload photos and review pending submissions from your team before they appear for students.
                        <?php else: ?>
                            Choose an event and upload photos. They stay pending until your moderator approves them.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="header-actions">
                    <?php if ($is_multimedia_moderator && $pending_photo_count > 0): ?>
                    <button type="button" class="btn btn-outline-light btn-sm btn-upcoming" data-bs-toggle="modal" data-bs-target="#photoApprovalsModal">
                        <i class="fas fa-user-shield me-1"></i> <?= $pending_photo_count ?> pending
                    </button>
                    <?php endif; ?>
                    <a class="btn btn-outline-light btn-sm btn-upcoming" href="#" data-bs-toggle="modal" data-bs-target="#upcomingEventsModal">
                        <i class="fas fa-calendar-check me-1"></i> Upcoming events
                    </a>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">Total events</div>
                        <div class="stat-value"><?= (int)$totalEvents ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-images"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">Total photos</div>
                        <div class="stat-value"><?= (int)$totalPhotos ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">Department</div>
                        <div class="stat-value stat-value-sm"><?= $department ? htmlspecialchars($department) : 'Not set' ?></div>
                    </div>
                </div>
            </div>

            <?php if (!$photoStatusEnabled): ?>
                <div class="alert alert-warning mm-migration-hint d-flex align-items-start gap-2 mb-0 mt-3" role="status">
                    <i class="fas fa-database mt-1 flex-shrink-0"></i>
                    <div class="small">
                        <strong>Photo moderation is unavailable until the database is updated.</strong>
                        Your <code>event_photos</code> table is missing the <code>status</code> column.
                        Run <code>migrations/event_photos_publish_columns.sql</code> in phpMyAdmin (or MySQL), then refresh this page.
                    </div>
                </div>
            <?php elseif (!$is_multimedia_moderator): ?>
                <div class="alert alert-info mm-migration-hint d-flex align-items-start gap-2 mb-0 mt-3" role="status">
                    <i class="fas fa-hourglass-half mt-1 flex-shrink-0"></i>
                    <div class="small">
                        Uploads are saved as <strong>pending</strong> until your multimedia moderator approves them. You cannot publish photos yourself.
                    </div>
                </div>
            <?php endif; ?>

            <div class="toolbar-row">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input id="eventSearchInput" type="search" class="search-input" placeholder="Search events by title or location..." autocomplete="off">
                </div>
                <div class="toolbar-hint">
                    <span class="hint-chip"><i class="fas fa-mouse-pointer"></i> Click “View photos” to open gallery</span>
                    <span class="hint-chip"><i class="fas fa-cloud-upload-alt"></i> Upload multiple images at once</span>
                </div>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="events-list" id="eventsList">
            <?php if (empty($events)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No events yet. Events will appear here when organizers create them.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                    <?php
                        $eid = (int)($ev['id'] ?? 0);
                        $title = (string)($ev['title'] ?? '');
                        $location = (string)($ev['location'] ?? '');
                        $myCount = (int)($ev['my_photo_count'] ?? 0);
                        $myDraftCount = (int)($ev['my_draft_count'] ?? 0);
                        $pendingDraftCount = (int)($ev['pending_draft_count'] ?? 0);
                        $canModeratePublish = $photoStatusEnabled && $is_multimedia_moderator && $pendingDraftCount > 0;
                        $showPendingNotice = $photoStatusEnabled && !$is_multimedia_moderator && $myDraftCount > 0;
                        if (!$photoStatusEnabled) {
                            $publishHelpTitle = 'Photo moderation needs the status column. Run migrations/event_photos_publish_columns.sql on your database, then refresh this page.';
                        } elseif ($is_multimedia_moderator && $pendingDraftCount <= 0) {
                            $publishHelpTitle = 'No pending photos for this event.';
                        } elseif ($is_multimedia_moderator) {
                            $publishHelpTitle = 'Approve all pending photos for this event';
                        } elseif ($myDraftCount <= 0) {
                            $publishHelpTitle = 'No pending photos from you for this event.';
                        } else {
                            $publishHelpTitle = 'Your photos are waiting for moderator approval';
                        }
                        $previewPath = null;
                        if (!empty($photosByEvent) && isset($photosByEvent[$eid]) && !empty($photosByEvent[$eid][0]['file_path'])) {
                            $previewPath = $photosByEvent[$eid][0]['file_path'];
                        }
                    ?>
                    <div class="event-card"
                         data-title="<?= htmlspecialchars($title) ?>"
                         data-location="<?= htmlspecialchars($location) ?>">
                        <div class="event-media">
                            <?php if ($previewPath): ?>
                                <img
                                    src="<?= BASE_URL ?>/<?= htmlspecialchars($previewPath) ?>"
                                    alt="Preview photo for <?= htmlspecialchars($title) ?>"
                                    class="event-preview"
                                    loading="lazy"
                                    decoding="async"
                                >
                            <?php else: ?>
                                <div class="event-preview-placeholder" aria-hidden="true">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="event-info">
                            <div class="event-title-row">
                                <h3 class="event-title"><?= htmlspecialchars($title) ?></h3>
                                <?php if (!empty($ev['department'])): ?>
                                    <span class="dept-pill"><?= htmlspecialchars($ev['department']) ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="event-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['date'])) ?></span>
                                <span class="dot">·</span>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($location) ?></span>
                            </p>

                            <div class="event-badges">
                                <span class="photo-badge">
                                    <i class="fas fa-images"></i> <?= (int)($ev['photo_count'] ?? 0) ?> photo(s)
                                </span>
                                <?php if ($is_multimedia_moderator && $pendingDraftCount > 0): ?>
                                    <span class="photo-badge" title="Pending photos awaiting approval">
                                        <i class="fas fa-user-shield"></i> <?= $pendingDraftCount ?> pending
                                    </span>
                                <?php elseif ($myDraftCount > 0): ?>
                                    <span class="photo-badge" title="Your photos waiting for moderator approval">
                                        <i class="fas fa-hourglass-half"></i> <?= $myDraftCount ?> pending
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="event-actions">
                            <button type="button" class="btn btn-upload" onclick="openUploadModal(this); return false;"
                                    data-event-id="<?= $eid ?>"
                                    data-event-title="<?= htmlspecialchars($title) ?>">
                                <i class="fas fa-cloud-upload-alt"></i> Upload
                            </button>
                            <?php if ($canModeratePublish): ?>
                                <button type="button"
                                        class="btn btn-outline-success mm-publish-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#publishPhotosModal"
                                        data-event-id="<?= $eid ?>"
                                        data-event-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                                        data-draft-count="<?= (int)$pendingDraftCount ?>"
                                        title="<?= htmlspecialchars($publishHelpTitle) ?>">
                                    <i class="fas fa-check-double"></i> Approve all
                                </button>
                            <?php elseif ($showPendingNotice): ?>
                                <span class="btn btn-outline-secondary mm-pending-label disabled" title="<?= htmlspecialchars($publishHelpTitle) ?>">
                                    <i class="fas fa-hourglass-half"></i> Pending
                                </span>
                            <?php endif; ?>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-gallery <?= empty($ev['photo_count']) ? 'disabled' : '' ?>"
                                    <?= empty($ev['photo_count']) ? 'disabled aria-disabled="true"' : '' ?>
                                    data-bs-toggle="modal"
                                    data-bs-target="#galleryModal"
                                    data-event-id="<?= $eid ?>"
                                    data-event-title="<?= htmlspecialchars($title) ?>">
                                <i class="fas fa-folder-open"></i> View
                            </button>
                            <a href="<?= BASE_URL ?>/event_photos_qr.php?id=<?= $eid ?>"
                               class="btn btn-outline-primary"
                               target="_blank"
                               title="Generate QR for student photo gallery">
                                <i class="fas fa-qrcode"></i> QR
                            </a>
                            <button type="button"
                                    class="btn btn-outline-danger btn-delete-photos <?= $myCount <= 0 ? 'disabled' : '' ?>"
                                    <?= $myCount <= 0 ? 'disabled aria-disabled="true"' : '' ?>
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteEventPhotosModal"
                                    data-event-id="<?= $eid ?>"
                                    data-event-title="<?= htmlspecialchars($title) ?>"
                                    data-my-count="<?= (int)$myCount ?>">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Upcoming Events Modal (for multimedia) -->
<div class="modal fade" id="upcomingEventsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i>Upcoming events</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($upcomingEvents)): ?>
                    <div class="mm-upcoming-empty">
                        <i class="fas fa-calendar-times"></i>
                        <div>
                            <div class="fw-semibold">No upcoming events</div>
                            <div class="text-muted small">Once organizers create active events, they will appear here.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mm-upcoming-list">
                        <?php foreach ($upcomingEvents as $ev): ?>
                            <div class="mm-upcoming-item">
                                <div class="mm-upcoming-date">
                                    <div class="m"><?= date('M', strtotime($ev['date'])) ?></div>
                                    <div class="d"><?= date('d', strtotime($ev['date'])) ?></div>
                                </div>
                                <div class="mm-upcoming-info">
                                    <div class="mm-upcoming-title"><?= htmlspecialchars($ev['title'] ?? 'Untitled') ?></div>
                                    <div class="mm-upcoming-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ev['location'] ?? 'TBA') ?></span>
                                        <span class="dot">·</span>
                                        <span class="pill"><?= htmlspecialchars(eventify_format_department_label((string)($ev['department'] ?? 'ALL'))) ?></span>
                                    </div>
                                    <?php if (!empty($ev['description'])): ?>
                                        <div class="mm-upcoming-desc">
                                            <?= htmlspecialchars(mb_strimwidth($ev['description'], 0, 140, '...')) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer justify-content-between">
                <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/upcoming_events.php">
                    <i class="fas fa-up-right-from-square me-1"></i> Open full page
                </a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<!-- Profile Modal (Multimedia) -->
<div id="mmProfileModal" class="profile-modal">
    <div class="profile-modal-content">
        <span class="profile-close" onclick="closeMmProfileModal()">&times;</span>
        <h2>My Information</h2>
        <form id="mmProfileForm" action="<?= BASE_URL ?>/backend/auth/update_multimedia_profile.php" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); confirmMmProfileChanges(this);">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="mmProfilePicture">Profile Picture</label>
                <div class="profile-picture-preview-container">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Current profile picture" id="mmProfilePicturePreview" class="profile-picture-preview profile-picture-clickable" onclick="openMmProfilePicFullscreen(this.src)" title="Click to view full screen">
                    <?php else: ?>
                        <div class="profile-picture-placeholder" id="mmProfilePicturePreview">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <input
                    type="file"
                    id="mmProfilePicture"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="form-control-file"
                    onchange="previewMmProfilePicture(this)"
                >
                <small class="text-muted">JPG, PNG, GIF, or WEBP (max 5MB)</small>
            </div>

            <div class="form-group">
                <label for="mmFullName">Full Name</label>
                <input
                    type="text"
                    id="mmFullName"
                    name="name"
                    value="<?= htmlspecialchars($user['name'] ?? $user_name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Multimedia Club</label>
                <select name="department" id="mmDepartment">
                    <option value="" <?= empty($department) ? 'selected' : '' ?>>Select Department</option>
                    <option value="High school department" <?= ($department === 'High school department') ? 'selected' : '' ?>>High School Department</option>
                    <option value="College of Communication, Information and Technology" <?= ($department === 'College of Communication, Information and Technology') ? 'selected' : '' ?>>College of Communication, Information and Technology</option>
                    <option value="College of Accountancy and Business" <?= ($department === 'College of Accountancy and Business') ? 'selected' : '' ?>>College of Accountancy and Business</option>
                    <option value="School of Law and Political Science" <?= ($department === 'School of Law and Political Science') ? 'selected' : '' ?>>School of Law and Political Science</option>
                    <option value="College of Education" <?= ($department === 'College of Education') ? 'selected' : '' ?>>College of Education</option>
                    <option value="College of Nursing and Allied health sciences" <?= ($department === 'College of Nursing and Allied health sciences') ? 'selected' : '' ?>>College of Nursing and Allied health sciences</option>
                    <option value="College of Hospitality Management" <?= ($department === 'College of Hospitality Management') ? 'selected' : '' ?>>College of Hospitality Management</option>
                </select>
                <small class="text-muted d-block mt-1">Choose your department so your multimedia club is set correctly.</small>
            </div>

            <button type="submit" class="btn btn-primary w-100">Save Info</button>
        </form>
    </div>
</div>

<!-- Confirm Multimedia Profile Save Modal -->
<div class="modal fade" id="confirmMmProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Save profile changes?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMmProfileMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmMmProfileBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Profile picture fullscreen viewer (multimedia) -->
<div class="profile-pic-fullscreen" id="mmProfilePicFullscreen" style="display:none;">
    <div class="profile-pic-fullscreen-overlay" onclick="closeMmProfilePicFullscreen()"></div>
    <button class="profile-pic-fullscreen-close" onclick="closeMmProfilePicFullscreen()" aria-label="Close"><i class="fas fa-times"></i></button>
    <div class="profile-pic-fullscreen-content">
        <img id="mmProfilePicFullscreenImg" src="" alt="Profile picture">
    </div>
</div>

<!-- Upload modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload photos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= BASE_URL ?>/backend/auth/upload_event_photo.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="uploadEventId" value="">
                <div class="modal-body">
                    <p class="mb-3 text-muted" id="uploadEventTitle"></p>
                    <label class="form-label">Select images (JPG, PNG, GIF — max 5MB each)</label>
                    <input type="file" name="photos[]" id="photosInput" class="form-control" accept="image/jpeg,image/png,image/gif" multiple required>
                    <small class="text-muted">You can select multiple files.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Logout modal -->
<?php include __DIR__ . '/partials/activities_hub_pick_modal.php'; ?>

<?php
    $notif_clear_modal_id = 'multimediaClearNotifsModal';
    include __DIR__ . '/partials/notification_clear_confirm_modal.php';
    include __DIR__ . '/partials/notification_detail_modal.php';
?>

<div class="modal fade" id="logoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <p class="mb-0">Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="btn btn-danger">Log out</a>
            </div>
        </div>
    </div>
</div>

<!-- Gallery modal -->
<div class="modal fade" id="galleryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="galleryTitle">Event photos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="galleryGrid" class="gallery-grid"><!-- Thumbnails injected by JS --></div>
                <p id="galleryEmpty" class="text-muted small" style="display:none;">No photos uploaded yet for this event.</p>
            </div>
        </div>
    </div>
</div>

<!-- Delete Photo Confirmation Modal -->
<div class="modal fade" id="deletePhotoModal" tabindex="-1" aria-labelledby="deletePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deletePhotoModalLabel"><i class="fas fa-trash-alt me-2"></i>Delete Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this photo? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="deletePhotoConfirmBtn"><i class="fas fa-trash-alt me-1"></i> Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Publish / approve pending photos confirmation -->
<div class="modal fade" id="publishPhotosModal" tabindex="-1" aria-labelledby="publishPhotosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="publishPhotosModalLabel"><i class="fas fa-check-double me-2"></i>Approve photos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/backend/auth/publish_event_photos.php">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="publishPhotosEventId" value="">
                <div class="modal-body">
                    <p class="mb-0" id="publishPhotosMessage"></p>
                    <small class="text-muted d-block mt-2">
                        All pending photos for this event will become visible in the student photo gallery and Activities hub.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i> Yes, approve all
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Photo approvals queue (moderator) -->
<?php if ($is_multimedia_moderator): ?>
<?php include __DIR__ . '/partials/photo_approvals_modal.php'; ?>
<?php include __DIR__ . '/partials/multimedia_photo_activity_modal.php'; ?>
<?php endif; ?>

<!-- Delete Event Photos (My uploads) Confirmation Modal -->
<div class="modal fade" id="deleteEventPhotosModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Delete your photos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/backend/auth/delete_my_event_photos.php">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" id="deleteEventPhotosEventId" value="">
                <div class="modal-body">
                    <p class="mb-0" id="deleteEventPhotosMessage"></p>
                    <small class="text-muted d-block mt-2">
                        This will delete only the photos you uploaded for this event.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Photo Viewer Lightbox (Facebook-style) -->
<div class="photo-viewer" id="photoViewer" style="display:none;">
    <div class="photo-viewer-overlay" onclick="closePhotoViewer()"></div>
    <button class="photo-viewer-close" onclick="closePhotoViewer()" aria-label="Close">
        <i class="fas fa-times"></i>
    </button>
    <button class="photo-viewer-nav photo-viewer-prev" onclick="navigatePhoto(-1)" aria-label="Previous">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="photo-viewer-nav photo-viewer-next" onclick="navigatePhoto(1)" aria-label="Next">
        <i class="fas fa-chevron-right"></i>
    </button>
    <div class="photo-viewer-content">
        <img id="viewerImage" src="" alt="Event photo" class="photo-viewer-img">
        <div class="photo-viewer-info">
            <span id="viewerPhotoCount" class="photo-count"></span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>window.csrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/eventify_notifications.js?v=6"></script>
<script src="<?= BASE_URL ?>/assets/js/eventify_dashboard_keyboard_scroll.js"></script>
<script src="<?= BASE_URL ?>/assets/js/logout_confirm.js"></script>
<script src="<?= BASE_URL ?>/assets/js/photo_moderation_confirm.js?v=1"></script>
<script>
// Upload modal helper:
// - Uses Bootstrap modal if available
// - Falls back to a simple modal display if Bootstrap JS isn't loaded (e.g., offline)
function showModalFallback(modalEl) {
    if (!modalEl) return;
    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    modalEl.setAttribute('role', 'dialog');
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');

    var backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.setAttribute('data-fallback', '1');
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', function() { hideModalFallback(modalEl); });

    var dismissers = modalEl.querySelectorAll('[data-bs-dismiss="modal"]');
    for (var i = 0; i < dismissers.length; i++) {
        dismissers[i].addEventListener('click', function() { hideModalFallback(modalEl); }, { once: true });
    }
}

function hideModalFallback(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    document.body.classList.remove('modal-open');
    var backdrops = document.querySelectorAll('.modal-backdrop[data-fallback="1"]');
    for (var i = 0; i < backdrops.length; i++) backdrops[i].remove();
}

function openUploadModal(btn) {
    var modalEl = document.getElementById('uploadModal');
    if (!modalEl || !btn) return;

    var eid = btn.dataset.eventId || '';
    var title = btn.dataset.eventTitle || '';
    var idEl = document.getElementById('uploadEventId');
    var titleEl = document.getElementById('uploadEventTitle');
    var photosEl = document.getElementById('photosInput');
    if (idEl) idEl.value = eid;
    if (titleEl) titleEl.textContent = 'Event: ' + title;
    if (photosEl) photosEl.value = '';

    if (window.bootstrap && bootstrap.Modal) {
        new bootstrap.Modal(modalEl).show();
    } else {
        showModalFallback(modalEl);
    }
}

// Profile modal (multimedia)
function openMmProfileModal() {
    var el = document.getElementById('mmProfileModal');
    if (el) el.classList.add('show');
}
function closeMmProfileModal() {
    var el = document.getElementById('mmProfileModal');
    if (el) el.classList.remove('show');
}
function previewMmProfilePicture(input) {
    var preview = document.getElementById('mmProfilePicturePreview');
    if (!preview) return;
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                var img = document.createElement('img');
                img.id = 'mmProfilePicturePreview';
                img.className = 'profile-picture-preview profile-picture-clickable';
                img.alt = 'Preview';
                img.src = e.target.result;
                img.onclick = function() { openMmProfilePicFullscreen(img.src); };
                preview.parentNode.replaceChild(img, preview);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
var pendingMmProfileForm = null;
function confirmMmProfileChanges(form) {
    var nameEl = form.querySelector('input[name="name"]');
    var name = nameEl ? nameEl.value : '';
    var fileInput = form.querySelector('input[name="profile_picture"]');
    var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
    var msg = 'Update your display name' + (name ? ' to "' + name + '"' : '') + '.';
    if (hasFile) msg += ' A new profile picture will be uploaded.';
    var messageEl = document.getElementById('confirmMmProfileMessage');
    if (messageEl) messageEl.textContent = msg;
    pendingMmProfileForm = form;
    var modalEl = document.getElementById('confirmMmProfileModal');
    if (!modalEl) { form.submit(); return; }
    var modal = new bootstrap.Modal(modalEl);
    modalEl.addEventListener('shown.bs.modal', function raiseConfirmZIndex() {
        modalEl.removeEventListener('shown.bs.modal', raiseConfirmZIndex);
        modalEl.style.zIndex = '1200';
        var backdrops = document.querySelectorAll('.modal-backdrop');
        for (var i = 0; i < backdrops.length; i++) { backdrops[i].style.zIndex = '1199'; }
    }, { once: true });
    modal.show();
    var btn = document.getElementById('confirmMmProfileBtn');
    if (btn) {
        btn.onclick = function() {
            modal.hide();
            if (pendingMmProfileForm) {
                pendingMmProfileForm.submit();
                pendingMmProfileForm = null;
            }
        };
    }
}
function openMmProfilePicFullscreen(src) {
    if (!src) return;
    var el = document.getElementById('mmProfilePicFullscreen');
    var img = document.getElementById('mmProfilePicFullscreenImg');
    if (el && img) {
        img.src = src;
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}
function closeMmProfilePicFullscreen() {
    var el = document.getElementById('mmProfilePicFullscreen');
    if (el) {
        el.style.display = 'none';
        document.body.style.overflow = '';
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMmProfilePicFullscreen();
});

// Make photos data available to JS
window.photosByEvent = <?= json_encode($photosByEvent ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.currentMultimediaUserId = <?= (int)($uid ?? 0) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar collapse toggle (desktop + tablet)
    var mmSidebarToggle = document.getElementById('mmSidebarToggle');
    var mmSidebarClose = document.getElementById('mmSidebarClose');
    var mmSidebarBackdrop = document.getElementById('mmSidebarBackdrop');
    var isMobileView = function() { return window.matchMedia('(max-width: 768px)').matches; };
    var closeMmMobileSidebar = function() { document.body.classList.remove('mm-sidebar-open'); };
    if (mmSidebarToggle) {
        mmSidebarToggle.addEventListener('click', function() {
            if (isMobileView()) {
                document.body.classList.toggle('mm-sidebar-open');
                return;
            }
            document.body.classList.toggle('mm-sidebar-collapsed');
        });
    }
    if (mmSidebarClose) mmSidebarClose.addEventListener('click', closeMmMobileSidebar);
    if (mmSidebarBackdrop) {
        mmSidebarBackdrop.addEventListener('click', closeMmMobileSidebar);
    }
    var mmSidebar = document.getElementById('mmSidebar');
    if (mmSidebar) {
        mmSidebar.addEventListener('click', function(e) {
            var target = e.target.closest('.action-btn, [data-bs-toggle="modal"]');
            if (target && isMobileView()) closeMmMobileSidebar();
        });
    }
    window.addEventListener('resize', function() {
        if (!isMobileView()) closeMmMobileSidebar();
    });

    // Client-side search filter (title + location)
    var searchInput = document.getElementById('eventSearchInput');
    var list = document.getElementById('eventsList');
    if (searchInput && list) {
        searchInput.addEventListener('input', function() {
            var q = (searchInput.value || '').trim().toLowerCase();
            var cards = list.querySelectorAll('.event-card');
            cards.forEach(function(card) {
                var t = (card.getAttribute('data-title') || '').toLowerCase();
                var loc = (card.getAttribute('data-location') || '').toLowerCase();
                var ok = !q || t.includes(q) || loc.includes(q);
                card.style.display = ok ? '' : 'none';
            });
        });
    }

    var publishPhotosModal = document.getElementById('publishPhotosModal');
    if (publishPhotosModal) {
        publishPhotosModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var idEl = document.getElementById('publishPhotosEventId');
            var msgEl = document.getElementById('publishPhotosMessage');
            if (!btn || !idEl || !msgEl) return;
            var eventId = btn.dataset.eventId || '';
            var title = btn.dataset.eventTitle || '';
            var drafts = parseInt(btn.dataset.draftCount || '0', 10) || 0;
            idEl.value = eventId;
            var noun = drafts === 1 ? 'pending photo' : 'pending photos';
            msgEl.textContent = 'Approve all ' + drafts + ' ' + noun + ' for "' + title + '"? Students will be able to see them in the gallery and Activities hub.';
        });
    }

    var openModal = new URLSearchParams(window.location.search).get('open_modal');
    if (openModal === 'photo_approvals') {
        var approvalsModal = document.getElementById('photoApprovalsModal');
        if (approvalsModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(approvalsModal).show();
        }
    }
    if (openModal === 'photo_activity') {
        var activityLogModal = document.getElementById('photoActivityLogModal');
        if (activityLogModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(activityLogModal).show();
        }
    }

    var deleteEventPhotosModal = document.getElementById('deleteEventPhotosModal');
    if (deleteEventPhotosModal) {
        deleteEventPhotosModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var idEl = document.getElementById('deleteEventPhotosEventId');
            var msgEl = document.getElementById('deleteEventPhotosMessage');
            if (!btn || !idEl || !msgEl) return;
            var eventId = btn.dataset.eventId || '';
            var title = btn.dataset.eventTitle || '';
            var count = parseInt(btn.dataset.myCount || '0', 10) || 0;
            idEl.value = eventId;
            msgEl.textContent = 'Delete ' + count + ' of your photo(s) from "' + title + '"? This cannot be undone.';
        });
    }

    var galleryModal = document.getElementById('galleryModal');
    if (galleryModal) {
        galleryModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var titleEl = document.getElementById('galleryTitle');
            var gridEl = document.getElementById('galleryGrid');
            var emptyEl = document.getElementById('galleryEmpty');
            if (!gridEl || !emptyEl) return;

            gridEl.innerHTML = '';
            emptyEl.style.display = 'none';

            if (btn && btn.dataset.eventId) {
                var eventId = btn.dataset.eventId;
                var eventTitle = btn.dataset.eventTitle || '';
                if (titleEl) {
                    titleEl.textContent = 'Photos for: ' + eventTitle;
                }

                var photos = (window.photosByEvent && window.photosByEvent[eventId]) || [];
                if (!photos.length) {
                    emptyEl.style.display = 'block';
                    return;
                }

                photos.forEach(function(photo, index) {
                    var wrapper = document.createElement('div');
                    wrapper.className = 'gallery-item';

                    var img = document.createElement('img');
                    img.src = '<?= BASE_URL ?>/' + photo.file_path;
                    img.alt = 'Event photo';
                    img.className = 'gallery-photo';
                    img.style.cursor = 'pointer';
                    img.onclick = function() {
                        openPhotoViewer(eventId, index);
                    };

                    wrapper.appendChild(img);
                    var currentUid = parseInt(window.currentMultimediaUserId || '0', 10) || 0;
                    var photoOwnerId = parseInt(photo.uploaded_by || '0', 10) || 0;
                    var canDeletePhoto = currentUid > 0 && photoOwnerId === currentUid;
                    if (canDeletePhoto) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '<?= BASE_URL ?>/backend/auth/delete_event_photo.php';
                        form.className = 'delete-photo-form';
                        form.onclick = function(ev) {
                            ev.stopPropagation();
                        };

                        var inputId = document.createElement('input');
                        inputId.type = 'hidden';
                        inputId.name = 'photo_id';
                        inputId.value = photo.id;

                        var inputEvent = document.createElement('input');
                        inputEvent.type = 'hidden';
                        inputEvent.name = 'event_id';
                        inputEvent.value = eventId;

                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn-delete-photo';
                        btn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                        btn.onclick = function (ev) {
                            ev.stopPropagation();
                            window.pendingDeletePhotoForm = form;
                            var modal = new bootstrap.Modal(document.getElementById('deletePhotoModal'));
                            modal.show();
                        };

                        var csrfEl = document.getElementById('csrf_token_value');
                        form.appendChild(inputId);
                        form.appendChild(inputEvent);
                        if (csrfEl) {
                            var inputCsrf = document.createElement('input');
                            inputCsrf.type = 'hidden';
                            inputCsrf.name = 'csrf_token';
                            inputCsrf.value = csrfEl.value;
                            form.appendChild(inputCsrf);
                        }
                        form.appendChild(btn);
                        wrapper.appendChild(form);
                    }
                    gridEl.appendChild(wrapper);
                });
            }
        });
    }

    var deleteConfirmBtn = document.getElementById('deletePhotoConfirmBtn');
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', function() {
            if (window.pendingDeletePhotoForm) {
                var modal = bootstrap.Modal.getInstance(document.getElementById('deletePhotoModal'));
                if (modal) modal.hide();
                window.pendingDeletePhotoForm.submit();
                window.pendingDeletePhotoForm = null;
            }
        });
    }

    // Photo viewer (Facebook-style)
    var currentEventId = null;
    var currentPhotoIndex = 0;
    var currentPhotos = [];

    window.openPhotoViewer = function(eventId, photoIndex) {
        currentEventId = eventId;
        currentPhotoIndex = parseInt(photoIndex) || 0;
        currentPhotos = (window.photosByEvent && window.photosByEvent[eventId]) || [];
        
        if (!currentPhotos.length) return;
        
        var viewer = document.getElementById('photoViewer');
        if (viewer) {
            viewer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            updateViewerImage();
        }
    };

    window.closePhotoViewer = function() {
        var viewer = document.getElementById('photoViewer');
        if (viewer) {
            viewer.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    window.navigatePhoto = function(direction) {
        if (!currentPhotos.length) return;
        currentPhotoIndex += direction;
        if (currentPhotoIndex < 0) currentPhotoIndex = currentPhotos.length - 1;
        if (currentPhotoIndex >= currentPhotos.length) currentPhotoIndex = 0;
        updateViewerImage();
    };

    function updateViewerImage() {
        if (!currentPhotos.length || currentPhotoIndex < 0 || currentPhotoIndex >= currentPhotos.length) return;
        var photo = currentPhotos[currentPhotoIndex];
        var img = document.getElementById('viewerImage');
        var countEl = document.getElementById('viewerPhotoCount');
        
        if (img) {
            img.src = '<?= BASE_URL ?>/' + photo.file_path;
        }
        if (countEl) {
            countEl.textContent = (currentPhotoIndex + 1) + ' / ' + currentPhotos.length;
        }
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        var viewer = document.getElementById('photoViewer');
        if (viewer && viewer.style.display === 'flex') {
            if (e.key === 'Escape') {
                closePhotoViewer();
            } else if (e.key === 'ArrowLeft') {
                navigatePhoto(-1);
            } else if (e.key === 'ArrowRight') {
                navigatePhoto(1);
            }
        }
    });
});
</script>
</body>
</html>
