<?php
session_start();
if (!defined('BASE_URL')) {
    define('BASE_URL', '/school_events');
}
include __DIR__ . '/config/db.php';
include __DIR__ . '/config/config.php';
include __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/backend/lib/event_ticketing.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_URL . '/views/login.php');
    exit();
}

eventify_ticketing_ensure_schema($conn);
$userId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? 0);
$error = '';
$msg = trim((string) ($_GET['msg'] ?? ''));

$order = eventify_load_ticket_order($conn, $orderId, $userId);
if (!$order) {
    $conn->close();
    header('Location: ' . BASE_URL . '/backend/auth/dashboard_student.php?error=' . urlencode('Order not found'));
    exit();
}

if (($order['status'] ?? '') === 'paid') {
    $conn->close();
    header('Location: ' . BASE_URL . '/my_tickets.php?order_id=' . $orderId . '&msg=' . urlencode('Payment already completed'));
    exit();
}

$payMode = eventify_payment_mode();
$allowSimulate = in_array($payMode, ['simulate', 'both'], true);
$allowGcash = in_array($payMode, ['gcash_manual', 'both'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
    $action = trim((string) ($_POST['payment_action'] ?? ''));
    if ($action === 'simulate' && $allowSimulate) {
        $result = eventify_fulfill_ticket_order($conn, $orderId, 'simulate', 'DEMO-' . date('YmdHis'));
        if ($result['ok']) {
            $conn->close();
            header('Location: ' . BASE_URL . '/my_tickets.php?order_id=' . $orderId . '&msg=' . urlencode('Payment successful! Your digital passes are ready.'));
            exit();
        }
        $error = $result['error'] ?? 'Payment failed';
    } elseif ($action === 'gcash' && $allowGcash) {
        $ref = trim((string) ($_POST['gcash_reference'] ?? ''));
        if ($ref === '' || strlen($ref) < 6) {
            $error = 'Enter your GCash reference number (at least 6 characters).';
        } else {
            $upd = $conn->prepare(
                "UPDATE ticket_orders SET payment_method = 'gcash', payment_reference = ? WHERE id = ? AND user_id = ? AND status = 'pending'"
            );
            if ($upd) {
                $upd->bind_param('sii', $ref, $orderId, $userId);
                $upd->execute();
                $upd->close();
            }
            try {
                $evId = (int) ($order['event_id'] ?? 0);
                $org = $conn->prepare('SELECT organizer_id FROM events WHERE id = ? LIMIT 1');
                if ($org) {
                    $org->bind_param('i', $evId);
                    $org->execute();
                    $or = $org->get_result()->fetch_assoc();
                    $org->close();
                    $orgId = (int) ($or['organizer_id'] ?? 0);
                    if ($orgId > 0) {
                        $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, event_id) VALUES (?, 'ticket_payment_pending', 'Ticket payment to verify', ?, ?)");
                        if ($n) {
                            $nMsg = ($_SESSION['name'] ?? 'A student') . ' submitted GCash ref ' . $ref . ' for order ' . ($order['order_ref'] ?? '') . '.';
                            $n->bind_param('isi', $orgId, $nMsg, $evId);
                            $n->execute();
                            $n->close();
                        }
                    }
                }
            } catch (Throwable $e) {
            }
            $conn->close();
            header('Location: ' . BASE_URL . '/ticket_payment.php?order_id=' . $orderId . '&msg=' . urlencode('Reference saved. Tickets will be issued after the organizer verifies your payment.'));
            exit();
        }
    }
}

$order = eventify_load_ticket_order($conn, $orderId, $userId);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay for tickets | EVENTIFY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/event_tickets.css">
</head>
<body class="ticket-shop-page">
<div class="container py-4" style="max-width: 520px;">
    <h1 class="h5 mb-3"><i class="fas fa-credit-card me-2 text-success"></i>Complete payment</h1>
    <p class="text-muted small">Order <strong><?= htmlspecialchars($order['order_ref'] ?? '') ?></strong> · <?= htmlspecialchars($order['event_title'] ?? '') ?></p>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <ul class="list-group mb-3">
        <?php foreach ($order['items'] ?? [] as $item): ?>
            <li class="list-group-item d-flex justify-content-between">
                <span><?= (int) ($item['quantity'] ?? 0) ?> × <?= htmlspecialchars($item['type_name'] ?? '') ?></span>
                <span><?= eventify_format_ticket_price((float) ($item['subtotal'] ?? 0)) ?></span>
            </li>
        <?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between fw-bold">
            <span>Total</span>
            <span class="text-success"><?= eventify_format_ticket_price((float) ($order['total_amount'] ?? 0)) ?></span>
        </li>
    </ul>

    <?php if ($allowGcash): ?>
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6">Pay via GCash</h2>
                <p class="small text-muted mb-2">Send the exact amount to the school GCash account shown by the organizer, then enter your reference number below.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_action" value="gcash">
                    <div class="mb-2">
                        <label class="form-label small">GCash reference no.</label>
                        <input type="text" name="gcash_reference" class="form-control" placeholder="e.g. 1234567890" required minlength="6" maxlength="120"
                               value="<?= htmlspecialchars((string) ($order['payment_reference'] ?? '')) ?>">
                    </div>
                    <button type="submit" class="btn btn-outline-success w-100">Submit reference for verification</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($allowSimulate): ?>
        <div class="card border-warning mb-3">
            <div class="card-body">
                <h2 class="h6">Demo / test payment</h2>
                <p class="small text-muted mb-2">For school demo only — marks this order as paid instantly and issues digital passes.</p>
                <form method="post" onsubmit="return confirm('Mark this order as paid for demo?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_action" value="simulate">
                    <button type="submit" class="btn btn-warning w-100">Pay now (demo)</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/event_tickets.php?id=<?= (int) ($order['event_id'] ?? 0) ?>" class="btn btn-link">← Back to tickets</a>
</div>
</body>
</html>
