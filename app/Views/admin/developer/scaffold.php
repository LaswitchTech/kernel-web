<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<h2 class="mb-3">Scaffold Generator</h2>
<div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-code-slash me-2 fs-5"></i>
    <div>
        Generate a starter extension scaffold into <code>/storage/extension-staging/</code> for review before installation.
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="/admin/developer/scaffold" id="scaffold-form">
            <div class="row g-3">
                <!-- Type -->
                <div class="col-md-6">
                    <label for="scaffold-type" class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="scaffold-type" name="type" required>
                        <option value="">-- Select type --</option>
                        <option value="plugin">Plugin</option>
                        <option value="theme">Theme</option>
                        <option value="layout">Layout</option>
                    </select>
                </div>

                <!-- Name -->
                <div class="col-md-6">
                    <label for="scaffold-name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="scaffold-name" name="name"
                           placeholder="e.g. My Extension" required maxlength="100">
                </div>

                <!-- Slug -->
                <div class="col-md-6">
                    <label for="scaffold-slug" class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="scaffold-slug" name="slug"
                           placeholder="e.g. my-extension" required maxlength="63"
                           pattern="^[a-z][a-z0-9-]*$" title="Lowercase letters, numbers, hyphens. Must start with a lowercase letter.">
                    <div class="form-text">Lowercase letters, numbers, hyphens. Must start with a letter.</div>
                </div>

                <!-- Version -->
                <div class="col-md-6">
                    <label for="scaffold-version" class="form-label fw-semibold">Version <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="scaffold-version" name="version"
                           placeholder="e.g. 0.1.0" required value="0.1.0">
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label for="scaffold-description" class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="scaffold-description" name="description"
                           placeholder="Short description of the extension" required maxlength="500">
                </div>

                <!-- Author -->
                <div class="col-md-6">
                    <label for="scaffold-author" class="form-label fw-semibold">Author</label>
                    <input type="text" class="form-control" id="scaffold-author" name="author"
                           placeholder="e.g. Jane Developer" maxlength="100">
                </div>

                <!-- Namespace -->
                <div class="col-md-6">
                    <label for="scaffold-namespace" class="form-label fw-semibold">Namespace / Class Prefix</label>
                    <input type="text" class="form-control" id="scaffold-namespace" name="namespace"
                           placeholder="e.g. MyExtension" maxlength="63">
                    <div class="form-text">PascalCase class prefix. Auto-generated from name if omitted.</div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary" id="scaffold-submit-btn">Generate Scaffold</button>
            </div>

            <!-- Hidden fields for CSRF if needed in the future -->
            <input type="hidden" name="_csrf" value="">
        </form>
    </div>
</div>

<?php if ($fileList): ?>
<div class="card border-success">
    <div class="card-body">
        <h5 class="card-title text-success mb-2">
            <i class="bi bi-check-circle me-1"></i> Scaffold generated successfully
        </h5>
        <p class="text-muted small mb-2">
            Files written to: <code>/storage/extension-staging/<?= htmlspecialchars($scaffoldSlug) ?>/</code>
        </p>
        <ul class="list-group list-group-flush mb-0">
            <?php foreach ($fileList as $file): ?>
            <li class="list-group-item px-0 py-1">
                <code><?= htmlspecialchars($file) ?></code>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="alert alert-warning mt-3 mb-0 small" role="alert">
            <i class="bi bi-info-circle me-1"></i>
            Review the generated files, then install through the
            <a href="/admin/extensions">Extension Catalog</a> or manually copy to <code>/lib/</code>.
        </div>
    </div>
</div>
<?php endif; ?>
