<?php
/**
 * Developer Tools — floating offcanvas.
 *
 * Only rendered when BOTH $app['developer'] and $app['debug'] are true.
 * Sensitive values are masked automatically.
 */

$appConfig  = ($config['app'] ?? $config) ?? [];
$devEnabled = (bool) ($appConfig['developer'] ?? false);
$debugEnabled = (bool) ($appConfig['debug'] ?? $appConfig['debug'] ?? false);

if (!$devEnabled || !$debugEnabled) {
    return;
}
?>

<!-- Floating toggle button — right edge, vertically centered -->
<button type="button"
        class="btn btn-primary btn-sm dev-tools-toggle"
        id="dev-tools-toggle"
        data-bs-toggle="offcanvas"
        data-bs-target="#dev-tools-offcanvas"
        aria-label="Open Developer Tools"
        title="Developer Tools">
    <i class="bi bi-wrench-adjustable-circle"></i>
</button>

<!-- Offcanvas panel -->
<div class="offcanvas offcanvas-end dev-tools-offcanvas"
     tabindex="-1"
     id="dev-tools-offcanvas"
     aria-labelledby="dev-tools-label">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="dev-tools-label">Developer Tools</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column" style="height: calc(100vh - 56px);">

        <!-- Variables section -->
        <div class="dev-tools-section" id="dev-tools-section-vars">
            <div class="d-flex align-items-center gap-2 mb-2">
                <h6 class="mb-0 flex-grow-1">Variables</h6>
                <input type="text"
                       class="form-control form-control-sm"
                       id="dev-tools-search"
                       placeholder="Filter variables…"
                       style="max-width: 180px;">
            </div>
            <div class="dev-vars-container border rounded"
                 style="overflow: auto; flex-grow: 1; background: var(--bs-body-bg); font-size: .75rem;">
                <pre id="dev-vars-output" class="mb-0 p-2 m-0" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .7rem; line-height: 1.4; white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var SENSITIVE_KEYS = ['password', 'token', 'secret', 'key', 'cookie', 'authorization', 'csrf'];

    function isSensitiveKey(key) {
        return SENSITIVE_KEYS.some(function (k) { return key.toLowerCase().indexOf(k) !== -1; });
    }

    function maskValue(val) {
        if (val === null || val === undefined) return '***';
        var s = String(val);
        if (s.length <= 2) return '***';
        return s.charAt(0) + '***' + s.charAt(s.length - 1);
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderValue(val, keyName, indent) {
        indent = indent || 0;
        var pad = '  '.repeat(indent);
        var type = typeof val;

        if (val === null || val === undefined) {
            return '<span class="dev-var-value">' + type + '</span>';
        }
        if (Array.isArray(val)) {
            var lines = [];
            lines.push('<span class="dev-var-type">array[' + val.length + ']</span>');
            if (val.length === 0) {
                lines.push('<span class="dev-var-value">' + pad + '[]</span>');
            } else if (val.length <= 5) {
                val.forEach(function (v, i) {
                    lines.push('<span class="dev-var-value">' + pad + '  [' + i + '] →</span>');
                    lines.push('<span class="dev-var-value">' + pad + '    ' + renderValue(v, i, indent + 2) + '</span>');
                });
            } else {
                lines.push('<span class="dev-var-value">' + pad + '  [first 5 of ' + val.length + ']</span>');
                val.slice(0, 5).forEach(function (v, i) {
                    lines.push('<span class="dev-var-value">' + pad + '  [' + i + '] →</span>');
                    lines.push('<span class="dev-var-value">' + pad + '    ' + renderValue(v, i, indent + 2) + '</span>');
                });
                lines.push('<span class="dev-var-value">' + pad + '  … (' + (val.length - 5) + ' more)</span>');
            }
            return lines.join('\n');
        }
        if (type === 'object') {
            var keys = Object.keys(val);
            var out = [];
            out.push('<span class="dev-var-type">object(' + keys.length + ')</span>');
            if (keys.length === 0) {
                out.push('<span class="dev-var-value">' + pad + '{}</span>');
            } else if (keys.length <= 3) {
                keys.forEach(function (k) {
                    out.push('<span class="dev-var-value">' + pad + '  "' + escapeHtml(k) + '" →</span>');
                    out.push('<span class="dev-var-value">' + pad + '    ' + renderValue(val[k], k, indent + 2) + '</span>');
                });
            } else {
                keys.slice(0, 3).forEach(function (k) {
                    out.push('<span class="dev-var-value">' + pad + '  "' + escapeHtml(k) + '" →</span>');
                    out.push('<span class="dev-var-value">' + pad + '    ' + renderValue(val[k], k, indent + 2) + '</span>');
                });
                out.push('<span class="dev-var-value">' + pad + '  … (' + (keys.length - 3) + ' more keys)</span>');
            }
            return out.join('\n');
        }
        if (type === 'string') {
            if (keyName && isSensitiveKey(keyName)) {
                return '<span class="dev-var-value" style="color:#dc3545">▌' + maskValue(val) + '</span>';
            }
            var display = val.length > 200 ? val.substring(0, 200) + '…' : val;
            return '<span class="dev-var-value">"' + escapeHtml(display) + '"</span>';
        }
        if (type === 'boolean') {
            return '<span class="dev-var-value" style="color:#6f42c1">' + val + '</span>';
        }
        if (type === 'number') {
            return '<span class="dev-var-value" style="color:#198754">' + val + '</span>';
        }
        return '<span class="dev-var-value">' + escapeHtml(String(val)) + '</span>';
    }

    function formatVars(vars) {
        var out = [];
        var keys = Object.keys(vars).sort();

        keys.forEach(function (k) {
            var val = vars[k];
            var type = (val !== null && typeof val === 'object')
                ? (Array.isArray(val) ? 'array' : 'object')
                : typeof val;
            var keyStr = escapeHtml(k);
            var typeStr = '<span class="dev-var-type">' + type + '</span>';
            var valueHtml = renderValue(val, k, 0);
            out.push('<div class="dev-var-entry" data-var-key="' + escapeHtml(k).toLowerCase() + '">' +
                     '  <span class="dev-var-name"> ' + keyStr + ':</span>' + typeStr +
                     '\n' + valueHtml +
                     '</div>\n');
        });

        return '<div class="dev-vars-list">' + out.join('') + '</div>';
    }

    // Render variables on open
    var toggle = document.getElementById('dev-tools-toggle');
    var panel = document.getElementById('dev-tools-offcanvas');
    var output = document.getElementById('dev-vars-output');
    var searchInput = document.getElementById('dev-tools-search');
    var renderedHTML = null;

    panel.addEventListener('show.bs.offcanvas', function () {
        if (renderedHTML === null) {
            renderedHTML = formatVars(get_defined_vars());
            output.innerHTML = renderedHTML;
        }
    });

    // Client-side filter
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = searchInput.value.toLowerCase().trim();
            var entries = output.querySelectorAll('.dev-var-entry');
            entries.forEach(function (el) {
                var key = el.getAttribute('data-var-key') || '';
                var valHtml = el.innerHTML || '';
                var valLower = valHtml.toLowerCase().replace(/<[^>]*>/g, '');
                if (!q || key.indexOf(q) !== -1 || valLower.indexOf(q) !== -1) {
                    el.classList.remove('dev-var-hidden');
                    el.classList.add('dev-var-matched');
                } else {
                    el.classList.add('dev-var-hidden');
                    el.classList.remove('dev-var-matched');
                }
            });
        });
    }

    // Close on Escape
    panel.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var bsOffcanvas = bootstrap.Offcanvas.getInstance(panel);
            if (bsOffcanvas) bsOffcanvas.hide();
        }
    });
})();
</script>
