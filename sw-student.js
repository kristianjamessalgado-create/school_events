/**
 * EVENTIFY student PWA — cache shell + ticket API for offline passes.
 */
const CACHE_VERSION = 'eventify-student-v2';
const STATIC_CACHE = CACHE_VERSION + '-static';
const TICKETS_CACHE = CACHE_VERSION + '-tickets';

function swBasePath() {
    var p = self.location.pathname || '';
    var marker = '/sw-student.js';
    var idx = p.indexOf(marker);
    if (idx >= 0) {
        return p.slice(0, idx);
    }
    return '/school_events';
}

const SW_BASE = swBasePath();

const STATIC_ASSETS = [
    SW_BASE + '/manifest-student.php',
    SW_BASE + '/assets/pwa/icon-192.png',
    SW_BASE + '/assets/pwa/icon-512.png',
    SW_BASE + '/assets/css/pwa_student.css',
    SW_BASE + '/assets/css/event_tickets.css',
    SW_BASE + '/assets/js/eventify_pwa.js',
    SW_BASE + '/my_tickets.php',
    SW_BASE + '/offline.html'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            return cache.addAll(STATIC_ASSETS).catch(function () {
                /* partial cache ok on dev */
            });
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (k) {
                    return k.startsWith('eventify-student-') && k !== STATIC_CACHE && k !== TICKETS_CACHE;
                }).map(function (k) {
                    return caches.delete(k);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

function isTicketsApi(url) {
    return url.pathname.indexOf('/backend/auth/student_tickets_api.php') !== -1;
}

function isTicketPassPage(url) {
    return url.pathname.indexOf('/ticket_pass.php') !== -1;
}

function isAppAsset(url) {
    return url.pathname.indexOf(SW_BASE + '/assets/') === 0;
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') {
        return;
    }
    var url = new URL(req.url);

    if (isTicketsApi(url)) {
        event.respondWith(
            fetch(req).then(function (res) {
                if (res.ok) {
                    var clone = res.clone();
                    caches.open(TICKETS_CACHE).then(function (cache) {
                        cache.put(req, clone);
                    });
                }
                return res;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || new Response(
                        JSON.stringify({ ok: false, offline: true, tickets: [] }),
                        { status: 200, headers: { 'Content-Type': 'application/json' } }
                    );
                });
            })
        );
        return;
    }

    if (req.mode === 'navigate' && isTicketPassPage(url)) {
        event.respondWith(
            fetch(req).then(function (res) {
                if (res.ok) {
                    var clone = res.clone();
                    caches.open(STATIC_CACHE).then(function (cache) {
                        cache.put(req, clone);
                    });
                }
                return res;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || caches.match(SW_BASE + '/offline.html');
                });
            })
        );
        return;
    }

    if (url.hostname === 'api.qrserver.com') {
        event.respondWith(
            caches.match(req).then(function (cached) {
                return cached || fetch(req).then(function (res) {
                    if (res.ok) {
                        var clone = res.clone();
                        caches.open(STATIC_CACHE).then(function (cache) {
                            cache.put(req, clone);
                        });
                    }
                    return res;
                });
            })
        );
        return;
    }

    if (req.mode === 'navigate' && url.pathname.indexOf(SW_BASE + '/my_tickets.php') !== -1) {
        event.respondWith(
            fetch(req).then(function (res) {
                if (res.ok) {
                    var clone = res.clone();
                    caches.open(STATIC_CACHE).then(function (cache) {
                        cache.put(req, clone);
                    });
                }
                return res;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || caches.match(SW_BASE + '/offline.html');
                });
            })
        );
        return;
    }

    if (isAppAsset(url)) {
        event.respondWith(
            caches.match(req).then(function (cached) {
                return cached || fetch(req).then(function (res) {
                    if (res.ok) {
                        var clone = res.clone();
                        caches.open(STATIC_CACHE).then(function (cache) {
                            cache.put(req, clone);
                        });
                    }
                    return res;
                });
            })
        );
    }
});
