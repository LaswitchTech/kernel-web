<!-- Flash messages -->
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger') ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Version info bar -->
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <small class="text-muted">
                <i class="bi bi-cube"></i> Current: v<?= htmlspecialchars($kernelVersion) ?>
            </small>
            <?php if ($hasPendingUpdate): ?>
            <span class="badge bg-info">Update staged and ready</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Action buttons -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-primary" id="btn-check">
                <i class="bi bi-arrow-repeat me-1"></i> Check for Updates
            </button>
            <button type="button" class="btn btn-success" id="btn-download" disabled>
                <i class="bi bi-download me-1"></i> Download Latest
            </button>
            <button type="button" class="btn btn-warning" id="btn-apply" disabled>
                <i class="bi bi-box-arrow-in-down me-1"></i> Apply Update
            </button>
            <button type="button" class="btn btn-outline-danger" id="btn-rollback" disabled>
                <i class="bi bi-arrow-counterclockwise me-1"></i> Rollback
            </button>
        </div>
        <div id="update-status" class="mt-3" style="display:none;">
            <div class="progress" style="height:6px;">
                <div class="progress-bar" id="update-progress" style="width:0%;"></div>
            </div>
            <small class="text-muted mt-1" id="update-message"></small>
        </div>
    </div>
</div>

<!-- Staged updates -->
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-semibold small">Staged Updates</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="staged-table">
                <tr><td colspan="4" class="text-muted text-center py-3">No staged updates</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Backups -->
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-semibold small">Backups</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="backups-table">
                <tr><td colspan="4" class="text-muted text-center py-3">No backups</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Update source config -->
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-semibold small">Update Source</span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">Configure a remote URL (JSON) that returns kernel update info.</p>
        <form id="update-source-form" class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="update-source-url" class="form-label small mb-0">Source URL</label>
                <input type="url" class="form-control form-control-sm" id="update-source-url"
                       value="<?= htmlspecialchars($updateUrl ?? '') ?>" placeholder="https://example.com/kernel-update.json">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Save Source</button>
            </div>
        </form>
        <div class="form-text small">
            Expected JSON: {"version": "1.1.0", "download_url": "...", "checksum": "sha256:..."}
        </div>
    </div>
</div>

<script>
(function() {
    const statusDiv = document.getElementById('update-status');
    const progressBar = document.getElementById('update-progress');
    const messageEl = document.getElementById('update-message');

    function showStatus(msg, percent) {
        statusDiv.style.display = '';
        messageEl.textContent = msg;
        progressBar.style.width = (percent || 0) + '%';
    }

    function hideStatus() {
        statusDiv.style.display = 'none';
        progressBar.style.width = '0%';
    }

    // Check for updates
    document.getElementById('btn-check').addEventListener('click', function() {
        showStatus('Checking...', 20);
        fetch('/admin/updates/check', { method: 'GET' })
            .then(r => r.json())
            .then(data => {
                showStatus(data.message || data.error || 'Check complete.', 100);
                if (data.has_update && data.latest_version) {
                    document.getElementById('btn-download').disabled = false;
                }
                // Update version badge
                if (data.current_version) {
                    location.reload();
                }
            })
            .catch(() => showStatus('Check failed.', 0));
    });

    // Download
    document.getElementById('btn-download').addEventListener('click', function() {
        showStatus('Downloading...', 30);
        fetch('/admin/updates/download', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                showStatus(data.message || data.error || 'Done.', 100);
                if (data.ok) {
                    document.getElementById('btn-apply').disabled = false;
                    loadStaged();
                    location.reload();
                }
            })
            .catch(() => showStatus('Download failed.', 0));
    });

    // Apply
    document.getElementById('btn-apply').addEventListener('click', function() {
        showStatus('Applying update...', 50);
        fetch('/admin/updates/apply', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                showStatus(data.message || data.error || 'Done.', 100);
                document.getElementById('btn-apply').disabled = true;
                if (data.ok) loadStaged();
                setTimeout(() => location.reload(), 2000);
            })
            .catch(() => showStatus('Apply failed.', 0));
    });

    // Rollback
    document.getElementById('btn-rollback').addEventListener('click', function() {
        showStatus('Rolling back...', 50);
        fetch('/admin/updates/rollback', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                showStatus(data.message || data.error || 'Done.', 100);
                setTimeout(() => location.reload(), 2000);
            })
            .catch(() => showStatus('Rollback failed.', 0));
    });

    // Load staged/backups via AJAX
    function loadStaged() {
        fetch('/admin/updates/staging')
            .then(r => r.json())
            .then(data => {
                // Staged table
                const staged = data.staged || [];
                const stagedTbody = document.getElementById('staged-table');
                if (staged.length === 0) {
                    stagedTbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">No staged updates</td></tr>';
                } else {
                    stagedTbody.innerHTML = staged.map(s =>
                        '<tr><td class="font-monospace small">' + s.filename + '</td>'
                        + '<td>' + formatSize(s.size) + '</td>'
                        + '<td>' + s.date + '</td>'
                        + '<td><button class="btn btn-sm btn-outline-danger btn-delete-staged" data-file="' + s.filename + '">Delete</button></td></tr>'
                    ).join('');
                }

                // Backups table
                const backups = data.backups || [];
                const backupsTbody = document.getElementById('backups-table');
                if (backups.length === 0) {
                    backupsTbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">No backups</td></tr>';
                } else {
                    backupsTbody.innerHTML = backups.map(b =>
                        '<tr><td class="font-monospace small">' + b.name + '</td>'
                        + '<td>' + formatSize(b.size) + '</td>'
                        + '<td>' + b.date + '</td>'
                        + '<td><button class="btn btn-sm btn-outline-warning btn-rollback-backup" data-backup="' + b.name + '">Rollback</button></td></tr>'
                    ).join('');
                }

                // Enable/disable rollback button
                if (backups.length > 0) {
                    document.getElementById('btn-rollback').disabled = false;
                }
            });
    }

    // Delete staged
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-delete-staged')) {
            const file = e.target.dataset.file;
            fetch('/admin/updates/staging/delete?file=' + encodeURIComponent(file), { method: 'DELETE' })
                .then(r => r.json())
                .then(() => loadStaged());
        }
    });

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Load on page show
    loadStaged();
})();
</script>
