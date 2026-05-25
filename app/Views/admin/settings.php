<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings" novalidate id="settings-form">

<!-- Application -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-gear-wide me-2 fs-4 text-primary"></i>
            <h5 class="card-title mb-0">Application</h5>
        </div>
        <p class="text-muted small mb-3">Core identity settings for this instance.</p>

        <div class="mb-3">
            <label for="app-name" class="form-label small fw-semibold">
                Application Name <span class="text-danger">*</span>
            </label>
            <input type="text"
                   id="app-name"
                   name="app_name"
                   class="form-control form-control-sm <?= isset($errors['app_name']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($settings['app_name'] ?? '') ?>"
                   maxlength="100"
                   required>
            <?php if (isset($errors['app_name'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['app_name']) ?></div>
            <?php else: ?>
                <div class="form-text">Displayed in the browser title bar and sidebar header.</div>
            <?php endif; ?>
        </div>

        <div class="mb-0">
            <label for="app-url" class="form-label small fw-semibold">
                Application URL <span class="text-danger">*</span>
            </label>
            <input type="url"
                   id="app-url"
                   name="app_url"
                   class="form-control form-control-sm <?= isset($errors['app_url']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($settings['app_url'] ?? '') ?>"
                   maxlength="255"
                   placeholder="https://kernel-web.example.com"
                   required>
            <?php if (isset($errors['app_url'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['app_url']) ?></div>
            <?php else: ?>
                <div class="form-text">Public-facing URL. Used in email links and external references. No trailing slash.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Authentication -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-shield-lock me-2 fs-4 text-primary"></i>
            <h5 class="card-title mb-0">Authentication</h5>
        </div>
        <p class="text-muted small mb-3">Security-related configuration.</p>

        <div class="d-flex align-items-center justify-content-between">
            <div>
                <p class="mb-1 small fw-semibold">System-wide 2FA Enforcement</p>
                <p class="text-muted small mb-0">
                    Require all users to complete 2FA challenge on login. Users without 2FA are not affected.
                </p>
            </div>
            <div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="settings_2fa_enforced"
                           <?= ($settings['auth.two_factor.enforced'] ?? false) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="settings_2fa_enforced">
                        <?= ($settings['auth.two_factor.enforced'] ?? false) ? 'On' : 'Off' ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-text mt-2 mb-0">
            Writes to <code>config/local.php</code> — takes effect immediately on next login.
        </div>
        <div id="toggle-2fa-result" class="mt-2"></div>
    </div>
</div>

<!-- Developer -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-terminal me-2 fs-4 text-primary"></i>
            <h5 class="card-title mb-0">Developer Settings</h5>
        </div>
        <p class="text-muted small mb-3">Development and debugging features.</p>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="settings_developer"
                           name="developer_developer"
                           <?= ($settings['developer.developer'] ?? false) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="settings_developer">
                        Developer Mode
                    </label>
                </div>
                <div class="form-text mt-1 mb-0">
                    Shows developer tools, scaffold generator, and debug-gated features.
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="settings_debug"
                           name="developer_debug"
                           <?= ($settings['developer.debug'] ?? false) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="settings_debug">
                        Debug Mode
                    </label>
                </div>
                <div class="form-text mt-1 mb-0">
                    Shows error details, stack traces, and debug logging.
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="settings_dev_console"
                           name="developer_dev_console"
                           <?= ($settings['developer.dev_console'] ?? false) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="settings_dev_console">
                        Dev Console
                    </label>
                </div>
                <div class="form-text mt-1 mb-0">
                    Shows the floating developer console button and offcanvas panel.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Plugin sections -->
<?php foreach ($sections as $section): ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <i class="bi bi-puzzle me-2 fs-4 text-primary"></i>
                <h5 class="card-title mb-0"><?= htmlspecialchars($section->label) ?></h5>
            </div>
            <?= $section->renderBody(['errors' => $errors, 'settings' => $settings]) ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Mailer -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-envelope me-2 fs-4 text-muted"></i>
            <h5 class="card-title mb-0 text-muted">Mailer</h5>
        </div>
        <p class="text-muted small mb-0">
            Mail delivery uses PHP's <code>mail()</code> function by default.
            Install the SMTP plugin to configure alternative delivery.
        </p>
    </div>
</div>

<!-- Notifications placeholder -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-bell me-2 fs-4 text-muted"></i>
            <h5 class="card-title mb-0 text-muted">Notifications</h5>
        </div>
        <p class="text-muted small mb-0">
            The notification system is not yet implemented.
            This section will be populated by notification plugins.
        </p>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Save Settings
    </button>
</div>

</form>

<script>
(function() {
    // --- 2FA enforcement toggle (AJAX) ---
    var toggle2fa = document.getElementById('settings_2fa_enforced');
    var result2fa = document.getElementById('toggle-2fa-result');
    if (toggle2fa) {
        toggle2fa.addEventListener('change', function() {
            var checked = toggle2fa.checked;
            toggle2fa.nextElementSibling.textContent = checked ? 'On' : 'Off';

            fetch('/admin/settings/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    key: 'auth.two_factor.enforced',
                    value: checked
                })
            })
            .then(function(resp) { return resp.json(); })
            .then(function(json) {
                if (json.ok) {
                    showResult(result2fa, 'success', json.message);
                } else {
                    toggle2fa.checked = !checked;
                    toggle2fa.nextElementSibling.textContent = checked ? 'On' : 'Off';
                    showResult(result2fa, 'danger', json.error || 'Failed to save.');
                }
            })
            .catch(function() {
                toggle2fa.checked = !checked;
                toggle2fa.nextElementSibling.textContent = checked ? 'On' : 'Off';
                showResult(result2fa, 'danger', 'Network error.');
            });
        });
    }

    // --- Developer section AJAX (existing) ---
    var toggle = document.getElementById('settings_developer');
    var result = document.getElementById('settings-dev-result');
    if (!toggle) return;

    toggle.addEventListener('change', function() {
        var checked = toggle.checked;
        toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';

        var data = new FormData();
        data.set('developer_developer', checked ? '1' : '0');

        fetch('/admin/developer/settings', {
            method: 'POST',
            body: data
        })
        .then(function(resp) { return resp.json(); })
        .then(function(json) {
            if (json.ok) {
                showResult(result, 'success', json.message);
            } else {
                toggle.checked = !checked;
                toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';
                showResult(result, 'danger', json.error || 'Failed to save.');
            }
        })
        .catch(function() {
            toggle.checked = !checked;
            toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';
            showResult(result, 'danger', 'Network error.');
        });
    });

    function showResult(container, type, message) {
        var cls = type === 'success' ? 'alert-success' : 'alert-danger';
        container.innerHTML = '<div class="alert ' + cls + ' alert-dismissible small mb-0">' +
            message +
            '<button type="button" class="btn-close btn-close-sm float-end" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        // Auto-dismiss after 4s
        setTimeout(function() { container.innerHTML = ''; }, 4000);
    }
})();
</script>
