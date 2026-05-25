<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
<div class="col-12 col-md-6">
<div class="input-group input-group-sm" style="max-width:320px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="search"
           id="settings-search"
           class="form-control"
           placeholder="Filter settings…"
           aria-label="Filter settings">
    <button class="btn btn-outline-secondary" type="button" id="settings-search-clear">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<div id="settings-search-empty" class="text-muted small mt-1 d-none">
    No settings match your search.
</div>
</div>
</div>

<form method="POST" action="/admin/settings" novalidate id="settings-form">

<div class="row g-3">

<!-- Application -->
<div class="col-12 col-md-6 js-settings-card">
<div class="card mb-4 h-100 w-100" data-section="application" data-search-text="<?= strtolower(htmlspecialchars('Application Core identity settings for this instance.', ENT_QUOTES)) ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-application"
         aria-expanded="false"
         aria-controls="collapse-application"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-gear-wide me-2 fs-4 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold fs-5 mb-0">Application</div>
                <div class="text-muted" style="font-size:11px">Core identity settings for this instance.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down js-settings-chevron text-muted text-nowrap"></i>
    </div>
    <div class="collapse js-settings-collapse" id="collapse-application">
    <div class="card-body">

        <div class="mb-3">
            <label for="app-name" class="form-label small fw-semibold">
                Application Name <span class="text-danger">*</span>
            </label>
            <input type="text"
               id="app-name"
               name="app_name"
               class="form-control form-control-sm <?= isset($errors['app_name']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($settings['app_name'] ?? '') ?>"
               maxlength="100"
               required>
            <?php if (isset($errors['app_name'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['app_name']) ?></div>
            <?php else: ?>
                <div class="form-text">Displayed in the browser title bar and sidebar header.</div>
            <?php endif; ?>
        </div>

        <div class="mb-0">
            <label for="app-url" class="form-label small fw-semibold">
                Application URL <span class="text-danger">*</span>
            </label>
            <input type="url"
               id="app-url"
               name="app_url"
               class="form-control form-control-sm <?= isset($errors['app_url']) ? 'is-invalid' : '' ?>"
               value="<?= htmlspecialchars($settings['app_url'] ?? '') ?>"
               maxlength="255"
               placeholder="https://kernel-web.example.com"
               required>
            <?php if (isset($errors['app_url'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['app_url']) ?></div>
            <?php else: ?>
                <div class="form-text">Public-facing URL. Used in email links and external references. No trailing slash.</div>
            <?php endif; ?>
        </div>

    </div>
    </div>
</div>
</div>

<!-- Authentication -->
<div class="col-12 col-md-6 js-settings-card">
<div class="card mb-4 h-100 w-100" data-section="authentication" data-search-text="<?= strtolower(htmlspecialchars('Authentication Security-related configuration. System-wide 2FA Enforcement Require all users to complete 2FA challenge on login.', ENT_QUOTES)) ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-authentication"
         aria-expanded="false"
         aria-controls="collapse-authentication"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-shield-lock me-2 fs-4 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold fs-5 mb-0">Authentication</div>
                <div class="text-muted" style="font-size:11px">Security-related configuration.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down js-settings-chevron text-muted text-nowrap"></i>
    </div>
    <div class="collapse js-settings-collapse" id="collapse-authentication">
    <div class="card-body">

        <div class="d-flex align-items-center justify-content-between">
            <div>
                <p class="mb-1 small fw-semibold">System-wide 2FA Enforcement</p>
                <p class="text-muted small mb-0">
                    Require all users to complete 2FA challenge on login. Users without 2FA are not affected.
                </p>
            </div>
            <div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
               id="settings_2fa_enforced"
 <?= ($settings['auth.two_factor.enforced'] ?? false) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="settings_2fa_enforced">
                        <?= ($settings['auth.two_factor.enforced'] ?? false) ? 'On' : 'Off' ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-text mt-2 mb-0">
            Writes to <code>config/local.php</code> — takes effect immediately on next login.
        </div>
        <div id="toggle-2fa-result" class="mt-2"></div>

    </div>
    </div>
</div>
</div>

<!-- Developer (from SettingsRegistry) -->
<?php
$__dev_sec__ = null;
$__dev_label__ = '';
foreach ($sections as $_sec):
    if ($_sec->id === 'developer') { $__dev_sec__ = $_sec; $__dev_label__ = $_sec->label; break; }
endforeach;
if ($__dev_sec__ !== null):
$__dev_body__ = $__dev_sec__->renderBody(['errors' => $errors, 'settings' => $settings]);
$__dev_search__ = strtolower(htmlspecialchars($__dev_label__ . ' ' . strip_tags($__dev_body__), ENT_QUOTES));
?>
<div class="col-12 col-md-6 js-settings-card">
<div class="card mb-4 h-100 w-100" data-section="developer" data-search-text="<?= $__dev_search__ ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-developer"
         aria-expanded="false"
         aria-controls="collapse-developer"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-terminal me-2 fs-4 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold fs-5 mb-0"><?= htmlspecialchars($__dev_label__) ?></div>
                <div class="text-muted" style="font-size:11px">Development and debugging features.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down js-settings-chevron text-muted text-nowrap"></i>
    </div>
    <div class="collapse js-settings-collapse" id="collapse-developer">
    <div class="card-body">
        <?= $__dev_body__ ?>
    </div>
    </div>
</div>
</div>
<?php endif; unset($__dev_sec__, $__dev_label__, $__dev_body__, $__dev_search__); ?>

<!-- Plugin sections (exclude developer — already rendered above) -->
<?php foreach ($sections as $section):
    if ($section->id === 'developer') continue;
    $safe_id = preg_replace('/[^a-z0-9_-]/', '_', $section->id);
    $section_body = $section->renderBody(['errors' => $errors, 'settings' => $settings]);
    $section_search = strtolower(htmlspecialchars($section->label . ' ' . strip_tags($section_body), ENT_QUOTES));
?>
<div class="col-12 col-md-6 js-settings-card">
<div class="card mb-4 h-100 w-100" data-section="<?= $safe_id ?>" data-search-text="<?= htmlspecialchars($section_search, ENT_QUOTES) ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-<?= $safe_id ?>"
         aria-expanded="false"
         aria-controls="collapse-<?= $safe_id ?>"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-puzzle me-2 fs-4 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold fs-5 mb-0"><?= htmlspecialchars($section->label) ?></div>
            </div>
        </div>
        <i class="bi bi-chevron-down js-settings-chevron text-muted text-nowrap"></i>
    </div>
    <div class="collapse js-settings-collapse" id="collapse-<?= $safe_id ?>">
    <div class="card-body">
        <?= $section_body ?>
    </div>
    </div>
</div>
</div>
<?php endforeach; ?>

<!-- Mailer -->
<div class="col-12 col-md-6 js-settings-card">
<div class="card mb-4 h-100 w-100" data-section="mailer" data-search-text="<?= strtolower(htmlspecialchars('Mailer Mail delivery uses PHP mail function by default. Install the SMTP plugin to configure alternative delivery.', ENT_QUOTES)) ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-mailer"
         aria-expanded="false"
         aria-controls="collapse-mailer"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-envelope me-2 fs-4 text-muted text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold fs-5 mb-0 text-muted">Mailer</div>
                <div class="text-muted" style="font-size:11px">Mail delivery configuration.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down js-settings-chevron text-muted text-nowrap"></i>
    </div>
    <div class="collapse js-settings-collapse" id="collapse-mailer">
    <div class="card-body">
        <p class="text-muted small mb-0">
            Mail delivery uses PHP's <code>mail()</code> function by default.
            Install the SMTP plugin to configure alternative delivery.
        </p>
    </div>
    </div>
</div>
</div>

<!-- Notifications placeholder -->
<div class="col-12 col-md-6 js-settings-card">
<div class="card mb-4 h-100 w-100" data-section="notifications" data-search-text="<?= strtolower(htmlspecialchars('Notifications The notification system is not yet implemented. This section will be populated by notification plugins.', ENT_QUOTES)) ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-notifications"
         aria-expanded="false"
         aria-controls="collapse-notifications"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-bell me-2 fs-4 text-muted text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold fs-5 mb-0 text-muted">Notifications</div>
                <div class="text-muted" style="font-size:11px">Plugin-managed notification settings.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down js-settings-chevron text-muted text-nowrap"></i>
    </div>
    <div class="collapse js-settings-collapse" id="collapse-notifications">
    <div class="card-body">
        <p class="text-muted small mb-0">
            The notification system is not yet implemented.
            This section will be populated by notification plugins.
        </p>
    </div>
    </div>
</div>
</div>

</div> <!-- end .row -->

<div class="mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Save Settings
    </button>
</div>

</form>

<script>
(function() {
    // --- 2FA enforcement toggle (AJAX) ---
    var toggle2fa = document.getElementById('settings_2fa_enforced');
    var result2fa = document.getElementById('toggle-2fa-result');
    if (toggle2fa) {
        toggle2fa.addEventListener('change', function() {
            var checked = toggle2fa.checked;
            toggle2fa.nextElementSibling.textContent = checked ? 'On' : 'Off';

            fetch('/admin/settings/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    key: 'auth.two_factor.enforced',
                    value: checked
                })
            })
            .then(function(resp) { return resp.json(); })
            .then(function(json) {
                if (json.ok) {
                    showResult(result2fa, 'success', json.message);
                } else {
                    toggle2fa.checked = !checked;
                    toggle2fa.nextElementSibling.textContent = checked ? 'On' : 'Off';
                    showResult(result2fa, 'danger', json.error || 'Failed to save.');
                }
            })
            .catch(function() {
                toggle2fa.checked = !checked;
                toggle2fa.nextElementSibling.textContent = checked ? 'On' : 'Off';
                showResult(result2fa, 'danger', 'Network error.');
            });
        });
    }

    // --- Developer section AJAX (existing) ---
    var toggle = document.getElementById('settings_developer');
    var result = document.getElementById('settings-dev-result');
    if (!toggle) return;

    toggle.addEventListener('change', function() {
        var checked = toggle.checked;
        toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';

        var data = new FormData();
        data.set('developer_developer', checked ? '1' : '0');

        fetch('/admin/developer/settings', {
            method: 'POST',
            body: data
        })
        .then(function(resp) { return resp.json(); })
        .then(function(json) {
            if (json.ok) {
                showResult(result, 'success', json.message);
            } else {
                toggle.checked = !checked;
                toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';
                showResult(result, 'danger', json.error || 'Failed to save.');
            }
        })
        .catch(function() {
            toggle.checked = !checked;
            toggle.nextElementSibling.textContent = checked ? 'On' : 'Off';
            showResult(result, 'danger', 'Network error.');
        });
    });

    function showResult(container, type, message) {
        var cls = type === 'success' ? 'alert-success' : 'alert-danger';
        container.innerHTML = '<div class="alert ' + cls + ' alert-dismissible small mb-0">' +
            message +
            '<button type="button" class="btn-close btn-close-sm float-end" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        // Auto-dismiss after 4s
        setTimeout(function() { container.innerHTML = ''; }, 4000);
    }

    // --- Chevron helpers ---
    function updateChevron(collapseEl, isOpen) {
        if (!collapseEl || !collapseEl.id) return;
        var selector = '[data-bs-target="#' + collapseEl.id + '"]';
        var trigger = document.querySelector(selector);
        if (!trigger) return;
        var icon = trigger.querySelector('.js-settings-chevron');
        if (!icon) return;
        if (isOpen) {
            icon.classList.remove('bi-chevron-down');
            icon.classList.add('bi-chevron-up');
        } else {
            icon.classList.remove('bi-chevron-up');
            icon.classList.add('bi-chevron-down');
        }
    }

    // --- Chevron: document-level event delegation (not per-element) ---
    document.addEventListener('shown.bs.collapse', function(event) {
        updateChevron(event.target, true);
    });

    document.addEventListener('hidden.bs.collapse', function(event) {
        updateChevron(event.target, false);
    });

    // Initialize chevrons based on current state
    var initialCollapses = Array.prototype.slice.call(document.querySelectorAll('.js-settings-collapse'));
    initialCollapses.forEach(function(collapseEl) {
        updateChevron(collapseEl, collapseEl.classList.contains('show'));
    });

    // --- Search / filter (defensive, no optional chaining, no .includes) ---
    var searchInput = document.getElementById('settings-search');
    var clearBtn    = document.getElementById('settings-search-clear');
    var emptyState  = document.getElementById('settings-search-empty');

    function getCards() {
        return Array.prototype.slice.call(document.querySelectorAll('.js-settings-card'));
    }

    function applySettingsFilter() {
        if (!searchInput) return;

        var query = searchInput.value.trim().toLowerCase();
        var visible = 0;
        var cards = getCards();

        for (var i = 0; i < cards.length; i++) {
            var wrapper = cards[i];
            var card = wrapper.querySelector('.card');
            var text = card ? (card.textContent || '').toLowerCase() : '';
            var matches = query === '' || text.indexOf(query) !== -1;

            wrapper.classList.toggle('d-none', !matches);
            if (matches) visible++;
        }

        if (emptyState) {
            emptyState.classList.toggle('d-none', visible !== 0);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applySettingsFilter);

        // Prevent Enter from submitting the form
        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function(event) {
            event.preventDefault();
            if (!searchInput) return;
            searchInput.value = '';
            applySettingsFilter();
            searchInput.focus();
        });
    }

    // Initial state
    applySettingsFilter();
})();
</script>
