<div class="app-topbar-user">
    <div class="app-topbar-user-trigger">
        <div class="dropdown">
            <a class="topbar-user" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="app-topbar-user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($currentUserDisplayName ?? '', 0, 1))) ?></div>
                <div class="app-topbar-user-meta">
                    <div class="app-topbar-user-label"><?= htmlspecialchars($currentUserDisplayName ?? '') ?></div>
                    <div class="app-topbar-user-subtitle"><?= $currentUserIsAdmin ? "Administrator" : "User" ?></div>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end mt-2" style="min-width: 300px;">
                <li>
                    <div class="d-flex align-items-center justify-content-center p-3">
                        <div class="app-topbar-user-avatar fs-2 fw-light" style="width: 4.5rem; height: 4.5rem;"><?= htmlspecialchars(mb_strtoupper(mb_substr($currentUserDisplayName ?? '', 0, 1))) ?></div>
                    </div>
                    <h4 class="d-flex align-items-center justify-content-center m-0 fw-light"><?= htmlspecialchars($currentUserDisplayName ?? '') ?></h4>
                    <div class="d-flex align-items-center justify-content-center text-body-secondary mb-3"><?= $currentUserIsAdmin ? "Administrator" : "User" ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item js-profile-trigger" type="button">
                        <i class="bi bi-person me-2"></i>Profile
                    </button>
                </li>
                <?php
                $_perms = $currentUserPermissions ?? [];
                $canAdmin = in_array('admin', $_perms, true)
                            || in_array('admin.access', $_perms, true);
                ?>
                <?php if ($canAdmin): ?>
                <li>
                    <a class="dropdown-item" href="/admin">
                        <i class="bi bi-gear me-2"></i>Administration
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="app-topbar-user-actions">
            <button id="js-logout" class="app-topbar-user-action">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </div>
    </div>
</div>
