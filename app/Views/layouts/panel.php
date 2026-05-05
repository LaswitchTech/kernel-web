<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= htmlspecialchars($appName) ?></title>

    <!-- Apply stored theme before CSS renders to prevent flash of wrong theme -->
    <script>
    (function(){
        var t=localStorage.getItem('kernel-web-theme')||'auto',e=document.documentElement;
        e.setAttribute('data-bs-theme',
            t==='light' ? 'light' :
            t==='dark'  ? 'dark'  :
            window.matchMedia('(prefers-color-scheme:light)').matches ? 'light' : 'dark'
        );
    }());
    </script>

    <!-- Bootstrap 5.3.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- DataTables 1.13.8 + Responsive 2.5.0 + Buttons 2.4.2 -->
    <link rel="stylesheet" href="/assets/vendor/datatables/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <!-- App theme — dynamically compiled LESS -->
    <link rel="stylesheet" href="/css">

    <?php
    // Hook: layout.head — plugins can inject meta tags, CSS links, etc.
    echo \App\Core\HookRegistry::render('layout.head');
    ?>
</head>
<body>

<?php
// Hook: layout.body.start — plugins can inject content at the start of <body>
echo \App\Core\HookRegistry::render('layout.body.start');
?>

<?php
// $displayName should be set by the controller before ob_start().
// Falls back to display_name or username from the user session row.
if (!isset($displayName) || $displayName === '') {
    $displayName = ($user['display_name'] ?? '') !== ''
        ? $user['display_name']
        : $user['username'];
}

// $activeSection may be set by controllers to mark a nav item as active
// independently of $pageTitle (e.g. sub-pages like Add/Edit Device).
// If not set, fall back to the current request URI for URL matching.
$navActive = $activeSection ?? $_SERVER['REQUEST_URI'] ?? '/';
?>

<!-- Overlay for mobile sidebar -->
<div class="app-sidebar-overlay" id="sidebar-overlay"></div>

<div class="app-shell">

    <!-- ============================================================
         Sidebar — admin-only navigation
         ============================================================ -->
    <aside class="app-sidebar" id="app-sidebar">

        <a class="sidebar-brand" href="/admin">
            <span class="sidebar-brand-icon">
                <i class="bi bi-gear"></i>
            </span>
            <span class="sidebar-brand-text"><?= htmlspecialchars($appName) ?> Admin</span>
        </a>

        <nav class="sidebar-nav">
<?php
// Render admin sidebar from MenuRegistry.
echo \App\Core\MenuHelper::renderSidebar($navActive, $permissions ?? [], [], 'admin-sidebar', $navActive);
?>
        </nav>

    </aside>

    <!-- ============================================================
         Main column
         ============================================================ -->
    <div class="app-main" id="app-main">

        <!-- Topbar -->
        <header class="app-topbar">
            <button class="topbar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                <i class="bi bi-list" style="font-size:1.25rem;"></i>
            </button>

            <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>

            <div class="topbar-actions">

                <!-- Theme selector -->
                <div class="dropdown">
                    <button class="topbar-icon-btn"
                            id="js-theme-toggle"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            title="Switch theme">
                        <i class="bi bi-circle-half" id="js-theme-icon"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:8.5rem">
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2 small"
                                    data-kernel-web-theme="dark">
                                <i class="bi bi-moon-stars-fill"></i>Dark
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2 small"
                                    data-kernel-web-theme="light">
                                <i class="bi bi-sun-fill"></i>Light
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2 small"
                                    data-kernel-web-theme="auto">
                                <i class="bi bi-circle-half"></i>Auto
                            </button>
                        </li>
                    </ul>
                </div>

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
                        <?php if (in_array('admin', $permissions ?? [], true)): ?>
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
            </div>
        </header>

        <!-- Page content -->
        <main class="app-content">
<?php if (isset($breadcrumbs) && is_array($breadcrumbs) && !empty($breadcrumbs)): ?>
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0">
                    <?php $bcLast = count($breadcrumbs) - 1; foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php if ($i === $bcLast): ?>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?= htmlspecialchars($crumb['label']) ?>
                    </li>
                    <?php else: ?>
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars($crumb['url'] ?? '#') ?>" class="text-decoration-none">
                            <?= htmlspecialchars($crumb['label']) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
<?php endif; ?>
            <?= $content ?>
        </main>

        <!-- Footer -->
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></span>
            <?php echo \App\Core\HookRegistry::render('panel.footer'); ?>
        </footer>

    </div><!-- /.app-main -->

</div><!-- /.app-shell -->

<!-- Bootstrap 5.3.3 -->
<script src="/assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<!-- jQuery 3.7.1 (required by DataTables) -->
<script src="/assets/vendor/jquery/3.7.1/jquery.min.js"></script>
<!-- DataTables 1.13.8 + Responsive 2.5.0 + Buttons 2.4.2 -->
<script src="/assets/vendor/datatables/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="/assets/vendor/datatables/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="/assets/vendor/datatables-responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="/assets/vendor/datatables-responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="/assets/vendor/datatables-buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="/assets/vendor/datatables-buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<!-- App shared JS -->
<script src="/assets/js/datatables-init.js"></script>

<script>
(function () {
    // ── Theme toggle ───────────────────────────────────────────────────────
    var THEME_KEY = 'kernel-web-theme';
    var THEME_ICONS = {
        dark  : 'bi-moon-stars-fill',
        light : 'bi-sun-fill',
        auto  : 'bi-circle-half',
    };

    function resolveEffective(pref) {
        if (pref === 'light') return 'light';
        if (pref === 'dark')  return 'dark';
        return window.matchMedia('(prefers-color-scheme:light)').matches ? 'light' : 'dark';
    }

    function applyThemePref(pref) {
        document.documentElement.setAttribute('data-bs-theme', resolveEffective(pref));

        var icon = document.getElementById('js-theme-icon');
        if (icon) {
            icon.className = 'bi ' + (THEME_ICONS[pref] || THEME_ICONS.auto);
        }

        document.querySelectorAll('[data-kernel-web-theme]').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-kernel-web-theme') === pref);
        });
    }

    function setThemePref(pref) {
        localStorage.setItem(THEME_KEY, pref);
        applyThemePref(pref);
    }

    // Apply on every page load (initial render already handled by inline head script)
    applyThemePref(localStorage.getItem(THEME_KEY) || 'auto');

    // Bind dropdown items
    document.querySelectorAll('[data-kernel-web-theme]').forEach(function (el) {
        el.addEventListener('click', function () {
            setThemePref(this.getAttribute('data-kernel-web-theme'));
        });
    });

    // React to system preference changes (relevant when user chose "auto")
    window.matchMedia('(prefers-color-scheme:light)').addEventListener('change', function () {
        var pref = localStorage.getItem(THEME_KEY) || 'auto';
        if (pref === 'auto') {
            applyThemePref('auto');
        }
    });

    // ── Sign-out ───────────────────────────────────────────────────────────
    document.getElementById('js-logout').addEventListener('click', async function () {
        this.disabled = true;
        try {
            await fetch('/auth/logout', { method: 'POST' });
        } finally {
            window.location.href = '/auth/login';
        }
    });

    // Sidebar toggle
    var sidebar  = document.getElementById('app-sidebar');
    var main     = document.getElementById('app-main');
    var overlay  = document.getElementById('sidebar-overlay');
    var toggle   = document.getElementById('sidebar-toggle');
    var mq       = window.matchMedia('(max-width: 991px)');

    function isMobile() { return mq.matches; }

    toggle.addEventListener('click', function () {
        if (isMobile()) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('visible');
        } else {
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        }
    });

    overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
    });
})();
</script>

<?php
// Hook: layout.body.end — plugins can inject JS, analytics, etc. before </body>
echo \App\Core\HookRegistry::render('layout.body.end');
?>

</body>
</html>
