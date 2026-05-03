<div class="row justify-content-center">
    <div class="col-lg-8">

        <div class="d-flex align-items-center gap-2 mb-4">
            <a href="/admin/extensions/catalog" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h5 class="mb-0">Submit Extension</h5>
        </div>

        <?php if (isset($flash['type']) && $flash['type'] === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($flash['message'] ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($flash['type']) && $flash['type'] === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($flash['message'] ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="/admin/extensions/catalog/submit" novalidate>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ext-name" class="form-label">
                                Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="ext-name"
                                   name="name"
                                   class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                                   required
                                   autofocus
                                   placeholder="e.g. My Plugin">
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ext-slug" class="form-label">
                                Slug <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="ext-slug"
                                   name="slug"
                                   class="form-control <?= isset($errors['slug']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($old['slug'] ?? '') ?>"
                                   required
                                   placeholder="e.g. my-plugin">
                            <?php if (isset($errors['slug'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['slug']) ?></div>
                            <?php endif; ?>
                            <div class="form-text">
                                URL-safe identifier. Lowercase letters, digits, hyphens, and underscores. Must start with a letter.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="ext-type" class="form-label">
                                Type <span class="text-danger">*</span>
                            </label>
                            <select name="type" id="ext-type" class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>">
                                <option value="plugin" <?= ($old['type'] ?? 'plugin') === 'plugin' ? 'selected' : '' ?>>Plugin</option>
                                <option value="theme" <?= ($old['type'] ?? '') === 'theme' ? 'selected' : '' ?>>Theme</option>
                                <option value="layout" <?= ($old['type'] ?? '') === 'layout' ? 'selected' : '' ?>>Layout</option>
                            </select>
                            <?php if (isset($errors['type'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['type']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ext-version" class="form-label">
                                Version <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="ext-version"
                                   name="version"
                                   class="form-control <?= isset($errors['version']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($old['version'] ?? '') ?>"
                                   required
                                   placeholder="e.g. 1.0.0">
                            <?php if (isset($errors['version'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['version']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="ext-author" class="form-label">Author</label>
                            <input type="text"
                                   id="ext-author"
                                   name="author"
                                   class="form-control"
                                   value="<?= htmlspecialchars($old['author'] ?? '') ?>"
                                   placeholder="e.g. John Doe">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="ext-desc" class="form-label">Description</label>
                        <textarea name="description"
                                  id="ext-desc"
                                  class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                                  rows="2"
                                  placeholder="Short description of the extension"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                        <?php if (isset($errors['description'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['description']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ext-download" class="form-label">Download URL</label>
                            <input type="url"
                                   id="ext-download"
                                   name="download_url"
                                   class="form-control"
                                   value="<?= htmlspecialchars($old['download_url'] ?? '') ?>"
                                   placeholder="https://example.com/plugin.zip">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ext-repo" class="form-label">Repository URL</label>
                            <input type="url"
                                   id="ext-repo"
                                   name="repo_url"
                                   class="form-control"
                                   value="<?= htmlspecialchars($old['repo_url'] ?? '') ?>"
                                   placeholder="https://github.com/user/repo">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ext-requirements" class="form-label">Requirements JSON</label>
                            <textarea name="requirements"
                                      id="ext-requirements"
                                      class="form-control font-monospace"
                                      rows="3"
                                      placeholder='{"php": ">=8.1"}'><?= htmlspecialchars($old['requirements'] ?? '') ?></textarea>
                            <div class="form-text">JSON object of requirements (e.g. PHP version).</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ext-dependencies" class="form-label">Dependencies JSON</label>
                            <textarea name="dependencies"
                                      id="ext-dependencies"
                                      class="form-control font-monospace"
                                      rows="3"
                                      placeholder='["slug-of-other-plugin"]'><?= htmlspecialchars($old['dependencies'] ?? '') ?></textarea>
                            <div class="form-text">JSON array of extension slugs this extension depends on.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="ext-checksum" class="form-label">Checksum</label>
                        <input type="text"
                               id="ext-checksum"
                               name="checksum"
                               class="form-control font-monospace"
                               value="<?= htmlspecialchars($old['checksum'] ?? '') ?>"
                               placeholder="sha256:abc123...">
                        <div class="form-text">SHA-256 checksum of the distribution archive (optional).</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Submit</button>
                        <a href="/admin/extensions/catalog" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
