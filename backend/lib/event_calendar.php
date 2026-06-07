<?php

/**
 * Calendar, date-range, and specific schedule-day helpers for events.
 */

function eventify_events_has_end_date(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'end_date'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_event_schedule_dates_table_exists(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_schedule_dates'");
        if ($res && $res->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_event_schedule_dates_ensure_table(mysqli $conn): bool
{
    if (eventify_event_schedule_dates_table_exists($conn)) {
        return true;
    }
    $sql = @file_get_contents(__DIR__ . '/../../migrations/add_event_schedule_dates.sql');
    if ($sql === false || $sql === '') {
        return false;
    }
    try {
        if ($conn->multi_query($sql)) {
            while ($conn->more_results() && $conn->next_result()) {
                // drain
            }
        }
        return eventify_event_schedule_dates_table_exists($conn);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return list<string> Y-m-d sorted unique
 */
function eventify_parse_schedule_dates_from_request(array $post): array
{
    $raw = $post['schedule_dates'] ?? [];
    if (!is_array($raw)) {
        $raw = $raw !== '' ? [$raw] : [];
    }
    $out = [];
    foreach ($raw as $d) {
        $d = substr(trim((string) $d), 0, 10);
        if ($d === '') {
            continue;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        if ($dt && $dt->format('Y-m-d') === $d) {
            $out[$d] = $d;
        }
    }
    $dates = array_values($out);
    sort($dates);
    return $dates;
}

/** @return 'single'|'range'|'specific' */
function eventify_resolve_schedule_mode_from_request(array $post): string
{
    $mode = strtolower(trim((string) ($post['schedule_mode'] ?? 'single')));
    if (!in_array($mode, ['single', 'range', 'specific'], true)) {
        $mode = 'single';
    }
    if ($mode === 'specific') {
        $dates = eventify_parse_schedule_dates_from_request($post);
        if (count($dates) > 1) {
            return 'specific';
        }
        if (count($dates) === 1) {
            return 'single';
        }
    }
    if ($mode === 'range') {
        $end = substr(trim((string) ($post['end_date'] ?? '')), 0, 10);
        $start = substr(trim((string) ($post['date'] ?? '')), 0, 10);
        if ($end !== '' && $start !== '' && $end > $start) {
            return 'range';
        }
    }
    return 'single';
}

function eventify_events_has_end_time_na(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM events WHERE Field = 'end_time_na'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_schedule_dates_have_end_time_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_schedule_dates WHERE Field = 'end_time_na'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

function eventify_schedule_dates_have_start_time_columns(mysqli $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM event_schedule_dates WHERE Field = 'start_time'");
        if ($col && $col->num_rows >= 1) {
            $cache = true;
        }
    } catch (Throwable $e) {
        // keep false
    }
    return $cache;
}

/**
 * @return array{end_time: ?string, end_time_na: bool}
 */
function eventify_parse_event_end_time_from_request(array $post): array
{
    $option = strtolower(trim((string) ($post['end_time_option'] ?? 'none')));
    if ($option === 'na') {
        return ['end_time' => null, 'end_time_na' => true];
    }
    if ($option === 'time') {
        $t = trim((string) ($post['end_time'] ?? ''));
        if ($t !== '' && preg_match('/^\d{2}:\d{2}$/', $t)) {
            return ['end_time' => $t, 'end_time_na' => false];
        }
    }
    return ['end_time' => null, 'end_time_na' => false];
}

/**
 * @return array<string, string> Y-m-d => H:i
 */
function eventify_parse_schedule_day_start_times_from_request(array $post): array
{
    $raw = $post['schedule_day_start'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $ymd => $time) {
        $ymd = substr(trim((string) $ymd), 0, 10);
        if ($ymd === '') {
            continue;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $ymd);
        if (!$dt || $dt->format('Y-m-d') !== $ymd) {
            continue;
        }
        $t = trim((string) $time);
        if ($t !== '' && preg_match('/^\d{2}:\d{2}$/', $t)) {
            $out[$ymd] = $t;
        }
    }
    return $out;
}

/**
 * @return array<string, array{end_time: ?string, end_time_na: bool}>
 */
function eventify_parse_schedule_day_end_times_from_request(array $post): array
{
    $raw = $post['schedule_day_end'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $ymd => $row) {
        $ymd = substr(trim((string) $ymd), 0, 10);
        if ($ymd === '' || !is_array($row)) {
            continue;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $ymd);
        if (!$dt || $dt->format('Y-m-d') !== $ymd) {
            continue;
        }
        $mode = strtolower(trim((string) ($row['mode'] ?? 'none')));
        if ($mode === 'na') {
            $out[$ymd] = ['end_time' => null, 'end_time_na' => true];
            continue;
        }
        if ($mode === 'time') {
            $t = trim((string) ($row['time'] ?? ''));
            if ($t !== '' && preg_match('/^\d{2}:\d{2}$/', $t)) {
                $out[$ymd] = ['end_time' => $t, 'end_time_na' => false];
            }
        }
    }
    return $out;
}

function eventify_format_end_time_label(?string $endTime, bool $endTimeNa = false): string
{
    if ($endTimeNa) {
        return 'N/A';
    }
    $endTime = trim((string) $endTime);
    if ($endTime === '') {
        return '';
    }
    $ts = strtotime($endTime);
    return $ts ? date('g:i A', $ts) : $endTime;
}

/**
 * @param list<string> $scheduleDates
 * @param array<string, array{end_time: ?string, end_time_na: bool}> $dayEndTimes
 * @param array<string, string> $dayStartTimes Y-m-d => H:i
 */
function eventify_save_event_schedule_dates(mysqli $conn, int $eventId, array $scheduleDates, array $dayEndTimes = [], array $dayStartTimes = []): void
{
    if ($eventId < 1 || !eventify_event_schedule_dates_ensure_table($conn)) {
        return;
    }
    $del = $conn->prepare('DELETE FROM event_schedule_dates WHERE event_id = ?');
    if ($del) {
        $del->bind_param('i', $eventId);
        $del->execute();
        $del->close();
    }
    if (count($scheduleDates) < 2) {
        return;
    }

    $hasEndCols = eventify_schedule_dates_have_end_time_columns($conn);
    $hasStartCols = eventify_schedule_dates_have_start_time_columns($conn);
    if ($hasStartCols && $hasEndCols) {
        $ins = $conn->prepare('INSERT INTO event_schedule_dates (event_id, schedule_date, start_time, end_time, end_time_na) VALUES (?, ?, ?, ?, ?)');
    } elseif ($hasEndCols) {
        $ins = $conn->prepare('INSERT INTO event_schedule_dates (event_id, schedule_date, end_time, end_time_na) VALUES (?, ?, ?, ?)');
    } else {
        $ins = $conn->prepare('INSERT INTO event_schedule_dates (event_id, schedule_date) VALUES (?, ?)');
    }
    if (!$ins) {
        return;
    }

    foreach ($scheduleDates as $ymd) {
        $ymd = substr(trim((string) $ymd), 0, 10);
        if ($ymd === '') {
            continue;
        }
        $dayEnd = $dayEndTimes[$ymd] ?? ['end_time' => null, 'end_time_na' => false];
        $et = $dayEnd['end_time'] ?? null;
        $na = !empty($dayEnd['end_time_na']) ? 1 : 0;
        $st = isset($dayStartTimes[$ymd]) && $dayStartTimes[$ymd] !== '' ? $dayStartTimes[$ymd] : null;
        if ($hasStartCols && $hasEndCols) {
            $ins->bind_param('isssi', $eventId, $ymd, $st, $et, $na);
        } elseif ($hasEndCols) {
            $ins->bind_param('issi', $eventId, $ymd, $et, $na);
        } else {
            $ins->bind_param('is', $eventId, $ymd);
        }
        $ins->execute();
    }
    $ins->close();
}

/**
 * @param list<int> $eventIds
 * @return array<int, list<string>>
 */
function eventify_load_schedule_dates_map(mysqli $conn, array $eventIds): array
{
    $daysMap = eventify_load_schedule_days_map($conn, $eventIds);
    $map = [];
    foreach ($daysMap as $eid => $days) {
        $map[$eid] = array_column($days, 'schedule_date');
    }
    return $map;
}

/**
 * @param list<int> $eventIds
 * @return array<int, list<array{schedule_date: string, start_time: ?string, end_time: ?string, end_time_na: bool}>>
 */
function eventify_load_schedule_days_map(mysqli $conn, array $eventIds): array
{
    $map = [];
    $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static function ($id) {
        return $id > 0;
    })));
    if ($eventIds === [] || !eventify_event_schedule_dates_table_exists($conn)) {
        return $map;
    }
    $hasEndCols = eventify_schedule_dates_have_end_time_columns($conn);
    $hasStartCols = eventify_schedule_dates_have_start_time_columns($conn);
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $types = str_repeat('i', count($eventIds));
    $cols = 'event_id, schedule_date';
    if ($hasStartCols) {
        $cols .= ', start_time';
    }
    if ($hasEndCols) {
        $cols .= ', end_time, end_time_na';
    }
    $sql = "SELECT {$cols} FROM event_schedule_dates WHERE event_id IN ($placeholders) ORDER BY schedule_date ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $map;
    }
    $stmt->bind_param($types, ...$eventIds);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $eid = (int) ($row['event_id'] ?? 0);
        $ymd = substr(trim((string) ($row['schedule_date'] ?? '')), 0, 10);
        if ($eid > 0 && $ymd !== '') {
            if (!isset($map[$eid])) {
                $map[$eid] = [];
            }
            $map[$eid][] = [
                'schedule_date' => $ymd,
                'start_time' => $hasStartCols ? (trim((string) ($row['start_time'] ?? '')) ?: null) : null,
                'end_time' => $hasEndCols ? (trim((string) ($row['end_time'] ?? '')) ?: null) : null,
                'end_time_na' => $hasEndCols && !empty($row['end_time_na']),
            ];
        }
    }
    $stmt->close();
    return $map;
}

/**
 * @param array<string, mixed> $e
 * @return list<string>
 */
/**
 * @return list<string> Y-m-d
 */
function eventify_dates_between_inclusive(string $startYmd, string $endYmd): array
{
    $start = DateTime::createFromFormat('Y-m-d', substr($startYmd, 0, 10));
    $end = DateTime::createFromFormat('Y-m-d', substr($endYmd, 0, 10));
    if (!$start || !$end || $start->format('Y-m-d') !== substr($startYmd, 0, 10) || $end->format('Y-m-d') !== substr($endYmd, 0, 10)) {
        return [];
    }
    if ($end < $start) {
        return [];
    }
    $out = [];
    $cur = clone $start;
    while ($cur <= $end) {
        $out[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }
    return $out;
}

function eventify_event_get_schedule_dates(array $e): array
{
    if (!empty($e['schedule_days']) && is_array($e['schedule_days'])) {
        $dates = [];
        foreach ($e['schedule_days'] as $day) {
            $d = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            if ($d !== '') {
                $dates[$d] = $d;
            }
        }
        $dates = array_values($dates);
        sort($dates);
        if (count($dates) > 0) {
            return $dates;
        }
    }
    if (!empty($e['schedule_dates']) && is_array($e['schedule_dates'])) {
        $dates = [];
        foreach ($e['schedule_dates'] as $d) {
            $d = substr(trim((string) $d), 0, 10);
            if ($d !== '') {
                $dates[$d] = $d;
            }
        }
        $dates = array_values($dates);
        sort($dates);
        if (count($dates) > 0) {
            return $dates;
        }
    }
    return [];
}

/** @param list<string> $dates */
function eventify_schedule_dates_are_consecutive_range(array $dates): bool
{
    $dates = array_values(array_unique(array_filter(array_map(static function ($d) {
        return substr(trim((string) $d), 0, 10);
    }, $dates))));
    sort($dates);
    if (count($dates) < 2) {
        return false;
    }
    $filled = eventify_dates_between_inclusive($dates[0], $dates[count($dates) - 1]);
    return count($filled) === count($dates);
}

/** Non-consecutive picked days (intramurals-style) — calendar shows one block per day. */
function eventify_event_has_specific_schedule(array $e): bool
{
    $dates = eventify_event_get_schedule_dates($e);
    return count($dates) > 1 && !eventify_schedule_dates_are_consecutive_range($dates);
}

/** Multi-day events stored in event_schedule_dates — one calendar block per day (range or specific). */
function eventify_event_use_per_day_calendar_entries(array $e): bool
{
    if (eventify_event_has_specific_schedule($e)) {
        return true;
    }
    $dates = eventify_event_get_schedule_dates($e);

    return count($dates) > 1 && !empty($e['schedule_days']);
}

/** @return array<string, array{start_time: ?string, end_time: ?string, end_time_na: bool}> */
function eventify_event_schedule_days_by_ymd(array $e): array
{
    $map = [];
    foreach ($e['schedule_days'] ?? [] as $day) {
        if (!is_array($day)) {
            continue;
        }
        $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
        if ($ymd === '') {
            continue;
        }
        $map[$ymd] = [
            'start_time' => isset($day['start_time']) && $day['start_time'] !== '' ? trim((string) $day['start_time']) : null,
            'end_time' => isset($day['end_time']) && $day['end_time'] !== '' ? trim((string) $day['end_time']) : null,
            'end_time_na' => !empty($day['end_time_na']),
        ];
    }
    return $map;
}

function eventify_event_resolve_end_date(array $e): string
{
    if (eventify_event_has_specific_schedule($e)) {
        $dates = eventify_event_get_schedule_dates($e);
        return (string) end($dates);
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $end = substr(trim((string) ($e['end_date'] ?? '')), 0, 10);
    if ($start === '') {
        return $end;
    }
    if ($end === '' || $end < $start) {
        return $start;
    }
    return $end;
}

function eventify_event_is_multi_day(array $e): bool
{
    if (eventify_event_has_specific_schedule($e)) {
        return true;
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $end = eventify_event_resolve_end_date($e);
    return $start !== '' && $end > $start;
}

/**
 * FullCalendar start, end, and allDay for one calendar day of an event.
 *
 * @return array{start: string, end: string|null, allDay: bool}
 */
function eventify_event_fullcalendar_times(array $e, ?string $forDate = null): array
{
    $startDate = $forDate !== null && $forDate !== ''
        ? substr($forDate, 0, 10)
        : substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $endDate = eventify_event_resolve_end_date($e);
    $startTime = trim((string) ($e['start_time'] ?? ''));
    $endTimeNa = !empty($e['end_time_na']);
    $endTime = $endTimeNa ? '' : trim((string) ($e['end_time'] ?? ''));

    if ($startDate === '') {
        return ['start' => '', 'end' => null, 'allDay' => true];
    }

    $scheduleDates = eventify_event_get_schedule_dates($e);
    $useRangeBar = $forDate === null
        && count($scheduleDates) > 1
        && eventify_schedule_dates_are_consecutive_range($scheduleDates);

    if (!$useRangeBar && $forDate === null && count($scheduleDates) <= 1) {
        $useRangeBar = !eventify_event_has_specific_schedule($e) && eventify_event_is_multi_day($e);
    }

    if ($useRangeBar) {
        $dt = DateTime::createFromFormat('Y-m-d', $endDate);
        $exclusiveEnd = $dt ? $dt->modify('+1 day')->format('Y-m-d') : $endDate;

        return [
            'start' => $startDate,
            'end' => $exclusiveEnd,
            'allDay' => true,
        ];
    }

    $hasStartTime = $startTime !== '';
    if ($hasStartTime) {
        $start = $startDate . 'T' . (strlen($startTime) === 5 ? $startTime . ':00' : $startTime);
        if ($endTime !== '') {
            $end = $startDate . 'T' . (strlen($endTime) === 5 ? $endTime . ':00' : $endTime);
        } else {
            $startDt = DateTime::createFromFormat('Y-m-d H:i:s', $startDate . ' ' . $startTime);
            if (!$startDt) {
                $startDt = DateTime::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime);
            }
            $end = $startDt ? $startDt->modify('+1 hour')->format('Y-m-d\TH:i:s') : ($startDate . 'T23:59:59');
        }

        return ['start' => $start, 'end' => $end, 'allDay' => false];
    }

    return ['start' => $startDate, 'end' => null, 'allDay' => true];
}

/**
 * Build one or more FullCalendar event objects (specific days => one block per day).
 *
 * @param array<string, mixed> $e
 * @param callable|null $extendedPropsBuilder fn(array $e): array
 * @return list<array<string, mixed>>
 */
function eventify_event_fullcalendar_entries(array $e, ?callable $extendedPropsBuilder = null): array
{
    $eid = (int) ($e['id'] ?? 0);
    $title = (string) ($e['title'] ?? 'Untitled');
    $scheduleDates = eventify_event_get_schedule_dates($e);
    $baseProps = $extendedPropsBuilder ? $extendedPropsBuilder($e) : [];

    $startYmd = !empty($e['date']) ? substr(trim((string) $e['date']), 0, 10) : '';
    $endYmd = eventify_event_resolve_end_date($e);
    $baseProps['event_date_ymd'] = $startYmd;
    $baseProps['event_end_ymd'] = $endYmd;
    $regMode = strtolower(trim((string) ($e['registration_mode'] ?? 'rsvp')));
    $baseProps['event_allows_rsvp'] = eventify_event_is_upcoming($e)
        && !in_array($regMode, ['paid_ticket', 'open'], true);
    $baseProps['event_allows_checkin'] = eventify_event_allows_checkin($e);
    $baseProps['schedule_dates'] = $scheduleDates;
    if (count($scheduleDates) > 1) {
        $baseProps['schedule_mode'] = eventify_event_has_specific_schedule($e) ? 'specific' : 'range';
    } else {
        $baseProps['schedule_mode'] = eventify_event_is_multi_day($e) ? 'range' : 'single';
    }
    $baseProps['schedule_days'] = $e['schedule_days'] ?? [];

    if (eventify_event_use_per_day_calendar_entries($e)) {
        $entries = [];
        $groupId = 'event-' . $eid;
        $daysByYmd = eventify_event_schedule_days_by_ymd($e);
        foreach ($scheduleDates as $ymd) {
            $dayEvent = $e;
            $dayStart = null;
            $dayEnd = null;
            $dayEndNa = false;
            if (isset($daysByYmd[$ymd])) {
                if (!empty($daysByYmd[$ymd]['start_time'])) {
                    $dayEvent['start_time'] = $daysByYmd[$ymd]['start_time'];
                    $dayStart = $daysByYmd[$ymd]['start_time'];
                }
                $dayEvent['end_time'] = $daysByYmd[$ymd]['end_time'];
                $dayEvent['end_time_na'] = $daysByYmd[$ymd]['end_time_na'];
                $dayEnd = $daysByYmd[$ymd]['end_time'];
                $dayEndNa = $daysByYmd[$ymd]['end_time_na'];
            }
            $fc = eventify_event_fullcalendar_times($dayEvent, $ymd);
            $entries[] = [
                'id' => $eid > 0 ? ($eid . '-' . $ymd) : $ymd,
                'groupId' => $groupId,
                'title' => $title,
                'start' => $fc['start'],
                'end' => $fc['end'],
                'allDay' => $fc['allDay'],
                'extendedProps' => array_merge($baseProps, [
                    'event_id' => $eid,
                    'schedule_date_ymd' => $ymd,
                    'start_time' => $dayStart ?? ($e['start_time'] ?? null),
                    'end_time' => $dayEnd,
                    'end_time_na' => $dayEndNa,
                    'event_allows_checkin' => eventify_schedule_day_allows_checkin_now([
                        'schedule_date' => $ymd,
                        'start_time' => $dayStart ?? ($e['start_time'] ?? ''),
                        'end_time' => $dayEnd ?? '',
                        'end_time_na' => $dayEndNa,
                    ], new DateTimeImmutable('now', eventify_calendar_app_timezone())),
                ]),
            ];
        }
        return $entries;
    }

    $fc = eventify_event_fullcalendar_times($e);
    return [[
        'id' => $eid > 0 ? $eid : null,
        'title' => $title,
        'start' => $fc['start'],
        'end' => $fc['end'],
        'allDay' => $fc['allDay'],
        'extendedProps' => array_merge($baseProps, [
            'event_id' => $eid,
            'schedule_date_ymd' => $startYmd,
        ]),
    ]];
}

/**
 * @param list<array<string, mixed>> $events
 * @return list<array<string, mixed>>
 */
function eventify_events_to_fullcalendar_list(array $events, ?callable $extendedPropsBuilder = null): array
{
    $out = [];
    foreach ($events as $e) {
        foreach (eventify_event_fullcalendar_entries($e, $extendedPropsBuilder) as $entry) {
            $out[] = $entry;
        }
    }
    return $out;
}

/** @param list<string> $dates Y-m-d */
function eventify_format_schedule_dates_list(array $dates): string
{
    $dates = array_values(array_unique(array_filter(array_map(static function ($d) {
        return substr(trim((string) $d), 0, 10);
    }, $dates))));
    sort($dates);
    if ($dates === []) {
        return '';
    }
    if (count($dates) === 1) {
        $t = strtotime($dates[0]);
        return $t ? date('M j, Y', $t) : $dates[0];
    }

    $byMonth = [];
    foreach ($dates as $ymd) {
        $t = strtotime($ymd);
        if (!$t) {
            continue;
        }
        $key = date('Y-m', $t);
        $byMonth[$key][] = (int) date('j', $t);
    }

    $parts = [];
    foreach ($byMonth as $ym => $days) {
        $t = strtotime($ym . '-01');
        $monthLabel = $t ? date('M', $t) : $ym;
        $year = $t ? date('Y', $t) : '';
        $dayStr = implode(', ', $days);
        $parts[] = $monthLabel . ' ' . $dayStr . ', ' . $year;
    }

    return implode(' · ', $parts);
}

/** Human-readable date (and optional time) range for display pages. */
function eventify_format_event_date_range(array $e, bool $includeTimes = true): string
{
    $scheduleDates = eventify_event_get_schedule_dates($e);
    $startDate = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    if ($startDate === '' && $scheduleDates === []) {
        return '';
    }

    if (count($scheduleDates) > 1) {
        $datePart = eventify_format_schedule_dates_list($scheduleDates);
    } else {
        $endDate = eventify_event_resolve_end_date($e);
        $fmtDate = static function (string $ymd): string {
            $t = strtotime($ymd);
            return $t ? date('M j, Y', $t) : $ymd;
        };
        $datePart = $fmtDate($startDate);
        if ($endDate > $startDate && !eventify_event_has_specific_schedule($e)) {
            $datePart = $fmtDate($startDate) . ' – ' . $fmtDate($endDate);
        }
    }

    if (!$includeTimes) {
        return $datePart;
    }

    $startTime = trim((string) ($e['start_time'] ?? ''));
    $endTimeNa = !empty($e['end_time_na']);
    $endTime = trim((string) ($e['end_time'] ?? ''));
    $tEndLabel = eventify_format_end_time_label($endTime, $endTimeNa);

    if ($startTime === '') {
        return $datePart;
    }

    $tStart = date('g:i A', strtotime($startTime));
    $allScheduleDates = eventify_event_get_schedule_dates($e);
    if (count($allScheduleDates) > 1 && !empty($e['schedule_days']) && is_array($e['schedule_days'])) {
        $dayParts = [];
        $hasPerDayStart = false;
        foreach ($e['schedule_days'] as $day) {
            if (!is_array($day)) {
                continue;
            }
            $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            if ($ymd === '') {
                continue;
            }
            $t = strtotime($ymd);
            $lbl = $t ? date('M j', $t) : $ymd;
            $daySt = trim((string) ($day['start_time'] ?? ''));
            $stLbl = $daySt !== '' ? date('g:i A', strtotime($daySt)) : '';
            if ($stLbl !== '') {
                $hasPerDayStart = true;
            }
            $et = eventify_format_end_time_label($day['end_time'] ?? null, !empty($day['end_time_na']));
            $segment = $lbl;
            if ($stLbl !== '') {
                $segment .= ' ' . $stLbl;
            }
            if ($et !== '') {
                $segment .= '–' . $et;
            }
            $dayParts[] = $segment;
        }
        if ($dayParts !== []) {
            if ($hasPerDayStart) {
                return $datePart . ' · ' . implode('; ', $dayParts);
            }
            return $datePart . ' · Starts ' . $tStart . ' · ' . implode('; ', $dayParts);
        }
    }

    if ($tEndLabel !== '') {
        if (count($scheduleDates) > 1 || eventify_event_is_multi_day($e)) {
            return $datePart . ' · Starts ' . $tStart . ' · Ends ' . $tEndLabel;
        }
        return $datePart . ' · ' . $tStart . ' – ' . $tEndLabel;
    }

    return $datePart . ' · ' . $tStart;
}

/**
 * Attach schedule_dates onto each event row.
 *
 * @param list<array<string, mixed>> $events
 */
function eventify_events_attach_schedule_dates(mysqli $conn, array &$events): void
{
    $ids = array_column($events, 'id');
    $daysMap = eventify_load_schedule_days_map($conn, $ids);
    foreach ($events as &$e) {
        $eid = (int) ($e['id'] ?? 0);
        $days = $daysMap[$eid] ?? [];
        $e['schedule_days'] = $days;
        $e['schedule_dates'] = array_column($days, 'schedule_date');
    }
    unset($e);
}

/**
 * Last moment the event is considered "on" (last schedule day + end time).
 */
function eventify_event_effective_end_datetime(array $e): ?DateTimeImmutable
{
    $lastYmd = '';
    $lastEndTime = '';
    $lastEndTimeNa = false;
    $scheduleDays = $e['schedule_days'] ?? [];
    if (is_array($scheduleDays) && $scheduleDays !== []) {
        usort($scheduleDays, static function ($a, $b) {
            return strcmp(
                substr(trim((string) ($a['schedule_date'] ?? '')), 0, 10),
                substr(trim((string) ($b['schedule_date'] ?? '')), 0, 10)
            );
        });
        $lastDay = $scheduleDays[count($scheduleDays) - 1];
        if (is_array($lastDay)) {
            $lastYmd = substr(trim((string) ($lastDay['schedule_date'] ?? '')), 0, 10);
            $lastEndTimeNa = !empty($lastDay['end_time_na']);
            $lastEndTime = trim((string) ($lastDay['end_time'] ?? ''));
        }
    }
    if ($lastYmd === '') {
        $lastYmd = eventify_event_resolve_end_date($e);
        $lastEndTimeNa = !empty($e['end_time_na']);
        $lastEndTime = trim((string) ($e['end_time'] ?? ''));
    }
    if ($lastYmd === '') {
        return null;
    }
    if ($lastEndTimeNa || $lastEndTime === '') {
        $timePart = '23:59:59';
    } else {
        $timePart = strlen($lastEndTime) === 5 ? $lastEndTime . ':00' : substr($lastEndTime, 0, 8);
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastYmd . ' ' . $timePart);
    if ($dt instanceof DateTimeImmutable) {
        return $dt;
    }
    $dayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $lastYmd);
    return $dayOnly ? $dayOnly->setTime(23, 59, 59) : null;
}

function eventify_calendar_app_timezone(): DateTimeZone
{
    $tzId = defined('EVENTIFY_APP_TIMEZONE') ? (string) EVENTIFY_APP_TIMEZONE : 'Asia/Manila';
    try {
        return new DateTimeZone($tzId);
    } catch (Throwable $e) {
        return new DateTimeZone('Asia/Manila');
    }
}

/**
 * Whether main-event QR check-in is open today (correct schedule day + within that day's time window).
 *
 * @param array<string, mixed> $e Event row with schedule_days attached when applicable
 */
function eventify_event_allows_checkin(array $e, ?DateTimeInterface $now = null): bool
{
    if (strtolower(trim((string) ($e['status'] ?? ''))) !== 'active') {
        return false;
    }
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $todayYmd = $now->format('Y-m-d');

    $scheduleDays = $e['schedule_days'] ?? [];
    if (is_array($scheduleDays) && $scheduleDays !== []) {
        foreach ($scheduleDays as $day) {
            if (!is_array($day)) {
                continue;
            }
            $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
            if ($ymd !== $todayYmd) {
                continue;
            }
            return eventify_schedule_day_allows_checkin_now($day, $now);
        }
        return false;
    }

    $startYmd = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    $endYmd = eventify_event_resolve_end_date($e);
    if ($startYmd === '' || $todayYmd < $startYmd || ($endYmd !== '' && $todayYmd > $endYmd)) {
        return false;
    }

    $dayTimes = [
        'start_time' => trim((string) ($e['start_time'] ?? '')),
        'end_time' => !empty($e['end_time_na']) ? '' : trim((string) ($e['end_time'] ?? '')),
        'end_time_na' => !empty($e['end_time_na']),
        'schedule_date' => $todayYmd,
    ];
    return eventify_schedule_day_allows_checkin_now($dayTimes, $now);
}

/**
 * @param array<string, mixed> $day schedule_date, start_time, end_time, end_time_na
 */
function eventify_schedule_day_allows_checkin_now(array $day, DateTimeImmutable $now): bool
{
    $ymd = substr(trim((string) ($day['schedule_date'] ?? '')), 0, 10);
    if ($ymd === '' || $ymd !== $now->format('Y-m-d')) {
        return false;
    }
    $tz = $now->getTimezone();
    $start = trim((string) ($day['start_time'] ?? ''));
    $endNa = !empty($day['end_time_na']);
    $end = $endNa ? '' : trim((string) ($day['end_time'] ?? ''));
    if ($start === '' && $end === '') {
        return true;
    }
    if ($start !== '') {
        $st = eventify_calendar_datetime($ymd, $start, $tz);
        if ($st !== null && $now < $st) {
            return false;
        }
    }
    if ($end !== '') {
        $et = eventify_calendar_datetime($ymd, $end, $tz);
        if ($et !== null && $now > $et) {
            return false;
        }
    }
    return true;
}

function eventify_calendar_datetime(string $dayYmd, string $time, DateTimeZone $tz): ?DateTimeImmutable
{
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $dayYmd . ' ' . $time, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }
    return null;
}

/** Whether an event should appear in student "upcoming" lists (active and not yet ended). */
function eventify_event_is_upcoming(array $e, ?DateTimeInterface $now = null): bool
{
    $status = strtolower(trim((string) ($e['status'] ?? '')));
    if ($status !== 'active') {
        return false;
    }
    $tz = eventify_calendar_app_timezone();
    $now = $now instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
        : new DateTimeImmutable('now', $tz);
    $end = eventify_event_effective_end_datetime($e);
    if ($end instanceof DateTimeInterface) {
        $end = $end->setTimezone($tz);
        return $end >= $now;
    }
    $start = substr(trim((string) ($e['date'] ?? '')), 0, 10);
    return $start !== '' && $start >= $now->format('Y-m-d');
}

/** Alias: active and before effective end — tickets, RSVP, and QR check-in allowed. */
function eventify_event_is_live(array $e, ?DateTimeInterface $now = null): bool
{
    return eventify_event_is_upcoming($e, $now);
}

/**
 * Label + badge for organizer/student UI.
 *
 * @return array{label: string, badge: string, is_live: bool}
 */
function eventify_event_status_ui(array $e): array
{
    $st = strtolower(trim((string) ($e['status'] ?? '')));
    if ($st === 'active' && eventify_event_is_live($e)) {
        return ['label' => 'Active', 'badge' => 'success', 'is_live' => true];
    }
    if ($st === 'active') {
        return ['label' => 'Ended', 'badge' => 'warning', 'is_live' => false];
    }
    if (in_array($st, ['closed', 'completed'], true)) {
        return ['label' => 'Closed', 'badge' => 'secondary', 'is_live' => false];
    }
    if ($st === 'pending') {
        return ['label' => 'Pending', 'badge' => 'warning', 'is_live' => false];
    }
    if ($st === 'rejected') {
        return ['label' => 'Rejected', 'badge' => 'danger', 'is_live' => false];
    }
    return ['label' => $st !== '' ? ucfirst($st) : 'Unknown', 'badge' => 'secondary', 'is_live' => false];
}
