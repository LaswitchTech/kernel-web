<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<h2 class="mb-3">Developer Tools</h2>
<div class="alert alert-info mb-4" role="alert">
    Developer mode is active.
</div>

<div class="alert alert-warning mb-4" role="alert">
    <i class="bi bi-info-circle me-1"></i>
    Configurations are file-backed values — not stored in the database.
    Change them via <code>.env</code> variables for application overrides or <code>config/local.php</code> for instance overrides.
</div>

<div class="row g-3 mb-3">
    <!-- Debug Mode -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-2">Debug Mode</h5>
                <p class="card-text text-muted small mb-3">Enable debug mode (error details, stack traces)</p>
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
            <div class="card-body pt-0">
                <div class="alert alert-info small mb-0" role="alert">
                    <i class="bi bi-lock-fill me-1"></i>
                    To change: set <code>APP_DEBUG</code> in <code>.env</code> or
                    add <code>'debug' => true</code> to <code>config/local.php</code>.
                    Reload the page after saving.
                </div>
            </div>
        </div>
    </div>

    <!-- Dev Console -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-2">Dev Console</h5>
                <p class="card-text text-muted small mb-3">Enable floating developer console (offcanvas)</p>
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
            <div class="card-body pt-0">
                <div class="alert alert-info small mb-0" role="alert">
                    <i class="bi bi-lock-fill me-1"></i>
                    To change: set <code>APP_DEV_CONSOLE</code> in <code>.env</code> or
                    add <code>'dev_console' => true</code> to <code>config/local.php</code>.
                    Reload the page after saving.
                </div>
            </div>
        </div>
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
