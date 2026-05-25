<?php

/**
 * Layout tests for /admin/settings view.
 *
 * Tests:
 *  - Only one Developer Settings section (no duplicates)
 *  - Cards render collapsed by default
 *  - Collapse buttons/targets exist with correct IDs
 *  - col/card equal-height classes present (d-flex, h-100, w-100)
 *  - Bootstrap grid classes present: row g-3, col-12 col-md-6
 *  - Search input exists
 *  - No duplicate Developer Settings section
 *  - Settings state reflected correctly
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';
reset_counters();

// --- Helper: mock section with renderBody ---

function make_section(string $id, string $label, string $body): object
{
    return new class($id, $label, $body) {
        public function __construct(
            public readonly string $id,
            public readonly string $label,
            private readonly string $body,
        ) {}
        public function renderBody(array $context = []): string
        {
            return $this->body;
        }
    };
}

// --- Helper: render settings view and capture output ---

function render_settings_view(array $settings, array $sections, array $errors = []): string
{
    $flash         = null;
    $pageTitle     = 'Settings';
    $activeSection = '/admin/settings';
    $breadcrumbs   = [];
    ob_start();
    extract(compact('settings', 'sections', 'errors', 'flash', 'pageTitle', 'activeSection', 'breadcrumbs'));
    require __DIR__ . '/../app/Views/admin/settings.php';
    $html = ob_get_clean();
    return $html;
}

// --- TEST 1: Only one Developer Settings section ---

echo "= TEST 1: Single Developer Settings section =\n";

$sections1 = [
    make_section('developer', 'Developer Settings', '<p>dev content</p>'),
    make_section('smtp', 'SMTP Settings', '<p>smtp content</p>'),
];

$settings1 = [
    'app_name' => 'Test App',
    'developer.developer' => true,
    'developer.debug' => false,
];

$html1 = render_settings_view($settings1, $sections1);

$developer_count = substr_count($html1, 'Developer Settings');
assert_equal(1, $developer_count, 'Only one "Developer Settings" heading renders');
echo "PASS\n";

// --- TEST 2: Cards render collapsed by default ---

echo "= TEST 2: Cards rendered collapsed by default =\n";

// Collapse divs should have aria-expanded="false" (Bootstrap collapsed class)
assert_true(str_contains($html1, 'collapse"'), 'collapse div class present');
assert_true(str_contains($html1, 'aria-expanded="false"'), 'cards start collapsed (aria-expanded=false)');
echo "PASS\n";

// --- TEST 3: Collapse buttons/targets exist ---

echo "= TEST 3: Collapse buttons and targets exist =\n";

// Application
assert_true(str_contains($html1, 'data-bs-toggle="collapse"'), 'collapse toggle attribute present');
assert_true(str_contains($html1, 'data-bs-target="#collapse-application"'), 'Application collapse target present');
assert_true(str_contains($html1, 'id="collapse-application"'), 'Application collapse body present');

// Authentication
assert_true(str_contains($html1, 'id="collapse-authentication"'), 'Authentication collapse body present');
assert_true(str_contains($html1, 'id="collapse-mailer"'), 'Mailer collapse body present');
assert_true(str_contains($html1, 'id="collapse-notifications"'), 'Notifications collapse body present');
echo "PASS\n";

// --- TEST 4: Equal-height classes present ---

echo "= TEST 4: Equal-height classes present =\n";

assert_true(str_contains($html1, 'd-flex'), 'd-flex class present for equal-height rows');
assert_true(str_contains($html1, 'h-100'), 'h-100 class present for equal-height cards');
assert_true(str_contains($html1, 'w-100'), 'w-100 class present for full-width cards');
echo "PASS\n";

// --- TEST 5: Bootstrap grid classes present ---

echo "= TEST 5: Bootstrap grid classes =\n";

assert_true(str_contains($html1, 'row g-3'), 'row g-3 class present');
assert_true(str_contains($html1, 'col-12'), 'col-12 class present');
assert_true(str_contains($html1, 'col-md-6'), 'col-md-6 class present');
echo "PASS\n";

// --- TEST 6: Search input exists ---

echo "= TEST 6: Search/filter input exists =\n";

assert_true(str_contains($html1, 'id="settings-search"'), 'search input present');
assert_true(str_contains($html1, 'type="search"'), 'search type input');
assert_true(str_contains($html1, 'Filter settings'), 'placeholder text present');
assert_true(str_contains($html1, 'id="settings-search-clear"'), 'clear button present');
assert_true(str_contains($html1, 'id="settings-search-empty"'), 'no-results message present');
echo "PASS\n";

// --- TEST 7: Card headers are clickable ---

echo "= TEST 7: Card headers are clickable collapsible triggers =\n";

// Headers should have role="button" and tabindex
assert_true(str_contains($html1, 'role="button"'), 'card header has role=button');
assert_true(str_contains($html1, 'tabindex="0"'), 'card header is keyboard-focusable (tabindex=0)');
echo "PASS\n";

// --- TEST 8: Settings state reflected correctly ---

echo "= TEST 8: Settings state reflected =\n";

assert_true(str_contains($html1, 'Test App'), 'app_name reflected in view');
assert_true(str_contains($html1, 'dev content'), 'Developer section renders');
echo "PASS\n";

// --- TEST 9: Plugin sections exclude developer ---

echo "= TEST 9: Plugin sections skip developer =\n";

$sections9 = [
    make_section('developer', 'Developer Settings', '<p>dev</p>'),
    make_section('smtp', 'SMTP Settings', '<p>smtp</p>'),
    make_section('mailer', 'Mailer Settings', '<p>mailer</p>'),
];

$html9 = render_settings_view([], $sections9);

$dev_count9 = substr_count($html9, 'Developer Settings');
assert_equal(1, $dev_count9, 'Only one Developer heading with multiple sections');
echo "PASS\n";

// --- TEST 10: No developer section → graceful fallback ---

echo "= TEST 10: No developer section → no error =\n";

$sections10 = [
    make_section('smtp', 'SMTP Settings', '<p>smtp</p>'),
];

$html10 = @render_settings_view([], $sections10);
assert_true(!str_contains($html10, 'PHP Fatal'), 'No fatal error when no developer section');
assert_true(str_contains($html10, 'SMTP Settings'), 'Other sections still render');
echo "PASS\n";

// --- TEST 11: Empty sections → no error ---

echo "= TEST 11: Empty sections list → no error =\n";

$html11 = @render_settings_view([], []);
assert_true(str_contains($html11, 'Mailer'), 'Mailer section still renders with no plugins');
assert_true(str_contains($html11, 'Notifications'), 'Notifications section still renders');
echo "PASS\n";

// --- TEST 12: Collapse chevrons present ---

echo "= TEST 12: Chevron icons present for expand/collapse =\n";

assert_true(str_contains($html1, 'bi-chevron-down'), 'chevron-down icons present');
echo "PASS\n";

// --- SUMMARY ---

summary();
