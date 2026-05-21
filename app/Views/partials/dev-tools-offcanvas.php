<?php
/**
 * Developer Tools — floating offcanvas.
 *
 * Only rendered when BOTH $app['developer'] and $app['debug'] are true.
 * Sensitive values are masked automatically.
 * Variables are captured server-side via get_defined_vars() and sanitized
 * before being passed to JS for rendering and filtering.
 */

$appConfig  = ($config['app'] ?? $config) ?? [];
$devEnabled = (bool) ($appConfig['developer'] ?? false);
$debugEnabled = (bool) ($appConfig['debug'] ?? $appConfig['debug'] ?? false);

if (!$devEnabled || !$debugEnabled) {
    return;
}

/* ── Capture and sanitize variables (server-side) ────────────────── */

$devVars   = get_defined_vars();
$devExclude = [
    'devVars', 'devExclude', 'devSanitized', 'devNoEx',
    'appConfig', 'devEnabled', 'debugEnabled',
    '__data',
    '_SESSION', '_GET', '_POST', '_FILES', '_COOKIE', '_SERVER', '_REQUEST', '_ENV',
];
foreach ($devExclude as $k) {
    unset($devVars[$k]);
}

/**
 * Recursively sanitize a value for safe HTML display.
 * - Scalars are HTML-escaped; strings over $maxStrLen are truncated.
 * - Arrays are capped at $maxItems depth and item count.
 * - Objects show class name, property count, and a safe preview.
 */
function devSanitize($val, int $depth = 0, int $maxDepth = 8, int $maxItems = 8, int $maxStrLen = 256): mixed {
    // Sensitive-key check
    static $sensitivePatterns = null;
    if ($sensitivePatterns === null) {
        $sensitivePatterns = ['password', 'passwd', 'secret', 'token', 'key', 'cookie', 'authorization', 'csrf', 'session'];
    }

    $sensitiveKey = fn(string $k): bool => str_starts_with($k, '_')
        || preg_match('/'.implode('|', $sensitivePatterns).'/', $k);

    if ($depth > $maxDepth) {
        return '… [max depth]';
    }
    if (is_array($val)) {
        if (empty($val)) return [];
        $result = [];
        $count = 0;
        foreach ($val as $k => $v) {
            if ($count >= $maxItems) {
                $result['… ('.count($val) - $count . ' more)'] = '…';
                break;
            }
            $keySafe = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
            if ($sensitiveKey((string)$k)) {
                $result[$keySafe] = '▌***';
            } elseif (is_scalar($v)) {
                $result[$keySafe] = devScalarDisplay($v, $maxStrLen);
            } else {
                $result[$keySafe] = devSanitize($v, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
            }
            $count++;
        }
        return $result;
    }
    if (is_object($val)) {
        $class = $val::class;
        $props = get_object_vars($val);
        $preview = [];
        $shown = 0;
        $allowedMagic = ['Stringable', 'Throwable', 'Exception', 'RuntimeException', 'InvalidArgumentException', 'LogicException', 'DomainException'];
        foreach ($props as $pk => $pv) {
            if ($shown >= 4) break;
            if ($sensitiveKey($pk)) continue;
            if (is_scalar($pv)) {
                $preview[$pk] = devScalarDisplay($pv, 80);
            } else {
                $preview[$pk] = devSanitize($pv, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
            }
            $shown++;
        }
        // Attempt safe string representation for allowed magic classes
        if (empty($preview) && in_array($class, $allowedMagic, true) && method_exists($val, '__toString')) {
            try {
                $s = $val->__toString();
                if (strlen($s) > 80) $s = substr($s, 0, 80) . '…';
                return [
                    'class' => htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
                    'toString' => htmlspecialchars($s, ENT_QUOTES, 'UTF-8'),
                    'properties' => count($props),
                ];
            } catch (\Throwable $e) { /* fall through */ }
        }
        return [
            'class' => htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
            'properties' => count($props),
            '_preview' => $preview,
        ];
    }
    if (is_scalar($val)) {
        return devScalarDisplay($val, $maxStrLen);
    }
    if ($val === null) return 'null';
    return ' [' . gettype($val) . ']';
}

function devScalarDisplay(mixed $val, int $maxLen): string {
    if (is_bool($val)) return $val ? 'true' : 'false';
    if ($val === null) return 'null';
    $s = (string)$val;
    if (strlen($s) > $maxLen) {
        return substr($s, 0, $maxLen) . '… (' . strlen($s) . ' chars)';
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$devSanitized = devSanitize($devVars);

/* ── Build JS-friendly representation ───────────────────────────── */
// Build a parallel array of [key, typeLabel] for each variable.
$devTypeMap = [];
foreach ($devSanitized as $k => $v) {
    if (is_array($v) && !empty($v) && isset($v['class'])) {
        $devTypeMap[$k] = 'object';
    } elseif (is_array($v)) {
        $devTypeMap[$k] = 'array';
    } elseif (is_string($v)) {
        $devTypeMap[$k] = 'string';
    } elseif (is_int($v) || is_float($v)) {
        $devTypeMap[$k] = 'number';
    } elseif (is_bool($v)) {
        $devTypeMap[$k] = 'boolean';
    } else {
        $devTypeMap[$k] = gettype($v);
    }
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

<style>
.dev-tools-toggle {
    position: fixed;
    top: 50%;
    right: 0;
    transform: translateY(-50%);
    border-radius: 0 8px 8px 0;
    padding: .5rem .65rem;
    z-index: 1055;
    border: none;
    box-shadow: -2px 2px 8px rgba(0,0,0,.15);
    font-size: .85rem;
}
.dev-tools-toggle:hover { box-shadow: -2px 2px 12px rgba(0,0,0,.25); }
.dev-tools-offcanvas { width: 520px !important; }
.dev-vars-container pre { max-height: calc(100vh - 180px); }
.dev-var-name { color: #0d6efd; font-weight: 600; }
.dev-var-type { color: #6c757d; font-size: .65rem; margin-left: .3rem; }
.dev-var-value { color: var(--bs-body-color); display: block; margin-top: .15rem; }
.dev-var-matched { background: #fff3cd; }
.dev-var-hidden { display: none; }
</style>

<script>
(function () {
    /* Server-side sanitized data (safe HTML strings) */
    var DEV_VARS = <?= json_encode($devSanitized, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    var DEV_TYPES = <?= json_encode($devTypeMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;

    /* Client-side rendering */
    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatValue(val, keyName) {
        if (val === null || val === undefined) {
            return '<span class="dev-var-value">null</span>';
        }

        if (Array.isArray(val)) {
            // Check if it's a devSanitized "preview" object {class, properties, _preview}
            if (val.class !== undefined) {
                var lines = [];
                lines.push('<span class="dev-var-type">object(' + escapeHtml(val.class) + ', ' + val.properties + ' props)</span>');
                if (val._preview && typeof val._preview === 'object') {
                    var keys = Object.keys(val._preview);
                    keys.forEach(function (k) {
                        var pv = val._preview[k];
                        if (typeof pv === 'object' && !Array.isArray(pv)) {
                            // Nested sanitized object — render as indented list
                            var nk = Object.keys(pv);
                            nk.forEach(function (nk2) {
                                lines.push('<span class="dev-var-value">  ' + escapeHtml(k) + '.' + escapeHtml(nk2) + ' = ' + escapeHtml(String(pv[nk2])) + '</span>');
                            });
                        } else {
                            lines.push('<span class="dev-var-value">  ' + escapeHtml(k) + ' = ' + escapeHtml(String(pv)) + '</span>');
                        }
                    });
                }
                return lines.join('\n');
            }
            // Regular array
            var lines = [];
            lines.push('<span class="dev-var-type">array[' + val.length + ']</span>');
            if (val.length === 0) {
                lines.push('<span class="dev-var-value">  []</span>');
            } else if (val.length <= 5) {
                val.forEach(function (v, i) {
                    lines.push('<span class="dev-var-value">  [' + i + '] →</span>');
                    lines.push('<span class="dev-var-value">    ' + formatValue(v, i) + '</span>');
                });
            } else {
                lines.push('<span class="dev-var-value">  [first 5 of ' + val.length + ']</span>');
                val.slice(0, 5).forEach(function (v, i) {
                    lines.push('<span class="dev-var-value">  [' + i + '] →</span>');
                    lines.push('<span class="dev-var-value">    ' + formatValue(v, i) + '</span>');
                });
                lines.push('<span class="dev-var-value">  … (' + (val.length - 5) + ' more)</span>');
            }
            return lines.join('\n');
        }

        if (typeof val === 'object') {
            var keys = Object.keys(val);
            var out = [];
            out.push('<span class="dev-var-type">object(' + keys.length + ')</span>');
            if (keys.length === 0) {
                out.push('<span class="dev-var-value">  {}</span>');
            } else if (keys.length <= 3) {
                keys.forEach(function (k) {
                    out.push('<span class="dev-var-value">  "' + escapeHtml(k) + '" →</span>');
                    out.push('<span class="dev-var-value">    ' + formatValue(val[k], k) + '</span>');
                });
            } else {
                keys.slice(0, 3).forEach(function (k) {
                    out.push('<span class="dev-var-value">  "' + escapeHtml(k) + '" →</span>');
                    out.push('<span class="dev-var-value">    ' + formatValue(val[k], k) + '</span>');
                });
                out.push('<span class="dev-var-value">  … (' + (keys.length - 3) + ' more keys)</span>');
            }
            return out.join('\n');
        }

        if (typeof val === 'string') {
            var masked = formatMaskedString(val, keyName);
            if (masked !== null) return masked;
            return '<span class="dev-var-value">"' + escapeHtml(val) + '"</span>';
        }

        if (typeof val === 'boolean') {
            return '<span class="dev-var-value" style="color:#6f42c1">' + val + '</span>';
        }
        if (typeof val === 'number') {
            return '<span class="dev-var-value" style="color:#198754">' + val + '</span>';
        }
        return '<span class="dev-var-value">' + escapeHtml(String(val)) + '</span>';
    }

    function formatMaskedString(val, keyName) {
        if (typeof keyName !== 'string') return null;
        var pat = ['password', 'passwd', 'secret', 'token', 'key', 'cookie', 'authorization', 'csrf', 'session'];
        var isSensitive = pat.some(function (p) { return keyName.toLowerCase().indexOf(p) !== -1; });
        if (!isSensitive) return null;
        if (val.length <= 2) return '<span class="dev-var-value" style="color:#dc3545">▌***</span>';
        return '<span class="dev-var-value" style="color:#dc3545">▌' + val.charAt(0) + '***' + val.charAt(val.length - 1) + '</span>';
    }

    function formatVars(vars, types) {
        var out = [];
        var keys = Object.keys(vars).sort();

        keys.forEach(function (k) {
            var val = vars[k];
            var typeLabel = types[k] || typeof val;
            var isObj = Array.isArray(val) && val.class !== undefined;
            var typeStr = isObj ? '' : '<span class="dev-var-type">' + typeLabel + '</span>';

            out.push('<div class="dev-var-entry" data-var-key="' + escapeHtml(k).toLowerCase() + '">' +
                    '  <span class="dev-var-name">' + escapeHtml(k) + ':</span>' + typeStr +
                    '\n' + formatValue(val, k) +
                    '</div>\n');
        });

        return '<div class="dev-vars-list">' + out.join('') + '</div>';
    }

    /* Render on first open */
    var panel = document.getElementById('dev-tools-offcanvas');
    var output = document.getElementById('dev-vars-output');
    var renderedHTML = null;

    panel.addEventListener('show.bs.offcanvas', function () {
        if (renderedHTML === null) {
            renderedHTML = formatVars(DEV_VARS, DEV_TYPES);
            output.innerHTML = renderedHTML;
        }
    });

    /* Client-side filter */
    var searchInput = document.getElementById('dev-tools-search');
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

    /* Close on Escape */
    panel.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var bsOffcanvas = bootstrap.Offcanvas.getInstance(panel);
            if (bsOffcanvas) bsOffcanvas.hide();
        }
    });
})();
</script>
