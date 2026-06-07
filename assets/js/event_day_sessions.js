/**
 * Day activities (sub-events) within a parent event — organizer manage + display.
 */
(function (global) {
    'use strict';

    function baseUrl() {
        return global.BASE_URL || '/school_events';
    }

    function csrfToken() {
        return global.csrfToken || '';
    }

    function parseClickedScheduleDate(event) {
        var props = event.extendedProps || {};
        if (props.schedule_date_ymd) {
            return String(props.schedule_date_ymd).slice(0, 10);
        }
        var id = String(event.id || '');
        var m = id.match(/-(\d{4}-\d{2}-\d{2})$/);
        return m ? m[1] : String(props.event_date_ymd || '').slice(0, 10);
    }

    function parseEventId(event) {
        var props = event.extendedProps || {};
        if (props.event_id) {
            return parseInt(props.event_id, 10);
        }
        var id = String(event.id || '');
        var m = id.match(/^(\d+)-\d{4}-\d{2}-\d{2}$/);
        if (m) {
            return parseInt(m[1], 10);
        }
        return parseInt(id, 10) || 0;
    }

    function formatYmdLong(ymd) {
        var d = new Date(ymd + 'T12:00:00');
        return isNaN(d.getTime())
            ? ymd
            : d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatTimeRange(start, end) {
        function fmt(t) {
            if (!t) {
                return '';
            }
            var x = new Date('1970-01-01T' + String(t).slice(0, 8));
            return isNaN(x.getTime()) ? t : x.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
        var a = fmt(start);
        var b = fmt(end);
        if (a && b) {
            return a + ' – ' + b;
        }
        return a || '';
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var edsMessageModalInstance = null;

    function ensureEdsMessageModal() {
        if (document.getElementById('edsMessageModal')) {
            return;
        }
        document.body.insertAdjacentHTML(
            'beforeend',
            '<div class="modal fade" id="edsMessageModal" tabindex="-1" aria-labelledby="edsMessageModalLabel" aria-hidden="true">' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title" id="edsMessageModalLabel"><i class="fas fa-info-circle me-2" id="edsMessageModalIcon"></i><span id="edsMessageModalTitle">Notice</span></h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body"><p class="mb-0" id="edsMessageModalBody"></p></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>' +
            '</div></div></div></div>'
        );
    }

    function showEdsMessageModal(message, options) {
        options = options || {};
        var msg = String(message || '').trim() || 'Something went wrong.';
        ensureEdsMessageModal();
        var titleEl = document.getElementById('edsMessageModalTitle');
        var bodyEl = document.getElementById('edsMessageModalBody');
        var iconEl = document.getElementById('edsMessageModalIcon');
        var modalEl = document.getElementById('edsMessageModal');
        if (!modalEl || !bodyEl) {
            window.alert(msg);
            return;
        }
        if (titleEl) {
            titleEl.textContent = options.title || 'Notice';
        }
        if (iconEl) {
            iconEl.className = 'fas ' + (options.icon || 'fa-info-circle') + ' me-2';
        }
        bodyEl.textContent = msg;
        if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
            edsPrepareStackedModal(modalEl);
            edsMessageModalInstance = edsCreateHelperModalInstance(modalEl);
            modalEl.addEventListener('hidden.bs.modal', function () {
                edsResetHelperModal(modalEl);
            }, { once: true });
            if (edsMessageModalInstance) {
                edsMessageModalInstance.show();
            }
        } else {
            window.alert(msg);
        }
    }

    function showEdsRsvpError(error) {
        var msg = error || 'Could not RSVP.';
        if (msg.indexOf('main event') !== -1) {
            showEdsMessageModal(msg, {
                title: 'Register for the event first',
                icon: 'fa-calendar-check'
            });
            return;
        }
        showEdsMessageModal(msg, { title: 'Activity RSVP', icon: 'fa-exclamation-circle' });
    }

    var edsConfirmModalInstance = null;

    function edsCountOpenModals() {
        return document.querySelectorAll('.modal.show').length;
    }

    function edsForceModalCleanupIfIdle() {
        if (edsCountOpenModals() > 0) {
            return;
        }
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function edsCreateHelperModalInstance(modalEl) {
        if (typeof global.bootstrap === 'undefined' || !global.bootstrap.Modal) {
            return null;
        }
        var existing = global.bootstrap.Modal.getInstance(modalEl);
        if (existing) {
            existing.dispose();
        }
        var stacked = edsCountOpenModals() > 0;
        return new global.bootstrap.Modal(modalEl, {
            backdrop: !stacked,
            keyboard: true,
            focus: true
        });
    }

    function edsPrepareStackedModal(modalEl) {
        var openCount = edsCountOpenModals();
        if (openCount > 0) {
            modalEl.style.zIndex = String(1055 + (openCount * 10));
        } else {
            modalEl.style.removeProperty('z-index');
        }
    }

    function edsResetHelperModal(modalEl) {
        modalEl.style.removeProperty('z-index');
        edsForceModalCleanupIfIdle();
    }

    function edsForceHideHelperModals() {
        ['edsConfirmModal', 'edsMessageModal'].forEach(function (id) {
            var helper = document.getElementById(id);
            if (!helper) {
                return;
            }
            if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
                var inst = global.bootstrap.Modal.getInstance(helper);
                if (inst) {
                    inst.hide();
                    return;
                }
            }
            helper.classList.remove('show');
            helper.setAttribute('aria-hidden', 'true');
            helper.style.removeProperty('display');
            helper.style.removeProperty('z-index');
        });
        edsForceModalCleanupIfIdle();
    }

    function ensureEdsConfirmModal() {
        if (document.getElementById('edsConfirmModal')) {
            return;
        }
        document.body.insertAdjacentHTML(
            'beforeend',
            '<div class="modal fade" id="edsConfirmModal" tabindex="-1" aria-labelledby="edsConfirmModalLabel" aria-hidden="true">' +
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title" id="edsConfirmModalLabel"><i class="fas fa-question-circle me-2" id="edsConfirmModalIcon"></i><span id="edsConfirmModalTitle">Confirm</span></h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body"><p class="mb-0" id="edsConfirmModalBody"></p></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" id="edsConfirmCancelBtn" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="button" class="btn btn-primary" id="edsConfirmYesBtn">Confirm</button>' +
            '</div></div></div></div>'
        );
    }

    function showEdsConfirmModal(options) {
        options = options || {};
        ensureEdsConfirmModal();
        return new Promise(function (resolve) {
            var modalEl = document.getElementById('edsConfirmModal');
            var titleEl = document.getElementById('edsConfirmModalTitle');
            var bodyEl = document.getElementById('edsConfirmModalBody');
            var iconEl = document.getElementById('edsConfirmModalIcon');
            var yesBtn = document.getElementById('edsConfirmYesBtn');
            var cancelBtn = document.getElementById('edsConfirmCancelBtn');
            if (!modalEl || !yesBtn || !bodyEl) {
                resolve(false);
                return;
            }
            if (titleEl) {
                titleEl.textContent = options.title || 'Confirm';
            }
            if (iconEl) {
                iconEl.className = 'fas ' + (options.icon || 'fa-question-circle') + ' me-2';
            }
            bodyEl.textContent = options.message || 'Are you sure?';
            yesBtn.textContent = options.confirmLabel || 'Confirm';
            yesBtn.className = 'btn ' + (options.confirmClass || 'btn-primary');
            if (cancelBtn) {
                cancelBtn.textContent = options.cancelLabel || 'Cancel';
            }

            var settled = false;
            var confirmed = false;

            function done(result) {
                if (settled) {
                    return;
                }
                settled = true;
                resolve(result);
            }

            function onHidden() {
                edsResetHelperModal(modalEl);
                done(confirmed);
            }

            function onYes() {
                confirmed = true;
                if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
                    var inst = global.bootstrap.Modal.getInstance(modalEl);
                    if (inst) {
                        inst.hide();
                    }
                } else {
                    onHidden();
                }
            }

            yesBtn.addEventListener('click', onYes, { once: true });
            modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });

            if (typeof global.bootstrap !== 'undefined' && global.bootstrap.Modal) {
                edsPrepareStackedModal(modalEl);
                edsConfirmModalInstance = edsCreateHelperModalInstance(modalEl);
                if (edsConfirmModalInstance) {
                    edsConfirmModalInstance.show();
                }
            } else {
                confirmed = window.confirm(bodyEl.textContent);
                onHidden();
            }
        });
    }

    function postActivitySessionRsvp(action, sessionId) {
        var body = new FormData();
        body.append('action', action);
        body.append('session_id', String(sessionId));
        body.append('csrf_token', csrfToken());
        return fetch(baseUrl() + '/backend/auth/event_day_sessions_api.php', {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        });
    }

    function refreshStudentRsvpLists(data) {
        if (!data || !data.sessions) {
            return;
        }
        state.sessions = data.sessions;
        refreshDetailsPanel();
        var prev = document.getElementById('studentDaySessionsPreview');
        if (prev) {
            prev.innerHTML = renderSessionsList(data.sessions, { canEdit: false, canRsvp: true });
            bindRsvpActions(prev);
        }
    }

    function findSessionById(sessions, sessionId) {
        sessionId = parseInt(sessionId, 10);
        if (!sessions || !sessionId) {
            return null;
        }
        for (var i = 0; i < sessions.length; i++) {
            if (parseInt(sessions[i].id, 10) === sessionId) {
                return sessions[i];
            }
        }
        return null;
    }

    function renderEahRsvpButtonHtml(session) {
        if (!session || String(session.status || 'scheduled').toLowerCase() === 'cancelled') {
            return '';
        }
        var sid = parseInt(session.id, 10);
        if (session.user_rsvped) {
            return '<button type="button" class="eah-btn eah-btn-outline js-eah-cancel-rsvp" data-session-id="' + sid + '">' +
                '<i class="fas fa-times"></i> Cancel RSVP</button>';
        }
        if (session.allows_rsvp === false) {
            return '<span class="eah-btn eah-btn-outline text-muted" style="pointer-events:none;opacity:.85">' +
                '<i class="fas fa-clock"></i> RSVP closed</span>';
        }
        return '<button type="button" class="eah-btn eah-btn-primary js-eah-rsvp" data-session-id="' + sid + '">' +
            '<i class="fas fa-check"></i> RSVP for this activity</button>';
    }

    function applyActivityHubRsvpUi(sessionId, sessions) {
        var session = findSessionById(sessions, sessionId);
        if (!session) {
            return;
        }
        var actions = document.querySelector('.eah-actions');
        if (actions) {
            var rsvpBtn = actions.querySelector('.js-eah-rsvp, .js-eah-cancel-rsvp');
            var btnHtml = renderEahRsvpButtonHtml(session);
            if (rsvpBtn) {
                if (btnHtml) {
                    rsvpBtn.outerHTML = btnHtml;
                } else {
                    rsvpBtn.remove();
                }
            } else if (btnHtml) {
                var backBtn = actions.querySelector('a.eah-btn-outline[href*="event_activities"]');
                if (backBtn) {
                    backBtn.insertAdjacentHTML('beforebegin', btnHtml);
                } else {
                    actions.insertAdjacentHTML('afterbegin', btnHtml);
                }
            }
        }
        document.querySelectorAll('.eah-info-item').forEach(function (item) {
            var label = item.querySelector('.eah-info-item-label');
            if (label && label.textContent.trim() === 'RSVP') {
                var val = item.querySelector('.eah-info-item-value');
                if (val) {
                    val.textContent = String(session.rsvp_count != null ? session.rsvp_count : 0) + ' registered';
                }
            }
        });
    }

    function handleActivityHubRsvpSuccess(sessionId, data, btn) {
        if (btn) {
            btn.disabled = false;
        }
        if (!data || !data.ok) {
            return;
        }
        if (data.sessions) {
            applyActivityHubRsvpUi(sessionId, data.sessions);
        }
    }

    function initActivitiesHubRsvp() {
        if (!document.body || !document.body.classList.contains('event-activities-hub')) {
            return;
        }
        if (document.documentElement.getAttribute('data-eah-rsvp-bound') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-eah-rsvp-bound', '1');

        function runPost(action, sessionId, btn) {
            btn.disabled = true;
            postActivitySessionRsvp(action, sessionId).then(function (data) {
                if (!data.ok) {
                    btn.disabled = false;
                    showEdsRsvpError(data.error);
                    return;
                }
                handleActivityHubRsvpSuccess(sessionId, data, btn);
            }).catch(function () {
                btn.disabled = false;
                showEdsMessageModal('Request failed. Please try again.', {
                    title: 'Activity RSVP',
                    icon: 'fa-exclamation-circle'
                });
            });
        }

        document.addEventListener('click', function (e) {
            var rsvpBtn = e.target.closest('.js-eah-rsvp');
            if (rsvpBtn) {
                e.preventDefault();
                var sid = rsvpBtn.getAttribute('data-session-id');
                if (!sid || typeof showEdsConfirmModal !== 'function') {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Confirm RSVP',
                    message: 'Are you sure you want to register for this activity?',
                    confirmLabel: 'Yes, register',
                    confirmClass: 'btn-primary',
                    icon: 'fa-calendar-check'
                }).then(function (ok) {
                    if (ok) {
                        runPost('rsvp', sid, rsvpBtn);
                    }
                });
                return;
            }
            var cancelBtn = e.target.closest('.js-eah-cancel-rsvp');
            if (cancelBtn) {
                e.preventDefault();
                var sid2 = cancelBtn.getAttribute('data-session-id');
                if (!sid2 || typeof showEdsConfirmModal !== 'function') {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Cancel RSVP',
                    message: 'Are you sure you want to cancel your RSVP for this activity?',
                    confirmLabel: 'Yes, cancel RSVP',
                    confirmClass: 'btn-danger',
                    icon: 'fa-user-minus'
                }).then(function (ok) {
                    if (ok) {
                        runPost('cancel_rsvp', sid2, cancelBtn);
                    }
                });
            }
        });
    }

    function fetchSessions(eventId, scheduleDate) {
        var url = baseUrl() + '/backend/auth/event_day_sessions_api.php?event_id=' +
            encodeURIComponent(eventId) +
            (scheduleDate ? '&schedule_date=' + encodeURIComponent(scheduleDate) : '');
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    function statusBadge(status) {
        status = String(status || 'scheduled').toLowerCase();
        if (status === 'scheduled') {
            return '';
        }
        var cls = status === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark';
        var label = status.charAt(0).toUpperCase() + status.slice(1);
        return ' <span class="badge ' + cls + ' ms-1">' + escapeHtml(label) + '</span>';
    }

    function renderSessionsList(sessions, options) {
        options = options || {};
        var canEdit = !!options.canEdit;
        var canRsvp = !!options.canRsvp;
        if (!sessions || !sessions.length) {
            return '<p class="text-muted small mb-0">No activities added for this day yet.' +
                (canEdit ? ' Use <strong>Add activity</strong> below.' : '') + '</p>';
        }
        var html = '<ul class="list-group list-group-flush event-day-sessions-list">';
        sessions.forEach(function (s) {
            var timeStr = formatTimeRange(s.start_time, s.end_time);
            var status = String(s.status || 'scheduled').toLowerCase();
            html += '<li class="list-group-item px-0 py-2' + (status === 'cancelled' ? ' opacity-75' : '') + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                '<div class="min-w-0">' +
                '<div class="fw-semibold">' + escapeHtml(s.title) + statusBadge(status) + '</div>';
            if (s.category) {
                html += '<div class="small text-muted">' + escapeHtml(s.category) + '</div>';
            }
            html += (timeStr ? '<div class="small text-muted">' + escapeHtml(timeStr) + '</div>' : '') +
                '<div class="small"><i class="fas fa-map-marker-alt me-1 text-secondary"></i>' + escapeHtml(s.location) + '</div>';
            if (s.notes) {
                html += '<div class="small mt-1 text-muted">' + escapeHtml(s.notes) + '</div>';
            }
            if (s.contact_name || s.contact_phone) {
                html += '<div class="small text-muted"><i class="fas fa-address-card me-1"></i>' +
                    escapeHtml([s.contact_name, s.contact_phone].filter(Boolean).join(' · ')) + '</div>';
            }
            if (s.max_capacity) {
                var spots = (s.rsvp_count != null ? s.rsvp_count : 0) + ' / ' + s.max_capacity + ' RSVP\'d';
                html += '<div class="small text-muted">' + escapeHtml(spots) + '</div>';
            } else if (s.rsvp_count != null && s.rsvp_count > 0) {
                html += '<div class="small text-muted">' + escapeHtml(String(s.rsvp_count)) + ' RSVP\'d</div>';
            }
            if (s.latitude != null && s.longitude != null) {
                html += '<div class="small mt-1"><a href="https://www.openstreetmap.org/?mlat=' +
                    encodeURIComponent(s.latitude) + '&mlon=' + encodeURIComponent(s.longitude) +
                    '#map=17/' + encodeURIComponent(s.latitude) + '/' + encodeURIComponent(s.longitude) +
                    '" target="_blank" rel="noopener">View on map</a></div>';
            }
            html += '</div>';
            if (canEdit) {
                html += '<div class="btn-group btn-group-sm flex-shrink-0 flex-column">' +
                    '<div class="btn-group btn-group-sm">' +
                    '<button type="button" class="btn btn-outline-secondary js-eds-edit" data-session-id="' + s.id + '" title="Edit"><i class="fas fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-outline-danger js-eds-delete" data-session-id="' + s.id + '" title="Delete"><i class="fas fa-trash"></i></button>' +
                    '</div>' +
                    '<a class="btn btn-outline-info btn-sm mt-1" href="' + baseUrl() + '/activity_qr.php?id=' + encodeURIComponent(s.id) + '" target="_blank" rel="noopener" title="Activity QR"><i class="fas fa-qrcode"></i></a>' +
                    '</div>';
            } else if (canRsvp && status !== 'cancelled') {
                if (s.user_rsvped) {
                    html += '<button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0 js-eds-cancel-rsvp" data-session-id="' + s.id + '">Cancel RSVP</button>';
                } else if (s.allows_rsvp === false) {
                    html += '<span class="small text-muted flex-shrink-0">Ended</span>';
                } else {
                    html += '<button type="button" class="btn btn-sm btn-primary flex-shrink-0 js-eds-rsvp" data-session-id="' + s.id + '">RSVP</button>';
                }
            }
            html += '</div></li>';
        });
        html += '</ul>';
        return html;
    }

    var state = {
        eventId: 0,
        scheduleDate: '',
        sessions: [],
        editingId: null
    };

    var locationPickerInstance = null;

    function geocodeBase() {
        return global.EVENTIFY_GEOCODE_URL || (baseUrl() + '/backend/auth/geocode_proxy.php');
    }

    function destroyLocationPicker() {
        if (locationPickerInstance && locationPickerInstance.map) {
            try {
                locationPickerInstance.map.remove();
            } catch (err) {
                /* ignore */
            }
        }
        locationPickerInstance = null;
    }

    function initLocationPicker() {
        destroyLocationPicker();
        if (!global.EVENTIFY_SESSIONS_HAVE_GEO) {
            return;
        }
        if (typeof global.initEventLocationPicker !== 'function' || !global.L) {
            return;
        }
        if (!document.getElementById('edsLocationMap')) {
            return;
        }
        locationPickerInstance = global.initEventLocationPicker({
            mapElId: 'edsLocationMap',
            latInputId: 'edsLatitude',
            lngInputId: 'edsLongitude',
            addressInputId: 'edsLocation',
            searchInputId: 'edsLocSearch',
            searchBtnId: 'edsLocSearchBtn',
            useLocationBtnId: 'edsLocUseGps',
            resultsElId: 'edsLocResults',
            geocodeBase: geocodeBase()
        });
    }

    function applySessionCoordsToMap(session) {
        if (!locationPickerInstance || !locationPickerInstance.setCoords) {
            return;
        }
        var lat = session.latitude != null ? parseFloat(session.latitude) : NaN;
        var lng = session.longitude != null ? parseFloat(session.longitude) : NaN;
        if (!isNaN(lat) && !isNaN(lng)) {
            locationPickerInstance.setCoords(lat, lng, true);
        }
    }

    function getManageEls() {
        return {
            modal: document.getElementById('eventDaySessionsModal'),
            list: document.getElementById('eventDaySessionsList'),
            dateLabel: document.getElementById('eventDaySessionsDateLabel'),
            form: document.getElementById('eventDaySessionForm'),
            formTitle: document.getElementById('eventDaySessionFormTitle'),
            sessionId: document.getElementById('edsSessionId'),
            title: document.getElementById('edsTitle'),
            location: document.getElementById('edsLocation'),
            latitude: document.getElementById('edsLatitude'),
            longitude: document.getElementById('edsLongitude'),
            startTime: document.getElementById('edsStartTime'),
            endTime: document.getElementById('edsEndTime'),
            category: document.getElementById('edsCategory'),
            status: document.getElementById('edsStatus'),
            maxCapacity: document.getElementById('edsMaxCapacity'),
            contactName: document.getElementById('edsContactName'),
            contactPhone: document.getElementById('edsContactPhone'),
            notes: document.getElementById('edsNotes'),
            sortOrder: document.getElementById('edsSortOrder'),
            printBtn: document.getElementById('edsPrintScheduleBtn'),
            hubBtn: document.getElementById('edsActivitiesHubBtn'),
            cancelEdit: document.getElementById('edsCancelEditBtn')
        };
    }

    function resetExtraFields(els) {
        if (els.category) {
            els.category.value = '';
        }
        if (els.status) {
            els.status.value = 'scheduled';
        }
        if (els.maxCapacity) {
            els.maxCapacity.value = '';
        }
        if (els.contactName) {
            els.contactName.value = '';
        }
        if (els.contactPhone) {
            els.contactPhone.value = '';
        }
        if (els.notes) {
            els.notes.value = '';
        }
        if (els.sortOrder) {
            els.sortOrder.value = '0';
        }
    }

    function resetForm() {
        var els = getManageEls();
        state.editingId = null;
        if (els.sessionId) {
            els.sessionId.value = '';
        }
        if (els.formTitle) {
            els.formTitle.textContent = 'Add activity';
        }
        if (els.title) {
            els.title.value = '';
        }
        if (els.location) {
            els.location.value = '';
        }
        if (els.latitude) {
            els.latitude.value = '';
        }
        if (els.longitude) {
            els.longitude.value = '';
        }
        if (els.startTime) {
            els.startTime.value = '';
        }
        if (els.endTime) {
            els.endTime.value = '';
        }
        resetExtraFields(els);
        if (els.cancelEdit) {
            els.cancelEdit.style.display = 'none';
        }
        var search = document.getElementById('edsLocSearch');
        if (search) {
            search.value = '';
        }
        var results = document.getElementById('edsLocResults');
        if (results) {
            results.innerHTML = '';
            results.style.display = 'none';
        }
        if (locationPickerInstance && locationPickerInstance.setCoords) {
            locationPickerInstance.setCoords(11.244, 125.004, false);
        }
    }

    function fillForm(session) {
        var els = getManageEls();
        state.editingId = session.id;
        if (els.sessionId) {
            els.sessionId.value = String(session.id);
        }
        if (els.formTitle) {
            els.formTitle.textContent = 'Edit activity';
        }
        if (els.title) {
            els.title.value = session.title || '';
        }
        if (els.location) {
            els.location.value = session.location || '';
        }
        if (els.latitude) {
            els.latitude.value = session.latitude != null && session.latitude !== '' ? String(session.latitude) : '';
        }
        if (els.longitude) {
            els.longitude.value = session.longitude != null && session.longitude !== '' ? String(session.longitude) : '';
        }
        if (els.startTime) {
            els.startTime.value = session.start_time ? String(session.start_time).slice(0, 5) : '';
        }
        if (els.endTime) {
            els.endTime.value = session.end_time ? String(session.end_time).slice(0, 5) : '';
        }
        if (els.category) {
            els.category.value = session.category || '';
        }
        if (els.status) {
            els.status.value = session.status || 'scheduled';
        }
        if (els.maxCapacity) {
            els.maxCapacity.value = session.max_capacity ? String(session.max_capacity) : '';
        }
        if (els.contactName) {
            els.contactName.value = session.contact_name || '';
        }
        if (els.contactPhone) {
            els.contactPhone.value = session.contact_phone || '';
        }
        if (els.notes) {
            els.notes.value = session.notes || '';
        }
        if (els.sortOrder) {
            els.sortOrder.value = session.sort_order != null ? String(session.sort_order) : '0';
        }
        if (els.cancelEdit) {
            els.cancelEdit.style.display = 'inline-block';
        }
        applySessionCoordsToMap(session);
    }

    function refreshManageList() {
        var els = getManageEls();
        if (els.list) {
            els.list.innerHTML = renderSessionsList(state.sessions, { canEdit: true });
            bindManageListActions();
        }
        refreshDetailsPanel();
    }

    function refreshDetailsPanel() {
        var panel = document.getElementById('eventDaySessionsPanel');
        var listEl = document.getElementById('eventDaySessionsPreview');
        var dayLbl = document.getElementById('eventDaySessionsDayLabel');
        var manageBtn = document.getElementById('eventDaySessionsManageBtn');
        if (!panel || !listEl) {
            return;
        }
        if (!state.scheduleDate) {
            panel.style.display = 'none';
            return;
        }
        panel.style.display = 'block';
        if (dayLbl) {
            dayLbl.textContent = formatYmdLong(state.scheduleDate);
        }
        listEl.innerHTML = renderSessionsList(state.sessions, { canEdit: false, canRsvp: global.currentRole === 'student' });
        bindRsvpActions(listEl);
        if (manageBtn) {
            manageBtn.style.display = global.currentRole === 'organizer' ? 'inline-block' : 'none';
        }
        var hubLink = document.getElementById('eventDaySessionsHubLink');
        if (hubLink && state.eventId) {
            hubLink.href = baseUrl() + '/event_activities.php?id=' + encodeURIComponent(state.eventId);
            hubLink.style.display = 'inline';
        }
    }

    function bindRsvpActions(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('.js-eds-rsvp').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                if (!sid) {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Confirm RSVP',
                    message: 'Are you sure you want to register for this activity?',
                    confirmLabel: 'Yes, register',
                    confirmClass: 'btn-primary',
                    icon: 'fa-calendar-check'
                }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    btn.disabled = true;
                    postActivitySessionRsvp('rsvp', sid).then(function (data) {
                        btn.disabled = false;
                        if (!data.ok) {
                            showEdsRsvpError(data.error);
                            return;
                        }
                        refreshStudentRsvpLists(data);
                    }).catch(function () {
                        btn.disabled = false;
                        showEdsMessageModal('Could not RSVP. Please try again.', {
                            title: 'Activity RSVP',
                            icon: 'fa-exclamation-circle'
                        });
                    });
                });
            });
        });
        container.querySelectorAll('.js-eds-cancel-rsvp').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                if (!sid) {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Cancel RSVP',
                    message: 'Are you sure you want to cancel your RSVP for this activity?',
                    confirmLabel: 'Yes, cancel RSVP',
                    confirmClass: 'btn-danger',
                    icon: 'fa-user-minus'
                }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    btn.disabled = true;
                    postActivitySessionRsvp('cancel_rsvp', sid).then(function (data) {
                        btn.disabled = false;
                        if (!data.ok) {
                            showEdsMessageModal(data.error || 'Could not cancel RSVP.', {
                                title: 'Cancel RSVP',
                                icon: 'fa-exclamation-circle'
                            });
                            return;
                        }
                        refreshStudentRsvpLists(data);
                    }).catch(function () {
                        btn.disabled = false;
                        showEdsMessageModal('Could not cancel RSVP. Please try again.', {
                            title: 'Cancel RSVP',
                            icon: 'fa-exclamation-circle'
                        });
                    });
                });
            });
        });
    }

    function bindManageListActions() {
        var els = getManageEls();
        if (!els.list) {
            return;
        }
        els.list.querySelectorAll('.js-eds-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                var session = state.sessions.find(function (s) {
                    return s.id === sid;
                });
                if (session) {
                    fillForm(session);
                    if (els.form) {
                        els.form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
            });
        });
        els.list.querySelectorAll('.js-eds-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = parseInt(btn.getAttribute('data-session-id'), 10);
                if (!sid) {
                    return;
                }
                showEdsConfirmModal({
                    title: 'Remove activity',
                    message: 'Are you sure you want to remove this activity?',
                    confirmLabel: 'Yes, remove',
                    confirmClass: 'btn-danger',
                    icon: 'fa-trash-alt'
                }).then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    var body = new FormData();
                    body.append('action', 'delete');
                    body.append('event_id', String(state.eventId));
                    body.append('session_id', String(sid));
                    body.append('csrf_token', csrfToken());
                    fetch(baseUrl() + '/backend/auth/event_day_sessions_api.php', {
                        method: 'POST',
                        body: body,
                        credentials: 'same-origin'
                    })
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data.ok) {
                                showEdsMessageModal(data.error || 'Could not delete.', {
                                    title: 'Remove activity',
                                    icon: 'fa-exclamation-circle'
                                });
                                return;
                            }
                            return fetchSessions(state.eventId, state.scheduleDate);
                        })
                        .then(function (data) {
                            if (data && data.ok) {
                                state.sessions = data.sessions || [];
                                refreshManageList();
                                if (state.editingId === sid) {
                                    resetForm();
                                }
                            }
                        });
                });
            });
        });
    }

    function openManageModal() {
        var els = getManageEls();
        if (!els.modal || !state.eventId || !state.scheduleDate) {
            return;
        }
        if (els.dateLabel) {
            els.dateLabel.textContent = formatYmdLong(state.scheduleDate);
        }
        if (els.printBtn && state.eventId && state.scheduleDate) {
            els.printBtn.href = baseUrl() + '/activity_schedule.php?event_id=' +
                encodeURIComponent(state.eventId) + '&date=' + encodeURIComponent(state.scheduleDate);
            els.printBtn.style.display = 'inline-block';
        }
        if (els.hubBtn && state.eventId) {
            els.hubBtn.href = baseUrl() + '/event_activities.php?id=' + encodeURIComponent(state.eventId);
            els.hubBtn.style.display = 'inline-block';
        }
        resetForm();
        refreshManageList();
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(els.modal).show();
        }
    }

    function initForm() {
        var els = getManageEls();
        if (els.modal) {
            els.modal.addEventListener('shown.bs.modal', function () {
                initLocationPicker();
            });
            els.modal.addEventListener('hidden.bs.modal', function () {
                destroyLocationPicker();
            });
        }
        if (!els.form) {
            return;
        }
        els.form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (global.EVENTIFY_SESSIONS_HAVE_GEO && els.latitude && els.longitude) {
                var lat = parseFloat(els.latitude.value);
                var lng = parseFloat(els.longitude.value);
                if (isNaN(lat) || isNaN(lng)) {
                    showEdsMessageModal('Please set the venue on the map, search for a place, or use your location.', {
                        title: 'Venue required',
                        icon: 'fa-map-marker-alt'
                    });
                    return;
                }
            }
            var body = new FormData();
            body.append('action', 'save');
            body.append('event_id', String(state.eventId));
            body.append('schedule_date', state.scheduleDate);
            body.append('title', els.title ? els.title.value : '');
            body.append('location', els.location ? els.location.value : '');
            if (els.latitude && els.longitude) {
                body.append('latitude', els.latitude.value);
                body.append('longitude', els.longitude.value);
            }
            body.append('start_time', els.startTime ? els.startTime.value : '');
            body.append('end_time', els.endTime ? els.endTime.value : '');
            if (els.category) {
                body.append('category', els.category.value);
            }
            if (els.status) {
                body.append('status', els.status.value);
            }
            if (els.maxCapacity) {
                body.append('max_capacity', els.maxCapacity.value);
            }
            if (els.contactName) {
                body.append('contact_name', els.contactName.value);
            }
            if (els.contactPhone) {
                body.append('contact_phone', els.contactPhone.value);
            }
            if (els.notes) {
                body.append('notes', els.notes.value);
            }
            if (els.sortOrder) {
                body.append('sort_order', els.sortOrder.value || '0');
            }
            if (state.editingId) {
                body.append('session_id', String(state.editingId));
            }
            body.append('csrf_token', csrfToken());
            fetch(baseUrl() + '/backend/auth/event_day_sessions_api.php', {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        try {
                            return JSON.parse(text);
                        } catch (err) {
                            throw new Error(text && text.indexOf('<') >= 0
                                ? 'Server error while saving. Check PHP error log.'
                                : (text || 'Invalid server response.'));
                        }
                    });
                })
                .then(function (data) {
                    if (!data.ok) {
                        showEdsMessageModal(data.error || 'Could not save.', {
                            title: 'Save activity',
                            icon: 'fa-exclamation-circle'
                        });
                        return;
                    }
                    state.sessions = data.sessions || [];
                    resetForm();
                    refreshManageList();
                })
                .catch(function (err) {
                    showEdsMessageModal(err && err.message ? err.message : 'Could not save activity.', {
                        title: 'Save activity',
                        icon: 'fa-exclamation-circle'
                    });
                });
        });
        if (els.cancelEdit) {
            els.cancelEdit.addEventListener('click', resetForm);
        }
        var manageBtn = document.getElementById('eventDaySessionsManageBtn');
        if (manageBtn) {
            manageBtn.addEventListener('click', openManageModal);
        }
    }

    function loadForCalendarEvent(event, canEdit) {
        state.eventId = parseEventId(event);
        state.scheduleDate = parseClickedScheduleDate(event);
        state.sessions = [];
        if (!state.eventId || !state.scheduleDate) {
            refreshDetailsPanel();
            return Promise.resolve();
        }
        return fetchSessions(state.eventId, state.scheduleDate).then(function (data) {
            if (data.ok) {
                state.sessions = data.sessions || [];
            }
            refreshDetailsPanel();
        });
    }

    function appendStudentSessionsBlock(bodyEl, eventLike) {
        if (!bodyEl) {
            return Promise.resolve();
        }
        var eventId = parseEventId(eventLike);
        var scheduleDate = parseClickedScheduleDate(eventLike);
        if (!eventId || !scheduleDate) {
            return Promise.resolve();
        }
        var existing = document.getElementById('studentDaySessionsBlock');
        if (!existing) {
            bodyEl.insertAdjacentHTML(
                'beforeend',
                '<div class="event-day-sessions-panel mt-3" id="studentDaySessionsBlock">' +
                '<strong class="small text-uppercase text-muted">Activities on this day</strong>' +
                '<div class="small fw-semibold mb-2" id="studentDaySessionsDateLabel">' + escapeHtml(formatYmdLong(scheduleDate)) + '</div>' +
                '<div id="studentDaySessionsPreview"><span class="text-muted small">Loading…</span></div></div>'
            );
        } else {
            var dateLbl = document.getElementById('studentDaySessionsDateLabel');
            if (dateLbl) {
                dateLbl.textContent = formatYmdLong(scheduleDate);
            }
            var prevExisting = document.getElementById('studentDaySessionsPreview');
            if (prevExisting) {
                prevExisting.innerHTML = '<span class="text-muted small">Loading…</span>';
            }
        }
        return fetchSessions(eventId, scheduleDate).then(function (data) {
            var prev = document.getElementById('studentDaySessionsPreview');
            if (!prev) {
                return;
            }
            if (data.ok) {
                prev.innerHTML = renderSessionsList(data.sessions || [], { canEdit: false, canRsvp: true });
                bindRsvpActions(prev);
            } else {
                prev.innerHTML = '<p class="text-muted small mb-0">Could not load activities.</p>';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initForm);
    document.addEventListener('DOMContentLoaded', initActivitiesHubRsvp);

    global.eventifyLoadDaySessionsForEvent = loadForCalendarEvent;
    global.eventifyOpenDaySessionsManager = openManageModal;
    global.eventifyAppendStudentDaySessions = appendStudentSessionsBlock;
    global.showEdsMessageModal = showEdsMessageModal;
    global.showEdsRsvpError = showEdsRsvpError;
    global.showEdsConfirmModal = showEdsConfirmModal;
    global.postActivitySessionRsvp = postActivitySessionRsvp;
    global.initActivitiesHubRsvp = initActivitiesHubRsvp;
    global.edsForceModalCleanupIfIdle = edsForceModalCleanupIfIdle;
    global.edsForceHideHelperModals = edsForceHideHelperModals;
})(typeof window !== 'undefined' ? window : this);
