<!-- <div class="dropdown">
    <a class="topbar-user" href="#" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="topbar-avatar">
            <?= htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1))) ?>
        </span>
        <span class="topbar-username"><?= htmlspecialchars($displayName) ?></span>
        <i class="bi bi-chevron-down" style="font-size:0.7rem;color:var(--app-text-muted);"></i>
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header"><?= htmlspecialchars($displayName) ?></h6></li>
        <li>
            <button class="dropdown-item js-profile-trigger" type="button">
                <i class="bi bi-person me-2"></i>Profile
            </button>
        </li>
        <?php
        $canAdmin = in_array('admin', $permissions ?? [], true)
                    || in_array('admin.access', $permissions ?? [], true);
        ?>
        <?php if ($canAdmin): ?>
        <li>
            <a class="dropdown-item" href="/admin">
                <i class="bi bi-gear me-2"></i>Administration
            </a>
        </li>
        <?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li>
            <button class="dropdown-item" id="js-logout">
                <i class="bi bi-box-arrow-right me-2"></i>Sign Out
            </button>
        </li>
    </ul>
</div> -->
<div class="app-topbar-user">
    <div class="app-topbar-user-trigger">
        <div class="dropdown">
            <a class="topbar-user" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="app-topbar-user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1))) ?></div>
                <div class="app-topbar-user-meta">
                    <div class="app-topbar-user-label"><?= htmlspecialchars($displayName) ?></div>
                    <div class="app-topbar-user-subtitle"><?= htmlspecialchars($user['email']) ?></div>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header"><?= htmlspecialchars($user['username']) ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item js-profile-trigger" type="button">
                        <i class="bi bi-person me-2"></i>Profile
                    </button>
                </li>
                <?php
                $canAdmin = in_array('admin', $permissions ?? [], true)
                            || in_array('admin.access', $permissions ?? [], true);
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
