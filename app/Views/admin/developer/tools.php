<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<h2 class="mb-3">Developer Tools</h2>
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-terminal me-2 fs-5"></i>
    <div>
        <strong>Developer mode is active.</strong> These tools are only available when <code>APP_DEBUG=true</code>.
        They will not appear in production deployments.
    </div>
</div>

<!-- Developer Settings Toggles -->
<form id="developer-settings-form">
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Developer Settings</h5>
            <div id="dev-settings-result" class="mb-3"></div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Developer Mode</label>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="developer_developer" id="toggle_developer"
                           <?= (isset($appConfig['developer']) && $appConfig['developer']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="toggle_developer">
                        Enable developer features across the application
                    </label>
                </div>
                <div class="form-text">Controls visibility of Developer section, scaffold generator, and debug-gated features.</div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Debug Mode</label>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="developer_debug" id="toggle_debug"
                           <?= (isset($appConfig['debug']) && $appConfig['debug']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="toggle_debug">
                        Enable debug mode (error details, stack traces)
                    </label>
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label small fw-semibold">Dev Console</label>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="developer_dev_console" id="toggle_dev_console"
                           <?= (isset($appConfig['dev_console']) && $appConfig['dev_console']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="toggle_dev_console">
                        Enable floating developer console (offcanvas)
                    </label>
                </div>
                <div class="form-text">Controls whether the floating dev console button and panel render on the site.</div>
            </div>
        </div>
    </div>
</form>
<script>
(function() {
    var form = document.getElementById('developer-settings-form');
    var result = document.getElementById('dev-settings-result');
    var toggles = form.querySelectorAll('input[type="checkbox"]');

    function showResult(type, message) {
        var cls = type === 'success' ? 'alert-success' : 'alert-danger';
        result.innerHTML = '<div class="alert ' + cls + ' alert-dismissible small mb-0">' +
            message +
            '<button type="button" class="btn-close btn-close-sm float-end" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }

    form.addEventListener('change', function(e) {
        var data = new FormData(form);
        // Unchecked checkboxes submit nothing — set to '0' explicitly.
        data.set('developer_developer', toggles[0].checked ? '1' : '0');
        data.set('developer_debug', toggles[1].checked ? '1' : '0');
        data.set('developer_dev_console', toggles[2].checked ? '1' : '0');

        fetch('/admin/developer/settings', {
            method: 'POST',
            body: data
        }).then(function(resp) { return resp.json(); })
          .then(function(json) {
              if (json.ok) {
                  showResult('success', json.message);
              } else {
                  showResult('error', json.error || 'Failed to save settings.');
              }
          }).catch(function() {
              showResult('error', 'Network error. Check your connection.');
          });
    });
})();
</script>

<?php if (!isset($appConfig['dev_console']) || !$appConfig['dev_console']): ?>
<div class="alert alert-warning mb-4" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    The Dev Console is disabled. Toggle it above or set <code>APP_DEV_CONSOLE=true</code> in your <code>.env</code> file.
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- Scaffold Generator -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-code-slash fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Scaffold Generator</h5>
                </div>
                <p class="text-muted small">Generate a starter plugin, theme, or layout into staging for review and installation.</p>
                <a href="/admin/developer/scaffold" class="btn btn-sm btn-primary">Open Generator</a>
                <span class="badge bg-success ms-2">Active</span>
            </div>
        </div>
    </div>

    <!-- Copy Example Code -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-file-earmark-code fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Copy Example Code</h5>
                </div>
                <p class="text-muted small">Copy example extension code (lifecycle hooks, menu registrations, route patterns) to a new extension.</p>
                <span class="badge bg-secondary">Planned</span>
            </div>
        </div>
    </div>

    <!-- Configure Local Repository -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-gear fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Configure Local Repository</h5>
                </div>
                <p class="text-muted small">Configure a local repository for extension development and testing.</p>
                <span class="badge bg-secondary">Planned</span>
            </div>
        </div>
    </div>

    <!-- Validate Manifests -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-clipboard-check fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Validate Manifests</h5>
                </div>
                <p class="text-muted small">Validate extension manifests (plugin.json, theme.json, layout.json) for common errors.</p>
                <span class="badge bg-secondary">Planned</span>
            </div>
        </div>
    </div>

    <!-- Run Development Diagnostics -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-clipboard-data fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Run Development Diagnostics</h5>
                </div>
                <p class="text-muted small">Run diagnostics: check plugin loading, theme discovery, layout resolution, and config state.</p>
                <span class="badge bg-secondary">Planned</span>
            </div>
        </div>
    </div>
</div>
