<?php

session_start();

if (!defined('BASE_URL')) {

    define('BASE_URL', '/school_events');

}

include __DIR__ . '/config/db.php';

include __DIR__ . '/config/config.php';

require_once __DIR__ . '/backend/lib/event_ticketing.php';



if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {

    header('Location: ' . BASE_URL . '/views/login.php');

    exit();

}



eventify_ticketing_ensure_schema($conn);

$userId = (int) $_SESSION['user_id'];

$eventFilter = (int) ($_GET['event_id'] ?? 0);

$orderFilter = (int) ($_GET['order_id'] ?? 0);

$msg = trim((string) ($_GET['msg'] ?? ''));



$tickets = eventify_load_user_tickets($conn, $userId, $eventFilter > 0 ? $eventFilter : null);

if ($orderFilter > 0) {

    $tickets = array_values(array_filter($tickets, static function ($t) use ($orderFilter) {

        return (int) ($t['order_id'] ?? 0) === $orderFilter;

    }));

}



$bootstrapTickets = array_map(static function (array $t) {

    return [

        'ticket_code' => (string) ($t['ticket_code'] ?? ''),

        'event_title' => (string) ($t['event_title'] ?? ''),

        'type_name'   => (string) ($t['type_name'] ?? ''),

        'event_date'  => substr((string) ($t['event_date'] ?? ''), 0, 10),

    ];

}, $tickets);



$conn->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My tickets | EVENTIFY</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_tickets.css">

    <link rel="manifest" href="<?= BASE_URL ?>/manifest-student.php">

    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/pwa/icon-192.png">

    <meta name="theme-color" content="#064e3b">

    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pwa_student.css">

</head>

<body class="ticket-shop-page">

<div class="container py-4" style="max-width: 720px;">

    <a href="<?= BASE_URL ?>/backend/auth/dashboard_student.php" class="btn btn-link ps-0 mb-2"><i class="fas fa-arrow-left"></i> Dashboard</a>

    <h1 class="h4 mb-3"><i class="fas fa-ticket-alt me-2 text-success"></i>My tickets</h1>

    <?php if ($msg): ?>

        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>

    <?php endif; ?>

    <div id="pwaOfflineTicketsNotice" class="pwa-offline-notice" hidden></div>

    <div id="myTicketsList">

        <?php if ($tickets === []): ?>

            <p class="text-muted mb-0">You have no active tickets yet. Browse events with ticket sales on your dashboard.</p>

        <?php else: ?>

            <div class="row g-3">

                <?php foreach ($tickets as $t): ?>

                    <div class="col-12">

                        <div class="card ticket-pass-preview shadow-sm border-0">

                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">

                                <div>

                                    <div class="fw-semibold"><?= htmlspecialchars($t['event_title'] ?? '') ?></div>

                                    <div class="small text-muted"><?= htmlspecialchars($t['type_name'] ?? '') ?> · <?= htmlspecialchars($t['ticket_code'] ?? '') ?></div>

                                    <div class="small"><?= htmlspecialchars(substr((string) ($t['event_date'] ?? ''), 0, 10)) ?></div>

                                </div>

                                <a href="<?= BASE_URL ?>/ticket_pass.php?code=<?= urlencode((string) ($t['ticket_code'] ?? '')) ?>" class="btn btn-success btn-sm">

                                    <i class="fas fa-qrcode me-1"></i>Digital pass

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>

<script>window.__myTicketsBootstrap = <?= json_encode($bootstrapTickets, JSON_UNESCAPED_UNICODE) ?>;</script>

<script src="<?= BASE_URL ?>/assets/js/eventify_pwa.js"></script>

</body>

</html>

