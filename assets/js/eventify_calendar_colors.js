/**
 * Calendar event block colors by approval + schedule state.
 * Keep in sync with views/partials/calendar_event_state_legend.php and calendar_legend.css
 */
(function (global) {
    'use strict';

    var COLORS = {
        pending: '#d97706',
        upcoming: '#f59e0b',
        active: '#16a34a',
        closed: '#6b7280',
        rejected: '#dc2626'
    };

    /**
     * @param {string} status Event row status (pending, active, etc.)
     * @param {Date|null} start Session start for this calendar block
     * @returns {'pending'|'upcoming'|'active'|'closed'|'rejected'}
     */
    function eventifyResolveCalendarEventState(status, start) {
        var s = String(status || '').toLowerCase();
        if (s === 'closed' || s === 'completed') {
            return 'closed';
        }
        if (s === 'rejected') {
            return 'rejected';
        }
        if (s === 'pending') {
            return 'pending';
        }
        if (start instanceof Date && !isNaN(start.getTime()) && start > new Date()) {
            return 'upcoming';
        }
        return 'active';
    }

    function eventifyApplyCalendarEventMount(info) {
        if (!info || !info.el) {
            return;
        }
        var props = info.event.extendedProps || {};
        var dept = props.department || 'ALL';
        info.el.setAttribute('data-dept', dept);
        var start = info.event.start instanceof Date ? info.event.start : null;
        var state = eventifyResolveCalendarEventState(props.status, start);
        info.el.setAttribute('data-event-state', state);
        var bg = COLORS[state] || COLORS.active;
        info.el.style.backgroundColor = bg;
        info.el.style.borderColor = bg;
        info.el.style.color = '#ffffff';
    }

    /**
     * Keep FullCalendar sized to its container so events stay in day cells while scrolling.
     * @param {import('fullcalendar').Calendar} calendar
     * @param {HTMLElement|null} containerEl usually .calendar-container
     */
    function eventifyBindCalendarScrollFix(calendar, containerEl) {
        if (!calendar || !containerEl) {
            return;
        }
        var refresh = function () {
            try {
                calendar.updateSize();
            } catch (e) { /* ignore */ }
        };
        requestAnimationFrame(refresh);
        setTimeout(refresh, 60);
        setTimeout(refresh, 280);
        if (!containerEl.dataset.eventifyResizeBound) {
            containerEl.dataset.eventifyResizeBound = '1';
            window.addEventListener('resize', refresh);
            if (typeof ResizeObserver !== 'undefined') {
                var ro = new ResizeObserver(refresh);
                ro.observe(containerEl);
            }
        }
    }

    /** Close FullCalendar "+more" day popovers so they do not sit above Bootstrap modals. */
    function eventifyCloseFullCalendarPopovers() {
        document.querySelectorAll('.fc-popover').forEach(function (el) {
            el.remove();
        });
    }

    function eventifyEscapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Compact week/day blocks — one line, no cramped side-by-side overlap text.
     * @returns {true|{html: string}} true = default FullCalendar render
     */
    function eventifyCalendarEventContent(arg) {
        var viewType = arg.view && arg.view.type ? arg.view.type : '';
        if (viewType.indexOf('timeGrid') !== 0) {
            return true;
        }
        var title = eventifyEscapeHtml(arg.event.title || 'Event');
        var timeText = arg.timeText ? eventifyEscapeHtml(arg.timeText) : '';
        var line = timeText ? timeText + ' · ' + title : title;
        return {
            html: '<div class="eventify-fc-compact" title="' + line + '">' + line + '</div>'
        };
    }

    global.eventifyResolveCalendarEventState = eventifyResolveCalendarEventState;
    global.eventifyApplyCalendarEventMount = eventifyApplyCalendarEventMount;
    global.eventifyBindCalendarScrollFix = eventifyBindCalendarScrollFix;
    global.eventifyCloseFullCalendarPopovers = eventifyCloseFullCalendarPopovers;
    global.eventifyCalendarEventContent = eventifyCalendarEventContent;
    global.eventifyCalendarEventColors = COLORS;
})(typeof window !== 'undefined' ? window : this);
