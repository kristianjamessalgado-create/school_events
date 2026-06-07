<?php
/** Calendar color guide — matches eventDidMount lifecycle colors in dashboard JS. */
$legendId = $legendId ?? 'calendarEventLegend';
$legendClass = trim(($legendClass ?? 'eventify-calendar-legend'));
$showMultiDayNote = !isset($showMultiDayNote) || $showMultiDayNote;
?>
<div class="<?= htmlspecialchars($legendClass) ?>" id="<?= htmlspecialchars($legendId) ?>" role="note" aria-label="Calendar color guide">
    <span class="eventify-calendar-legend__title">Color guide</span>
    <ul class="eventify-calendar-legend__list">
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--pending" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Orange</strong> — Pending approval (not live yet)</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--active" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Green</strong> — Active (approved and in progress)</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--upcoming" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Gold</strong> — Upcoming (approved; that day has not started)</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--closed" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Gray</strong> — Closed or completed</span>
        </li>
        <li>
            <span class="eventify-calendar-legend__swatch eventify-calendar-legend__swatch--rejected" aria-hidden="true"></span>
            <span class="eventify-calendar-legend__label"><strong>Red</strong> — Rejected</span>
        </li>
    </ul>
    <?php if ($showMultiDayNote): ?>
    <p class="eventify-calendar-legend__note">Pending events stay orange on every day until admin approves. After approval, multi-day events may use green or gold per day.</p>
    <?php endif; ?>
</div>
