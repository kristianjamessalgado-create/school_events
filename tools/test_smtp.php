<?php
/**
 * CLI: php tools/test_smtp.php [recipient@email.com]
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../backend/lib/email_sender.php';

$to = $argv[1] ?? EVENTIFY_SMTP_USERNAME;
$result = eventify_send_email(
    $to,
    '[EVENTIFY] SMTP test',
    "EVENTIFY SMTP test at " . date('Y-m-d H:i:s') . "\n\nIf you received this, email OTP delivery is working."
);

echo 'SMTP enabled: ' . (eventify_email_enabled() ? 'yes' : 'no') . PHP_EOL;
var_export($result);
echo PHP_EOL;
