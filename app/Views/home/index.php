<div class="container">
    <div class="card landing-card shadow">
        <div class="card-body p-5">
            <h1 class="card-title h2 mb-3">Welcome to <span class="text-primary"><?= htmlspecialchars($appName) ?></span></h1>
            <p class="card-text text-muted mb-4">
                This is the <strong>Kernel-Web</strong> foundation — a modular PHP application kernel
                with plugin architecture, theming, and layout system.
            </p>

            <hr>

            <h6 class="text-uppercase text-muted small fw-bold mb-3">Documentation</h6>
            <div class="row g-2 mb-4">
                <div class="col-sm-6">
                    <a href="/docs/developer/installation/install.md" class="card p-3 text-decoration-none text-dark h-100">
                        <i class="bi bi-download text-primary mb-2"></i>
                        <div class="small fw-bold">Installation Guide</div>
                        <div class="small text-muted">Setup and configuration</div>
                    </a>
                </div>
                <div class="col-sm-6">
                    <a href="/docs/developer/kernel/architecture.md" class="card p-3 text-decoration-none text-dark h-100">
                        <i class="bi bi-code-slash text-primary mb-2"></i>
                        <div class="small fw-bold">Architecture</div>
                        <div class="small text-muted">Kernel design and structure</div>
                    </a>
                </div>
                <div class="col-sm-6">
                    <a href="/docs/user/kernel/dashboard.md" class="card p-3 text-decoration-none text-dark h-100">
                        <i class="bi bi-book text-primary mb-2"></i>
                        <div class="small fw-bold">User Guide</div>
                        <div class="small text-muted">Dashboard and admin features</div>
                    </a>
                </div>
                <div class="col-sm-6">
                    <a href="/docs/developer/plugins/" class="card p-3 text-decoration-none text-dark h-100">
                        <i class="bi bi-puzzle text-primary mb-2"></i>
                        <div class="small fw-bold">Plugins</div>
                        <div class="small text-muted">Available plugin documentation</div>
                    </a>
                </div>
            </div>

            <hr>

            <h6 class="text-uppercase text-muted small fw-bold mb-3">Quick Links</h6>
            <div class="mb-4">
                <a href="/README.md" class="me-3 text-decoration-none"><i class="bi bi-file-earmark-text"></i> README</a>
                <a href="/DESIGN.md" class="me-3 text-decoration-none"><i class="bi bi-file-earmark-code"></i> DESIGN</a>
                <a href="/CLAUDE.md" class="me-3 text-decoration-none"><i class="bi bi-file-earmark-person"></i> CLAUDE</a>
            </div>

            <hr>

            <h6 class="text-uppercase text-muted small fw-bold mb-3">Next Steps</h6>
            <ol class="small text-muted mb-0">
                <li>If this is a fresh install, run the <a href="/install">installer</a> to configure your database.</li>
                <li>Enable plugins in <code>/lib/plugins/</code> by adding a <code>plugin.json</code> manifest.</li>
                <li>Customize the theme by editing files in <code>/lib/themes/</code>.</li>
                <li>Read the <a href="/docs/developer/">developer documentation</a> for architecture details.</li>
            </ol>

            <hr>

            <div class="d-grid gap-2">
                <a href="/auth/login" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Log In
                </a>
                <a href="/admin/locations" class="btn btn-outline-secondary">
                    <i class="bi bi-gear"></i> Admin Panel
                </a>
            </div>
        </div>
    </div>
</div>
