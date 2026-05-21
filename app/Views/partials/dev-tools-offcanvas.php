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

/* ── Build table-friendly variable rows ─────────────────────────── */

function devScalarDisplay(mixed $val, int $maxLen): string {
    if (is_bool($val)) return $val ? 'true' : 'false';
    if ($val === null) return 'null';
    $s = (string)$val;
    if (strlen($s) > $maxLen) {
        return substr($s, 0, $maxLen) . '… (' . strlen($s) . ' chars)';
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function devSanitize($val, int $depth = 0, int $maxDepth = 6, int $maxItems = 6, int $maxStrLen = 200): mixed {
    static $sensitivePatterns = null;
    if ($sensitivePatterns === null) {
        $sensitivePatterns = ['password', 'passwd', 'secret', 'token', 'key', 'cookie', 'authorization', 'csrf', 'session'];
    }
    $sensitiveKey = fn(string $k): bool => str_starts_with($k, '_')
        || preg_match('/'.implode('|', $sensitivePatterns).'/', $k);

    if ($depth > $maxDepth) return '… [max depth]';

    if (is_array($val)) {
        if (empty($val)) return [];
        $result  = [];
        $preview = 'Array ('. count($val) . ' items)';
        $count   = 0;
        foreach ($val as $k => $v) {
            if ($count >= $maxItems) break;
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
        return ['preview' => $preview, 'items' => $result];
    }

    if (is_object($val)) {
        $class  = $val::class;
        $props  = get_object_vars($val);
        $result = ['class' => htmlspecialchars($class, ENT_QUOTES, 'UTF-8'), 'preview' => 'Object ('. count($props) . ' props)'];
        // Safe string repr for allowed magic classes
        $allowedMagic = ['Stringable', 'Throwable', 'Exception', 'RuntimeException', 'InvalidArgumentException', 'LogicException', 'DomainException'];
        if (empty($props) && in_array($class, $allowedMagic, true) && method_exists($val, '__toString')) {
            try {
                $s = $val->__toString();
                $result['toString'] = (strlen($s) > 80 ? substr($s, 0, 80) . '…' : $s);
                return $result;
            } catch (\Throwable $e) { /* fall through */ }
        }
        $shown = 0;
        foreach ($props as $pk => $pv) {
            if ($shown >= 4) break;
            if (str_starts_with($pk, '_')) continue;
            $pkSafe = htmlspecialchars($pk, ENT_QUOTES, 'UTF-8');
            if (is_scalar($pv)) {
                $result[$pkSafe] = devScalarDisplay($pv, 80);
            } else {
                $result[$pkSafe] = devSanitize($pv, $depth + 1, $maxDepth, $maxItems, $maxStrLen);
            }
            $shown++;
        }
        return $result;
    }

    if (is_scalar($val)) return devScalarDisplay($val, $maxStrLen);
    if ($val === null)    return 'null';
    return ' [' . gettype($val) . ']';
}

$devSanitized = devSanitize($devVars);

/* ── Build JS data structures ────────────────────────────────────── */

$devTypes = [];
foreach ($devSanitized as $k => $v) {
    if (is_array($v) && isset($v['class'])) {
        $devTypes[$k] = 'object';
    } elseif (is_array($v)) {
        $devTypes[$k] = 'array';
    } elseif (is_string($v)) {
        $devTypes[$k] = 'string';
    } elseif (is_int($v) || is_float($v)) {
        $devTypes[$k] = 'number';
    } elseif (is_bool($v)) {
        $devTypes[$k] = 'boolean';
    } else {
        $devTypes[$k] = gettype($v);
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
                            <th style="width: 28%; min-width: 100px;">Name</th>
                            <th style="width: 14%; min-width: 60px;">Type</th>
                            <th style="width: 58%; min-width: 200px;">Value / Preview</th>
                        </tr>
                    </thead>
                    <tbody id="dev-vars-body">
                        <?php foreach ($devSanitized as $varName => $varData): ?>
                        <?php
            $typeLabel = $devTypes[$varName];
            $typeBadge = match ($typeLabel) {
                'array'  => 'bg-secondary-subtle text-secondary-emphasis',
                'object' => 'bg-info-subtle text-info-emphasis',
                'string' => 'bg-success-subtle text-success-emphasis',
                'number' => 'bg-success-subtle text-success-emphasis',
                'boolean'=> 'bg-warning-subtle text-warning-emphasis',
                default  => 'bg-secondary-subtle text-secondary-emphasis',
            };
            // Extract preview and details from sanitized data
            $isExpandable = is_array($varData) && !isset($varData['class']);
            $isClass = is_array($varData) && isset($varData['class']);
            $preview  = $isExpandable ? ($varData['preview'] ?? 'Array ()') : null;
            $details  = $isExpandable ? ($varData['items'] ?? []) : null;
            // Scalar values (already HTML-escaped by devScalarDisplay)
            $isScalar = !$isExpandable && !$isClass && !is_array($varData);
            $scalarVal = $isScalar ? $varData : null;
            // Object preview
            $objClass = $isClass ? ($varData['class'] ?? 'unknown') : null;
            $objPreview = $isClass ? ($varData['preview'] ?? '') : null;
            ?>
                        <tr class="dev-var-row" data-var-name="<?= htmlspecialchars($varName, ENT_QUOTES, 'UTF-8') ?>"
                            data-var-type="<?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>">
                            <!-- Name -->
                            <td>
                                <code class="dev-var-name"><?= htmlspecialchars($varName, ENT_QUOTES, 'UTF-8') ?></code>
                                <?php if ($isExpandable || $isClass): ?>
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
                            <!-- Type -->
                            <td><span class="badge <?= htmlspecialchars($typeBadge, ENT_QUOTES, 'UTF-8') ?> dev-var-type-badge"><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                            <!-- Value / Preview -->
                            <td>
                                <?php if ($scalarVal !== null): ?>
                                    <code class="dev-var-value"><?= $scalarVal ?></code>
                                <?php elseif ($isClass): ?>
                                    <code class="dev-var-value"><?= htmlspecialchars($objClass, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($objPreview, ENT_QUOTES, 'UTF-8') ?></code>
                                    <?php if (isset($varData['toString'])): ?>
                                    <div class="dev-obj-tostring mt-1"><code><?= $varData['toString'] ?></code></div>
                                    <?php endif; ?>
                                <?php elseif ($isExpandable && !empty($details)): ?>
                                    <div class="dev-var-preview text-muted small"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php elseif ($isExpandable): ?>
                                    <div class="dev-var-preview text-muted small"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <!-- Expandable details row -->
                        <?php if ($isExpandable || $isClass): ?>
                        <tr class="dev-var-detail-row" style="display: none;">
                            <td colspan="3">
                                <div class="collapse" id="dev-var-<?= (string)$varName ?>">
                                    <div class="dev-var-details p-2 mt-1 border rounded" style="background: var(--bs-tertiary-bg); font-size: .7rem;">
                                        <?php if ($details && is_array($details)): ?>
                                        <table class="dev-var-detail-table table table-sm table-borderless mb-0"
                                               style="font-size: .7rem;">
                                            <?php foreach ($details as $detailKey => $detailVal): ?>
                                            <tr>
                                                <td style="width: 35%;"><code class="text-muted"><?= $detailKey ?></code></td>
                                                <td><?= is_array($detailVal) ? htmlspecialchars(json_encode($detailVal, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') : $detailVal ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
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
    var DEV_TYPES = <?= json_encode($devTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;

    /* Expand/collapse toggle icon */
    function updateExpandIcon(btn) {
        var icon = btn.querySelector('i');
        if (!icon) return;
        var target = document.getElementById(icon.getAttribute('aria-controls'));
        if (target && target.classList.contains('show')) {
            icon.className = 'bi bi-chevron-expand';
        } else {
            icon.className = 'bi bi-chevron-collapse';
        }
    }

    /* Bind collapse events to toggle icons */
    document.getElementById('dev-tools-offcanvas').addEventListener('shown.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) updateExpandIcon(btn);
    });
    document.getElementById('dev-tools-offcanvas').addEventListener('hidden.bs.collapse', function (e) {
        var btn = document.querySelector('[data-bs-target="#' + e.target.id + '"]');
        if (btn) updateExpandIcon(btn);
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
                var match = !q ||
                    name.indexOf(q) !== -1 ||
                    type.indexOf(q) !== -1 ||
                    preview.toLowerCase().indexOf(q) !== -1 ||
                    value.toLowerCase().indexOf(q) !== -1;

                row.classList.toggle('dev-var-hidden', !match);

                // Show/hide detail row along with parent
                var detailRows = row.nextElementSibling;
                if (detailRows && detailRows.classList.contains('dev-var-detail-row')) {
                    detailRows.classList.toggle('dev-var-hidden', !match);
                }
            });
        });
    }

    /* Close on Escape */
    var panel = document.getElementById('dev-tools-offcanvas');
    panel.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var bsOffcanvas = bootstrap.Offcanvas.getInstance(panel);
            if (bsOffcanvas) bsOffcanvas.hide();
        }
    });
})();
</script>
