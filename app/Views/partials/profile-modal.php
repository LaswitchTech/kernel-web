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

                    <!-- API Tokens placeholder tab -->
                    <div class="tab-pane fade" id="panel-tokens" role="tabpanel" aria-labelledby="tab-tokens">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-key d-block mb-3" style="font-size:2rem;opacity:.35;"></i>
                            <p class="mb-0">API token management will be available here.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
