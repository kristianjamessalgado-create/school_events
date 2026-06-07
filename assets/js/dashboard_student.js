// Global calendar instance
let calendar = null;
let currentDate = new Date();
let renderMiniCalendar = null; // Will be set by initMiniCalendar

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initStudentOpenModalFromUrl();
    initStudentSettings();
    initStudentCourseDepartmentDisplay();
    initMiniCalendar();
    initFullCalendar();
    initViewButtons();
    initTopCalendarShortcut();
    initCalendarNavigation();
    initMobileSidebar();
    initScanQRModal();
    initStudentUpcomingEventClicks();
    initUrgentFeedbackPrompt();
    initCancelRsvpConfirmModal();
    initRegisterRsvpConfirmModal();
    initStudentNotificationHooks();
    initStudentEventDetailsModalCleanup();
    initStudentMobileNav();
});

function jumpStudentCalendarToToday(options) {
    if (!calendar) return;
    var opts = options || {};
    calendar.today();
    const focus = calendar.getDate ? calendar.getDate() : new Date();
    currentDate = new Date(focus);
    selectedDate = new Date(focus);
    if (renderMiniCalendar) renderMiniCalendar();

    if (opts.syncActiveButton) {
        const viewButtons = document.querySelectorAll('.view-btn');
        viewButtons.forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-view') === 'today');
        });
    }
}

function initTopCalendarShortcut() {
    const topCalendarBtn = document.getElementById('topCalendarShortcutBtn');
    const controlsEl = document.querySelector('.calendar-controls');
    if (!topCalendarBtn) return;

    topCalendarBtn.addEventListener('click', function () {
        jumpStudentCalendarToToday({ syncActiveButton: true });
        if (controlsEl && typeof controlsEl.scrollIntoView === 'function') {
            controlsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}

function initStudentCourseDepartmentDisplay() {
    var courseEl = document.getElementById('studentCourseModal');
    var deptEl = document.getElementById('studentDepartmentModal');
    if (!courseEl || !deptEl) return;
    var map = window.__studentCourseDepartmentMap || {};
    var syncDept = function () {
        var course = String(courseEl.value || '');
        var dept = String(map[course] || '').trim();
        deptEl.value = dept || 'Department will be set from selected course';
    };
    courseEl.addEventListener('change', syncDept);
    syncDept();
}

function initStudentOpenModalFromUrl() {
    var openModal = String(window.__studentOpenModal || '').toLowerCase();
    if (!openModal || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }
    var targetId = null;
    if (openModal === 'change_password') {
        targetId = 'studentChangePasswordModal';
    } else if (openModal === 'settings') {
        targetId = 'settingsModal';
    } else if (openModal === 'scan') {
        targetId = 'scanQRModal';
    }
    if (!targetId) {
        return;
    }
    var modalEl = document.getElementById(targetId);
    if (!modalEl) {
        return;
    }
    setTimeout(function () {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }, 180);
}

function initStudentSettings() {
    var form = document.getElementById('studentSettingsForm');
    var updateBtn = document.getElementById('studentSettingsUpdateBtn');
    var confirmModalEl = document.getElementById('confirmStudentSettingsModal');
    var confirmYesBtn = document.getElementById('confirmStudentSettingsYesBtn');
    var settings = window.__studentSettings || {};
    var legend = document.getElementById('studentCalendarLegend');

    if (legend && Number(settings.show_calendar_legend || 0) !== 1) {
        legend.style.display = 'none';
    }

    if (!form || !updateBtn || !confirmModalEl || !confirmYesBtn || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }

    var confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
    updateBtn.addEventListener('click', function () {
        confirmModal.show();
    });
    confirmYesBtn.addEventListener('click', function () {
        confirmModal.hide();
        form.submit();
    });

    document.querySelectorAll('.password-toggle-btn[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target') || '';
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;
            var icon = btn.querySelector('i');
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            if (icon) {
                icon.classList.toggle('fa-eye', !isHidden);
                icon.classList.toggle('fa-eye-slash', isHidden);
            }
        });
    });

}

function initRegisterRsvpConfirmModal() {
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.classList.contains('js-register-rsvp-form')) return;
        e.preventDefault();
        var eventId = form.querySelector('input[name="event_id"]');
        eventId = eventId ? eventId.value : '';
        if (!eventId) return;

        function submitAjax() {
            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            postMainEventRsvpAjax(
                getStudentDashboardBase() + '/backend/auth/register_event_rsvp.php',
                eventId
            ).then(function (data) {
                if (submitBtn) submitBtn.disabled = false;
                handleMainEventRsvpResponse(data, eventId);
            }).catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                showStudentRsvpNotice('Could not register. Please try again.', 'fa-exclamation-circle');
            });
        }

        if (typeof showEdsConfirmModal === 'function') {
            showEdsConfirmModal({
                title: 'Confirm registration',
                message: 'Are you sure you want to register for this event?',
                confirmLabel: 'Yes, register',
                confirmClass: 'btn-primary',
                icon: 'fa-user-plus'
            }).then(function (ok) {
                if (ok) submitAjax();
            });
            return;
        }
        form.submit();
    });
}

function initCancelRsvpConfirmModal() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
    var modalEl = document.getElementById('cancelRsvpConfirmModal');
    var yesBtn = document.getElementById('cancelRsvpConfirmYesBtn');
    if (!modalEl || !yesBtn) return;

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var pendingForm = null;

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.classList.contains('js-cancel-rsvp-form')) return;
        e.preventDefault();
        pendingForm = form;
        modal.show();
    });

    yesBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        var form = pendingForm;
        pendingForm = null;
        modal.hide();
        var eventInput = form.querySelector('input[name="event_id"]');
        var eventId = eventInput ? eventInput.value : '';
        if (!eventId) return;
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        postMainEventRsvpAjax(
            getStudentDashboardBase() + '/backend/auth/cancel_event_rsvp.php',
            eventId
        ).then(function (data) {
            if (submitBtn) submitBtn.disabled = false;
            handleMainEventRsvpResponse(data, eventId);
        }).catch(function () {
            if (submitBtn) submitBtn.disabled = false;
            showStudentRsvpNotice('Could not cancel RSVP. Please try again.', 'fa-exclamation-circle');
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        pendingForm = null;
    });
}

function getStudentDashboardBase() {
    return (window.BASE_URL || '').replace(/\/$/, '');
}

function postMainEventRsvpAjax(url, eventId) {
    var body = new FormData();
    body.append('ajax', '1');
    body.append('csrf_token', window.csrfToken || '');
    body.append('event_id', String(eventId));
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) {
        return r.json();
    });
}

function patchStudentEventRegistration(eventId, patch) {
    var eid = String(eventId);
    (window.studentEvents || []).forEach(function (ev) {
        if (!ev) return;
        var props = ev.extendedProps || {};
        var id = String(props.event_id || String(ev.id || '').split('-')[0]);
        if (id === eid) {
            if (!ev.extendedProps) ev.extendedProps = {};
            Object.assign(ev.extendedProps, patch);
        }
    });
}

function refreshOpenStudentEventDetails(eventId) {
    var current = window.__studentEventDetailsCurrent;
    if (!current) return;
    var props = current.extendedProps || {};
    var id = String(props.event_id || String(current.id || '').split('-')[0]);
    if (id !== String(eventId)) return;
    var events = window.studentEvents || [];
    var updated = events.find(function (e) {
        var p = e.extendedProps || {};
        return String(p.event_id || String(e.id || '').split('-')[0]) === id;
    });
    if (updated) {
        showStudentEventDetails(updated, { contentOnly: true });
    }
}

function showStudentRsvpNotice(message, icon) {
    if (typeof showEdsMessageModal === 'function') {
        showEdsMessageModal(message, { title: 'Event RSVP', icon: icon || 'fa-info-circle' });
    }
}

function handleMainEventRsvpResponse(data, eventId) {
    if (!data) return;
    if (typeof data.is_registered === 'boolean' || data.registration_count != null) {
        patchStudentEventRegistration(eventId, {
            is_registered: !!data.is_registered,
            registration_count: parseInt(data.registration_count, 10) || 0
        });
    }
    if (data.ok) {
        refreshOpenStudentEventDetails(eventId);
        return;
    }
    if (data.is_registered) {
        refreshOpenStudentEventDetails(eventId);
    }
    showStudentRsvpNotice(data.message || 'Request failed.', 'fa-exclamation-circle');
}

function fetchStudentEventRsvpStatus(eventId) {
    var base = getStudentDashboardBase();
    return fetch(base + '/backend/auth/student_event_rsvp_status.php?event_id=' + encodeURIComponent(String(eventId)), {
        credentials: 'same-origin'
    }).then(function (r) {
        return r.json();
    });
}

function buildStudentEventFooterRsvpHtml(opts) {
    opts = opts || {};
    var eventId = opts.eventId;
    var csrf = opts.csrf || '';
    var base = opts.base || '';
    var isRegistered = !!opts.isRegistered;
    var isFull = !!opts.isFull;
    var allowsMainRsvp = opts.allowsMainRsvp !== false;

    if (!allowsMainRsvp) {
        if (isRegistered) {
            return '<span class="btn btn-success btn-sm disabled pe-none" style="opacity:1" aria-disabled="true">' +
                '<i class="fas fa-check-circle me-1"></i>RSVP confirmed</span>';
        }
        return '';
    }
    if (isRegistered) {
        var html = '<span class="btn btn-success btn-sm disabled pe-none me-1" style="opacity:1" aria-disabled="true">' +
            '<i class="fas fa-check-circle me-1"></i>RSVP confirmed</span>';
        if (eventId && csrf) {
            html += '<form method="post" action="' + escapeHtmlStudent(base + '/backend/auth/cancel_event_rsvp.php') + '" class="d-inline js-cancel-rsvp-form">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtmlStudent(csrf) + '">' +
                '<input type="hidden" name="event_id" value="' + escapeHtmlStudent(String(eventId)) + '">' +
                '<button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-user-minus me-1"></i>Cancel RSVP</button>' +
                '</form>';
        }
        return html;
    }
    if (isFull) {
        return '<span class="text-muted small">No spots available</span>';
    }
    if (eventId && csrf) {
        return '<form method="post" action="' + escapeHtmlStudent(base + '/backend/auth/register_event_rsvp.php') + '" class="d-inline js-register-rsvp-form">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtmlStudent(csrf) + '">' +
            '<input type="hidden" name="event_id" value="' + escapeHtmlStudent(String(eventId)) + '">' +
            '<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-user-plus me-1"></i>RSVP for this event</button>' +
            '</form>';
    }
    return '';
}

function initStudentEventDetailsModalCleanup() {
    var modalEl = document.getElementById('eventDetailsModal');
    if (!modalEl) return;
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (typeof edsForceModalCleanupIfIdle === 'function') {
            edsForceModalCleanupIfIdle();
        }
    });
}

function studentEventAllowsMainRsvp(props) {
    var regMode = String((props && props.registration_mode) || 'rsvp').toLowerCase();
    if (regMode === 'paid_ticket' || regMode === 'open') {
        return false;
    }
    if (props && props.event_allows_rsvp === false) {
        return false;
    }
    if (props && props.event_allows_rsvp === true) {
        return String((props.status) || '').toLowerCase() === 'active';
    }
    var st = String((props && props.status) || '').toLowerCase();
    if (st !== 'active') {
        return false;
    }
    var endYmd = String((props && props.event_end_ymd) || (props && props.event_date_ymd) || '').trim();
    var today = todayYmdLocal();
    if (endYmd !== '' && endYmd < today) {
        return false;
    }
    return true;
}

function closeStudentNotifDropdown() {
    var toggle = document.getElementById('studentNotifDropdownToggle');
    if (!toggle || typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
        return;
    }
    var dd = bootstrap.Dropdown.getInstance(toggle);
    if (dd) {
        dd.hide();
    }
}

function updateStudentNavbarNotifBadge(count) {
    var btn = document.getElementById('studentNotifDropdownToggle');
    if (!btn) return;
    var badge = btn.querySelector('.badge');
    var n = parseInt(count, 10) || 0;
    if (n < 1) {
        if (badge) badge.remove();
        return;
    }
    var label = n > 99 ? '99+' : String(n);
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
        badge.style.fontSize = '0.55rem';
        btn.appendChild(badge);
    }
    badge.textContent = label;
}

function initStudentNotificationHooks() {
    document.addEventListener('eventify:notif-read', function (e) {
        var detail = (e && e.detail) || {};
        if (typeof detail.unreadCount === 'number') {
            updateStudentNavbarNotifBadge(detail.unreadCount);
        }
        closeStudentNotifDropdown();
        if (detail.eventId) {
            setTimeout(function () {
                openStudentEventById(String(detail.eventId));
            }, 120);
        }
    });
}

function initStudentMobileNav() {
    document.querySelectorAll('.student-app-tabbar__btn[data-scroll-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sel = btn.getAttribute('data-scroll-target');
            if (!sel) return;
            var el = document.querySelector(sel);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

// ===============================
// SCAN QR FOR ATTENDANCE
// ===============================
function initScanQRModal() {
    const modalEl = document.getElementById('scanQRModal');
    const videoEl = document.getElementById('scanQRVideo');
    const canvasEl = document.getElementById('scanQRCanvas');
    const placeholderEl = document.getElementById('scanQRPlaceholder');
    const statusEl = document.getElementById('scanQRStatus');
    if (!modalEl || !videoEl || !canvasEl) return;

    let stream = null;
    let scanAnimationId = null;
    let startTimeoutId = null;
    let hasCameraStarted = false;
    let readyPollId = null;

    function clearStartTimeout() {
        if (startTimeoutId != null) {
            clearTimeout(startTimeoutId);
            startTimeoutId = null;
        }
    }

    function clearReadyPoll() {
        if (readyPollId != null) {
            clearInterval(readyPollId);
            readyPollId = null;
        }
    }

    function stopCamera() {
        clearStartTimeout();
        clearReadyPoll();
        hasCameraStarted = false;
        if (stream) {
            stream.getTracks().forEach(function(t) { t.stop(); });
            stream = null;
        }
        if (scanAnimationId != null) {
            cancelAnimationFrame(scanAnimationId);
            scanAnimationId = null;
        }
        if (videoEl.srcObject) {
            videoEl.srcObject = null;
        }
    }

    function parseCheckinFromQrUrl(urlString) {
        try {
            var url = new URL(urlString);
            var tk = url.searchParams.get('tk');
            if (tk) {
                return { type: 'ticket', token: tk };
            }
            var st = url.searchParams.get('st');
            if (st) {
                return { type: 'activity', token: st };
            }
            var t = url.searchParams.get('t') || url.searchParams.get('token');
            if (t) {
                return { type: 'main', token: t };
            }
        } catch (e) {
            /* ignore */
        }
        return null;
    }

    function tick() {
        if (!videoEl || !videoEl.srcObject || videoEl.readyState !== videoEl.HAVE_ENOUGH_DATA) {
            scanAnimationId = requestAnimationFrame(tick);
            return;
        }
        var w = videoEl.videoWidth;
        var h = videoEl.videoHeight;
        if (!w || !h) {
            scanAnimationId = requestAnimationFrame(tick);
            return;
        }
        canvasEl.width = w;
        canvasEl.height = h;
        var ctx = canvasEl.getContext('2d');
        ctx.drawImage(videoEl, 0, 0, w, h);
        var imageData = ctx.getImageData(0, 0, w, h);
        if (typeof jsQR !== 'undefined') {
            var code = jsQR(imageData.data, imageData.width, imageData.height);
            if (code && code.data) {
                var parsed = parseCheckinFromQrUrl(code.data);
                if (parsed && parsed.token) {
                    stopCamera();
                    var base = (window.BASE_URL || '').replace(/\/$/, '');
                    if (parsed.type === 'ticket') {
                        window.location.href = base + '/ticket_checkin.php?tk=' + encodeURIComponent(parsed.token);
                    } else if (parsed.type === 'activity') {
                        window.location.href = base + '/activity_checkin.php?st=' + encodeURIComponent(parsed.token);
                    } else {
                        window.location.href = base + '/checkin.php?t=' + encodeURIComponent(parsed.token);
                    }
                    return;
                }
            }
        }
        scanAnimationId = requestAnimationFrame(tick);
    }

    function getCameraStream(constraints) {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            return navigator.mediaDevices.getUserMedia(constraints);
        }
        var legacy = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
        if (legacy) {
            return new Promise(function(resolve, reject) {
                legacy.call(navigator, constraints, resolve, reject);
            });
        }
        return Promise.reject(new Error('Not supported'));
    }

    modalEl.addEventListener('shown.bs.modal', function() {
        placeholderEl.innerHTML = '<span><i class="fas fa-camera fa-2x mb-2 d-block"></i>Starting camera…</span>';
        placeholderEl.style.display = 'flex';
        videoEl.style.display = 'none';
        statusEl.textContent = 'Requesting camera permission...';
        clearStartTimeout();
        hasCameraStarted = false;
        startTimeoutId = setTimeout(function () {
            if (hasCameraStarted) return;
            statusEl.innerHTML = 'Camera is taking too long to start. Tap <strong>Close</strong>, allow camera permission, then try again. ' +
                'If your phone blocks this page camera, use your phone camera app to scan the QR.';
        }, 9000);
        var constraints = { video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } } };
        getCameraStream(constraints).then(function(mediaStream) {
            stream = mediaStream;
            videoEl.srcObject = stream;
            videoEl.setAttribute('playsinline', true);
            videoEl.setAttribute('autoplay', 'autoplay');
            // Desktop browsers can show live video while playback events are delayed.
            // Hide the startup overlay immediately once stream access is granted.
            placeholderEl.style.display = 'none';
            videoEl.style.display = 'block';
            statusEl.textContent = 'Position the event QR code within the frame.';
            var onReady = function () {
                if (hasCameraStarted) return;
                hasCameraStarted = true;
                clearStartTimeout();
                clearReadyPoll();
                placeholderEl.style.display = 'none';
                videoEl.style.display = 'block';
                statusEl.textContent = 'Position the event QR code within the frame.';
                tick();
            };
            videoEl.onloadedmetadata = onReady;
            videoEl.onloadeddata = onReady;
            videoEl.oncanplay = onReady;
            videoEl.onplaying = onReady;
            clearReadyPoll();
            readyPollId = setInterval(function () {
                if (!videoEl) return;
                if (videoEl.readyState >= 2 && videoEl.videoWidth > 0 && videoEl.videoHeight > 0) {
                    onReady();
                }
            }, 220);
            videoEl.play().then(onReady).catch(function() {
                clearStartTimeout();
                clearReadyPoll();
                statusEl.textContent = 'Could not start video.';
                placeholderEl.style.display = 'none';
            });
        }).catch(function(err) {
            clearStartTimeout();
            clearReadyPoll();
            placeholderEl.innerHTML = '<span><i class="fas fa-video-slash fa-2x mb-2 d-block"></i>Camera not available here</span>';
            placeholderEl.style.display = 'flex';
            statusEl.innerHTML = 'Camera access needs <strong>HTTPS</strong> or is blocked in this browser. <br class="d-none d-md-inline">' +
                '<strong>Workaround:</strong> Open your phone’s <strong>Camera</strong> or <strong>QR scanner</strong> app, scan the event QR code, then open the link to check in.';
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function() {
        stopCamera();
        placeholderEl.style.display = 'flex';
        placeholderEl.innerHTML = '<span><i class="fas fa-camera fa-2x mb-2 d-block"></i>Starting camera…</span>';
        statusEl.textContent = 'Position the event QR code within the frame.';
    });
}

// ===============================
// MOBILE SIDEBAR DRAWER
// ===============================
function initMobileSidebar() {
    const toggle = document.getElementById('sidebarToggleMobile');
    const closeBtn = document.getElementById('sidebarCloseMobile');
    const backdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.getElementById('studentSidebar');
    const isMobileView = () => window.matchMedia('(max-width: 768px)').matches;
    const refreshCalendarLayout = () => {
        if (!calendar) return;
        if (typeof calendar.updateSize === 'function') {
            calendar.updateSize();
        }
    };
    const refreshCalendarLayoutSmooth = () => {
        if (!calendar) return;
        // Recompute during and after transition so grid stays fluid.
        [0, 90, 180, 280, 360].forEach(function (ms) {
            setTimeout(refreshCalendarLayout, ms);
        });
    };

    function openSidebar() {
        document.body.classList.add('student-sidebar-open');
    }

    function closeSidebar() {
        document.body.classList.remove('student-sidebar-open');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobileView()) {
                document.body.classList.toggle('student-sidebar-open');
                return;
            }
            // Desktop: use the same icon to collapse/expand sidebar.
            document.body.classList.toggle('student-sidebar-collapsed');
            refreshCalendarLayoutSmooth();
        });
    }
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // Close drawer when a quick action or modal trigger is clicked
    if (sidebar) {
        sidebar.addEventListener('transitionend', function (e) {
            if (e.propertyName === 'width' || e.propertyName === 'padding-left' || e.propertyName === 'padding-right') {
                refreshCalendarLayout();
            }
        });
        sidebar.addEventListener('click', function(e) {
            var target = e.target.closest('.action-btn, .logout-btn, [data-bs-toggle="modal"]');
            if (target && isMobileView()) {
                closeSidebar();
            }
        });
    }

    window.addEventListener('resize', function () {
        if (!isMobileView()) {
            // Ensure mobile drawer state does not leak to desktop.
            closeSidebar();
        }
        refreshCalendarLayoutSmooth();
    });
}

// ===============================
// MINI CALENDAR
// ===============================
function initMiniCalendar() {
    const miniCalEl = document.getElementById('miniCalendar');
    const monthEl = document.getElementById('miniCalMonth');
    const prevBtn = document.getElementById('miniCalPrev');
    const nextBtn = document.getElementById('miniCalNext');

    if (!miniCalEl || !monthEl) return;

    renderMiniCalendar = function() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        // Update month display
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        monthEl.textContent = `${monthNames[month]} ${year}`;

        // Clear previous content
        miniCalEl.innerHTML = '';

        // Day headers
        const dayHeaders = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.className = 'mini-cal-day-header';
            header.textContent = day;
            miniCalEl.appendChild(header);
        });

        // Get first day of month and number of days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();

        // Previous month days
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = startingDayOfWeek - 1; i >= 0; i--) {
            const day = prevMonthLastDay - i;
            const dayEl = document.createElement('div');
            dayEl.className = 'mini-cal-day other-month';
            dayEl.textContent = day;
            miniCalEl.appendChild(dayEl);
        }

        // Current month days
        const today = new Date();
        for (let day = 1; day <= daysInMonth; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'mini-cal-day';
            dayEl.textContent = day;

            // Highlight today only
            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayEl.classList.add('today');
            }

            miniCalEl.appendChild(dayEl);
        }

        // Next month days
        const totalCells = 42; // 6 rows × 7 days
        const remainingCells = totalCells - (startingDayOfWeek + daysInMonth);
        for (let day = 1; day <= remainingCells; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'mini-cal-day other-month';
            dayEl.textContent = day;
            miniCalEl.appendChild(dayEl);
        }
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            if (calendar) {
                calendar.prev();
                // Sync with calendar focus date
                const focus = calendar.getDate ? calendar.getDate() : new Date();
                currentDate = new Date(focus);
                selectedDate = new Date(focus);
            }
            renderMiniCalendar();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            if (calendar) {
                calendar.next();
                // Sync with calendar focus date
                const focus = calendar.getDate ? calendar.getDate() : new Date();
                currentDate = new Date(focus);
                selectedDate = new Date(focus);
            }
            renderMiniCalendar();
        });
    }

    // Initial render
    renderMiniCalendar();
}

// ===============================
// FULLCALENDAR INITIALIZATION
// ===============================
function getStudentCalendarHeight() {
    var container = document.querySelector('.main-content .calendar-container');
    if (!container) {
        return 520;
    }
    var h = container.clientHeight;
    if (h < 200) {
        h = container.getBoundingClientRect().height;
    }
    return Math.max(360, Math.min(h || 520, 720));
}

function initFullCalendar() {
    const calendarEl = document.getElementById('student-calendar');
    if (!calendarEl) return;

    var settings = window.__studentSettings || {};
    var allowedViews = ['dayGridMonth', 'timeGridWeek', 'timeGridDay'];
    var defaultView = allowedViews.indexOf(String(settings.default_calendar_view || '')) !== -1
        ? String(settings.default_calendar_view)
        : 'dayGridMonth';

    var rawStudentEvents = Array.isArray(window.studentEvents) ? window.studentEvents : [];
    var studentCalendarEvents = rawStudentEvents.filter(function (ev) {
        var props = ev.extendedProps || {};
        var st = String(props.status != null ? props.status : (ev.status || '')).toLowerCase().trim();
        return st !== 'rejected';
    });

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: defaultView,
        initialDate: currentDate,
        selectable: false, // Students can't create events
        dayMaxEvents: true,
        headerToolbar: false, // We use custom controls
        events: studentCalendarEvents,
        eventDisplay: 'block',
        height: getStudentCalendarHeight(),
        expandRows: true,
        dayHeaderFormat: window.matchMedia('(max-width: 768px)').matches ? { weekday: 'short' } : { weekday: 'long' },
        firstDay: 0,
        weekends: true,
        nowIndicator: true,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
        },

        // Click event -> show details in modal (read-only for students)
        eventClick: function(info) {
            showStudentEventDetails(info.event);
            info.jsEvent.preventDefault();
        },

        eventDidMount: function (info) {
            if (typeof eventifyApplyCalendarEventMount === 'function') {
                eventifyApplyCalendarEventMount(info);
            }
        },

        // Update title when view changes and sync mini calendar
        datesSet: function(info) {
            updateCalendarTitle(info);
            // Use the calendar focus date, not the visible-range start.
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
            // Update mini calendar to match main calendar focus date
            if (renderMiniCalendar) {
                renderMiniCalendar();
            }
            requestAnimationFrame(function () {
                try { calendar.updateSize(); } catch (e) { /* ignore */ }
            });
        }
    });

    calendar.render();

    var calContainer = calendarEl.closest('.calendar-container');
    function syncStudentCalendarHeight() {
        if (!calendar) return;
        var h = getStudentCalendarHeight();
        try {
            calendar.setOption('height', h);
            calendar.updateSize();
        } catch (e) { /* ignore */ }
    }
    requestAnimationFrame(syncStudentCalendarHeight);
    setTimeout(syncStudentCalendarHeight, 80);
    setTimeout(syncStudentCalendarHeight, 320);
    if (typeof eventifyBindCalendarScrollFix === 'function') {
        eventifyBindCalendarScrollFix(calendar, calContainer);
    }
    window.addEventListener('resize', syncStudentCalendarHeight);

    // On resize (e.g. rotate phone), switch day headers between short (mobile) and long (desktop)
    window.addEventListener('resize', function() {
        if (!calendar) return;
        var isMobile = window.matchMedia('(max-width: 768px)').matches;
        calendar.setOption('dayHeaderFormat', isMobile ? { weekday: 'short' } : { weekday: 'long' });
    });

    // Force initial sync (removes the hardcoded placeholder month in sidebar)
    const focus = calendar.getDate ? calendar.getDate() : new Date();
    currentDate = new Date(focus);
    selectedDate = new Date(focus);
    if (renderMiniCalendar) renderMiniCalendar();
}

function studentFormatDeptLabel(stored) {
    const d = String(stored || 'ALL').trim();
    if (d === '' || d === 'ALL') return 'All Departments';
    if (d.charAt(0) === '[') {
        try {
            const arr = JSON.parse(d);
            if (Array.isArray(arr) && arr.length) return arr.join(' · ');
        } catch (e) { /* ignore */ }
    }
    return d;
}

function eventifyFormatDayEndTimeLabel(endTime, endTimeNa, tOpts) {
    if (endTimeNa) {
        return 'N/A';
    }
    if (!endTime) {
        return '';
    }
    const et = new Date('1970-01-01T' + String(endTime).slice(0, 8));
    if (isNaN(et.getTime())) {
        return '';
    }
    return et.toLocaleTimeString(undefined, tOpts);
}

function eventifyAppendPerDayEndTimes(dateStr, props, tOpts) {
    const days = Array.isArray(props.schedule_days) ? props.schedule_days : [];
    if (days.length < 2) {
        return dateStr;
    }
    const parts = [];
    let hasPerDayStart = false;
    days.forEach(function (day) {
        const ymd = String(day.schedule_date || '').slice(0, 10);
        if (!ymd) {
            return;
        }
        const d = new Date(ymd + 'T12:00:00');
        const lbl = isNaN(d.getTime()) ? ymd : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        const startLbl = day.start_time
            ? eventifyFormatDayEndTimeLabel(day.start_time, false, tOpts)
            : '';
        if (startLbl) {
            hasPerDayStart = true;
        }
        let segment = lbl;
        if (startLbl) {
            segment += ' ' + startLbl;
        }
        if (!day.end_time_na && day.end_time) {
            const endLbl = eventifyFormatDayEndTimeLabel(day.end_time, false, tOpts);
            if (endLbl) {
                segment += (startLbl ? '–' : ', ends ') + endLbl;
            }
        }
        parts.push(segment);
    });
    if (parts.length) {
        let out = dateStr + ' · ' + parts.join('; ');
        const allEndNa = days.every(function (day) {
            return !!day.end_time_na;
        });
        if (allEndNa) {
            out += ' · Ends N/A';
        }
        return out;
    }
    return dateStr;
}

// ===============================
// STUDENT EVENT DETAILS (SHARED)
// ===============================
function showStudentEventDetails(eventLike, options) {
    if (!eventLike) return;
    options = options || {};
    window.__studentEventDetailsCurrent = eventLike;
    const props = eventLike.extendedProps || {};
    const deptText = String(props.department_display || '').trim() || studentFormatDeptLabel(props.department);
    let startDate = null;
    let endDate = null;

    // eventLike may be a FullCalendar Event or a plain object from window.studentEvents
    if (eventLike.start instanceof Date) {
        startDate = eventLike.start;
        endDate = eventLike.end instanceof Date ? eventLike.end : null;
    } else if (eventLike.start) {
        const s = new Date(eventLike.start);
        if (!isNaN(s.getTime())) {
            startDate = s;
        }
        if (eventLike.end) {
            const e = new Date(eventLike.end);
            if (!isNaN(e.getTime())) {
                endDate = e;
            }
        }
    }

    let dateStr = '';
    const startYmd = String(props.event_date_ymd || '').trim();
    const endYmd = String(props.event_end_ymd || props.event_date_ymd || '').trim();
    const scheduleDates = Array.isArray(props.schedule_dates) ? props.schedule_dates.filter(Boolean) : [];
    const dOpts = { year: 'numeric', month: 'long', day: 'numeric' };
    const tOpts = { hour: 'numeric', minute: '2-digit', hour12: true };
    if (scheduleDates.length > 1) {
        dateStr = scheduleDates.map(function (ymd) {
            const d = new Date(ymd + 'T12:00:00');
            return isNaN(d.getTime()) ? ymd : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        }).join(', ');
        const y = scheduleDates[0].slice(0, 4);
        if (scheduleDates.every(function (d) { return d.slice(0, 4) === y; })) {
            dateStr += ', ' + y;
        }
        const hasPerDayStartInProps = Array.isArray(props.schedule_days) && props.schedule_days.some(function (d) {
            return d && String(d.start_time || '').trim() !== '';
        });
        if (props.start_time && !hasPerDayStartInProps) {
            const st = new Date('1970-01-01T' + String(props.start_time).slice(0, 8));
            if (!isNaN(st.getTime())) {
                dateStr += ' · Starts ' + st.toLocaleTimeString(undefined, tOpts);
            }
        }
        if (Array.isArray(props.schedule_days) && props.schedule_days.length >= 2) {
            dateStr = eventifyAppendPerDayEndTimes(dateStr, props, tOpts);
        } else if (props.end_time_na) {
            dateStr += ' · Ends N/A';
        } else if (props.end_time) {
            const et = new Date('1970-01-01T' + String(props.end_time).slice(0, 8));
            if (!isNaN(et.getTime())) {
                dateStr += ' · Ends ' + et.toLocaleTimeString(undefined, tOpts);
            }
        }
    } else if (startYmd && endYmd && endYmd > startYmd) {
        const startD = new Date(startYmd + 'T12:00:00');
        const endD = new Date(endYmd + 'T12:00:00');
        dateStr = startD.toLocaleDateString(undefined, dOpts) + ' – ' + endD.toLocaleDateString(undefined, dOpts);
        const hasPerDayStartRange = Array.isArray(props.schedule_days) && props.schedule_days.some(function (d) {
            return d && String(d.start_time || '').trim() !== '';
        });
        if (props.start_time && !hasPerDayStartRange) {
            const st = new Date('1970-01-01T' + String(props.start_time).slice(0, 8));
            if (!isNaN(st.getTime())) {
                dateStr += ' · Starts ' + st.toLocaleTimeString(undefined, tOpts);
            }
        }
        if (Array.isArray(props.schedule_days) && props.schedule_days.length >= 2) {
            dateStr = eventifyAppendPerDayEndTimes(dateStr, props, tOpts);
        } else if (props.end_time_na) {
            dateStr += ' · Ends N/A';
        } else if (props.end_time) {
            const et = new Date('1970-01-01T' + String(props.end_time).slice(0, 8));
            if (!isNaN(et.getTime())) {
                dateStr += ' · Ends ' + et.toLocaleTimeString(undefined, tOpts);
            }
        }
    } else if (startDate) {
        dateStr = startDate.toLocaleDateString(undefined, dOpts);
        const startTime = startDate.toLocaleTimeString(undefined, tOpts);
        let range = startTime;
        if (endDate && !eventLike.allDay) {
            const endTime = endDate.toLocaleTimeString(undefined, tOpts);
            range = startTime + ' – ' + endTime;
        }
        if (!eventLike.allDay) {
            dateStr += ' · ' + range;
        }
    }

    const eventYmd = endYmd || startYmd || '';
    const todayY = todayYmdLocal();
    const isPast = eventYmd !== '' && eventYmd < todayY;
    const statusLower = String(props.status || '').toLowerCase();
    const endedByOrganizer = statusLower === 'closed' || statusLower === 'completed';
    const isEndedForFeedback = isPast || endedByOrganizer;
    const allowsMainRsvp = studentEventAllowsMainRsvp(props);

    const maxCap = props.max_capacity != null && props.max_capacity !== '' ? parseInt(props.max_capacity, 10) : null;
    const regCount = parseInt(props.registration_count, 10) || 0;
    const isRegistered = !!props.is_registered;
    const hasFeedback = !!props.has_feedback;
    const attended = !!props.attended;
    let eventId = props.event_id || eventLike.id;
    if (eventId && String(eventId).indexOf('-') > 0) {
        eventId = String(eventId).split('-')[0];
    }
    const csrf = window.csrfToken || '';
    const base = (window.BASE_URL || '').replace(/\/$/, '');

    let capacityHtml = '';
    if (maxCap != null && !isNaN(maxCap) && maxCap > 0) {
        capacityHtml = '<p class="mb-2"><strong>RSVPs:</strong> ' + regCount + ' / ' + maxCap + '</p>';
    } else {
        capacityHtml = '<p class="mb-2"><strong>RSVPs:</strong> ' + regCount + ' registered (no cap)</p>';
    }

    let actionHtml = '';
    let footerRsvpHtml = '';
    const isFull = maxCap != null && !isNaN(maxCap) && maxCap > 0 && regCount >= maxCap;
    const registrationMode = String(props.registration_mode || 'rsvp').toLowerCase();
    const isPaidTicketEvent = registrationMode === 'paid_ticket';
    const eventIsLive = props.event_is_live === true;

    if (isPaidTicketEvent && !isEndedForFeedback && eventIsLive) {
        capacityHtml = '<p class="mb-2"><strong>Entry:</strong> Ticket required (paid event)</p>';
        actionHtml = '<p class="mb-2 small text-muted">Purchase a ticket to receive your digital pass with QR for venue entry.</p>';
        if (eventId) {
            footerRsvpHtml = '<a class="btn btn-success btn-sm" href="' + escapeHtmlStudent(base + '/event_tickets.php?event_id=' + encodeURIComponent(String(eventId))) + '">' +
                '<i class="fas fa-ticket-alt me-1"></i>Buy tickets</a>' +
                ' <a class="btn btn-outline-primary btn-sm" href="' + escapeHtmlStudent(base + '/my_tickets.php') + '">My tickets</a>';
        }
    } else if (isPaidTicketEvent && !eventIsLive) {
        capacityHtml = '<p class="mb-2"><strong>Entry:</strong> Ticket sales closed</p>';
        actionHtml = '<p class="mb-2 small text-muted">This event has ended. If you already bought a ticket, open <strong>My tickets</strong> for your digital pass.</p>';
        footerRsvpHtml = '<a class="btn btn-outline-primary btn-sm" href="' + escapeHtmlStudent(base + '/my_tickets.php') + '"><i class="fas fa-ticket-alt me-1"></i>My tickets</a>';
    } else if (allowsMainRsvp) {
        if (isRegistered) {
            actionHtml = '<p class="mb-2 text-success small"><i class="fas fa-check-circle me-1"></i>Your RSVP for this event is confirmed.</p>';
        } else if (isFull) {
            actionHtml = '<p class="mb-2 text-warning small mb-0">This event is full.</p>';
        } else if (eventId && csrf) {
            actionHtml = '<p class="mb-2 small text-muted">Register for the main event below. Activity RSVPs are separate.</p>';
        } else {
            actionHtml = '<p class="mb-0 small text-muted">RSVP unavailable — refresh the page and try again.</p>';
        }
        footerRsvpHtml = buildStudentEventFooterRsvpHtml({
            eventId: eventId,
            csrf: csrf,
            base: base,
            isRegistered: isRegistered,
            isFull: isFull,
            allowsMainRsvp: true
        });
    } else if (!allowsMainRsvp && isRegistered) {
        actionHtml = '<p class="mb-2 text-success small"><i class="fas fa-check-circle me-1"></i>Your RSVP for this event is confirmed.</p>';
        footerRsvpHtml = buildStudentEventFooterRsvpHtml({
            eventId: eventId,
            csrf: csrf,
            base: base,
            isRegistered: true,
            allowsMainRsvp: false
        });
    } else if (attended) {
        if (!hasFeedback && eventId && csrf) {
            var fbRad = 'fbvis_' + String(eventId).replace(/\W/g, '');
            actionHtml = '<hr class="my-3">' +
                '<h6 class="small text-uppercase text-muted mb-2">Post-event feedback</h6>' +
                '<p class="small text-muted mb-2">You checked in to this event. Choose whether the organizer and admin see your <strong>name</strong> with your rating and comment.</p>' +
                '<form method="post" action="' + escapeHtmlStudent(base + '/backend/auth/submit_event_feedback.php') + '">' +
                '<input type="hidden" name="csrf_token" value="' + escapeHtmlStudent(csrf) + '">' +
                '<input type="hidden" name="event_id" value="' + escapeHtmlStudent(String(eventId)) + '">' +
                '<div class="mb-2">' +
                '<label class="form-label small">Rating (1–5)</label>' +
                '<select name="rating" class="form-select form-select-sm" required>' +
                '<option value="">Choose…</option>' +
                '<option value="5">5 – Excellent</option>' +
                '<option value="4">4</option>' +
                '<option value="3">3</option>' +
                '<option value="2">2</option>' +
                '<option value="1">1 – Poor</option>' +
                '</select></div>' +
                '<div class="mb-2">' +
                '<label class="form-label small">Comments (optional)</label>' +
                '<textarea name="comment" class="form-control form-control-sm" rows="3" maxlength="2000" placeholder="How was the event? Suggestions?"></textarea>' +
                '</div>' +
                '<div class="mb-2">' +
                '<span class="form-label small d-block">Visibility</span>' +
                '<div class="form-check">' +
                '<input class="form-check-input" type="radio" name="feedback_visibility" id="' + fbRad + '_anon" value="anonymous" checked>' +
                '<label class="form-check-label small" for="' + fbRad + '_anon">Anonymous — name hidden from organizer and admin</label>' +
                '</div>' +
                '<div class="form-check">' +
                '<input class="form-check-input" type="radio" name="feedback_visibility" id="' + fbRad + '_named" value="named">' +
                '<label class="form-check-label small" for="' + fbRad + '_named">Show my name — organizer and admin may see my name with this feedback</label>' +
                '</div></div>' +
                '<button type="submit" class="btn btn-outline-primary btn-sm">Submit feedback</button>' +
                '</form>';
        } else if (hasFeedback) {
            actionHtml = '<p class="mb-0 small text-muted mt-2"><i class="fas fa-check me-1"></i>Thanks — you already submitted feedback for this event.</p>';
        }
    } else {
        actionHtml = '<p class="mb-0 small text-muted">This event is finished or was marked ended by the organizer. <strong>Post-event feedback</strong> is only available if you attended using <strong>QR check-in</strong>.</p>';
    }

    const title = eventLike.title || 'Untitled';
    const bodyEl = document.getElementById('eventDetailsModalBody');
    if (bodyEl) {
        bodyEl.innerHTML = '<p class="mb-2"><strong>Event:</strong> ' + escapeHtmlStudent(title) + '</p>' +
            '<p class="mb-2"><strong>Date &amp; Time:</strong> ' + escapeHtmlStudent(dateStr || 'TBA') + '</p>' +
            '<p class="mb-2"><strong>Location:</strong> ' + escapeHtmlStudent(props.location || 'N/A') + '</p>' +
            '<p class="mb-2"><strong>Department:</strong> ' + escapeHtmlStudent(deptText) + '</p>' +
            capacityHtml +
            '<p class="mb-2"><strong>Description:</strong> ' + escapeHtmlStudent(props.description || 'No description provided.') + '</p>' +
            (eventId ? '<p class="mb-2"><a class="btn btn-sm btn-outline-success" href="' + escapeHtmlStudent(base + '/event_activities.php?id=' + encodeURIComponent(String(eventId))) + '" target="_blank" rel="noopener"><i class="fas fa-th-large me-1"></i>Browse activities</a></p>' : '') +
            actionHtml;
    }
    var footerRsvpEl = document.getElementById('studentEventDetailsRsvpActions');
    if (footerRsvpEl) {
        footerRsvpEl.innerHTML = footerRsvpHtml;
    }
    if (eventId && !isPaidTicketEvent && (allowsMainRsvp || !isRegistered)) {
        fetchStudentEventRsvpStatus(eventId).then(function (data) {
            if (!data || !data.ok) return;
            var regChanged = !!data.is_registered !== isRegistered;
            var countChanged = (parseInt(data.registration_count, 10) || 0) !== regCount;
            if (!regChanged && !countChanged) return;
            patchStudentEventRegistration(eventId, {
                is_registered: !!data.is_registered,
                registration_count: parseInt(data.registration_count, 10) || 0
            });
            var current = window.__studentEventDetailsCurrent;
            if (!current) return;
            var curProps = current.extendedProps || {};
            var curId = String(curProps.event_id || String(current.id || '').split('-')[0]);
            if (curId !== String(eventId)) return;
            showStudentEventDetails(current, { contentOnly: true });
        }).catch(function () { /* ignore */ });
    }
    if (typeof eventifyAppendStudentDaySessions === 'function' && bodyEl) {
        eventifyAppendStudentDaySessions(bodyEl, eventLike);
    }
    const modalEl = document.getElementById('eventDetailsModal');
    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        if (!options.contentOnly && !modalEl.classList.contains('show')) {
            if (typeof eventifyCloseFullCalendarPopovers === 'function') {
                eventifyCloseFullCalendarPopovers();
            }
            if (typeof edsForceHideHelperModals === 'function') {
                edsForceHideHelperModals();
            }
            var existingModal = bootstrap.Modal.getInstance(modalEl);
            if (existingModal) {
                existingModal.dispose();
            }
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }
}

function initStudentUpcomingEventClicks() {
    const links = document.querySelectorAll('.student-event-link[data-event-id], .js-student-open-event[data-event-id]');
    if (!links.length || !window.studentEvents) return;

    links.forEach(function(el) {
        el.addEventListener('click', function() {
            const id = this.getAttribute('data-event-id');
            if (!id) return;
            openStudentEventById(id);
        });
        el.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (e.key === ' ') e.preventDefault();
            const id = this.getAttribute('data-event-id');
            if (!id) return;
            openStudentEventById(id);
        });
    });
}

function openStudentEventById(id) {
    if (!id || !window.studentEvents) return;
    const events = Array.isArray(window.studentEvents) ? window.studentEvents : [];
    const match = events.find(function(e) {
        return String(e.id) === String(id) || String((e.extendedProps && e.extendedProps.event_id) || '') === String(id);
    });
    if (!match) {
        var byPrefix = events.find(function(e) {
            return String(e.id || '').indexOf(String(id) + '-') === 0;
        });
        if (byPrefix) {
            showStudentEventDetails(byPrefix);
            return;
        }
    }
    if (match) {
        showStudentEventDetails(match);
    }
}

function studentEventFromUrgentPayload(ev) {
    if (!ev || ev.id == null) return null;
    var ymd = String(ev.date || '').trim();
    return {
        id: ev.id,
        title: ev.title || 'Event',
        start: ymd ? new Date(ymd + 'T12:00:00') : null,
        extendedProps: {
            event_id: ev.id,
            event_date_ymd: ymd,
            location: '',
            description: 'Open your calendar for full details.',
            department: 'ALL',
            department_display: '',
            max_capacity: null,
            registration_count: 0,
            is_registered: false,
            has_feedback: false,
            attended: true,
            status: String(ev.status || '')
        }
    };
}

function initUrgentFeedbackPrompt() {
    var openModal = String(window.__studentOpenModal || '').toLowerCase();
    if (openModal === 'change_password' || openModal === 'settings' || openModal === 'scan') {
        return;
    }
    var list = window.__studentPendingUrgentFeedback;
    if (!Array.isArray(list) || !list.length) return;
    try {
        var until = parseInt(sessionStorage.getItem('eventify_urgent_feedback_snooze_until') || '0', 10);
        if (until && Date.now() < until) return;
    } catch (e) { /* ignore */ }

    var modalEl = document.getElementById('studentUrgentFeedbackModal');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;

    var body = document.getElementById('studentUrgentFeedbackModalBody');
    if (body) {
        var html = '<p class="mb-3 fw-semibold">You attended the event(s) below. Please share feedback while it is still fresh — you can choose anonymous or named when you submit.</p>' +
            '<ul class="list-group list-group-flush">';
        list.forEach(function (ev) {
            var dateLine = '';
            if (ev.date) {
                try {
                    var d = new Date(ev.date + 'T12:00:00');
                    if (!isNaN(d.getTime())) {
                        dateLine = d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                } catch (e2) { dateLine = String(ev.date); }
            }
            html += '<li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 px-0">' +
                '<span><strong>' + escapeHtmlStudent(String(ev.title || 'Event')) + '</strong>' +
                (dateLine ? '<br><span class="small text-muted">' + escapeHtmlStudent(dateLine) + '</span>' : '') +
                '</span>' +
                '<button type="button" class="btn btn-primary btn-sm urgent-fb-open" data-event-id="' + escapeHtmlStudent(String(ev.id)) + '">' +
                '<i class="fas fa-comment-dots me-1"></i>Give feedback</button></li>';
        });
        html += '</ul>';
        body.innerHTML = html;
        body.querySelectorAll('.urgent-fb-open').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-event-id');
                var urgent = Array.isArray(window.__studentPendingUrgentFeedback) ? window.__studentPendingUrgentFeedback : [];
                var payload = urgent.find(function (x) { return String(x.id) === String(id); });
                var events = Array.isArray(window.studentEvents) ? window.studentEvents : [];
                var match = events.find(function (e) { return String(e.id) === String(id); });
                var toShow = match || studentEventFromUrgentPayload(payload);
                var inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
                if (toShow && typeof showStudentEventDetails === 'function') {
                    setTimeout(function () { showStudentEventDetails(toShow); }, 320);
                }
            });
        });
    }

    var snoozeBtn = document.getElementById('studentUrgentFeedbackSnoozeBtn');
    if (snoozeBtn) {
        snoozeBtn.onclick = function () {
            try {
                sessionStorage.setItem('eventify_urgent_feedback_snooze_until', String(Date.now() + 4 * 60 * 60 * 1000));
            } catch (e3) { /* ignore */ }
            var m = bootstrap.Modal.getInstance(modalEl);
            if (m) m.hide();
        };
    }

    setTimeout(function () {
        try {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            setTimeout(function () {
                var backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.style.zIndex = '1199';
                modalEl.style.zIndex = '1200';
            }, 10);
        } catch (e4) { /* ignore */ }
    }, 650);
}

// ===============================
// CALENDAR TITLE UPDATE
// ===============================
function updateCalendarTitle(info) {
    const titleEl = document.getElementById('calendarTitle');
    if (!titleEl || !calendar) return;

    // Always use FullCalendar's own computed title (prevents wrong month)
    titleEl.textContent = calendar.view?.title || '';
}

// ===============================
// VIEW BUTTONS
// ===============================
function initViewButtons() {
    const viewButtons = document.querySelectorAll('.view-btn');
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            
            // Remove active from all
            viewButtons.forEach(b => b.classList.remove('active'));
            
            // Add active to clicked
            this.classList.add('active');
            
            // Handle "Today" button
            if (view === 'today') {
                jumpStudentCalendarToToday({ syncActiveButton: false });
            } else {
                // Change view
                calendar.changeView(view);
            }
        });
    });
}

// ===============================
// CALENDAR NAVIGATION
// ===============================
function initCalendarNavigation() {
    const prevBtn = document.getElementById('calPrev');
    const nextBtn = document.getElementById('calNext');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            calendar.prev();
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
            // Update mini calendar
            if (renderMiniCalendar) {
                renderMiniCalendar();
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            calendar.next();
            const focus = calendar.getDate ? calendar.getDate() : new Date();
            currentDate = new Date(focus);
            selectedDate = new Date(focus);
            // Update mini calendar
            if (renderMiniCalendar) {
                renderMiniCalendar();
            }
        });
    }
}

// ===============================
// PROFILE MODAL FUNCTIONS
// ===============================
function openProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('profileModal');
    if (event.target === modal) {
        closeProfileModal();
    }
});

// Get BASE_URL from window or set default
const BASE_URL = window.BASE_URL || '/school_events';

function escapeHtmlStudent(s) {
    if (s == null || s === undefined) {
        return '';
    }
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function todayYmdLocal() {
    var d = new Date();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + m + '-' + day;
}
