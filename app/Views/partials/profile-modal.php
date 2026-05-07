<!-- Profile Modal -->
<div class="modal fade" id="profile-modal" tabindex="-1" aria-labelledby="profile-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profile-modal-label">Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Tab navigation -->
                <ul class="nav nav-tabs profile-modal-tabs" id="profile-modal-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-overview" data-bs-toggle="tab"
                                data-bs-target="#panel-overview" type="button" role="tab"
                                aria-controls="panel-overview" aria-selected="true">
                            Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-tokens" data-bs-toggle="tab"
                                data-bs-target="#panel-tokens" type="button" role="tab"
                                aria-controls="panel-tokens" aria-selected="false">
                            API Tokens
                        </button>
                    </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content profile-modal-content" id="profile-modal-panes">
                    <!-- Overview tab -->
                    <div class="tab-pane fade show active" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview">
                        <div class="profile-modal-loading" id="pm-loading">
                            <div class="text-center py-4">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                <span class="text-muted">Loading…</span>
                            </div>
                        </div>
                        <div class="profile-modal-error d-none" id="pm-error" role="alert"></div>
                        <div class="d-none" id="pm-data"></div>
                    </div>

                    <!-- API Tokens tab -->
                    <div class="tab-pane fade" id="panel-tokens" role="tabpanel" aria-labelledby="tab-tokens">

                        <!-- Button to show the new token form (hidden when form is visible) -->
                        <div class="d-flex justify-content-end pb-3">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#pm-new-token-form" aria-expanded="false" aria-controls="pm-new-token-form">
                                <i class="bi bi-plus-lg me-1"></i>New token
                            </button>
                        </div>

                        <!-- Plaintext token display (shown once after creation) -->
                        <div class="alert alert-warning d-none border-warning" id="pm-token-created" role="alert">
                            <div class="small fw-semibold mb-1">Your new token — save it now, it won't be shown again:</div>
                            <code class="d-block bg-body-secondary p-2 mb-2" id="pm-token-value" style="word-break:break-all;user-select:all;"></code>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-dismiss="alert" aria-label="Dismiss">Dismiss</button>
                        </div>

                        <!-- New token form (hidden by default) -->
                        <div class="collapse" id="pm-new-token-form">
                            <form id="pm-token-create-form" class="card mb-4 border-primary">
                                <div class="card-body">
                                    <h6 class="fw-semibold mb-3">Create new token</h6>
                                    <div id="pm-token-error" class="text-danger small mt-2" style="display:none;"></div>
                                    <div class="mb-3">
                                        <label for="pm-token-name" class="form-label small">Token name</label>
                                        <input type="text" class="form-control form-control-sm"
                                            id="pm-token-name" placeholder="e.g. Grafana integration"
                                            maxlength="100" autocomplete="off">
                                    </div>
                                    <div class="mb-3">
                                        <label for="pm-token-expires" class="form-label small">
                                            Expires at <span class="text-muted">(optional)</span>
                                        </label>
                                        <input type="datetime-local" class="form-control form-control-sm"
                                            id="pm">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-sm btn-primary" id="pm-token-create-btn">
                                            <span class="create-label">Create</span>
                                            <span class="loading-label d-none"><span class="spinner-border spinner-border-sm me-1"></span>Creating…</span>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#pm-new-token-form" aria-expanded="false" aria-controls="pm-new-token-form">Cancel</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Token list -->
                        <div id="pm-token-list-loading" class="text-center py-4 text-muted small" style="display:none;">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Loading…
                        </div>
                        <div id="pm-token-list-empty" class="text-center py-4 text-muted small" style="display:none;">
                            <i class="bi bi-key d-block mb-2" style="font-size:1.5rem;opacity:.35;"></i>
                            No API tokens yet. Create one above.
                        </div>
                        <div id="pm-token-list" class="list-group list-group-flush" style="max-height:300px;overflow-y:auto;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var profileModal = document.getElementById('profile-modal');

    var loadingEl   = document.getElementById('pm-loading');
    var errorEl     = document.getElementById('pm-error');
    var dataEl      = document.getElementById('pm-data');

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showLoading() {
        errorEl.classList.add('d-none');
        errorEl.innerHTML = '';
        dataEl.innerHTML = '';
        if (loadingEl) loadingEl.style.display = '';
    }

    function showError(msg) {
        if (loadingEl) loadingEl.style.display = 'none';
        if (dataEl) { dataEl.style.display = 'none'; dataEl.innerHTML = ''; }
        errorEl.classList.remove('d-none');
        errorEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>' + escHtml(msg);
    }

    function renderOverview(user) {
        if (loadingEl) loadingEl.style.display = 'none';
        if (dataEl) dataEl.classList.remove('d-none');
        var dl = document.createElement('dl');
        dl.className = 'row mb-0';

        var fields = [
            ['Username',    'username'],
            ['Email',       'email'],
            ['Display Name','display_name'],
            ['Created At',  'created_at'],
            ['Updated At',  'updated_at'],
        ];

        fields.forEach(function (f) {
            var dt = document.createElement('dt');
            dt.className = 'col-sm-4 text-muted';
            dt.textContent = f[0];

            var dd = document.createElement('dd');
            dd.className = 'col-sm-8';
            dd.textContent = (user[f[1]] && user[f[1]] !== '') ? user[f[1]] : '—';

            dl.appendChild(dt);
            dl.appendChild(dd);
        });

        if (dataEl) {
            dataEl.innerHTML = '';
            dataEl.appendChild(dl);
        }
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.js-profile-trigger');
        if (!trigger) return;
        e.preventDefault();

        showLoading();
        var bsModal = new bootstrap.Modal(profileModal);
        bsModal.show();
        // Focus the modal title after show to avoid aria-hidden accessibility warning
        setTimeout(function () {
            var title = document.getElementById('profile-modal-label');
            if (title) title.focus();
        }, 150);

        fetch('/api/profile', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('Failed to load profile');
                return r.json();
            })
            .then(function (data) { renderOverview(data.user || {}); })
            .catch(function () { showError('Could not load profile data.'); });
    });

    // Load API Tokens tab content on first tab click (lazy).
    (function () {
        var loaded = false;
        var tabTokensEl = document.getElementById('tab-tokens');
        if (!tabTokensEl) { console.error('PROFILE MODAL ERROR: #tab-tokens is null'); return; }
        tabTokensEl.addEventListener('shown.bs.tab', function () {
            if (loaded) return;
            loaded = true;

            var panel = document.getElementById('panel-tokens');

            var createdEl  = document.getElementById('pm-token-created');
            var valueEl    = document.getElementById('pm-token-value');
            var createForm = document.getElementById('pm-token-create-form');
            var nameInput  = document.getElementById('pm-token-name');
            var listEl     = document.getElementById('pm-token-list');
            var listLoading = document.getElementById('pm-token-list-loading');
            var listEmpty  = document.getElementById('pm-token-list-empty');

            // ── Show the form container ──
            if (createForm) panel.appendChild(createForm);
            if (createdEl)  panel.appendChild(createdEl);
            if (listLoading) panel.appendChild(listLoading);
            if (listEmpty)   panel.appendChild(listEmpty);
            if (listEl)      panel.appendChild(listEl);

            // ── Helpers ──
            function escHtml(s) {
                return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            function showListLoading() { listLoading.style.display=''; listEmpty.style.display='none'; listEl.innerHTML=''; }
            function hideListLoading() { listLoading.style.display='none'; }
            function showListEmpty()  { listLoading.style.display='none'; listEmpty.style.display=''; listEl.innerHTML=''; }

            // ── Render token list ──
            function renderTokens(tokens) {
                hideListLoading();
                if (!tokens || tokens.length === 0) { showListEmpty(); return; }
                listEl.innerHTML = '';
                tokens.forEach(function (t) {
                    var row = document.createElement('div');
                    row.className = 'list-group-item d-flex align-items-center justify-content-between py-2 px-3';

                    var info = document.createElement('div');
                    info.className = 'd-flex align-items-center gap-2';

                    var icon = document.createElement('i');
                    icon.className = 'bi bi-key text-muted';
                    info.appendChild(icon);

                    var nameSpan = document.createElement('span');
                    nameSpan.className = 'small';
                    nameSpan.textContent = (t.name && t.name !== '') ? t.name : '(unnamed)';
                    info.appendChild(nameSpan);

                    var createdSpan = document.createElement('small');
                    createdSpan.className = 'text-muted ms-2';
                    createdSpan.textContent = t.created_at || '';
                    info.appendChild(createdSpan);

                    var actions = document.createElement('div');
                    actions.className = 'd-flex align-items-center gap-2';

                    var isRevoked = !!t.revoked_at;
                    var isExpired = !isRevoked && t.expires_at && new Date(t.expires_at) < new Date();
                    var expiredSpan = document.createElement('span');
                    expiredSpan.className = 'badge bg-secondary';
                    expiredSpan.textContent = isRevoked ? 'Revoked' : (isExpired ? 'Expired' : '');
                    expiredSpan.style.display = (isRevoked || isExpired ? '' : 'none');
                    actions.appendChild(expiredSpan);

                    var revokeBtn = document.createElement('button');
                    revokeBtn.className = 'btn btn-sm btn-outline-danger';
                    revokeBtn.type = 'button';
                    revokeBtn.textContent = 'Revoke';
                    revokeBtn.disabled = isRevoked;
                    revokeBtn.setAttribute('data-token-id', t.id);
                    revokeBtn.addEventListener('click', function () {
                        if (!confirm('Revoke this token?')) return;
                        revokeBtn.disabled = true;
                        revokeBtn.textContent = 'Revoking…';
                        fetch('/api/tokens/' + t.id, { method: 'DELETE', credentials: 'same-origin' })
                            .then(function (r) {
                                if (!r.ok) throw new Error('Failed to revoke');
                                return r.json();
                            })
                            .then(function () { loadTokens(); })
                            .catch(function () {
                                revokeBtn.disabled = false;
                                revokeBtn.textContent = 'Revoke';
                            });
                    });
                    actions.appendChild(revokeBtn);

                    row.appendChild(info);
                    row.appendChild(actions);
                    listEl.appendChild(row);
                });
            }

            // ── Load tokens ──
            function loadTokens() {
                showListLoading();
                fetch('/api/tokens', { credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Failed to load tokens');
                        return r.json();
                    })
                    .then(function (data) { renderTokens(data.tokens || []); })
                    .catch(function () { showListEmpty(); });
            }

            // ── Create token handler ──
            if (!createForm) { console.error('PROFILE MODAL ERROR: #pm-token-create-form is null'); return; }
            createForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = document.getElementById('pm-token-create-btn');
                var createError = document.getElementById('pm-token-create-error');
                var label = btn.querySelector('.create-label');
                var loader = btn.querySelector('.loading-label');
                var tokenName = nameInput.value.trim();

                if (tokenName === '') { nameInput.focus(); return; }

                btn.disabled = true;
                label.classList.add('d-none');
                loader.classList.remove('d-none');
                if (createError) { createError.classList.add('d-none'); createError.innerHTML = ''; }

                fetch('/api/tokens', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: tokenName }),
                })
                .then(function (r) {
                    if (!r.ok) throw new Error('Failed to create token');
                    return r.json();
                })
                .then(function (data) {
                    if (data.token) {
                        valueEl.textContent = data.token;
                        createdEl.classList.remove('d-none');
                    }
                    if (data.record) {
                        loadTokens();
                    }
                })
                .catch(function (err) {
                    if (createError) {
                        createError.classList.remove('d-none');
                        createError.textContent = err.message || 'Could not create token.';
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    label.classList.remove('d-none');
                    loader.classList.add('d-none');
                    nameInput.value = '';
                    nameInput.focus();
                });
            });

            // Initial load
            loadTokens();

            // Focus token name input for accessibility
            if (nameInput) setTimeout(function () { nameInput.focus(); }, 200);
        });
    })();
})();
</script>
