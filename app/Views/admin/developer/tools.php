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

<!-- Dev Tools Offcanvas Toggle -->
<div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between">
        <div>
            <h5 class="card-title mb-1">Dev Tools Console</h5>
            <p class="text-muted small mb-0">Inspect request context, container state, and view variables.</p>
        </div>
        <a class="btn btn-outline-primary"
           data-bs-toggle="offcanvas"
           href="#dev-tools-offcanvas"
           role="button"
           aria-controls="#dev-tools-offcanvas"
           title="Open Developer Tools Console">
            <i class="bi bi-terminal"></i> Open Console
        </a>
    </div>
</div>

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
