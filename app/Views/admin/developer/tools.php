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

<div class="row g-3 mb-4">
    <!-- Create Plugin Scaffold -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-plug fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Create Plugin Scaffold</h5>
                </div>
                <p class="text-muted small">Generate a new plugin directory structure with manifest, routes.php, and skeleton controllers.</p>
                <span class="badge bg-secondary">Planned</span>
            </div>
        </div>
    </div>

    <!-- Create Theme Scaffold -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-palette fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Create Theme Scaffold</h5>
                </div>
                <p class="text-muted small">Generate a new theme directory with theme.json, less/app.less, and Bootstrap token overrides.</p>
                <span class="badge bg-secondary">Planned</span>
            </div>
        </div>
    </div>

    <!-- Create Layout Scaffold -->
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-layout-sidebar fs-4 me-2 text-primary"></i>
                    <h5 class="card-title mb-0">Create Layout Scaffold</h5>
                </div>
                <p class="text-muted small">Generate a new layout file with standard panel regions and hook points.</p>
                <span class="badge bg-secondary">Planned</span>
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
