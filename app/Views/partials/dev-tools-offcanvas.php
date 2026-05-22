<?php
/**
 * Developer Tools — floating offcanvas.
 *
 * Rendered only when ALL THREE are true:
 *   1. appConfig['developer']
 *   2. appConfig['debug']
 *   3. appConfig['dev_console']
 *
 * Sensitive values are masked automatically.
 * Variables are captured server-side via get_defined_vars() and sanitized
 * before being passed to JS for rendering and filtering.
 *
 * $appConfig is guaranteed by the Global View Context (ViewGlobals::contextFromContainer).
 */

/* Assert context is available — if missing, it's a pipeline bug. */
if (!isset($appConfig) || !is_array($appConfig)) {
    trigger_error(
        'Developer Tools: $appConfig is missing — Global View Context did not provide it. '
        . 'This is a context pipeline bug, not a dev-tools bug.',
        E_USER_WARNING
    );
    return;
}

$devEnabled    = (bool) ($appConfig['developer'] ?? false);
$debugEnabled  = (bool) ($appConfig['debug'] ?? false);
$consoleEnabled = (bool) ($appConfig['dev_console'] ?? false);

// Only render when ALL THREE flags are true.
if (!$devEnabled || !$debugEnabled || !$consoleEnabled) {
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

/* ── Strict helpers ─────────────────────────────────────── */

if (!function_exists('devTypeLabel')) {
function devTypeLabel(mixed $val): string {
    if (is_array($val))   return 'array';
    if (is_object($val))  return 'object';
    if (is_string($val))  return 'string';
    if (is_int($val))     return 'integer';
    if (is_float($val))   return 'float';
    if (is_bool($val))    return 'boolean';
    if (is_null($val))    return 'null';
    if (is_resource($val)) return 'resource';
    return 'unknown';
}
}

if (!function_exists('devScalarDisplay')) {
function devScalarDisplay(mixed $val, int $maxLen): string {
    if (is_bool($val))   return $val ? 'true' : 'false';
    if ($val === null)   return 'null';
    $s = (string)$val;
    if (strlen($s) > $maxLen) {
        return substr($s, 0, $maxLen) . '… (' . strlen($s) . ' chars)';
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
}

/**
 * Recursively sanitize a value into a strict typed structure.
 *
 * Returns:
 *  [
 *    'type'      => string (strict type name)
 *    'preview'   => string (human-readable preview)
 *    'value'     => ?string (HTML-escaped scalar value, null if not scalar)
 *    'count'     => ?int    (array/object count)
 *    'children'  => ?array  (sanitized children for array/object)
 *    'truncated' => ?int    (number of hidden children)
 *    'masked'    => ?bool   (true if this node is a masked value)
 *  ]
 */
if (!function_exists('devSanitize')) {
function devSanitize(mixed $val, int $depth = 0, int $maxDepth = 5, int $maxItems = 6, int $maxStrLen = 200): array {
    static $sensitivePatterns = null;
    if ($sensitivePatterns === null) {
        $sensitivePatterns = ['password', 'passwd', 'secret', 'token', 'key', 'cookie', 'authorization', 'csrf', 'session'];
    }
    $sensitiveKey = fn(string $k): bool => str_starts_with($k, '_')
        || preg_match('/'.implode('|', $sensitivePatterns).'/', $k);

    if ($depth > $maxDepth) {
        return [
            'type' => 'unknown', 'preview' => '… [max depth]',
            'value' => null, 'count' => null, 'children' => null,
            'truncated' => null, 'masked' => false,
        ];
    }

    /* Arrays */
    if (is_array($val)) {
        $total = count($val);
        $result = [
            'type' => 'array', 'preview' => 'Array (' . $total . ' items)',
            'count' => $total, 'value' => null,
            'children' => [], 'truncated' => null, 'masked' => false,
        ];
        if ($total === 0) return $result;
        $count = 0;
        foreach ($val as $k => $v) {
            if ($count >= $maxItems) break;
            $keySafe = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
            if ($sensitiveKey((string)$k)) {
                $result['children'][$keySafe] = [
                    'type' => 'masked', 'preview' => '▌***',
                    'value' => null, 'count' => null, 'children' => null,
                    'truncated' => null, 'masked' => true,
                ];
            } elseif (is_scalar($v)) {
                $result['children'][$keySafe] = [
                    'type' => devTypeLabel($v), 'preview' => devScalarDisplay($v, $maxStrLen),
                    'value' => devScalarDisplay($v, $maxStrLen), 'count' => null,
                    'children' => null, 'truncated' => null, 'masked' => false,
                ];
            } else {
                $result['children'][$keySafe] = devSanitize($v, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
            }
            $count++;
        }
        if ($count < $total) $result['truncated'] = $total - $count;
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
            'count' => $total, 'value' => null,
            'children' => [], 'truncated' => null, 'masked' => false,
        ];
        $allowedMagic = ['Stringable', 'Throwable', 'Exception', 'RuntimeException',
                         'InvalidArgumentException', 'LogicException', 'DomainException'];
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
                    'type' => devTypeLabel($pv), 'preview' => devScalarDisplay($pv, 80),
                    'value' => devScalarDisplay($pv, 80), 'count' => null,
                    'children' => null, 'truncated' => null, 'masked' => false,
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
        $d = devScalarDisplay($val, $maxStrLen);
        return [
            'type' => devTypeLabel($val), 'preview' => $d, 'value' => $d,
            'count' => null, 'children' => null, 'truncated' => null, 'masked' => false,
        ];
    }
    if ($val === null) {
        return [
            'type' => 'null', 'preview' => 'null', 'value' => 'null',
            'count' => null, 'children' => null, 'truncated' => null, 'masked' => false,
        ];
    }
    // Resources / unknown
    return [
        'type' => gettype($val), 'preview' => '[' . gettype($val) . ']',
        'value' => null, 'count' => null, 'children' => null,
        'truncated' => null, 'masked' => false,
    ];
}
}

$devSanitized = [];
foreach ($devVars as $_k => $_v) {
    $devSanitized[$_k] = devSanitize($_v);
}

/* ── Path helpers for unique collapse IDs ────────────────── */

// Encode a path segment into a valid HTML ID fragment
if (!function_exists('devIdSegment')) {
function devIdSegment(string $seg): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $seg));
}
}

// Generate a unique collapse ID from the variable path
if (!function_exists('devCollapseId')) {
function devCollapseId(string $varPath, string $childKey): string {
    return 'dev-var-' . $varPath . '-' . devIdSegment($childKey);
}
}

/**
 * Render a nested expandable node (recursive).
 * Outputs HTML rows for the child and its descendants.
 */
if (!function_exists('devRenderNestedNode')) {
function devRenderNestedNode(string $detailKey, array $childMeta, string $parentPath, int $level, int $initialBatch = 5): void {
    $childType  = $childMeta['type'];
    $hasChildren = ($childType === 'array' || $childType === 'object') && !empty($childMeta['children']);
    $indent = str_repeat('&nbsp;&nbsp;', max(0, $level));
    $path   = ($parentPath !== '') ? $parentPath . '-' . devIdSegment($detailKey) : devIdSegment($detailKey);

    // Determine which children are initial (visible) vs hidden
    $children = $childMeta['children'] ?? [];
    $childCount = count($children);
    $visibleCount = min($initialBatch, $childCount);
    $hiddenCount  = $childCount - $visibleCount;

    // Indentation badge for nested levels
    $indentBadge = $level > 0 ? '<span class="dev-nested-indent">' . $indent . '</span>' : '';

    // Type badge for child
    $childBadge = match ($childType) {
        'array'     => 'bg-secondary-subtle text-secondary-emphasis',
        'object'    => 'bg-info-subtle text-info-emphasis',
        'masked'    => 'bg-danger-subtle text-danger-emphasis',
        'string'    => 'bg-success-subtle text-success-emphasis',
        'integer'   => 'bg-success-subtle text-success-emphasis',
        'float'     => 'bg-success-subtle text-success-emphasis',
        'boolean'   => 'bg-warning-subtle text-warning-emphasis',
        'null'      => 'bg-secondary-subtle text-secondary-emphasis',
        'resource'  => 'bg-warning-subtle text-warning-emphasis',
        default     => 'bg-secondary-subtle text-secondary-emphasis',
    };

    // Preview value
    $childPreview = '';
    if ($childType === 'masked') {
        $childPreview = '▌***';
    } elseif ($childType === 'array' || $childType === 'object') {
        $childPreview = htmlspecialchars($childMeta['preview'] ?? $childType, ENT_QUOTES, 'UTF-8');
    } elseif ($childMeta['value'] !== null) {
        $childPreview = $childMeta['value'];
    } else {
        $childPreview = htmlspecialchars($childMeta['preview'] ?? $childMeta['type'], ENT_QUOTES, 'UTF-8');
    }

    echo '<tr class="dev-nested-row" data-path="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '  <td class="dev-nested-name">' . $indentBadge . '<code class="text-muted">' . htmlspecialchars($detailKey, ENT_QUOTES, 'UTF-8') . '</code></td>' . "\n";
    echo '  <td><span class="badge ' . htmlspecialchars($childBadge, ENT_QUOTES, 'UTF-8') . ' dev-var-type-badge dev-nested-type-badge">' . htmlspecialchars($childType, ENT_QUOTES, 'UTF-8') . '</span></td>' . "\n";
    echo '  <td>' . $childPreview;
    if ($hasChildren) {
        $collapseId = 'dev-var-' . $path;
        echo ' <button class="btn btn-sm btn-link dev-nested-expand p-0 ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '"><i class="bi bi-chevron-expand"></i></button>';
    }
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    if ($hasChildren) {
        // Collapse container for grandchildren
        $collapseId = 'dev-var-' . $path;
        echo '<tr class="dev-nested-detail-row">' . "\n";
        echo '  <td colspan="3">' . "\n";
        echo '    <div class="collapse" id="' . $collapseId . '">' . "\n";
        echo '      <div class="dev-nested-details">' . "\n";
        echo '        <table class="dev-nested-table">' . "\n";

        $idx = 0;
        foreach ($children as $gk => $gm) {
            $isHidden = $idx >= $initialBatch;
            $extra = $isHidden ? ' class="dev-var-batch-hidden"' : '';
            $idx++;
            echo '          <tr class="dev-batch-item"' . $extra . '>' . "\n";
            echo '            <td colspan="3">' . "\n";
            devRenderNestedNode($gk, $gm, $path, $level + 1, $initialBatch);
            echo '            </td>' . "\n";
            echo '          </tr>' . "\n";
        }

        echo '        </table>' . "\n";

        // Show N more button (only if there are hidden items)
        if ($hiddenCount > 0) {
            $totalShown = $visibleCount;
            echo '        <div class="dev-batch-more" data-batch-total="' . $childCount . '" data-batch-shown="' . $visibleCount . '" data-batch-step="' . $initialBatch . '">' . "\n";
            echo '          <button type="button" class="btn btn-link btn-sm dev-batch-more-btn p-0">' . "\n";
            echo '            +&nbsp;Show ' . $initialBatch . ' more' . "\n";
            echo '          </button>' . "\n";
            echo '        </div>' . "\n";
        }

        // Truncation info from parent
        if (!empty($childMeta['truncated']) && $childMeta['truncated'] > 0) {
            echo '        <div class="dev-var-trunc mt-1">';
            echo '+ ' . $childMeta['truncated'] . ' ' . ($childMeta['truncated'] === 1 ? 'item' : 'items') . ' not shown';
            echo '</div>' . "\n";
        }

        echo '      </div>' . "\n";
        echo '    </div>' . "\n";
        echo '  </td>' . "\n";
        echo '</tr>' . "\n";
    }
}
}

/**
 * Render detail rows for top-level children (inside the expanded variable).
 */
if (!function_exists('devRenderTopLevelChildren')) {
function devRenderTopLevelChildren(array $children, string $varId, int $initialBatch = 5): void {
    $childCount = count($children);
    $visibleCount = min($initialBatch, $childCount);
    $hiddenCount = $childCount - $visibleCount;
    $idx = 0;

    foreach ($children as $detailKey => $childMeta) {
        $isHidden = $idx >= $initialBatch;
        $extra = $isHidden ? ' class="dev-var-batch-hidden"' : '';
        $idx++;
        echo '<tr class="dev-batch-item"' . $extra . '>' . "\n";
        echo '  <td colspan="3">' . "\n";
        devRenderNestedNode($detailKey, $childMeta, $varId, 0, $initialBatch);
        echo '  </td>' . "\n";
        echo '</tr>' . "\n";
    }

    if ($hiddenCount > 0) {
        echo '<tr class="dev-batch-row">' . "\n";
        echo '  <td colspan="3">' . "\n";
        echo '    <div class="dev-batch-more" data-batch-total="' . $childCount . '" data-batch-shown="' . $visibleCount . '" data-batch-step="' . $initialBatch . '">' . "\n";
        echo '      <button type="button" class="btn btn-link btn-sm dev-batch-more-btn p-0">' . "\n";
        echo '        +&nbsp;Show ' . $initialBatch . ' more' . "\n";
        echo '      </button>' . "\n";
        echo '    </div>' . "\n";
        echo '  </td>' . "\n";
        echo '</tr>' . "\n";
    }
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
            $typeLabel  = $meta['type'];
            $typeBadge  = match ($typeLabel) {
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
            $isScalar     = in_array($typeLabel, ['string','integer','float','boolean']);
            $isMasked     = ($meta['masked'] === true);
            $preview      = $meta['preview'];
            $children     = ($isExpandable && !empty($meta['children'])) ? $meta['children'] : [];
            $truncCount   = ($meta['truncated'] ?? 0);
            $scalarVal    = $isScalar ? ($meta['value'] ?? '') : null;
            // Encode var name for use in IDs
            $varPath = devIdSegment($varName);
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
                                        data-bs-target="#dev-var-<?= $varPath ?>"
                                        aria-expanded="false"
                                        aria-controls="dev-var-<?= $varPath ?>">
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
                                <div class="collapse" id="dev-var-<?= $varPath ?>">
                                    <div class="dev-var-details">
                                        <?php if (!empty($children)): ?>
                                        <table class="dev-var-detail-table">
                                            <?php devRenderTopLevelChildren($children, $varPath, 5); ?>
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
    var panel = document.getElementById('dev-tools-offcanvas');

    /* Bind all collapse events (including nested) */
    panel.addEventListener('shown.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) { var icon = btn.querySelector('i'); if (icon) icon.className = 'bi bi-chevron-collapse'; }
    });
    panel.addEventListener('hidden.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) { var icon = btn.querySelector('i'); if (icon) icon.className = 'bi bi-chevron-expand'; }
    });

    /* "Show N more" progressive loading */
    panel.addEventListener('click', function (e) {
        var btn = e.target.closest('.dev-batch-more-btn');
        if (!btn) return;
        var container = btn.closest('.dev-batch-more');
        if (!container) return;
        var total = parseInt(container.getAttribute('data-batch-total'), 10) || 0;
        var shown = parseInt(container.getAttribute('data-batch-shown'), 10) || 0;
        var step  = parseInt(container.getAttribute('data-batch-step'), 10) || 5;
        var next  = Math.min(shown + step, total);

        // Find the batch items in the associated detail table
        var detailDiv = btn.closest('.dev-nested-details, .dev-var-details');
        if (!detailDiv) return;
        var items = detailDiv.querySelectorAll('.dev-batch-item');

        items.forEach(function (item, idx) {
            if (idx >= shown && idx < next) {
                item.classList.remove('dev-var-batch-hidden');
            }
        });

        container.setAttribute('data-batch-shown', next);
        var remaining = total - next;
        if (remaining > 0) {
            btn.childNodes[1].textContent = ' + Show ' + Math.min(step, remaining) + ' more ';
        } else {
            // All shown — hide the button
            btn.parentElement.style.display = 'none';
        }
    });

    /* Client-side filter (top-level rows only) */
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

                // Hide detail row along with parent
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
