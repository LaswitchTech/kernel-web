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

/* ── Scalars ───────────────────────────────────────────────────── */

function devScalarDisplay(mixed $val, int $maxLen): string {
    if (is_bool($val)) return $val ? 'true' : 'false';
    if ($val === null) return 'null';
    $s = (string)$val;
    if (strlen($s) > $maxLen) {
        return substr($s, 0, $maxLen) . '… (' . strlen($s) . ' chars)';
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ── Sanitize a value for safe HTML display ─────────────────── */
// Returns one of:
//   scalar (string) — already HTML-escaped
//   ['_t' => 'array', '_p' => 'Array (N items)', '_d' => [...], '_c' => ?count]
//   ['_t' => 'object', '_c' => class, '_p' => 'Object (N props)', '_d' => [...]]
function devSanitize(mixed $val, int $depth = 0, int $maxDepth = 6, int $maxItems = 6, int $maxStrLen = 200): array|string {
    static $sensitivePatterns = null;
    if ($sensitivePatterns === null) {
        $sensitivePatterns = ['password', 'passwd', 'secret', 'token', 'key', 'cookie', 'authorization', 'csrf', 'session'];
    }
    $sensitiveKey = fn(string $k): bool => str_starts_with($k, '_')
        || preg_match('/'.implode('|', $sensitivePatterns).'/', $k);

    if ($depth > $maxDepth) return '… [max depth]';

    /* Arrays */
    if (is_array($val)) {
        $total   = count($val);
        $preview = 'Array (' . $total . ' items)';
        $result  = ['_t' => 'array', '_p' => $preview, '_d' => []];
        if ($total === 0) {
            $result['_c'] = 0;
            return $result;
        }
        $count = 0;
        foreach ($val as $k => $v) {
            if ($count >= $maxItems) break;
            $keySafe = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
            if ($sensitiveKey((string)$k)) {
                $result['_d'][$keySafe] = ['_m' => 1]; // masked
            } elseif (is_scalar($v)) {
                $result['_d'][$keySafe] = devScalarDisplay($v, $maxStrLen);
            } else {
                $r = devSanitize($v, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
                $result['_d'][$keySafe] = is_array($r) ? $r : ['_v' => $r];
            }
            $count++;
        }
        if ($count < $total) {
            $result['_c'] = $total - $count; // truncated count
        }
        return $result;
    }

    /* Objects */
    if (is_object($val)) {
        $class     = $val::class;
        $props     = get_object_vars($val);
        $total     = count($props);
        $result    = ['_t' => 'object', '_c' => htmlspecialchars($class, ENT_QUOTES, 'UTF-8'), '_p' => 'Object (' . $total . ' props)', '_d' => []];
        $allowedMagic = ['Stringable', 'Throwable', 'Exception', 'RuntimeException', 'InvalidArgumentException', 'LogicException', 'DomainException'];
        // Safe string repr for allowed magic classes
        if ($total === 0 && in_array($class, $allowedMagic, true) && method_exists($val, '__toString')) {
            try {
                $s = $val->__toString();
                $result['_to'] = (strlen($s) > 80 ? substr($s, 0, 80) . '…' : $s);
                return $result;
            } catch (\Throwable $e) { /* fall through */ }
        }
        $shown = 0;
        foreach ($props as $pk => $pv) {
            if ($shown >= 4) break;
            if (str_starts_with($pk, '_') || str_starts_with($pk, 'private') || str_starts_with($pk, 'protected')) continue;
            $pkSafe = htmlspecialchars($pk, ENT_QUOTES, 'UTF-8');
            if (is_scalar($pv)) {
                $result['_d'][$pkSafe] = devScalarDisplay($pv, 80);
            } else {
                $r = devSanitize($pv, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
                $result['_d'][$pkSafe] = is_array($r) ? $r : ['_v' => $r];
            }
            $shown++;
        }
        return $result;
    }

    /* Scalars */
    if (is_scalar($val)) return ['_v' => devScalarDisplay($val, $maxStrLen)];
    if ($val === null)   return ['_v' => 'null'];
    return ['_v' => ' [' . gettype($val) . ']'];
}

$devSanitized = [];
foreach ($devVars as $varName => $varVal) {
    $devSanitized[$varName] = devSanitize($varVal);
}

/* ── Build type labels and row data ────────────────────────────── */

$devRowTypes = [];
$devRowExtras = []; // truncation info per variable
foreach ($devSanitized as $varName => $varData) {
    // Extract type and truncation count from the sanitized structure
    $type = match ($varData['_t'] ?? 'unknown') {
        'array'  => 'array',
        'object' => 'object',
        default  => $varData['_v'] ?? 'unknown',
    };
    // If type is a scalar hint (string/number/boolean/null), convert
    if (in_array($type, ['string', 'number', 'boolean', 'null'])) {
        $typeLabel = ucfirst($type);
    } else {
        $typeLabel = $type;
    }
    $devRowTypes[$varName] = $typeLabel;

    // Truncation info
    if (is_array($varData) && isset($varData['_c']) && $varData['_c'] > 0) {
        $devRowExtras[$varName] = $varData['_c'];
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
                        <?php foreach ($devSanitized as $varName => $varData): ?>
                        <?php
            $typeLabel = $devRowTypes[$varName];
            $typeBadge = match ($typeLabel) {
                'array'  => 'bg-secondary-subtle text-secondary-emphasis',
                'object' => 'bg-info-subtle text-info-emphasis',
                'string' => 'bg-success-subtle text-success-emphasis',
                'number' => 'bg-success-subtle text-success-emphasis',
                'boolean'=> 'bg-warning-subtle text-warning-emphasis',
                'null'   => 'bg-secondary-subtle text-secondary-emphasis',
                default  => 'bg-secondary-subtle text-secondary-emphasis',
            };
            $isExpandable = isset($varData['_t']) && ($varData['_t'] === 'array' || $varData['_t'] === 'object');
            $isScalar     = isset($varData['_v']) && !isset($varData['_t']);
            $preview      = $isExpandable ? ($varData['_p'] ?? '') : null;
            $details      = $isExpandable ? ($varData['_d'] ?? []) : null;
            $truncCount   = ($devRowExtras[$varName] ?? 0);
            $scalarVal    = $isScalar ? ($varData['_v'] ?? '') : null;
            $objClass     = $isExpandable && isset($varData['_c']) ? $varData['_c'] : null;
            $toStringVal  = $isExpandable ? ($varData['_to'] ?? null) : null;
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
                                <?php if ($scalarVal !== null): ?>
                                    <code class="dev-var-value"><?= $scalarVal ?></code>
                                <?php elseif ($isExpandable && isset($objClass)): ?>
                                    <code class="dev-var-value"><?= htmlspecialchars($objClass, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></code>
                                    <?php if ($toStringVal !== null): ?>
                                    <div class="dev-obj-tostring mt-1"><code><?= $toStringVal ?></code></div>
                                    <?php endif; ?>
                                <?php elseif ($isExpandable): ?>
                                    <div class="dev-var-preview text-muted small"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($isExpandable): ?>
                        <tr class="dev-var-detail-row" style="display: none;">
                            <td colspan="3">
                                <div class="collapse" id="dev-var-<?= (string)$varName ?>">
                                    <div class="dev-var-details p-2 mt-1 border rounded" style="background: var(--bs-tertiary-bg); font-size: .7rem;">
                                        <?php if (!empty($details)): ?>
                                        <table class="dev-var-detail-table table table-sm table-borderless mb-0" style="font-size: .7rem;">
                                            <?php foreach ($details as $detailKey => $detailValRaw): ?>
                                            <?php
                                                // Flatten one level: if _d is a nested sanitized value, unwrap it
                                                if (is_array($detailValRaw) && isset($detailValRaw['_d'])) {
                                                    $nested = $detailValRaw;
                                                    $hasNestedDetails = isset($nested['_d']) && is_array($nested['_d']);
                                                } else {
                                                    $nested = null;
                                                    $hasNestedDetails = false;
                                                }
                                            ?>
                                            <tr>
                                                <td style="width: 35%;"><code class="text-muted"><?= $detailKey ?></code></td>
                                                <td>
                                                    <?php if ($nested !== null): ?>
                                                        <span class="text-muted small">[</span>
                                                        <?php if (isset($nested['_t'])): ?>
                                                            <code class="text-<?= $nested['_t'] === 'array' ? 'secondary' : 'info' ?>-emphasis"><?= htmlspecialchars(strtoupper($nested['_t']), ENT_QUOTES, 'UTF-8') ?></code>
                                                        <?php elseif (isset($nested['_m'])): ?>
                                                            <code style="color:#dc3545;">▌***</code>
                                                        <?php elseif (isset($nested['_v'])): ?>
                                                            <code><?= $nested['_v'] ?></code>
                                                        <?php else: ?>
                                                            <code><?= htmlspecialchars(json_encode($nested, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?></code>
                                                        <?php endif; ?>
                                                        <span class="text-muted small">]</span>
                                                    <?php elseif (is_string($detailValRaw)): ?>
                                                        <code><?= $detailValRaw ?></code>
                                                    <?php else: ?>
                                                        <code><?= htmlspecialchars(json_encode($detailValRaw, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?></code>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                        <?php endif; ?>
                                        <?php if ($truncCount > 0): ?>
                                        <div class="dev-var-trunc mt-1" style="font-size: .68rem; color: var(--bs-secondary-color);">
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
    /* Server-side sanitized data (safe HTML strings) */
    var DEV_VARS = <?= json_encode($devSanitized, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    var DEV_TYPES = <?= json_encode($devRowTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;

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
