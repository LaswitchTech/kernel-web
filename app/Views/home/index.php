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
                    <a href="<?= htmlspecialchars($installUrl) ?>" class="card p-3 text-decoration-none text-dark h-100">
                        <i class="bi bi-download text-primary mb-2"></i>
                        <div class="small fw-bold">Installation</div>
                        <div class="small text-muted"><?= $isInstalled ? 'Verify your setup' : 'Get started' ?></div>
                    </a>
                </div>
                <div class="col-sm-6">
                    <a href="#" class="card p-3 text-decoration-none text-dark h-100" data-todo="docs-plugin">
                        <i class="bi bi-code-slash text-primary mb-2"></i>
                        <div class="small fw-bold">Architecture</div>
                        <div class="small text-muted">Kernel design and structure</div>
                    </a>
                </div>
                <div class="col-sm-6">
                    <a href="#" class="card p-3 text-decoration-none text-dark h-100" data-todo="docs-plugin">
                        <i class="bi bi-book text-primary mb-2"></i>
                        <div class="small fw-bold">User Guide</div>
                        <div class="small text-muted">Dashboard and admin features</div>
                    </a>
                </div>
                <div class="col-sm-6">
                    <a href="#" class="card p-3 text-decoration-none text-dark h-100" data-todo="docs-plugin">
                        <i class="bi bi-puzzle text-primary mb-2"></i>
                        <div class="small fw-bold">Plugins</div>
                        <div class="small text-muted">Available plugin documentation</div>
                    </a>
                </div>
            </div>

            <hr>

            <h6 class="text-uppercase text-muted small fw-bold mb-3">Next Steps</h6>
            <ol class="small text-muted mb-0">
                <li><?= $isInstalled ? 'Sign in to the application with your administrator account.' : 'Run the <a href="' . htmlspecialchars($installUrl) . '">installer</a> to configure your database and create an admin account.' ?></li>
                <li><?= $isInstalled ? 'Explore the admin panel to configure settings, users, and groups.' : 'Extensions like plugins and themes will be managed through a built-in Extensions interface in a future release.' ?></li>
            </ol>

            <hr>

            <div class="d-grid gap-2">
                <?php if ($isInstalled): ?>
                <a href="/signin" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </a>
                <?php else: ?>
                <a href="<?= htmlspecialchars($installUrl) ?>" class="btn btn-primary btn-lg">
                    <i class="bi bi-download"></i> Run Installer
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
