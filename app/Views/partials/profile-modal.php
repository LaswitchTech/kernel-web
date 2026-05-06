<div class="modal fade" id="profile-modal" tabindex="-1" aria-labelledby="profile-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profile-modal-label">Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="profile-loading" class="text-center py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    <span class="text-muted">Loading…</span>
                </div>
                <div id="profile-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="profile-content" class="d-none">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Username</dt>
                        <dd class="col-sm-8" id="pm-username"></dd>
                        <dt class="col-sm-4 text-muted">Email</dt>
                        <dd class="col-sm-8" id="pm-email"></dd>
                        <dt class="col-sm-4 text-muted">Display Name</dt>
                        <dd class="col-sm-8" id="pm-display-name"></dd>
                        <dt class="col-sm-4 text-muted">Member Since</dt>
                        <dd class="col-sm-8" id="pm-created"></dd>
                    </dl>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
