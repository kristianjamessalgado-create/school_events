/**
 * Full-page staff messenger (admin ↔ organizer), EVENTIFY UI.
 */
(function () {
    function esc(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function initials(name) {
        var p = String(name || '?').trim().split(/\s+/);
        if (p.length >= 2) {
            return (p[0][0] + p[1][0]).toUpperCase();
        }
        return (name || '?').slice(0, 2).toUpperCase();
    }

    function formatShortTime(iso) {
        if (!iso) return '';
        try {
            var d = new Date(String(iso).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            var now = new Date();
            var sameDay = d.toDateString() === now.toDateString();
            if (sameDay) {
                return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            }
            return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        } catch (e) {
            return '';
        }
    }

    function formatMsgTime(iso) {
        if (!iso) return '';
        try {
            var d = new Date(String(iso).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            return d.toLocaleString(undefined, {
                weekday: 'short',
                hour: 'numeric',
                minute: '2-digit'
            });
        } catch (e) {
            return '';
        }
    }

    function truncate(str, n) {
        var s = String(str || '');
        if (s.length <= n) return s;
        return s.slice(0, n - 1) + '…';
    }

    function bootPage() {
        var app = document.getElementById('staffMessengerApp');
        if (!app) return;

        if (window.__staffMessengerError) {
            var le = document.getElementById('msgrPeerList');
            if (le) {
                le.innerHTML = '<div class="p-3 small text-danger">' + esc(window.__staffMessengerError) + '</div>';
            }
            return;
        }

        var base = (window.BASE_URL || '').replace(/\/$/, '');
        var selfId = parseInt(window.__staffMessengerSelfId, 10) || 0;
        var peers = Array.isArray(window.__staffMessengerPeers) ? window.__staffMessengerPeers.slice() : [];
        var peerLabel = window.__staffMessengerPeerLabel || 'Contacts';
        var initialWith = parseInt(window.__staffMessengerInitialWith, 10) || 0;

        var listEl = document.getElementById('msgrPeerList');
        var threadEl = document.getElementById('msgrThread');
        var formEl = document.getElementById('msgrSendForm');
        var recipientInput = document.getElementById('msgrRecipientId');
        var textareaEl = document.getElementById('msgrBody');
        var sendBtn = document.getElementById('msgrSendBtn');
        var csrfInput = document.getElementById('msgrCsrf');
        var searchEl = document.getElementById('msgrSearch');
        var headAvatar = document.getElementById('msgrHeadAvatar');
        var headName = document.getElementById('msgrHeadName');
        var headSub = document.getElementById('msgrHeadSub');
        var detailAvatar = document.getElementById('msgrDetailAvatar');
        var detailName = document.getElementById('msgrDetailName');
        var detailEmail = document.getElementById('msgrDetailEmail');
        var detailRole = document.getElementById('msgrDetailRole');
        var detailToggle = document.getElementById('msgrToggleDetail');
        var detailPanel = document.getElementById('msgrDetailPanel');
        var detailBackdrop = document.getElementById('msgrDetailBackdrop');
        var mqTablet = window.matchMedia ? window.matchMedia('(max-width: 1199.98px)') : null;

        function updateDetailBackdrop() {
            if (!detailBackdrop || !detailPanel) return;
            var overlayOpen = !!(mqTablet && mqTablet.matches) && !detailPanel.classList.contains('msgr-detail-collapsed');
            detailBackdrop.classList.toggle('is-visible', overlayOpen);
            detailBackdrop.hidden = !overlayOpen;
            detailBackdrop.setAttribute('aria-hidden', overlayOpen ? 'false' : 'true');
        }

        function syncDetailPanelToViewport() {
            if (!detailPanel || !detailToggle) return;
            if (mqTablet && mqTablet.matches) {
                detailPanel.classList.add('msgr-detail-collapsed');
                detailToggle.setAttribute('aria-expanded', 'false');
            } else {
                detailPanel.classList.remove('msgr-detail-collapsed');
                detailToggle.setAttribute('aria-expanded', 'true');
            }
            updateDetailBackdrop();
        }

        if (mqTablet) {
            if (typeof mqTablet.addEventListener === 'function') {
                mqTablet.addEventListener('change', syncDetailPanelToViewport);
            } else if (typeof mqTablet.addListener === 'function') {
                mqTablet.addListener(syncDetailPanelToViewport);
            }
        }

        var filter = 'all';
        var selectedPeerId = null;
        var selectedPeer = null;
        var pollTimer = null;
        var lastDayLabel = null;

        function getCsrf() {
            if (csrfInput && csrfInput.value) return csrfInput.value;
            return window.csrfToken || '';
        }

        function peerById(id) {
            for (var i = 0; i < peers.length; i++) {
                if (parseInt(peers[i].id, 10) === id) return peers[i];
            }
            return null;
        }

        function previewLine(p) {
            if (!p.last_body) return 'Start a conversation';
            var you = parseInt(p.last_sender_id, 10) === selfId;
            return (you ? 'You: ' : '') + truncate(p.last_body, 52);
        }

        function matchesFilter(p) {
            if (filter === 'unread' && !(parseInt(p.unread_count, 10) > 0)) return false;
            return true;
        }

        function matchesSearch(p, q) {
            if (!q) return true;
            q = q.toLowerCase();
            return (String(p.name || '').toLowerCase().indexOf(q) >= 0) ||
                (String(p.email || '').toLowerCase().indexOf(q) >= 0);
        }

        function renderPeerList() {
            if (!listEl) return;
            if (!peers.length) {
                listEl.innerHTML = '<div class="msgr-list-empty">No ' + esc(peerLabel.toLowerCase()) + ' available yet.</div>';
                return;
            }
            var q = (searchEl && searchEl.value) ? searchEl.value.trim() : '';
            listEl.innerHTML = '';
            var any = false;
            peers.forEach(function (p) {
                if (!matchesFilter(p) || !matchesSearch(p, q)) return;
                any = true;
                var id = parseInt(p.id, 10);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'msgr-chat-row' + (selectedPeerId === id ? ' active' : '') +
                    (parseInt(p.unread_count, 10) > 0 ? ' unread' : '');
                btn.setAttribute('data-peer-id', String(id));
                var unreadDot = parseInt(p.unread_count, 10) > 0 ? '<span class="msgr-unread-dot" aria-hidden="true"></span>' : '';
                btn.innerHTML =
                    '<div class="msgr-avatar msgr-avatar-sm">' + esc(initials(p.name)) + '</div>' +
                    '<div class="msgr-chat-body">' +
                    '<div class="msgr-chat-top">' +
                    '<span class="msgr-chat-name">' + esc(p.name || 'User') + '</span>' +
                    '<span class="msgr-chat-time">' + esc(formatShortTime(p.last_at)) + '</span>' +
                    '</div>' +
                    '<div class="msgr-chat-preview">' + esc(previewLine(p)) + '</div>' +
                    '</div>' + unreadDot;
                btn.addEventListener('click', function () {
                    selectPeer(id);
                });
                listEl.appendChild(btn);
            });
            if (!any && peers.length) {
                listEl.innerHTML = '<div class="msgr-list-empty">No chats match this filter.</div>';
            }
        }

        function setSelectionClass() {
            app.classList.toggle('msgr-has-selection', !!selectedPeerId);
        }

        function updateHeaderDetail(p) {
            if (!p) {
                if (headName) headName.textContent = 'Select a chat';
                if (headSub) headSub.textContent = peerLabel;
                if (headAvatar) headAvatar.textContent = '?';
                if (detailName) detailName.textContent = '—';
                if (detailEmail) detailEmail.textContent = '';
                if (detailAvatar) detailAvatar.textContent = '?';
                if (detailRole) detailRole.style.display = 'none';
                return;
            }
            var ini = initials(p.name);
            if (headName) headName.textContent = p.name || 'User';
            if (headSub) headSub.textContent = p.email || peerLabel;
            if (headAvatar) headAvatar.textContent = ini;
            if (detailName) detailName.textContent = p.name || 'User';
            if (detailEmail) detailEmail.textContent = p.email || '';
            if (detailAvatar) detailAvatar.textContent = ini;
            if (detailRole) {
                detailRole.textContent = peerLabel;
                detailRole.style.display = '';
            }
        }

        function selectPeer(id) {
            var p = peerById(id);
            if (!p) return;
            selectedPeerId = id;
            selectedPeer = p;
            if (recipientInput) recipientInput.value = String(id);
            if (textareaEl) textareaEl.disabled = false;
            if (sendBtn) sendBtn.disabled = false;
            updateHeaderDetail(p);
            renderPeerList();
            setSelectionClass();
            loadThread();
            markRead();
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('with', String(id));
                window.history.replaceState({}, '', u.toString());
            } catch (e) { /* ignore */ }
        }

        function renderMessages(rows) {
            if (!threadEl) return;
            threadEl.innerHTML = '';
            lastDayLabel = null;
            if (!selectedPeerId) {
                threadEl.innerHTML =
                    '<div class="msgr-empty-thread">' +
                    '<div class="msgr-empty-icon-wrap"><i class="fas fa-comments"></i></div>' +
                    '<div class="msgr-empty-title">Select a conversation</div>' +
                    '<p class="mb-0 msgr-muted small">Pick someone from the list to read and send staff messages.</p></div>';
                return;
            }
            if (!rows || !rows.length) {
                var empty = document.createElement('div');
                empty.className = 'msgr-empty-thread';
                empty.innerHTML =
                    '<div class="msgr-empty-icon-wrap"><i class="fas fa-paper-plane"></i></div>' +
                    '<div class="msgr-empty-title">Start the conversation</div>' +
                    '<p class="mb-0 msgr-muted small">No messages yet — say hello below.</p>';
                threadEl.appendChild(empty);
                return;
            }
            rows.forEach(function (m) {
                var dayKey = (m.created_at || '').slice(0, 10);
                if (dayKey && dayKey !== lastDayLabel) {
                    lastDayLabel = dayKey;
                    var sep = document.createElement('div');
                    sep.className = 'msgr-day-sep';
                    sep.textContent = formatMsgTime(m.created_at);
                    threadEl.appendChild(sep);
                }
                var row = document.createElement('div');
                row.className = 'msgr-row ' + (m.mine ? 'mine' : 'theirs');
                var bubble = document.createElement('div');
                bubble.className = 'msgr-bubble ' + (m.mine ? 'mine' : 'theirs');
                var meta = document.createElement('div');
                meta.className = 'msgr-bubble-meta';
                meta.textContent = (m.mine ? 'You' : (m.sender_name || 'Other'));
                var body = document.createElement('div');
                body.textContent = m.body || '';
                bubble.appendChild(meta);
                bubble.appendChild(body);
                row.appendChild(bubble);
                threadEl.appendChild(row);
            });
            threadEl.scrollTop = threadEl.scrollHeight;
        }

        function loadThread() {
            if (!selectedPeerId || !threadEl) return;
            var url = base + '/backend/messaging/staff_fetch.php?with=' + encodeURIComponent(String(selectedPeerId));
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok && data.messages) {
                        renderMessages(data.messages);
                    } else {
                        threadEl.innerHTML = '<div class="p-3 text-danger small">' +
                            esc(data && data.error ? data.error : 'Could not load messages.') + '</div>';
                    }
                })
                .catch(function () {
                    threadEl.innerHTML = '<div class="p-3 text-danger small">Network error.</div>';
                });
        }

        function markRead() {
            if (!selectedPeerId) return;
            var fd = new FormData();
            fd.append('csrf_token', getCsrf());
            fd.append('other_user_id', String(selectedPeerId));
            fetch(base + '/backend/messaging/staff_mark_read.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function () {
                    var p = peerById(selectedPeerId);
                    if (p) {
                        p.unread_count = 0;
                        renderPeerList();
                    }
                })
                .catch(function () { /* ignore */ });
        }

        function bumpLocalPreview(bodyText) {
            var p = peerById(selectedPeerId);
            if (!p) return;
            p.last_body = bodyText;
            p.last_sender_id = selfId;
            p.last_at = new Date().toISOString();
            peers.sort(function (a, b) {
                var ta = a.last_at ? new Date(String(a.last_at).replace(' ', 'T')).getTime() : 0;
                var tb = b.last_at ? new Date(String(b.last_at).replace(' ', 'T')).getTime() : 0;
                return tb - ta;
            });
            renderPeerList();
        }

        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!selectedPeerId || !textareaEl) return;
                var body = (textareaEl.value || '').trim();
                if (!body) return;
                var fd = new FormData();
                fd.append('csrf_token', getCsrf());
                fd.append('recipient_id', String(selectedPeerId));
                fd.append('body', body);
                if (sendBtn) sendBtn.disabled = true;
                fetch(base + '/backend/messaging/staff_send.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (sendBtn) sendBtn.disabled = false;
                        if (data && data.ok) {
                            textareaEl.value = '';
                            bumpLocalPreview(body);
                            loadThread();
                        } else {
                            window.alert(data && data.error ? data.error : 'Send failed.');
                        }
                    })
                    .catch(function () {
                        if (sendBtn) sendBtn.disabled = false;
                        window.alert('Send failed.');
                    });
            });
        }

        document.querySelectorAll('[data-msgr-filter]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                filter = tab.getAttribute('data-msgr-filter') || 'all';
                document.querySelectorAll('[data-msgr-filter]').forEach(function (t) {
                    t.classList.toggle('active', t === tab);
                });
                renderPeerList();
            });
        });

        if (searchEl) {
            searchEl.addEventListener('input', function () {
                renderPeerList();
            });
        }

        if (detailToggle && detailPanel) {
            detailToggle.addEventListener('click', function () {
                var collapsed = detailPanel.classList.toggle('msgr-detail-collapsed');
                detailToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                updateDetailBackdrop();
            });
        }

        if (detailBackdrop) {
            detailBackdrop.addEventListener('click', function () {
                if (!detailPanel || !detailToggle) return;
                detailPanel.classList.add('msgr-detail-collapsed');
                detailToggle.setAttribute('aria-expanded', 'false');
                updateDetailBackdrop();
            });
        }

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape' || !detailBackdrop || !detailBackdrop.classList.contains('is-visible')) return;
            if (!detailPanel || !detailToggle) return;
            detailPanel.classList.add('msgr-detail-collapsed');
            detailToggle.setAttribute('aria-expanded', 'false');
            updateDetailBackdrop();
        });

        syncDetailPanelToViewport();

        var backBtn = document.getElementById('msgrBackToList');
        if (backBtn) {
            backBtn.addEventListener('click', function () {
                selectedPeerId = null;
                selectedPeer = null;
                if (recipientInput) recipientInput.value = '';
                if (textareaEl) {
                    textareaEl.disabled = true;
                    textareaEl.value = '';
                }
                if (sendBtn) sendBtn.disabled = true;
                updateHeaderDetail(null);
                renderPeerList();
                renderMessages([]);
                setSelectionClass();
                try {
                    var u = new URL(window.location.href);
                    u.searchParams.delete('with');
                    window.history.replaceState({}, '', u.pathname + (u.search || ''));
                } catch (e2) { /* ignore */ }
            });
        }

        function startPoll() {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(function () {
                if (document.hidden || !selectedPeerId) return;
                loadThread();
            }, 8000);
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && selectedPeerId) {
                loadThread();
            }
        });

        renderPeerList();
        if (initialWith > 0 && peerById(initialWith)) {
            selectPeer(initialWith);
        } else if (peers.length === 1) {
            selectPeer(parseInt(peers[0].id, 10));
        } else {
            renderMessages([]);
            setSelectionClass();
        }

        startPoll();
    }

    document.addEventListener('DOMContentLoaded', bootPage);
})();
