<?php
// $root          — array: id, label, path
// $relativePath  — string: current relative path ('' = root)
// $entries       — array from FileManagerService::listDirectory()
// $breadcrumbs   — array of ['label' => string, 'url' => string|null]
// $flash         — ?array ['type' => string, 'message' => string]
?>

<!-- ── Breadcrumb ─────────────────────────────────────────────────────── -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="/files">File Manager</a>
        </li>
        <?php foreach ($breadcrumbs as $crumb): ?>
        <?php if ($crumb['url'] !== null): ?>
        <li class="breadcrumb-item">
            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
        </li>
        <?php else: ?>
        <li class="breadcrumb-item active" aria-current="page">
            <?= htmlspecialchars($crumb['label']) ?>
        </li>
        <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>

<!-- ── Flash ──────────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ── Action panels ──────────────────────────────────────────────────── -->
<div class="d-flex gap-2 mb-3 flex-wrap">

    <!-- New Folder -->
    <button class="btn btn-sm btn-outline-secondary"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#fm-new-folder"
            aria-expanded="false"
            aria-controls="fm-new-folder">
        <i class="bi bi-folder-plus me-1"></i>New Folder
    </button>

    <!-- Upload File -->
    <button class="btn btn-sm btn-outline-secondary"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#fm-upload"
            aria-expanded="false"
            aria-controls="fm-upload">
        <i class="bi bi-upload me-1"></i>Upload File
    </button>

</div>

<!-- New Folder panel -->
<div class="collapse mb-3" id="fm-new-folder">
    <div class="card">
        <div class="card-body">
            <form method="POST"
                  action="/files/<?= rawurlencode($root['id']) ?>/mkdir"
                  class="d-flex gap-2 align-items-end">
                <input type="hidden" name="path" value="<?= htmlspecialchars($relativePath) ?>">
                <div class="flex-grow-1">
                    <label for="fm-folder-name" class="form-label small mb-1">Folder name</label>
                    <input type="text"
                           id="fm-folder-name"
                           name="name"
                           class="form-control form-control-sm"
                           placeholder="new-folder"
                           autocomplete="off"
                           required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Create</button>
            </form>
        </div>
    </div>
</div>

<!-- Upload File panel -->
<div class="collapse mb-3" id="fm-upload">
    <div class="card">
        <div class="card-body">
            <form method="POST"
                  action="/files/<?= rawurlencode($root['id']) ?>/upload"
                  enctype="multipart/form-data"
                  class="d-flex gap-2 align-items-end flex-wrap">
                <input type="hidden" name="path" value="<?= htmlspecialchars($relativePath) ?>">
                <div class="flex-grow-1">
                    <label for="fm-upload-file" class="form-label small mb-1">File</label>
                    <input type="file"
                           id="fm-upload-file"
                           name="file"
                           class="form-control form-control-sm"
                           required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Upload</button>
            </form>
        </div>
    </div>
</div>

<!-- ── Directory listing ──────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">Contents</span>
        <span class="text-muted small"><?= count($entries) ?> item<?= count($entries) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="fm-table" class="table table-hover mb-0 w-100">
                <thead>
                    <tr>
                        <th class="d-none">Sort</th><!-- col 0: hidden type sort key -->
                        <th style="width:2rem;"></th><!-- col 1: icon -->
                        <th>Name</th>               <!-- col 2 -->
                        <th>Size</th>               <!-- col 3 -->
                        <th>Modified</th>           <!-- col 4 -->
                        <th class="text-end">Actions</th><!-- col 5 -->
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                    <!-- DataTables will render its own empty state -->
                    <?php endif; ?>
                    <?php foreach ($entries as $e):
                        $isDir     = $e['type'] === 'dir';
                        $entryPath = $e['path'];

                        // Build browse/download/preview URLs for this entry
                        $browseUrl   = '/files/' . rawurlencode($root['id']) . '?path=' . rawurlencode($entryPath);
                        $downloadUrl = '/files/' . rawurlencode($root['id']) . '/download?path=' . rawurlencode($entryPath);
                        $previewUrl  = '/files/' . rawurlencode($root['id']) . '/preview?path='  . rawurlencode($entryPath);

                        $sizeDisplay  = $isDir ? '—' : \App\Modules\FileManager\Services\FileManagerService::formatBytes($e['size']);
                        $isPreviewable = !$isDir && \App\Modules\FileManager\Services\PreviewDetector::isPreviewable($e['name']);
                    ?>
                    <tr>
                        <!-- col 0: hidden sort key — dirs=0, files=1 -->
                        <td class="d-none"><?= $isDir ? '0' : '1' ?></td>

                        <!-- col 1: type icon -->
                        <td class="text-muted" style="width:2rem;">
                            <i class="bi bi-<?= $isDir ? 'folder-fill text-warning' : 'file-earmark' ?>"></i>
                        </td>

                        <!-- col 2: name -->
                        <td>
                            <?php if ($isDir): ?>
                                <a href="<?= htmlspecialchars($browseUrl) ?>"
                                   class="text-body fw-medium text-decoration-none">
                                    <?= htmlspecialchars($e['name']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($e['name']) ?>
                            <?php endif; ?>
                        </td>

                        <!-- col 3: size -->
                        <td class="text-muted small">
                            <?= htmlspecialchars($sizeDisplay) ?>
                        </td>

                        <!-- col 4: modified -->
                        <td class="text-muted small" style="white-space:nowrap;">
                            <?= htmlspecialchars(substr($e['modified'], 0, 16)) ?>
                        </td>

                        <!-- col 5: actions -->
                        <td class="text-end" style="white-space:nowrap;">
                            <?php if ($isPreviewable): ?>
                            <a href="<?= htmlspecialchars($previewUrl) ?>"
                               class="btn btn-sm btn-outline-secondary"
                               title="Preview">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php endif; ?>

                            <?php if (!$isDir): ?>
                            <a href="<?= htmlspecialchars($downloadUrl) ?>"
                               class="btn btn-sm btn-outline-secondary"
                               title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                            <?php endif; ?>

                            <!-- Rename -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary fm-rename-btn"
                                    title="Rename"
                                    data-path="<?= htmlspecialchars($entryPath) ?>"
                                    data-name="<?= htmlspecialchars($e['name']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Move -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary fm-move-btn"
                                    title="Move"
                                    data-path="<?= htmlspecialchars($entryPath) ?>"
                                    data-name="<?= htmlspecialchars($e['name']) ?>">
                                <i class="bi bi-arrows-move"></i>
                            </button>

                            <!-- Delete -->
                            <form method="POST"
                                  action="/files/<?= rawurlencode($root['id']) ?>/delete"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete &quot;<?= htmlspecialchars(addslashes($e['name'])) ?>&quot;? This cannot be undone.')">
                                <input type="hidden" name="path" value="<?= htmlspecialchars($entryPath) ?>">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Delete">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Rename Modal ────────────────────────────────────────────────────── -->
<div class="modal fade" id="fm-rename-modal" tabindex="-1" aria-labelledby="fm-rename-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/files/<?= rawurlencode($root['id']) ?>/rename" id="fm-rename-form">
                <input type="hidden" name="path" id="fm-rename-path">
                <div class="modal-header">
                    <h5 class="modal-title" id="fm-rename-title">Rename</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="fm-rename-name" class="form-label small">New name</label>
                    <input type="text"
                           id="fm-rename-name"
                           name="name"
                           class="form-control"
                           autocomplete="off"
                           required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Rename</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Move Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="fm-move-modal" tabindex="-1" aria-labelledby="fm-move-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/files/<?= rawurlencode($root['id']) ?>/move" id="fm-move-form">
                <input type="hidden" name="path" id="fm-move-path">
                <div class="modal-header">
                    <h5 class="modal-title" id="fm-move-title">Move</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Enter the destination folder path relative to the storage root.
                        Leave empty to move to the root folder.
                    </p>
                    <label for="fm-move-destination" class="form-label small">Destination folder</label>
                    <input type="text"
                           id="fm-move-destination"
                           name="destination"
                           class="form-control"
                           placeholder="e.g. reports/2025"
                           autocomplete="off">
                    <div class="form-text">
                        Moving: <strong id="fm-move-name"></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Move</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
KernelWeb.dt.init('#fm-table', {
    // Directories always sort before files regardless of user column choice.
    orderFixed: { pre: [[0, 'asc']] },
    order: [[2, 'asc']],
    columnDefs: [
        { visible: false,    targets: [0] },
        { orderable: false,  targets: [1, 5] },
    ],
    language: {
        emptyTable: 'This folder is empty.'
    }
});

// ── Rename modal wiring ────────────────────────────────────────────────
document.querySelectorAll('.fm-rename-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('fm-rename-path').value = btn.dataset.path;
        var nameInput = document.getElementById('fm-rename-name');
        nameInput.value = btn.dataset.name;
        var modal = new bootstrap.Modal(document.getElementById('fm-rename-modal'));
        modal.show();
        // Focus and select the name field after the modal transitions in.
        document.getElementById('fm-rename-modal').addEventListener('shown.bs.modal', function () {
            nameInput.select();
        }, { once: true });
    });
});

// ── Move modal wiring ──────────────────────────────────────────────────
document.querySelectorAll('.fm-move-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('fm-move-path').value = btn.dataset.path;
        document.getElementById('fm-move-name').textContent = btn.dataset.name;
        document.getElementById('fm-move-destination').value = '';
        var modal = new bootstrap.Modal(document.getElementById('fm-move-modal'));
        modal.show();
        document.getElementById('fm-move-modal').addEventListener('shown.bs.modal', function () {
            document.getElementById('fm-move-destination').focus();
        }, { once: true });
    });
});
</script>
