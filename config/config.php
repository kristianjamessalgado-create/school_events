<?php



require_once __DIR__ . '/env.php';

eventify_load_env_file();



/** Local timezone for schedules, “live now”, and date comparisons (Philippines). */

if (!defined('EVENTIFY_APP_TIMEZONE')) {

    define('EVENTIFY_APP_TIMEZONE', eventify_env('EVENTIFY_APP_TIMEZONE', 'Asia/Manila') ?? 'Asia/Manila');

}

if (function_exists('date_default_timezone_set')) {

    @date_default_timezone_set(EVENTIFY_APP_TIMEZONE);

}



if (!defined('BASE_URL')) {

    $baseFromEnv = eventify_env('BASE_URL');

    define('BASE_URL', $baseFromEnv !== null && $baseFromEnv !== '' ? $baseFromEnv : '/school_events');

}



/** Require main-event RSVP before QR check-in (main and activity). */

if (!defined('EVENTIFY_CHECKIN_REQUIRE_RSVP')) {

    define('EVENTIFY_CHECKIN_REQUIRE_RSVP', true);

}



/** Require session RSVP before activity session check-in (when RSVP table exists). */

if (!defined('EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP')) {

    define('EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP', true);

}



/** When an event/activity has map coordinates, require live GPS within radius. Disabled for local testing. QR check-in stays enabled. */

if (!defined('EVENTIFY_CHECKIN_GEO_WHEN_PINNED')) {

    define('EVENTIFY_CHECKIN_GEO_WHEN_PINNED', false);

}



/** Meters from venue pin allowed for check-in. */

if (!defined('EVENTIFY_CHECKIN_GEO_RADIUS_M')) {

    define('EVENTIFY_CHECKIN_GEO_RADIUS_M', 300);

}



/** Minutes before activity start time when QR check-in opens. */

if (!defined('EVENTIFY_CHECKIN_EARLY_MINUTES')) {

    define('EVENTIFY_CHECKIN_EARLY_MINUTES', 15);

}



/** Ticket payment: simulate (demo), gcash_manual (reference + organizer confirm), both */

if (!defined('EVENTIFY_PAYMENT_MODE')) {

    define('EVENTIFY_PAYMENT_MODE', eventify_env('EVENTIFY_PAYMENT_MODE', 'both') ?? 'both');

}



if (!defined('EVENTIFY_SMS_PROVIDER')) {

    define('EVENTIFY_SMS_PROVIDER', eventify_env('EVENTIFY_SMS_PROVIDER', 'semaphore') ?? 'semaphore');

}



if (!defined('SEMAPHORE_API_KEY')) {

    define('SEMAPHORE_API_KEY', eventify_env('SEMAPHORE_API_KEY', '') ?? '');

}



if (!defined('SEMAPHORE_SENDER_NAME')) {

    define('SEMAPHORE_SENDER_NAME', eventify_env('SEMAPHORE_SENDER_NAME', '') ?? '');

}



$smtpLocalPath = __DIR__ . '/smtp.local.php';
if (is_readable($smtpLocalPath)) {
    require_once $smtpLocalPath;
}

if (!defined('EVENTIFY_SMTP_HOST')) {
    define('EVENTIFY_SMTP_HOST', eventify_env('EVENTIFY_SMTP_HOST', 'smtp.gmail.com') ?? 'smtp.gmail.com');
}

if (!defined('EVENTIFY_SMTP_PORT')) {
    define('EVENTIFY_SMTP_PORT', (int) (eventify_env('EVENTIFY_SMTP_PORT', '587') ?? '587'));
}

if (!defined('EVENTIFY_SMTP_USERNAME')) {
    define('EVENTIFY_SMTP_USERNAME', eventify_env('EVENTIFY_SMTP_USERNAME', '') ?? '');
}

if (!defined('EVENTIFY_SMTP_PASSWORD')) {
    define('EVENTIFY_SMTP_PASSWORD', eventify_env('EVENTIFY_SMTP_PASSWORD', '') ?? '');
}

if (!defined('EVENTIFY_SMTP_FROM_EMAIL')) {
    define('EVENTIFY_SMTP_FROM_EMAIL', eventify_env('EVENTIFY_SMTP_FROM_EMAIL', '') ?? '');
}

if (!defined('EVENTIFY_SMTP_FROM_NAME')) {
    define('EVENTIFY_SMTP_FROM_NAME', eventify_env('EVENTIFY_SMTP_FROM_NAME', 'EVENTIFY') ?? 'EVENTIFY');
}

if (!defined('EVENTIFY_SMTP_ALLOW_INSECURE_TLS')) {
    $insecureTls = strtolower(eventify_env('EVENTIFY_SMTP_ALLOW_INSECURE_TLS', 'false') ?? 'false');
    define('EVENTIFY_SMTP_ALLOW_INSECURE_TLS', in_array($insecureTls, ['1', 'true', 'yes'], true));
}



$error = $error ?? '';

$success = $success ?? '';

?>

