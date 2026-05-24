<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings" novalidate>

<div class="g-4">

    <!-- Application -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold small">Application</span>
        </div>
        <div class="card-body">

            <div class="mb-3">
                <label for="app-name" class="form-label">
                    Application Name <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="app-name"
                       name="app_name"
                       class="form-control <?= isset($errors['app_name']) ? 'is-invalid' : '' ?>"
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
                <label for="app-url" class="form-label">
                    Application URL <span class="text-danger">*</span>
                </label>
                <input type="url"
                       id="app-url"
                       name="app_url"
                       class="form-control <?= isset($errors['app_url']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($settings['app_url'] ?? '') ?>"
                       maxlength="255"
                       placeholder="https://kernel-web.example.com"
                       required>
                <?php if (isset($errors['app_url'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['app_url']) ?></div>
                <?php else: ?>
                    <div class="form-text">
                        Public-facing URL. Used in email links and external references.
                        No trailing slash.
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Developer Mode -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold small">Developer Mode</span>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <p class="mb-1 fw-semibold small">Developer Mode</p>
                    <p class="text-muted small mb-0">
                        Enables developer tools, scaffold generator, and debug-gated features across the application.
                    </p>
                </div>
                <div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="developer_developer" id="settings_developer"
                               <?= ($settings['developer.developer'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="settings_developer">
                            <?= ($settings['developer.developer'] ?? false) ? 'On' : 'Off' ?>
                        </label>
                    </div>
                </div>
            </div>
            <div id="settings-dev-result" class="mb-2"></div>
            <div class="form-text">
                Toggling this saves to the database and takes effect immediately.
                Reload the page to confirm the new state.
            </div>
        </div>
    </div>

    <!-- Plugin sections -->
    <?php foreach ($sections as $section): ?>
        <div class="card mb-4">
            <div class="card-header">
                <span class="fw-semibold small"><?= htmlspecialchars($section->label) ?></span>
            </div>
            <div class="card-body">
                <?= $section->renderBody(['errors' => $errors, 'settings' => $settings]) ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Mailer (default: PHP mail()) -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold small">Mailer</span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-0 small">
                Mail delivery uses PHP's <code>mail()</code> function by default.
                Install an SMTP plugin to configure alternative delivery.
                Plugin settings appear above once installed.
            </p>
        </div>
    </div>

    <!-- Notifications -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold small">Notifications</span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-0 small">
                The notification system is not yet implemented.
                This section will be populated by notification plugins.
            </p>
        </div>
    </div>

    <!-- 2FA Enforcement (read-only) -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-semibold small">Two-Factor Authentication</span>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <p class="mb-1 fw-semibold small">System-wide 2FA Enforcement</p>
                    <p class="text-muted small mb-0">
                        When enabled, users with 2FA enabled must complete the 2FA challenge on each login.
                        Without it, they are redirected to the 2FA form after login, blocking access to the application.
                    </p>
                </div>
                <div>
                    <span class="badge bg-<?= ($settings['auth.two_factor.enforced'] ?? false) ? 'success' : 'secondary' ?> fs-6">
                        <?= ($settings['auth.two_factor.enforced'] ?? false) ? 'On' : 'Off' ?>
                    </span>
                </div>
            </div>
            <div class="form-text">
                Controlled by the file-backed config key <code>auth.two_factor.enforced</code>.
                Set via <code>config/local.php</code> — not available as a toggle here.
            </div>
        </div>
    </div>

</div><!-- /.g-4 -->

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Save Settings
    </button>
</div>

</form>
<script>
(function() {
    var toggle = document.getElementById('settings_developer');
    var result = document.getElementById('settings-dev-result');
    if (!toggle) return;

    function showResult(type, message) {
        var cls = type === 'success' ? 'alert-success' : 'alert-danger';
        result.innerHTML = '<div class="alert ' + cls + ' alert-dismissible small mb-0">' +
            message +
            '<button type="button" class="btn-close btn-close-sm float-end" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }

    toggle.addEventListener('change', function() {
        var checked = toggle.checked;
        // Optimistically update label.
        toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';

        var data = new FormData();
        data.set('developer_developer', checked ? '1' : '0');

        fetch('/admin/developer/settings', {
            method: 'POST',
            body: data
        }).then(function(resp) { return resp.json(); })
          .then(function(json) {
              if (json.ok) {
                  showResult('success', json.message);
              } else {
                  toggle.checked = !checked;
                  toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';
                  showResult('error', json.error || 'Failed to save.');
              }
          }).catch(function() {
              toggle.checked = !checked;
              toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';
              showResult('error', 'Network error.');
          });
    });
})();
</script>
