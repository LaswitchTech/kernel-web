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

/* ── Use parent-scope vars (captured by layout before include) ───── */
$devVars = $__devVars ?? get_defined_vars();
unset($__devVars);

/* Exclude internal/helper variable names */
$devExclude = [
    'devVars', 'devExclude', 'devSanitized', 'devTypes', 'devNoEx',
    'appConfig', 'devEnabled', 'debugEnabled',
    '__data',
    '_SESSION', '_GET', '_POST', '_FILES', '_COOKIE', '_SERVER', '_REQUEST', '_ENV',
    '__devVars',
];
$devVars = array_diff_key($devVars, array_flip($devExclude));

/**
 * Strict type label for any PHP value.
 */
function devTypeLabel(mixed $val): string {
    if (is_array($val))  return 'array';
    if (is_object($val)) return 'object';
    if (is_string($val)) return 'string';
    if (is_int($val))    return 'integer';
    if (is_float($val))  return 'float';
    if (is_bool($val))   return 'boolean';
    if (is_null($val))   return 'null';
    if (is_resource($val)) return 'resource';
    return 'unknown';
}

/**
 * HTML-escape a scalar for display. Truncates at $maxLen.
 */
function devScalarDisplay(mixed $val, int $maxLen): string {
    if (is_bool($val))   return $val ? 'true' : 'false';
    if ($val === null)   return 'null';
    $s = (string)$val;
    if (strlen($s) > $maxLen) {
        return substr($s, 0, $maxLen) . '… (' . strlen($s) . ' chars)';
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ── Unified sanitized structure ──────────────────────── */
// Always returns an associative array with strict keys:
//   'type'      => string  (strict type name)
//   'preview'   => string  (human-readable preview)
//   'value'     => ?string (scalar HTML-escaped value, null if not scalar)
//   'count'     => ?int    (array/object count)
//   'children'  => ?array  (sanitized children for array/object)
//   'truncated' => ?int    (number of hidden children)
function devSanitize(mixed $val, int $depth = 0, int $maxDepth = 6, int $maxItems = 6, int $maxStrLen = 200): array {
    static $sensitivePatterns = null;
    if ($sensitivePatterns === null) {
        $sensitivePatterns = ['password', 'passwd', 'secret', 'token', 'key', 'cookie', 'authorization', 'csrf', 'session'];
    }
    $sensitiveKey = fn(string $k): bool => str_starts_with($k, '_')
        || preg_match('/'.implode('|', $sensitivePatterns).'/', $k);

    if ($depth > $maxDepth) {
        return ['type' => 'unknown', 'preview' => '… [max depth]', 'value' => null, 'count' => null, 'children' => null, 'truncated' => null];
    }

    /* Arrays */
    if (is_array($val)) {
        $total = count($val);
        $result = [
            'type' => 'array',
            'preview' => 'Array (' . $total . ' items)',
            'count' => $total,
            'value' => null,
            'children' => [],
            'truncated' => null,
        ];
        if ($total === 0) return $result;

        $count = 0;
        foreach ($val as $k => $v) {
            if ($count >= $maxItems) break;
            $keySafe = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
            if ($sensitiveKey((string)$k)) {
                $result['children'][$keySafe] = [
                    'type' => 'masked',
                    'preview' => '▌***',
                    'value' => null,
                    'count' => null,
                    'children' => null,
                    'truncated' => null,
                ];
            } elseif (is_scalar($v)) {
                $result['children'][$keySafe] = [
                    'type' => devTypeLabel($v),
                    'preview' => devScalarDisplay($v, $maxStrLen),
                    'value' => devScalarDisplay($v, $maxStrLen),
                    'count' => null,
                    'children' => null,
                    'truncated' => null,
                ];
            } else {
                $result['children'][$keySafe] = devSanitize($v, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
            }
            $count++;
        }
        if ($count < $total) {
            $result['truncated'] = $total - $count;
        }
        return $result;
    }

    /* Objects */
    if (is_object($val)) {
        $class  = $val::class;
        $props  = get_object_vars($val);
        $total  = count($props);
        $result = [
            'type' => 'object',
            'preview' => htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' (' . $total . ' props)',
            'count' => $total,
            'value' => null,
            'children' => [],
            'truncated' => null,
        ];

        $allowedMagic = ['Stringable', 'Throwable', 'Exception', 'RuntimeException', 'InvalidArgumentException', 'LogicException', 'DomainException'];
        // Safe string repr for allowed magic classes
        if ($total === 0 && in_array($class, $allowedMagic, true) && method_exists($val, '__toString')) {
            try {
                $s = $val->__toString();
                $result['preview'] = htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' → '
                    . ((strlen($s) > 80 ? substr($s, 0, 80) . '…' : htmlspecialchars($s, ENT_QUOTES, 'UTF-8')));
                return $result;
            } catch (\Throwable $e) { /* fall through */ }
        }

        $shown = 0;
        foreach ($props as $pk => $pv) {
            if ($shown >= 4) break;
            if (str_starts_with($pk, '_') || str_starts_with($pk, 'private') || str_starts_with($pk, 'protected')) continue;
            $pkSafe = htmlspecialchars($pk, ENT_QUOTES, 'UTF-8');
            if (is_scalar($pv)) {
                $result['children'][$pkSafe] = [
                    'type' => devTypeLabel($pv),
                    'preview' => devScalarDisplay($pv, 80),
                    'value' => devScalarDisplay($pv, 80),
                    'count' => null,
                    'children' => null,
                    'truncated' => null,
                ];
            } else {
                $result['children'][$pkSafe] = devSanitize($pv, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
            }
            $shown++;
        }
        return $result;
    }

    /* Scalars */
    if (is_scalar($val)) {
        $display = devScalarDisplay($val, $maxStrLen);
        return [
            'type' => devTypeLabel($val),
            'preview' => $display,
            'value' => $display,
            'count' => null,
            'children' => null,
            'truncated' => null,
        ];
    }
    if ($val === null) {
        return [
            'type' => 'null',
            'preview' => 'null',
            'value' => 'null',
            'count' => null,
            'children' => null,
            'truncated' => null,
        ];
    }
    // Fallback for unknown types (resources, etc.)
    return [
        'type' => gettype($val),
        'preview' => '[' . gettype($val) . ']',
        'value' => null,
        'count' => null,
        'children' => null,
        'truncated' => null,
    ];
}

$devSanitized = [];
foreach ($devVars as $varName => $varVal) {
    $devSanitized[$varName] = devSanitize($varVal);
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
                 style="overflow: auto; flex-grow: 1; background: var(--bs-body-bg);">
                <table class="dev-vars-table table table-sm table-borderless mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Value / Preview</th>
                        </tr>
                    </thead>
                    <tbody id="dev-vars-body">
                        <?php foreach ($devSanitized as $varName => $meta): ?>
                        <?php
            $typeLabel = $meta['type'];
            $typeBadge = match ($typeLabel) {
                'array'     => 'bg-secondary-subtle text-secondary-emphasis',
                'object'    => 'bg-info-subtle text-info-emphasis',
                'string'    => 'bg-success-subtle text-success-emphasis',
                'integer'   => 'bg-success-subtle text-success-emphasis',
                'float'     => 'bg-success-subtle text-success-emphasis',
                'boolean'   => 'bg-warning-subtle text-warning-emphasis',
                'null'      => 'bg-secondary-subtle text-secondary-emphasis',
                'resource'  => 'bg-warning-subtle text-warning-emphasis',
                'masked'    => 'bg-danger-subtle text-danger-emphasis',
                'unknown'   => 'bg-secondary-subtle text-secondary-emphasis',
                default     => 'bg-secondary-subtle text-secondary-emphasis',
            };
            $isExpandable = ($typeLabel === 'array' || $typeLabel === 'object');
            $isScalar     = ($typeLabel === 'string' || $typeLabel === 'integer' || $typeLabel === 'float' || $typeLabel === 'boolean');
            $isMasked     = ($typeLabel === 'masked');
            $preview      = $meta['preview'];
            $children     = ($isExpandable && !empty($meta['children'])) ? $meta['children'] : [];
            $truncCount   = ($meta['truncated'] ?? 0);
            $scalarVal    = $isScalar ? ($meta['value'] ?? '') : null;
        ?>
                        <tr class="dev-var-row"
                            data-var-name="<?= htmlspecialchars($varName, ENT_QUOTES, 'UTF-8') ?>"
                            data-var-type="<?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <code class="dev-var-name"><?= htmlspecialchars($varName, ENT_QUOTES, 'UTF-8') ?></code>
                                <?php if ($isExpandable): ?>
                                <button class="btn btn-sm btn-link dev-var-expand p-0 ms-1"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#dev-var-<?= (string)$varName ?>"
                                        aria-expanded="false"
                                        aria-controls="dev-var-<?= (string)$varName ?>">
                                    <i class="bi bi-chevron-expand"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= htmlspecialchars($typeBadge, ENT_QUOTES, 'UTF-8') ?> dev-var-type-badge"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <?php if ($isMasked): ?>
                                    <code class="dev-var-value dev-var-masked">▌***</code>
                                <?php elseif ($isScalar && $scalarVal !== null): ?>
                                    <code class="dev-var-value"><?= $scalarVal ?></code>
                                <?php elseif ($isExpandable): ?>
                                    <div class="dev-var-preview text-muted small"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($isExpandable): ?>
                        <tr class="dev-var-detail-row">
                            <td colspan="3">
                                <div class="collapse" id="dev-var-<?= (string)$varName ?>">
                                    <div class="dev-var-details">
                                        <?php if (!empty($children)): ?>
                                        <table class="dev-var-detail-table">
                                            <?php foreach ($children as $detailKey => $childMeta): ?>
                                            <tr>
                                                <td><code class="text-muted"><?= $detailKey ?></code></td>
                                                <td>
                                                    <?php
                                                        $childType = $childMeta['type'];
                                                        // Handle nested arrays/objects
                                                        if ($childType === 'array' || $childType === 'object') {
                                                            echo '<span class="text-muted small">[</span>';
                                                            echo '<code class="text-' . ($childType === 'array' ? 'secondary' : 'info') . '-emphasis">'
                                                                 . htmlspecialchars(strtoupper($childType), ENT_QUOTES, 'UTF-8')
                                                                 . '</code>';
                                                            if (!empty($childMeta['children'])) {
                                                                // One level of nested detail: show first few children inline
                                                                $inlineChildren = array_slice($childMeta['children'], 0, 3);
                                                                $inlineParts = [];
                                                                foreach ($inlineChildren as $ik => $iv) {
                                                                    $ivDisplay = $iv['type'] === 'masked'
                                                                        ? '▌***'
                                                                        : ($iv['value'] ?? $iv['preview'] ?? $iv['type']);
                                                                    $inlineParts[] = $ik . ' → ' . $ivDisplay;
                                                                }
                                                                $childCount = count($childMeta['children']);
                                                                if ($childCount > 3) {
                                                                    $inlineParts[] = '+ ' . ($childCount - 3) . ' more';
                                                                }
                                                                echo ' <span class="text-muted small">{' . implode(', ', $inlineParts) . '}</span>';
                                                            } else {
                                                                echo ' <span class="text-muted small">{' . ($childMeta['count'] ?? 0) . '}</span>';
                                                            }
                                                            echo '<span class="text-muted small">]</span>';
                                                        } elseif ($childType === 'masked') {
                                                            echo '<code style="color:#dc3545;">▌***</code>';
                                                        } else {
                                                            echo '<code>' . ($childMeta['value'] ?? $childMeta['preview'] ?? htmlspecialchars($childType, ENT_QUOTES, 'UTF-8')) . '</code>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                        <?php endif; ?>
                                        <?php if ($truncCount > 0): ?>
                                        <div class="dev-var-trunc">
                                            + <?= $truncCount ?> <?= $truncCount === 1 ? 'item' : 'items' ?> not shown
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    /* Bind collapse events to toggle icons */
    var panel = document.getElementById('dev-tools-offcanvas');
    panel.addEventListener('shown.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) btn.querySelector('i').className = 'bi bi-chevron-collapse';
    });
    panel.addEventListener('hidden.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) btn.querySelector('i').className = 'bi bi-chevron-expand';
    });

    /* Client-side filter */
    var searchInput = document.getElementById('dev-tools-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var q = searchInput.value.toLowerCase().trim();
            var rows = document.querySelectorAll('#dev-vars-body .dev-var-row');
            rows.forEach(function (row) {
                var name = row.getAttribute('data-var-name') || '';
                var type = row.getAttribute('data-var-type') || '';
                var preview = row.querySelector('.dev-var-preview')?.textContent || '';
                var value = row.querySelector('.dev-var-value')?.textContent || '';
                var trunc = row.querySelector('.dev-var-trunc')?.textContent || '';
                var match = !q ||
                    name.indexOf(q) !== -1 ||
                    type.toLowerCase().indexOf(q) !== -1 ||
                    preview.toLowerCase().indexOf(q) !== -1 ||
                    value.toLowerCase().indexOf(q) !== -1 ||
                    trunc.toLowerCase().indexOf(q) !== -1;

                row.classList.toggle('dev-var-hidden', !match);

                // Show/hide detail row along with parent
                var detailRow = row.nextElementSibling;
                if (detailRow && detailRow.classList.contains('dev-var-detail-row')) {
                    detailRow.classList.toggle('dev-var-hidden', !match);
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
