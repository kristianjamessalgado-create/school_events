/**
 * Confirm approve/reject for multimedia photo moderation (Bootstrap modal, not window.confirm).
 */
(function (global) {
    'use strict';

    function boot() {
        var modalEl = document.getElementById('photoModerationConfirmModal');
        var titleEl = document.getElementById('photoModerationConfirmModalLabel');
        var msgEl = document.getElementById('photoModerationConfirmMessage');
        var confirmBtn = document.getElementById('photoModerationConfirmBtn');
        if (!modalEl || !titleEl || !msgEl || !confirmBtn || !global.bootstrap) {
            return;
        }

        var pendingForm = null;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function stackModalZIndex() {
            var openCount = document.querySelectorAll('.modal.show').length;
            var z = 1055 + openCount * 10;
            modalEl.style.zIndex = String(z);
            setTimeout(function () {
                var backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length) {
                    backdrops[backdrops.length - 1].style.zIndex = String(z - 1);
                }
            }, 0);
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-photo-moderate-trigger');
            if (!btn) {
                return;
            }
            e.preventDefault();
            var form = btn.closest('form.js-photo-moderate-form');
            if (!form) {
                return;
            }

            pendingForm = form;
            var action = (btn.dataset.action || 'approve').toLowerCase();
            var label = btn.dataset.photoLabel || 'this photo';

            if (action === 'reject') {
                titleEl.innerHTML = '<i class="fas fa-times me-2"></i>Reject photo';
                msgEl.textContent = 'Are you sure you want to reject "' + label + '"? It will stay hidden from students.';
                confirmBtn.className = 'btn btn-danger btn-sm';
                confirmBtn.innerHTML = '<i class="fas fa-times me-1"></i> Yes, reject';
            } else {
                titleEl.innerHTML = '<i class="fas fa-check me-2"></i>Approve photo';
                msgEl.textContent = 'Are you sure you want to approve "' + label + '"? Students will be able to see it.';
                confirmBtn.className = 'btn btn-success btn-sm';
                confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Yes, approve';
            }

            stackModalZIndex();
            modal.show();
        });

        confirmBtn.addEventListener('click', function () {
            if (pendingForm) {
                pendingForm.submit();
            }
            pendingForm = null;
            modal.hide();
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            pendingForm = null;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : this);
