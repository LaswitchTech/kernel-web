<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-4" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings" novalidate id="settings-form">

<!-- Search -->
<div class="mb-3">
    <div class="input-group input-group-sm" style="max-width:320px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search"
               id="settings-search"
               class="form-control"
               placeholder="Filter settings…"
               aria-label="Filter settings">
        <button class="btn btn-outline-secondary" type="button" id="settings-search-clear" style="display:none">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div id="settings-search-empty" class="text-muted small mt-1" style="display:none">
        No settings match your search.
    </div>
</div>

<div class="row g-3">

<!-- Application -->
<div class="col-12 col-md-6">
<div class="card mb-4 h-100 w-100" data-section="application">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-application"
         aria-expanded="false"
         aria-controls="collapse-application"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-gear-wide me-2 fs-5 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold small">Application</div>
                <div class="text-muted" style="font-size:11px">Core identity settings for this instance.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down text-muted text-nowrap"></i>
    </div>
    <div class="collapse" id="collapse-application">
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
<div class="col-12 col-md-6">
<div class="card mb-4 h-100 w-100" data-section="authentication">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-authentication"
         aria-expanded="false"
         aria-controls="collapse-authentication"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-shield-lock me-2 fs-5 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold small">Authentication</div>
                <div class="text-muted" style="font-size:11px">Security-related configuration.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down text-muted text-nowrap"></i>
    </div>
    <div class="collapse" id="collapse-authentication">
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
foreach ($sections as $_sec):
    if ($_sec->id === 'developer') { $__dev_sec__ = $_sec; break; }
endforeach;
if ($__dev_sec__ !== null): ?>
<div class="col-12 col-md-6">
<div class="card mb-4 h-100 w-100" data-section="developer">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-developer"
         aria-expanded="false"
         aria-controls="collapse-developer"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-terminal me-2 fs-5 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold small">Developer Settings</div>
                <div class="text-muted" style="font-size:11px">Development and debugging features.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down text-muted text-nowrap"></i>
    </div>
    <div class="collapse" id="collapse-developer">
    <div class="card-body">
        <?= $__dev_sec__->renderBody(['errors' => $errors, 'settings' => $settings]) ?>
    </div>
    </div>
</div>
</div>
<?php endif; unset($__dev_sec__); ?>

<!-- Plugin sections (exclude developer — already rendered above) -->
<?php foreach ($sections as $section):
    if ($section->id === 'developer') continue;
    $safe_id = preg_replace('/[^a-z0-9_-]/', '_', $section->id);
    $search_text = htmlspecialchars($section->label) . ' ' . $section->label;
?>
<div class="col-12 col-md-6">
<div class="card mb-4 h-100 w-100" data-section="<?= $safe_id ?>">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-<?= $safe_id ?>"
         aria-expanded="false"
         aria-controls="collapse-<?= $safe_id ?>"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-puzzle me-2 fs-5 text-primary text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold small"><?= htmlspecialchars($section->label) ?></div>
            </div>
        </div>
        <i class="bi bi-chevron-down text-muted text-nowrap"></i>
    </div>
    <div class="collapse" id="collapse-<?= $safe_id ?>">
    <div class="card-body">
        <?= $section->renderBody(['errors' => $errors, 'settings' => $settings]) ?>
    </div>
    </div>
</div>
</div>
<?php endforeach; ?>

<!-- Mailer -->
<div class="col-12 col-md-6">
<div class="card mb-4 h-100 w-100" data-section="mailer">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-mailer"
         aria-expanded="false"
         aria-controls="collapse-mailer"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-envelope me-2 fs-5 text-muted text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold small text-muted">Mailer</div>
                <div class="text-muted" style="font-size:11px">Mail delivery configuration.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down text-muted text-nowrap"></i>
    </div>
    <div class="collapse" id="collapse-mailer">
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
<div class="col-12 col-md-6">
<div class="card mb-4 h-100 w-100" data-section="notifications">
    <div class="card-header d-flex align-items-center justify-content-between py-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#collapse-notifications"
         aria-expanded="false"
         aria-controls="collapse-notifications"
         tabindex="0">
        <div class="d-flex align-items-center overflow-hidden me-2">
            <i class="bi bi-bell me-2 fs-5 text-muted text-nowrap"></i>
            <div class="text-truncate">
                <div class="fw-semibold small text-muted">Notifications</div>
                <div class="text-muted" style="font-size:11px">Plugin-managed notification settings.</div>
            </div>
        </div>
        <i class="bi bi-chevron-down text-muted text-nowrap"></i>
    </div>
    <div class="collapse" id="collapse-notifications">
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

    // --- Search / filter ---
    var searchInput = document.getElementById('settings-search');
    var clearBtn = document.getElementById('settings-search-clear');
    var emptyMsg = document.getElementById('settings-search-empty');
    if (!searchInput) return;

    var allCards = Array.from(document.querySelectorAll('.row.g-3 > .col-12'));
    var allCardsNoWrap = Array.from(document.querySelectorAll('[data-section]'));

    searchInput.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        clearBtn.style.display = q.length > 0 ? '' : 'none';

        var visibleCount = 0;
        var cards = allCardsNoWrap.length > 0 ? allCardsNoWrap : allCards;
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var section = (card.getAttribute('data-section') || '').toLowerCase();
            var searchText = (card.textContent || card.innerText || '').toLowerCase();
            var match = !q || searchText.indexOf(q) !== -1 || section.indexOf(q) !== -1;
            card.closest('.col-12, [data-section]')
                ? (match ? card.closest('.col-12, .col-md-6').style.display = '' : card.closest('.col-12, .col-md-6').style.display = 'none')
                : (match ? card.style.display = '' : card.style.display = 'none');
            // Actually filter the column wrapper
            var col = card.closest('.col-12');
            if (col) {
                var innerText = (col.textContent || col.innerText || '').toLowerCase();
                var sectionAttr = (card.getAttribute('data-section') || '').toLowerCase();
                var m = !q || innerText.indexOf(q) !== -1 || sectionAttr.indexOf(q) !== -1;
                col.style.display = m ? '' : 'none';
                if (m) visibleCount++;
            } else {
                // fallback: just hide/show card directly
                card.style.display = (!q || (card.textContent || '').toLowerCase().indexOf(q) !== -1) ? '' : 'none';
                if (!q || (card.textContent || '').toLowerCase().indexOf(q) !== -1) visibleCount++;
            }
        }
        emptyMsg.style.display = visibleCount === 0 && q.length > 0 ? '' : 'none';
    });

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });
})();
</script>
