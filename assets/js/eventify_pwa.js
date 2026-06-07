/**
 * EVENTIFY student PWA — install prompt, service worker, offline ticket cache.
 */
(function (global) {
    'use strict';

    var STORAGE_TICKETS = 'eventify_my_tickets_v1';
    var STORAGE_PASSES = 'eventify_ticket_passes_v1';
    var STORAGE_INSTALL_DISMISSED = 'eventify_pwa_install_dismissed';

    function baseUrl() {
        return (global.BASE_URL || '/school_events').replace(/\/$/, '');
    }

    function ticketsApiUrl() {
        return baseUrl() + '/backend/auth/student_tickets_api.php';
    }

    function isOnline() {
        return typeof navigator.onLine === 'boolean' ? navigator.onLine : true;
    }

    function readJson(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) { /* quota */ }
    }

    function normalizeTicketsPayload(data) {
        if (!data || !data.ok || !Array.isArray(data.tickets)) {
            return [];
        }
        return data.tickets;
    }

    function saveTicketsCache(tickets, cachedAt) {
        writeJson(STORAGE_TICKETS, {
            tickets: tickets,
            cached_at: cachedAt || new Date().toISOString()
        });
    }

    function loadTicketsCache() {
        var blob = readJson(STORAGE_TICKETS, null);
        if (!blob || !Array.isArray(blob.tickets)) {
            return { tickets: [], cached_at: null };
        }
        return blob;
    }

    function saveTicketPassCache(ticket) {
        if (!ticket || !ticket.ticket_code) {
            return;
        }
        var map = readJson(STORAGE_PASSES, {});
        map[ticket.ticket_code] = {
            ticket_code: ticket.ticket_code,
            event_title: ticket.event_title || '',
            type_name: ticket.type_name || '',
            event_date: ticket.event_date || '',
            event_location: ticket.event_location || '',
            holder_name: ticket.holder_name || '',
            holder_student_id: ticket.holder_student_id || '',
            checkin_url: ticket.checkin_url || '',
            qr_url: ticket.qr_url || '',
            status: ticket.status || 'valid',
            cached_at: new Date().toISOString()
        };
        writeJson(STORAGE_PASSES, map);
    }

    function loadTicketPassCache(code) {
        var map = readJson(STORAGE_PASSES, {});
        return map[code] || null;
    }

    function fetchAndCacheTickets() {
        if (!isOnline()) {
            return Promise.resolve(loadTicketsCache());
        }
        return fetch(ticketsApiUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var tickets = normalizeTicketsPayload(data);
                saveTicketsCache(tickets, data.cached_at);
                return { tickets: tickets, cached_at: data.cached_at, from_network: true };
            })
            .catch(function () {
                return loadTicketsCache();
            });
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return Promise.resolve();
        }
        return navigator.serviceWorker.register(baseUrl() + '/sw-student.js', {
            scope: baseUrl() + '/'
        }).catch(function () { /* dev / http */ });
    }

    function isStandalone() {
        return global.matchMedia('(display-mode: standalone)').matches
            || global.navigator.standalone === true;
    }

    function initInstallPrompt() {
        var deferredPrompt = null;
        var banner = document.getElementById('pwaInstallBanner');

        global.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredPrompt = e;
            if (banner && !isStandalone() && localStorage.getItem(STORAGE_INSTALL_DISMISSED) !== '1') {
                banner.hidden = false;
            }
        });

        if (banner) {
            var installBtn = document.getElementById('pwaInstallBtn');
            var dismissBtn = document.getElementById('pwaInstallDismiss');

            if (installBtn) {
                installBtn.addEventListener('click', function () {
                    if (!deferredPrompt) {
                        alert('Install: use your browser menu → "Add to Home screen" or "Install app".');
                        return;
                    }
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.finally(function () {
                        deferredPrompt = null;
                        banner.hidden = true;
                    });
                });
            }

            if (dismissBtn) {
                dismissBtn.addEventListener('click', function () {
                    banner.hidden = true;
                    try {
                        localStorage.setItem(STORAGE_INSTALL_DISMISSED, '1');
                    } catch (err) { /* ignore */ }
                });
            }

            if (isStandalone()) {
                banner.hidden = true;
            }
        }

        global.addEventListener('appinstalled', function () {
            if (banner) {
                banner.hidden = true;
            }
        });
    }

    function renderTicketsList(container, tickets, opts) {
        if (!container) {
            return;
        }
        opts = opts || {};
        if (!tickets.length) {
            container.innerHTML = '<p class="text-muted mb-0">You have no saved tickets. Buy tickets while online, then they appear here for offline use.</p>';
            return;
        }
        var html = '<div class="row g-3">';
        tickets.forEach(function (t) {
            var passUrl = t.pass_url || (baseUrl() + '/ticket_pass.php?code=' + encodeURIComponent(t.ticket_code || ''));
            html += '<div class="col-12"><div class="card ticket-pass-preview shadow-sm border-0">' +
                '<div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">' +
                '<div><div class="fw-semibold">' + escapeHtml(t.event_title || '') + '</div>' +
                '<div class="small text-muted">' + escapeHtml(t.type_name || '') + ' · ' + escapeHtml(t.ticket_code || '') + '</div>' +
                '<div class="small">' + escapeHtml(t.event_date || '') + '</div></div>' +
                '<a href="' + escapeHtml(passUrl) + '" class="btn btn-success btn-sm"><i class="fas fa-qrcode me-1"></i>Digital pass</a>' +
                '</div></div></div>';
        });
        html += '</div>';
        container.innerHTML = html;

        if (opts.offlineBanner && opts.cachedAt) {
            var notice = document.getElementById('pwaOfflineTicketsNotice');
            if (notice) {
                notice.hidden = false;
                notice.textContent = 'Offline — showing tickets saved ' + formatCachedAt(opts.cachedAt);
            }
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatCachedAt(iso) {
        try {
            return new Date(iso).toLocaleString();
        } catch (e) {
            return 'earlier';
        }
    }

    function initMyTicketsPage() {
        var listEl = document.getElementById('myTicketsList');
        if (!listEl) {
            return;
        }
        var serverTickets = global.__myTicketsBootstrap || [];

        function apply(tickets, cachedAt, offline) {
            if (tickets.length) {
                renderTicketsList(listEl, tickets, { offlineBanner: offline, cachedAt: cachedAt });
            } else if (!offline && serverTickets.length) {
                renderTicketsList(listEl, serverTickets, {});
            }
        }

        if (isOnline()) {
            fetchAndCacheTickets().then(function (blob) {
                apply(blob.tickets.length ? blob.tickets : serverTickets, blob.cached_at, false);
            });
        } else {
            var cached = loadTicketsCache();
            var tickets = cached.tickets.length ? cached.tickets : serverTickets.map(function (t) {
                return {
                    ticket_code: t.ticket_code,
                    event_title: t.event_title,
                    type_name: t.type_name,
                    event_date: (t.event_date || '').slice(0, 10),
                    pass_url: baseUrl() + '/ticket_pass.php?code=' + encodeURIComponent(t.ticket_code || '')
                };
            });
            apply(tickets, cached.cached_at, true);
        }

        global.addEventListener('online', function () {
            fetchAndCacheTickets().then(function (blob) {
                var notice = document.getElementById('pwaOfflineTicketsNotice');
                if (notice) {
                    notice.hidden = true;
                }
                apply(blob.tickets, blob.cached_at, false);
            });
        });
    }

    function initTicketPassPage() {
        var bootstrap = global.__ticketPassBootstrap;
        if (bootstrap && isOnline()) {
            saveTicketPassCache(bootstrap);
        }
        if (!isOnline() && bootstrap && bootstrap.ticket_code) {
            var cached = loadTicketPassCache(bootstrap.ticket_code);
            if (cached) {
                applyOfflinePass(cached);
            }
        }
    }

    function applyOfflinePass(cached) {
        var title = document.getElementById('ticketPassEventTitle');
        var code = document.getElementById('ticketPassCode');
        var type = document.getElementById('ticketPassType');
        var qr = document.getElementById('ticketPassQr');
        var notice = document.getElementById('pwaOfflinePassNotice');
        if (title) title.textContent = cached.event_title || 'Event';
        if (type) type.textContent = cached.type_name || '';
        if (code) code.textContent = cached.ticket_code || '';
        if (qr && cached.qr_url) qr.src = cached.qr_url;
        if (notice) notice.hidden = false;
    }

    function initOfflineBanner() {
        var banner = document.getElementById('pwaOfflineBanner');
        if (!banner) {
            return;
        }
        function sync() {
            banner.hidden = isOnline();
        }
        sync();
        global.addEventListener('online', sync);
        global.addEventListener('offline', sync);
    }

    function initStudentDashboard() {
        initOfflineBanner();
        if (isOnline()) {
            fetchAndCacheTickets();
        }
    }

    function boot() {
        registerServiceWorker();
        initInstallPrompt();
        initOfflineBanner();
        if (document.getElementById('myTicketsList')) {
            initMyTicketsPage();
        }
        if (global.__ticketPassBootstrap) {
            initTicketPassPage();
        }
        if (document.body && document.body.classList.contains('student-dashboard')) {
            initStudentDashboard();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    global.eventifyPwa = {
        fetchAndCacheTickets: fetchAndCacheTickets,
        loadTicketsCache: loadTicketsCache,
        saveTicketPassCache: saveTicketPassCache,
        loadTicketPassCache: loadTicketPassCache
    };
})(typeof window !== 'undefined' ? window : this);
