<?php
// $root         — array: id, label, path
// $relativePath — string: root-relative path to the file
// $filename     — string: basename of the file
// $size         — int: byte size of the file
// $mimeType     — string|null: detected MIME type
// $previewType  — string: 'text' | 'image' | 'pdf' | 'none'
// $textContent  — string|null: HTML-escaped text content (only when previewType='text')
// $textTooLarge — bool: true when file exceeds TEXT_SIZE_LIMIT
// $statData     — array: from FileManagerService::stat()
// $breadcrumbs  — array of ['label' => string, 'url' => string|null]
// $backUrl      — string: URL back to the parent directory listing
// $downloadUrl  — string: URL for forced download
// $serveUrl     — string: URL for inline stream (image/PDF)

use App\Modules\FileManager\Services\FileManagerService;
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

<!-- ── File header ────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>

    <div class="d-flex align-items-center gap-2 flex-grow-1 min-width-0">
        <?php
        $iconClass = match ($previewType) {
            'image' => 'bi-file-image text-info',
            'pdf'   => 'bi-file-pdf text-danger',
            'text'  => 'bi-file-text text-body-secondary',
            default => 'bi-file-earmark text-body-secondary',
        };
        ?>
        <i class="bi <?= $iconClass ?> fs-5 flex-shrink-0"></i>
        <span class="fw-semibold text-truncate" title="<?= htmlspecialchars($filename) ?>">
            <?= htmlspecialchars($filename) ?>
        </span>
    </div>

    <div class="d-flex gap-2 flex-shrink-0">
        <a href="<?= htmlspecialchars($downloadUrl) ?>"
           class="btn btn-sm btn-outline-secondary"
           title="Download">
            <i class="bi bi-download me-1"></i>Download
        </a>
    </div>
</div>

<!-- ── File meta strip ────────────────────────────────────────────────── -->
<div class="d-flex gap-3 mb-3 text-muted small flex-wrap">
    <span>
        <i class="bi bi-hdd me-1"></i><?= htmlspecialchars(FileManagerService::formatBytes($size)) ?>
    </span>
    <span>
        <i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars(substr($statData['modified'], 0, 16)) ?>
    </span>
    <?php if ($mimeType): ?>
    <span>
        <i class="bi bi-tag me-1"></i><?= htmlspecialchars($mimeType) ?>
    </span>
    <?php endif; ?>
</div>

<!-- ── Preview panel ──────────────────────────────────────────────────── -->
<?php if ($previewType === 'text' && !$textTooLarge && $textContent !== null): ?>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="small fw-semibold">Text Preview</span>
        <span class="text-muted small"><?= htmlspecialchars(FileManagerService::formatBytes($size)) ?></span>
    </div>
    <div class="card-body p-0">
        <pre class="fm-preview-text mb-0"><?= $textContent ?></pre>
    </div>
</div>

<?php elseif ($previewType === 'text' && $textTooLarge): ?>

<div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <div>
        This file is too large to preview
        (<?= htmlspecialchars(FileManagerService::formatBytes($size)) ?>,
        limit <?= htmlspecialchars(FileManagerService::formatBytes(\App\Modules\FileManager\Services\PreviewDetector::TEXT_SIZE_LIMIT)) ?>).
        <a href="<?= htmlspecialchars($downloadUrl) ?>" class="alert-link ms-1">Download</a> to view the full contents.
    </div>
</div>

<?php elseif ($previewType === 'image'): ?>

<div class="card">
    <div class="card-header py-2">
        <span class="small fw-semibold">Image Preview</span>
    </div>
    <div class="card-body text-center">
        <img src="<?= htmlspecialchars($serveUrl) ?>"
             alt="<?= htmlspecialchars($filename) ?>"
             class="img-fluid fm-preview-image"
             loading="lazy">
    </div>
</div>

<?php elseif ($previewType === 'pdf'): ?>

<div class="card">
    <div class="card-header py-2">
        <span class="small fw-semibold">PDF Preview</span>
    </div>
    <div class="card-body p-0">
        <embed src="<?= htmlspecialchars($serveUrl) ?>"
               type="application/pdf"
               class="fm-preview-pdf">
        <p class="p-3 small text-muted mb-0">
            PDF preview requires a browser with a built-in PDF viewer.
            <a href="<?= htmlspecialchars($downloadUrl) ?>">Download</a> to open locally.
        </p>
    </div>
</div>

<?php else: ?>

<div class="alert alert-secondary d-flex align-items-center gap-2">
    <i class="bi bi-eye-slash flex-shrink-0"></i>
    <div>
        Preview is not available for this file type.
        <a href="<?= htmlspecialchars($downloadUrl) ?>" class="alert-link ms-1">Download</a> to open locally.
    </div>
</div>

<?php endif; ?>
