<?php
/** Shared logout confirmation — include once per page (id="logoutModal"). */
if (!defined('BASE_URL')) {
    define('BASE_URL', '/school_events');
}
$logoutUrl = BASE_URL . '/backend/auth/logout.php';
?>
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel"><i class="fas fa-sign-out-alt me-2"></i>Confirm log out</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">Are you sure you want to log out?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-danger js-logout-confirm">Yes, log out</a>
            </div>
        </div>
    </div>
</div>
