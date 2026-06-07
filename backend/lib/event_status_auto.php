<?php

require_once __DIR__ . '/event_calendar.php';

/**
 * Status used when an event is finished (auto or organizer "mark as ended").
 * Matches ENUM if `completed` exists, otherwise `closed`.
 */
function eventify_events_completed_or_closed_target(mysqli $conn): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = 'closed';
    try {
        $col = $conn->query("SHOW COLUMNS FROM events LIKE 'status'");
        if ($col && ($row = $col->fetch_assoc())) {
            $type = strtolower((string) ($row['Type'] ?? ''));
            if (strpos($type, "'completed'") !== false) {
                $cached = 'completed';
            }
        }
    } catch (Throwable $e) {
        // keep default
    }
    return $cached;
}

function eventify_auto_complete_past_events(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $targetStatus = eventify_events_completed_or_closed_target($conn);

        $hasScheduleTable = eventify_event_schedule_dates_table_exists($conn);
        $endDateExpr = eventify_events_has_end_date($conn)
            ? 'COALESCE(NULLIF(end_date, \'\'), `date`)'
            : '`date`';
        if ($hasScheduleTable) {
            $sql = "
                UPDATE events e
                SET status = ?
                WHERE e.status = 'active'
                  AND (
                    (
                      EXISTS (SELECT 1 FROM event_schedule_dates sd WHERE sd.event_id = e.id)
                      AND TIMESTAMP(
                        (SELECT MAX(sd.schedule_date) FROM event_schedule_dates sd WHERE sd.event_id = e.id),
                        COALESCE(NULLIF(e.end_time, ''), '23:59:59')
                      ) < NOW()
                    )
                    OR (
                      NOT EXISTS (SELECT 1 FROM event_schedule_dates sd WHERE sd.event_id = e.id)
                      AND TIMESTAMP({$endDateExpr}, COALESCE(NULLIF(e.end_time, ''), '23:59:59')) < NOW()
                    )
                  )
            ";
        } else {
            $sql = "
                UPDATE events
                SET status = ?
                WHERE status = 'active'
                  AND TIMESTAMP({$endDateExpr}, COALESCE(NULLIF(end_time, ''), '23:59:59')) < NOW()
            ";
        }
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $targetStatus);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Keep dashboard available even if auto-complete fails.
    }
}
