/**
 * EVENTIFY in-app notifications — POST + CSRF for mark read / clear all.
 */
(function (global) {
    'use strict';

    function baseUrl() {
        return (global.BASE_URL || '/school_events').replace(/\/$/, '');
    }

    function csrfToken() {
        return global.csrfToken || global.__adminCsrfToken || '';
    }

    function postNotification(action, extra) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken());
        fd.append('action', action);
        fd.append('ajax', '1');
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                if (extra[k] != null && extra[k] !== '') {
                    fd.append(k, String(extra[k]));
                }
            });
        }
        return fetch(baseUrl() + '/backend/auth/mark_notification_read.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        });
    }

    function updateBadgeCount(count) {
        var n = parseInt(count, 10) || 0;
        document.querySelectorAll('[data-eventify-notif-badge]').forEach(function (btn) {
            var badge = btn.querySelector('.badge');
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
        });
        document.querySelectorAll('.eventify-notif-dropdown__header .badge').forEach(function (b) {
            if (n < 1) b.remove();
        });
    }

    function showEmptyList(listEl) {
        if (!listEl) return;
        var scroll = listEl.closest('.eventify-notif-scroll') || listEl.parentElement;
        if (!scroll) return;
        scroll.innerHTML =
            '<div class="eventify-notif-empty">' +
            '<div class="eventify-notif-empty__icon" aria-hidden="true"><i class="fas fa-bell-slash"></i></div>' +
            '<div class="eventify-notif-empty__title">All caught up</div>' +
            '<p class="eventify-notif-empty__text">No new notifications right now.</p>' +
            '</div>';
    }

    function markCardRead(card, data) {
        card.classList.remove('eventify-notif-card--unread');
        card.removeAttribute('data-unread');
        var badge = card.querySelector('.eventify-notif-card__badge');
        if (badge) badge.remove();
        if (data && typeof data.unread_count === 'number') {
            updateBadgeCount(data.unread_count);
        }
    }

    function initCards() {
        var list = document.getElementById('eventifyNotifList');
        if (!list) return;

        function activate(card) {
            var notifId = card.getAttribute('data-notif-id');
            if (!notifId) return;
            postNotification('mark_one', { id: notifId }).then(function (data) {
                if (!data || !data.ok) return;
                markCardRead(card, data);
                global.dispatchEvent(new CustomEvent('eventify:notif-read', {
                    detail: {
                        notifId: notifId,
                        eventId: data.event_id || card.getAttribute('data-event-id') || null,
                        notifType: card.getAttribute('data-notif-type') || '',
                        unreadCount: data.unread_count
                    }
                }));
            }).catch(function () { /* ignore */ });
        }

        list.addEventListener('click', function (e) {
            var card = e.target.closest('.js-eventify-notif-card');
            if (!card || !list.contains(card)) return;
            e.preventDefault();
            activate(card);
        });

        list.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var card = e.target.closest('.js-eventify-notif-card');
            if (!card || !list.contains(card)) return;
            e.preventDefault();
            activate(card);
        });
    }

    function initMarkAll() {
        document.querySelectorAll('.js-eventify-mark-all-notifs').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                postNotification('mark_all').then(function (data) {
                    if (!data || !data.ok) return;
                    var list = document.getElementById('eventifyNotifList');
                    showEmptyList(list);
                    updateBadgeCount(data.unread_count);
                    var toggle = document.querySelector('[data-bs-toggle="dropdown"][id$="NotifDropdownToggle"], #studentNotifDropdownToggle');
                    if (toggle && global.bootstrap) {
                        var dd = bootstrap.Dropdown.getInstance(toggle);
                        if (dd) dd.hide();
                    }
                }).catch(function () { /* ignore */ });
            });
        });
    }

    function initClearForms() {
        document.querySelectorAll('.js-eventify-clear-notifs-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                postNotification('clear_all').then(function (data) {
                    if (!data || !data.ok) return;
                    var list = document.getElementById('eventifyNotifList');
                    showEmptyList(list);
                    updateBadgeCount(0);
                    var modal = form.closest('.modal');
                    if (modal && global.bootstrap) {
                        var inst = bootstrap.Modal.getInstance(modal);
                        if (inst) inst.hide();
                    }
                    document.querySelectorAll('.eventify-notif-dropdown__footer').forEach(function (f) {
                        f.style.display = 'none';
                    });
                }).catch(function () { /* ignore */ });
            });
        });
    }

    function boot() {
        initCards();
        initMarkAll();
        initClearForms();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    global.eventifyNotifications = { postNotification: postNotification, updateBadgeCount: updateBadgeCount };
})(typeof window !== 'undefined' ? window : this);
