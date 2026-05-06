<div class="dropdown">
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
            <a class="dropdown-item" href="/profile">
                <i class="bi bi-person me-2"></i>Profile
            </a>
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
</div>
