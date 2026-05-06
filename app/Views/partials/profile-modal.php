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
                        <!-- Plaintext token display (shown once after creation) -->
                        <div class="alert alert-warning d-none" id="pm-token-created" role="alert">
                            <div class="small fw-semibold mb-1">Your new token — save it now, it won't be shown again:</div>
                            <code class="d-block bg-body-secondary p-2 mb-2" id="pm-token-value" style="word-break:break-all;user-select:all;"></code>
                            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="document.getElementById('pm-token-created').classList.add('d-none');">Dismiss</button>
                        </div>

                        <!-- Create token form -->
                        <div class="px-3 py-3 border-bottom">
                            <form id="pm-token-create-form" class="d-flex gap-2 align-items-start">
                                <input type="text" id="pm-token-name" class="form-control form-control-sm" placeholder="Token name" required aria-label="Token name" style="min-width:180px;">
                                <button type="submit" class="btn btn-sm btn-primary" id="pm-token-create-btn">
                                    <span class="create-label">Create Token</span>
                                    <span class="loading-label d-none"><span class="spinner-border spinner-border-sm me-1"></span>Creating…</span>
                                </button>
                            </form>
                            <div class="profile-modal-error d-none mt-2 small" id="pm-token-create-error" role="alert"></div>
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
