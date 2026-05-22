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

<!-- Config Flags — file-backed (no persistence) -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Developer Config Flags</h5>
        <div class="alert alert-warning small mb-0" role="alert">
            <i class="bi bi-info-circle me-1"></i>
            These are <strong>file-backed configuration</strong> values — not stored in the database.
            Change them via <code>.env</code> variables or <code>config/local.php</code> overrides.
        </div>
    </div>
</div>

<!-- Developer Mode (read-only status) -->
<div class="card mb-3">
    <div class="card-body d-flex align-items-center justify-content-between">
        <div>
            <p class="mb-1 fw-semibold small">Developer Mode</p>
            <p class="text-muted small mb-0">Enables developer tools, scaffold generator, and debug-gated features.</p>
        </div>
        <div>
            <span class="badge <?= (isset($appConfig['developer']) && $appConfig['developer']) ? 'bg-success' : 'bg-danger' ?>">
                <?= (isset($appConfig['developer']) && $appConfig['developer']) ? 'On' : 'Off' ?>
            </span>
            <span class="ms-2 small text-muted">→ <?= isset($appConfig['developer']) && $appConfig['developer'] ? 'true' : 'false' ?></span>
        </div>
    </div>
</div>

<!-- Debug Mode (read-only display, file-backed) -->
<div class="card mb-3">
    <div class="card-body d-flex align-items-center justify-content-between">
        <div>
            <p class="mb-1 fw-semibold small">Debug Mode</p>
            <p class="text-muted small mb-0">Enable debug mode (error details, stack traces)</p>
        </div>
        <div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="toggle_debug"
                       <?= (isset($appConfig['debug']) && $appConfig['debug']) ? 'checked' : '' ?>
                       disabled>
                <label class="form-check-label small" for="toggle_debug">
                    <?= (isset($appConfig['debug']) && $appConfig['debug']) ? 'On' : 'Off' ?>
                </label>
            </div>
        </div>
    </div>
</div>
<div class="ps-4 mb-4">
    <div class="alert alert-info small mb-0" role="alert">
        <i class="bi bi-lock-fill me-1"></i>
        To change: set <code>APP_DEBUG=true</code> in <code>.env</code> or
        add <code>'debug' => true</code> to <code>config/local.php</code> → <code>['app' => ['debug' => true]]</code>.
        Reload the page after saving.
    </div>
</div>

<!-- Dev Console (read-only display, file-backed) -->
<div class="card mb-3">
    <div class="card-body d-flex align-items-center justify-content-between">
        <div>
            <p class="mb-1 fw-semibold small">Dev Console</p>
            <p class="text-muted small mb-0">Enable floating developer console (offcanvas)</p>
        </div>
        <div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="toggle_dev_console"
                       <?= (isset($appConfig['dev_console']) && $appConfig['dev_console']) ? 'checked' : '' ?>
                       disabled>
                <label class="form-check-label small" for="toggle_dev_console">
                    <?= (isset($appConfig['dev_console']) && $appConfig['dev_console']) ? 'On' : 'Off' ?>
                </label>
            </div>
        </div>
    </div>
</div>
<div class="ps-4 mb-4">
    <div class="alert alert-info small mb-0" role="alert">
        <i class="bi bi-lock-fill me-1"></i>
        To change: set <code>APP_DEV_CONSOLE=true</code> in <code>.env</code> or
        add <code>'dev_console' => true</code> to <code>config/local.php</code> → <code>['app' => ['dev_console' => true]]</code>.
        Reload the page after saving.
    </div>
</div>

<?php if (!isset($appConfig['dev_console']) || !$appConfig['dev_console']): ?>
<div class="alert alert-warning mb-4" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    The Dev Console is disabled. Toggle it above (requires file change) or set <code>APP_DEV_CONSOLE=true</code>.
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
