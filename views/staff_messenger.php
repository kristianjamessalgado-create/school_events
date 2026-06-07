<?php
/** @var string $pageTitle */
/** @var string $dashboardHref */
/** @var int $uid */
/** @var string $role */
/** @var string $myName */
/** @var array $peersList */
/** @var int $initialWith */
/** @var string|null $messaging_error */
$pageTitle = $pageTitle ?? 'Messages';
$dashboardHref = $dashboardHref ?? BASE_URL . '/';
$myName = $myName ?? '';
$peersList = $peersList ?? [];
$initialWith = isset($initialWith) ? (int)$initialWith : 0;
$messaging_error = $messaging_error ?? null;
$uid = isset($uid) ? (int)$uid : (int)($_SESSION['user_id'] ?? 0);
$role = $role ?? (string)($_SESSION['role'] ?? '');
$peerLabel = ($role === 'admin') ? 'Organizers' : 'Admins';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> — EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL) ?>/assets/css/staff_messenger.css?v=2">
</head>
<body class="msgr-body">
<div class="msgr-detail-backdrop" id="msgrDetailBackdrop" hidden aria-hidden="true"></div>
<div class="msgr-app" id="staffMessengerApp">
    <aside class="msgr-rail" aria-label="Shortcuts">
        <div class="msgr-rail-brand">EVENTIFY</div>
        <a href="<?= htmlspecialchars($dashboardHref) ?>" class="msgr-rail-btn" title="Back to dashboard">
            <i class="fas fa-arrow-left"></i>
        </a>
        <span class="msgr-rail-btn msgr-rail-btn-active" title="Staff messages"><i class="fas fa-comments"></i></span>
    </aside>

    <aside class="msgr-inbox">
        <div class="msgr-inbox-head">
            <div class="d-flex align-items-start justify-content-between mb-1">
                <div>
                    <h1 class="msgr-inbox-title mb-0">Staff messages</h1>
                    <p class="msgr-inbox-sub mb-0">Chat with <?= htmlspecialchars(strtolower($peerLabel)) ?></p>
                </div>
                <a href="<?= htmlspecialchars($dashboardHref) ?>" class="msgr-icon-btn" title="Close"><i class="fas fa-times"></i></a>
            </div>
            <div class="msgr-search-wrap">
                <i class="fas fa-search msgr-search-icon"></i>
                <input type="search" id="msgrSearch" class="msgr-search" placeholder="Search <?= htmlspecialchars(strtolower($peerLabel)) ?>…" autocomplete="off">
            </div>
            <div class="msgr-tabs" role="tablist">
                <button type="button" class="msgr-tab active" data-msgr-filter="all">All</button>
                <button type="button" class="msgr-tab" data-msgr-filter="unread">Unread</button>
            </div>
        </div>
        <div class="msgr-chat-list" id="msgrPeerList">
            <?php if ($messaging_error): ?>
                <div class="p-3 small text-danger"><?= htmlspecialchars($messaging_error) ?></div>
            <?php elseif (empty($peersList)): ?>
                <div class="msgr-list-empty">No <?= htmlspecialchars(strtolower($peerLabel)) ?> to message yet.</div>
            <?php endif; ?>
        </div>
    </aside>

    <main class="msgr-conversation">
        <header class="msgr-conv-head" id="msgrConvHead">
            <div class="msgr-conv-head-main">
                <button type="button" class="msgr-icon-btn msgr-back-mobile" id="msgrBackToList" title="Chats"><i class="fas fa-arrow-left"></i></button>
                <div class="msgr-avatar msgr-avatar-lg" id="msgrHeadAvatar">?</div>
                <div>
                    <div class="msgr-conv-name" id="msgrHeadName">Select a chat</div>
                    <div class="msgr-conv-sub msgr-muted" id="msgrHeadSub"><?= htmlspecialchars($peerLabel) ?></div>
                </div>
            </div>
            <div class="msgr-conv-actions">
                <button type="button" class="msgr-icon-btn" id="msgrToggleDetail" title="Chat info" aria-expanded="true"><i class="fas fa-circle-info"></i></button>
            </div>
        </header>

        <div class="msgr-messages" id="msgrThread"></div>

        <footer class="msgr-composer-wrap">
            <form id="msgrSendForm" class="msgr-composer">
                <input type="hidden" name="csrf_token" id="msgrCsrf" value="<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '') ?>">
                <input type="hidden" name="recipient_id" id="msgrRecipientId" value="">
                <button type="button" class="msgr-composer-icon" disabled title="Attachments (coming soon)"><i class="fas fa-plus"></i></button>
                <textarea class="msgr-input" name="body" id="msgrBody" rows="1" placeholder="Write a message…" maxlength="8000" disabled></textarea>
                <button type="submit" class="msgr-send" id="msgrSendBtn" disabled title="Send"><i class="fas fa-paper-plane"></i></button>
            </form>
        </footer>
    </main>

    <aside class="msgr-detail" id="msgrDetailPanel">
        <div class="msgr-detail-inner">
            <div class="msgr-detail-hero">
                <div class="msgr-avatar msgr-avatar-xl" id="msgrDetailAvatar">?</div>
                <h2 class="msgr-detail-name" id="msgrDetailName">—</h2>
                <p class="msgr-detail-email msgr-muted small mb-0" id="msgrDetailEmail"></p>
                <span class="msgr-detail-role" id="msgrDetailRole"><?= htmlspecialchars($peerLabel) ?></span>
            </div>
            <div class="msgr-detail-actions">
                <button type="button" class="msgr-pill-btn" disabled><i class="fas fa-bell-slash me-2"></i>Mute</button>
                <button type="button" class="msgr-pill-btn" disabled><i class="fas fa-magnifying-glass me-2"></i>Search</button>
            </div>
            <div class="msgr-detail-section">
                <div class="msgr-detail-section-title"><i class="fas fa-shield-alt me-1"></i> Staff channel</div>
                <p class="small msgr-muted mb-0">Official admin ↔ organizer messaging on EVENTIFY. Use this for event approvals, updates, and coordination.</p>
            </div>
        </div>
    </aside>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
window.csrfToken = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;
window.__staffMessengerSelfId = <?= (int)$uid ?>;
window.__staffMessengerPeers = <?= json_encode($peersList, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
window.__staffMessengerInitialWith = <?= (int)$initialWith ?>;
window.__staffMessengerPeerLabel = <?= json_encode($peerLabel) ?>;
window.__staffMessengerError = <?= json_encode($messaging_error) ?>;
</script>
<script src="<?= htmlspecialchars(BASE_URL) ?>/assets/js/staff_messenger.js?v=2"></script>
</body>
</html>
