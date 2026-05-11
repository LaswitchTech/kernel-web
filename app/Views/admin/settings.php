<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings" novalidate>

<div class="row g-4">

    <!-- ── Left column ───────────────────────────────────────────────── -->
    <div class="col-lg-6">

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

    </div>

    <!-- ── Right column ──────────────────────────────────────────────── -->
    <div class="col-lg-6">

        <!-- Mailer (default: PHP mail()) -->
        <div class="card">
            <div class="card-header">
                <span class="fw-semibold small">Mailer</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0 small">
                    Mail delivery uses PHP's <code>mail()</code> function by default.
                    Install an SMTP plugin to configure alternative delivery.
                    Plugin settings appear here once installed.
                </p>
            </div>
        </div>

        <!-- Notifications -->
        <div class="card mt-4">
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
    </div>

</div><!-- /.row -->

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Save Settings
    </button>
</div>

</form>
