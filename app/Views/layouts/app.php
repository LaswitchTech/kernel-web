<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= htmlspecialchars($appName) ?></title>

    <!-- Bootstrap 5.3.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- DataTables 2.3.8 + Buttons 3.2.6 + Responsive 3.0.8 + Select 3.1.3 + StateRestore 1.4.3 + RowGroup 1.6.0 + Scroller 2.4.3 + ColumnControl 1.2.1
         Core CSS embedded in JS. Extension CSS + BS5 integration files below. -->
    <link rel="stylesheet" href="/assets/vendor/datatables/2.3.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-buttons/3.2.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-responsive/3.0.8/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-select/3.1.3/css/select.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-rowgroup/1.6.0/css/rowGroup.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-scroller/2.4.3/css/scroller.bootstrap5.min.css">
    <link rel="stylesheet" href="/assets/vendor/datatables-columncontrol/1.2.1/css/columnControl.bootstrap5.min.css">
    <!-- App theme — dynamically compiled LESS -->
    <link rel="stylesheet" href="/css">

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
    <script src="/assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery 3.7.1 (required by DataTables) -->
    <script src="/assets/vendor/jquery/3.7.1/jquery.min.js"></script>
    <!-- Export support deps — required by Buttons HTML5 -->
    <script src="/assets/vendor/jszip/3.10.1/jszip.min.js"></script>
    <script src="/assets/vendor/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="/assets/vendor/pdfmake/0.2.7/vfs_fonts.js"></script>
    <!-- DataTables 2.3.8 (Bootstrap 5 classes built into core) -->
    <script src="/assets/vendor/datatables/2.3.8/js/dataTables.min.js"></script>
    <script src="/assets/vendor/datatables/2.3.8/js/dataTables.bootstrap5.min.js"></script>
    <!-- Buttons 3.2.6 — core + BS5 integration + export extensions -->
    <script src="/assets/vendor/datatables-buttons/3.2.6/js/dataTables.buttons.min.js"></script>
    <script src="/assets/vendor/datatables-buttons/3.2.6/js/buttons.bootstrap5.min.js"></script>
    <script src="/assets/vendor/datatables-buttons/3.2.6/js/buttons.html5.min.js"></script>
    <script src="/assets/vendor/datatables-buttons/3.2.6/js/buttons.print.min.js"></script>
    <script src="/assets/vendor/datatables-buttons/3.2.6/js/buttons.colVis.min.js"></script>
    <!-- Responsive 3.0.8 -->
    <script src="/assets/vendor/datatables-responsive/3.0.8/js/dataTables.responsive.min.js"></script>
    <script src="/assets/vendor/datatables-responsive/3.0.8/js/responsive.bootstrap5.min.js"></script>
    <!-- Select 3.1.3 -->
    <script src="/assets/vendor/datatables-select/3.1.3/js/dataTables.select.min.js"></script>
    <script src="/assets/vendor/datatables-select/3.1.3/js/select.bootstrap5.min.js"></script>
    <!-- StateRestore 1.4.3 (BS5 built into core) -->
    <script src="/assets/vendor/datatables-staterestore/1.4.3/js/dataTables.stateRestore.min.js"></script>
    <!-- RowGroup 1.6.0 (BS5 built into core) -->
    <script src="/assets/vendor/datatables-rowgroup/1.6.0/js/dataTables.rowGroup.min.js"></script>
    <!-- Scroller 2.4.3 -->
    <script src="/assets/vendor/datatables-scroller/2.4.3/js/dataTables.scroller.min.js"></script>
    <script src="/assets/vendor/datatables-scroller/2.4.3/js/scroller.bootstrap5.min.js"></script>
    <!-- ColumnControl 1.2.1 (BS5 built into core) -->
    <script src="/assets/vendor/datatables-columncontrol/1.2.1/js/dataTables.columnControl.min.js"></script>
    <!-- App shared JS -->
    <script src="/assets/js/datatables-init.js"></script>

    <?php
    // Hook: layout.head — plugins can inject meta tags, CSS links, etc.
    echo \App\Core\HookRegistry::render('layout.head');
    ?>

    <!-- KernelWeb — always defined before body scripts run -->
    <script>
    window.KernelWeb = window.KernelWeb || {};
    window.KernelWeb._dtInitQueue = window.KernelWeb._dtInitQueue || [];
    window.KernelWeb.dt = window.KernelWeb.dt || {
        init: function(s, o) { window.KernelWeb._dtInitQueue.push(function() { $(s).DataTable($.extend(true, {}, o || {})); }); },
        initCompact: function(s, o) { window.KernelWeb._dtInitQueue.push(function() { $(s).DataTable($.extend(true, {}, o || {})); }); },
    };
    </script>
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
$navActive = $activeSection ?? $pageTitle;

// Total unread chat message count for the Chat sidebar badge.
// Only queried for users with chat.use permission.
$chatUnreadCount = 0;
if (in_array('chat.use', $permissions ?? [], true)) {
    try {
        $chatUserId      = (int) ($principal['user']['id'] ?? 0);
        $chatUnreadCount = (new \App\Modules\Chat\Models\ChatRoomMemberRepository(
            $this->container->get('db')
        ))->countUnreadForUser($chatUserId);
    } catch (\Throwable) {
        // Non-critical — badge simply shows 0 on failure.
    }
}
?>

<!-- Overlay for mobile sidebar -->
<div class="app-sidebar-overlay" id="sidebar-overlay"></div>

<div class="app-shell">

    <!-- ============================================================
         Sidebar
         ============================================================ -->
    <aside class="app-sidebar" id="app-sidebar">

        <a class="sidebar-brand" href="/">
            <span class="sidebar-brand-icon">
                <i class="bi bi-activity"></i>
            </span>
            <span class="sidebar-brand-text"><?= htmlspecialchars($appName) ?></span>
        </a>

        <nav class="sidebar-nav">
<?php
// Render sidebar from MenuRegistry.
// $chatUnreadCount is available from the PHP scope above (computed earlier).
$sidebarBadges = ['/chat' => $chatUnreadCount];
echo \App\Core\MenuHelper::renderSidebar($navActive, $permissions ?? [], $sidebarBadges);
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

                <!-- Notification bell dropdown -->
                <div class="dropdown" id="notif-dropdown">
                    <button class="topbar-icon-btn position-relative"
                            id="js-notif-bell"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            aria-expanded="false"
                            title="Notifications">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle
                                     badge rounded-pill bg-danger js-topbar-notif-badge"
                              style="display:none; font-size:.6rem; min-width:1.2em; padding:.18em .4em;"></span>
                    </button>

                    <!-- Notification panel -->
                    <div class="dropdown-menu dropdown-menu-end notif-panel p-0"
                         id="js-notif-panel"
                         aria-labelledby="js-notif-bell">

                        <!-- Panel header -->
                        <div class="notif-panel-header d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                            <span class="fw-semibold small">Notifications</span>
                            <button class="btn btn-link btn-sm text-muted p-0 small"
                                    id="js-notif-mark-all"
                                    style="display:none; font-size:.8rem;">
                                Mark all read
                            </button>
                        </div>

                        <!-- Loading state -->
                        <div id="js-notif-loading" class="px-3 py-4 text-center text-muted small">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Loading…
                        </div>

                        <!-- Notification list -->
                        <div id="js-notif-list" style="display:none; overflow-y:auto; max-height:340px;"></div>

                        <!-- Footer -->
                        <div class="notif-panel-footer border-top text-center px-3 py-2" style="display:none;" id="js-notif-footer">
                            <a href="/notifications" class="small text-muted text-decoration-none">
                                View all notifications
                            </a>
                        </div>

                    </div>
                </div>

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

                <?php include __DIR__ . '/../partials/user-menu.php'; ?>
            </div>
        </header>

        <!-- Page content -->
        <main class="app-content">
            <?= $content ?>
        </main>

        <!-- Footer -->
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></span>
            <?php echo \App\Core\HookRegistry::render('panel.footer'); ?>
        </footer>

    </div><!-- /.app-main -->

</div><!-- /.app-shell -->

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

    // ── Notification badge + dropdown ──────────────────────────────────────
    function updateBadge(count) {
        var badges = document.querySelectorAll('.js-topbar-notif-badge');
        badges.forEach(function (el) {
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : String(count);
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }

    // Fetch and display unread count on page load.
    (async function () {
        try {
            var r = await fetch('/api/notifications/count', { credentials: 'same-origin' });
            if (!r.ok) return;
            var data = await r.json();
            updateBadge(data.count);
        } catch (e) { /* silent */ }
    }());

    // ── Topbar notification dropdown ───────────────────────────────────────
    (function () {
        var bell       = document.getElementById('js-notif-bell');
        var panel      = document.getElementById('js-notif-panel');
        var loading    = document.getElementById('js-notif-loading');
        var list       = document.getElementById('js-notif-list');
        var footer     = document.getElementById('js-notif-footer');
        var markAllBtn = document.getElementById('js-notif-mark-all');

        var loaded = false;

        function escHtml(s) {
            return String(s || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function renderNotifList(notifications, unreadCount) {
            loading.style.display = 'none';
            list.innerHTML = '';

            if (notifications.length === 0) {
                list.innerHTML =
                    '<div class="px-3 py-4 text-center text-muted small">' +
                    '<i class="bi bi-bell-slash d-block mb-2" style="font-size:1.5rem;opacity:.4;"></i>' +
                    'No notifications yet.</div>';
            } else {
                notifications.forEach(function (n) {
                    var item = document.createElement('div');
                    item.className = 'notif-item' + (n.is_unread ? ' unread' : '');
                    item.dataset.id = n.id;

                    var sourceTag = n.source_type
                        ? '<span class="badge bg-secondary text-uppercase ms-1" style="font-size:.6rem;">' +
                          escHtml(n.source_type) + '</span>'
                        : '';

                    item.innerHTML =
                        '<div class="notif-item-dot"></div>' +
                        '<div class="notif-item-body">' +
                            '<div class="notif-item-title">' +
                                escHtml(n.title) + sourceTag +
                            '</div>' +
                            '<div class="notif-item-meta">' + escHtml(n.created_at) + '</div>' +
                        '</div>' +
                        (n.is_unread
                            ? '<button class="notif-item-read-btn" title="Mark as read" data-id="' + n.id + '">' +
                              '<i class="bi bi-check2"></i></button>'
                            : '');

                    list.appendChild(item);
                });
            }

            list.style.display = '';
            footer.style.display = '';

            markAllBtn.style.display = unreadCount > 0 ? '' : 'none';
        }

        function loadNotifications() {
            loaded = false;
            loading.style.display = '';
            list.style.display = 'none';
            footer.style.display = 'none';
            markAllBtn.style.display = 'none';

            fetch('/api/notifications/recent', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loaded = true;
                    renderNotifList(data.notifications || [], data.unread_count || 0);
                    updateBadge(data.unread_count || 0);
                })
                .catch(function () {
                    loading.innerHTML =
                        '<span class="text-danger small">Failed to load notifications.</span>';
                });
        }

        // Load when dropdown opens
        bell.addEventListener('show.bs.dropdown', function () {
            loadNotifications();
        });

        // Mark one as read
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('.notif-item-read-btn');
            if (!btn) return;
            var id   = parseInt(btn.dataset.id, 10);
            var item = btn.closest('.notif-item');
            btn.disabled = true;

            fetch('/notifications/' + id + '/read', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    item.classList.remove('unread');
                    btn.remove();
                    item.querySelector('.notif-item-dot').style.opacity = '0';
                    updateBadge(data.unread_count);
                    if (data.unread_count === 0) markAllBtn.style.display = 'none';
                }
            })
            .catch(function () { btn.disabled = false; });
        });

        // Mark all read
        markAllBtn.addEventListener('click', function () {
            markAllBtn.disabled = true;

            fetch('/notifications/read-all', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    list.querySelectorAll('.notif-item.unread').forEach(function (item) {
                        item.classList.remove('unread');
                        var btn = item.querySelector('.notif-item-read-btn');
                        if (btn) btn.remove();
                        var dot = item.querySelector('.notif-item-dot');
                        if (dot) dot.style.opacity = '0';
                    });
                    updateBadge(0);
                    markAllBtn.style.display = 'none';
                }
                markAllBtn.disabled = false;
            })
            .catch(function () { markAllBtn.disabled = false; });
        });
    }());

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

// ── Chat unread badge poller ───────────────────────────────────────────────
// Refreshes the sidebar Chat badge every 30 seconds via /api/chat/unread.
// Only runs when the badge element is present (i.e. user has chat.use).
// Failures are swallowed silently — the server-rendered initial count remains.
(function () {
    var badge = document.getElementById('js-chat-unread-badge');
    if (!badge) { return; }

    function refreshChatUnread() {
        fetch('/api/chat/unread', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { return; }
                var count = data.unread_count || 0;
                badge.textContent = count;
                badge.style.display = count > 0 ? '' : 'none';
            })
            .catch(function () { /* silent */ });
    }

    setInterval(refreshChatUnread, 30000);
}());
</script>

<?php
// Hook: layout.body.end — plugins can inject JS, analytics, etc. before </body>
echo \App\Core\HookRegistry::render('layout.body.end');
?>

<!-- Profile modal -->
<?php include __DIR__ . '/../partials/profile-modal.php'; ?>

<!-- Developer Tools -->
<?php include __DIR__ . '/../partials/dev-tools-offcanvas.php'; ?>

</body>
</html>
