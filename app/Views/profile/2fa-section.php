<?php
/**
 * Two-Factor Auth Profile Modal section content.
 *
 * Rendered via the ProfileModal section callback.
 * JS handles all state transitions (generate, enable, disable, recovery).
 */

$ctx = $ctx ?? [];
?>

<div id="pm-2fa-status" class="d-none"></div>

<div id="pm-2fa-setup" class="card border-warning d-none">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock"></i> Set Up Two-Factor Authentication
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Protect your account with TOTP. Scan the QR code with your authenticator app
            (Google Authenticator, Authy, etc.), then enter the code to enable.
        </p>
        <div class="text-center mb-3">
            <div id="pm-2fa-qr" class="d-inline-block p-3 bg-white border rounded"></div>
        </div>
        <div class="mb-3">
            <label for="pm-2fa-code" class="form-label small">Verification Code</label>
            <input type="text" class="form-control form-control-sm" id="pm-2fa-code"
                   maxlength="6" placeholder="123456" inputmode="numeric">
            <div class="form-text">Enter the 6-digit code from your authenticator app.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" id="pm-2fa-verify-btn">
                <span class="btn-label">Verify &amp; Enable</span>
                <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span>Verifying…</span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pm-2fa-skip-btn">Skip for Now</button>
        </div>
        <div id="pm-2fa-error" class="text-danger small mt-2" style="display:none;"></div>
    </div>
</div>

<div id="pm-2fa-enabled" class="card border-success d-none">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-shield-check"></i> Two-Factor Authentication Active
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Your account is protected with two-factor authentication.
        </p>
        <div class="mb-3">
            <label class="form-label small fw-semibold">Recovery Codes</label>
            <p class="small text-muted">Use these codes if you lose access to your authenticator app. Each code can only be used once.</p>
            <div id="pm-2fa-recovery-codes" class="d-flex flex-wrap gap-2"></div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pm-2fa-regen-btn">
                <i class="bi bi-arrow-clockwise me-1"></i>Regenerate Codes
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="pm-2fa-disable-btn">
                <i class="bi bi-shield-x me-1"></i>Disable 2FA
            </button>
        </div>
        <div id="pm-2fa-disable-confirm" class="mt-3 d-none">
            <div class="alert alert-warning small mb-2">
                Enter your current TOTP code to confirm disabling two-factor authentication.
            </div>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" class="form-control form-control-sm" id="pm-2fa-disable-code"
                       maxlength="6" placeholder="123456" inputmode="numeric" style="width:140px;">
                <button type="button" class="btn btn-sm btn-danger" id="pm-2fa-disable-confirm-btn">Confirm Disable</button>
                <button type="button" class="btn btn-sm btn-link btn-sm" id="pm-2fa-disable-cancel-btn">Cancel</button>
            </div>
            <div id="pm-2fa-disable-error" class="text-danger small mt-1" style="display:none;"></div>
        </div>
        <div id="pm-2fa-regen-confirm" class="mt-3 d-none">
            <div class="alert alert-info small mb-2">
                Regenerating recovery codes will invalidate all existing codes.
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-primary" id="pm-2fa-regen-confirm-btn">Confirm Regenerate</button>
                <button type="button" class="btn btn-sm btn-link btn-sm" id="pm-2fa-regen-cancel-btn">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div id="pm-2fa-disabled" class="card d-none">
    <div class="card-body">
        <p class="small text-muted mb-0">Two-factor authentication is not enabled for your account.</p>
    </div>
</div>

<script>
(function () {
    var statusEl    = document.getElementById('pm-2fa-status');
    var setupEl     = document.getElementById('pm-2fa-setup');
    var enabledEl   = document.getElementById('pm-2fa-enabled');
    var disabledEl  = document.getElementById('pm-2fa-disabled');
    var qrEl        = document.getElementById('pm-2fa-qr');
    var codeEl      = document.getElementById('pm-2fa-code');
    var errorEl     = document.getElementById('pm-2fa-error');
    var verifyBtn   = document.getElementById('pm-2fa-verify-btn');
    var skipBtn     = document.getElementById('pm-2fa-skip-btn');
    var disableEl   = document.getElementById('pm-2fa-disable-confirm');
    var regenEl     = document.getElementById('pm-2fa-regen-confirm');
    var disableCode = document.getElementById('pm-2fa-disable-code');
    var regenCodes  = document.getElementById('pm-2fa-recovery-codes');
    var disableErr  = document.getElementById('pm-2fa-disable-error');

    function showEl() { statusEl.classList.add('d-none'); setupEl.classList.add('d-none'); enabledEl.classList.add('d-none'); disabledEl.classList.add('d-none'); var el = arguments[0]; if (el) el.classList.remove('d-none'); }
    function showErr(msg) { errorEl.style.display = ''; errorEl.textContent = msg; }
    function clearErr() { errorEl.style.display = ''; errorEl.textContent = ''; }

    function loadStatus() {
        fetch('/api/profile/2fa/status', { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('Failed'); return r.json(); })
            .then(function (data) {
                if (data.enabled) {
                    // 2FA is fully enabled — show the enabled card.
                    showEl(enabledEl);
                    renderRecoveryCodes([]);
                    // Mark as pending-disable so the disable form knows what to show.
                    statusEl.dataset.disableHint = 'totp';
                } else if (data.pending) {
                    // 2FA setup is pending (secret generated but not confirmed).
                    // Re-show the setup card with the existing secret.
                    showEl(setupEl);
                    generateSecret();
                    statusEl.dataset.disableHint = 'pending';
                } else {
                    // No 2FA at all.
                    showEl(setupEl);
                    generateSecret();
                    statusEl.dataset.disableHint = 'pending';
                }
            })
            .catch(function () { showEl(disabledEl); });
    }

    function renderRecoveryCodes(codes) {
        if (!codes || codes.length === 0) {
            regenCodes.innerHTML = '<p class="small text-muted">Click "Regenerate Codes" to get new recovery codes.</p>';
            return;
        }
        regenCodes.innerHTML = '';
        codes.forEach(function (c) {
            var s = document.createElement('code');
            s.className = 'small bg-body-secondary px-2 py-1 rounded';
            s.style.userSelect = 'all';
            s.textContent = c.substring(0, 4) + '-' + c.substring(4, 8) + '-' + c.substring(8, 12) + '-' + c.substring(12);
            regenCodes.appendChild(s);
        });
    }

    function generateSecret() {
        fetch('/api/profile/2fa/generate', { method: 'POST', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.uri) {
                    // Render QR via the server-side barcode API (query-string form
                    // avoids %2F routing issues with complex otpauth URIs).
                    var encoded = encodeURIComponent(data.uri);
                    var img = document.createElement('img');
                    img.src = '/api/barcode/QR/SVG?value=' + encoded + '&size=180&margin=2';
                    img.className = 'd-block';
                    img.style.maxWidth = '100%';
                    img.alt = '2FA setup QR code';
                    qrEl.innerHTML = '';
                    qrEl.appendChild(img);

                    // Add secret text fallback below QR
                    var secretWrap = document.createElement('div');
                    secretWrap.className = 'mt-2';
                    var secretLabel = document.createElement('div');
                    secretLabel.className = 'small text-muted mb-1';
                    secretLabel.textContent = 'Manual entry secret:';
                    var secretCode = document.createElement('code');
                    secretCode.className = 'small bg-body-secondary px-2 py-1 rounded';
                    secretCode.style.userSelect = 'all';
                    // Extract secret from otpauth URI
                    var match = data.uri.match(/secret=([^&]+)/);
                    secretCode.textContent = match ? match[1] : data.uri;
                    secretWrap.appendChild(secretLabel);
                    secretWrap.appendChild(secretCode);
                    qrEl.appendChild(secretWrap);
                }
            })
            .catch(function () { showEl(disabledEl); });
    }

    if (verifyBtn) verifyBtn.addEventListener('click', function () {
        var code = codeEl.value.trim();
        if (code.length !== 6) { showErr('Please enter a 6-digit code.'); return; }
        verifyBtn.disabled = true;
        verifyBtn.querySelector('.btn-label').classList.add('d-none');
        verifyBtn.querySelector('.btn-loading').classList.remove('d-none');
        clearErr();
        fetch('/api/profile/2fa/enable', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.enabled) {
                enabledEl.classList.remove('d-none');
                setupEl.classList.add('d-none');
                renderRecoveryCodes(data.recoveryCodes || []);
            } else {
                showErr(data.error || 'Invalid code.');
            }
        })
        .catch(function () { showErr('Request failed.'); })
        .finally(function () {
            verifyBtn.disabled = false;
            verifyBtn.querySelector('.btn-label').classList.remove('d-none');
            verifyBtn.querySelector('.btn-loading').classList.add('d-none');
        });
    });

    if (skipBtn) skipBtn.addEventListener('click', function () {
        setupEl.classList.add('d-none');
        disabledEl.classList.remove('d-none');
    });

    var disableBtn = document.getElementById('pm-2fa-disable-btn');
    if (disableBtn) disableBtn.addEventListener('click', function () {
        disableEl.classList.toggle('d-none');
        disableCode.value = '';
        disableErr.style.display = 'none';
        disableErr.textContent = '';
        var hint = statusEl.dataset?.disableHint || 'pending';
        disableEl.setAttribute('data-disable-hint', hint);
    });

    var confirmDisable = document.getElementById('pm-2fa-disable-confirm-btn');
    if (confirmDisable) confirmDisable.addEventListener('click', function () {
        var code = disableCode.value.trim();
        var disableHint = disableEl.getAttribute('data-disable-hint') || 'pending';

        // If the user has no 2FA at all (disabledEl), no code needed.
        if (disableHint === 'none') {
            disableEl.classList.add('d-none');
            return;
        }

        if (disableHint === 'pending' && code === '') {
            // No code needed for pending setup — just disable.
            confirmDisable.disabled = true;
            disableErr.style.display = 'none';
            fetch('/api/profile/2fa/disable', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: '' }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.enabled === false || data.success) {
                    enabledEl.classList.add('d-none');
                    disabledEl.classList.remove('d-none');
                    disableEl.classList.add('d-none');
                } else {
                    disableErr.style.display = ''; disableErr.textContent = data.error || 'Failed.';
                }
            })
            .catch(function () { disableErr.style.display = ''; disableErr.textContent = 'Request failed.'; })
            .finally(function () { confirmDisable.disabled = false; });
            return;
        }

        // Requires a TOTP code or recovery code.
        if (code.length !== 6) { disableErr.style.display = ''; disableErr.textContent = 'Enter a 6-digit code.'; return; }
        confirmDisable.disabled = true;
        disableErr.style.display = 'none';
        fetch('/api/profile/2fa/disable', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                enabledEl.classList.add('d-none');
                disabledEl.classList.remove('d-none');
                disableEl.classList.add('d-none');
            } else {
                disableErr.style.display = ''; disableErr.textContent = data.error || 'Failed.';
            }
        })
        .catch(function () { disableErr.style.display = ''; disableErr.textContent = 'Request failed.'; })
        .finally(function () { confirmDisable.disabled = false; });
    });

    var cancelDisable = document.getElementById('pm-2fa-disable-cancel-btn');
    if (cancelDisable) cancelDisable.addEventListener('click', function () { disableEl.classList.add('d-none'); });

    var regenBtn = document.getElementById('pm-2fa-regen-btn');
    if (regenBtn) regenBtn.addEventListener('click', function () { regenEl.classList.toggle('d-none'); });

    var confirmRegen = document.getElementById('pm-2fa-regen-confirm-btn');
    if (confirmRegen) confirmRegen.addEventListener('click', function () {
        confirmRegen.disabled = true;
        fetch('/api/profile/2fa/recovery-codes', { method: 'POST', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderRecoveryCodes(data.recoveryCodes || []); })
            .catch(function () { showErr('Request failed.'); })
            .finally(function () { confirmRegen.disabled = false; regenEl.classList.add('d-none'); });
    });

    var cancelRegen = document.getElementById('pm-2fa-regen-cancel-btn');
    if (cancelRegen) cancelRegen.addEventListener('click', function () { regenEl.classList.add('d-none'); });

    loadStatus();
})();
</script>
