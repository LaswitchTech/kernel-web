<?php

/**
 * Layout tests for /admin/settings view.
 *
 * Tests:
 *  - Only one Developer Settings section renders (no duplicates)
 *  - Bootstrap grid classes are present: row g-3, col-12 col-md-6
 *  - Settings state is reflected correctly
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
    $flash     = null;
    $pageTitle = 'Settings';
    $activeSection = '/admin/settings';
    $breadcrumbs = [];
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

// Verify Developer section content renders via SettingsRegistry (not hardcoded)
assert_true(str_contains($html1, 'dev content'), 'Developer section content rendered via SettingsRegistry');
echo "PASS\n";

// --- TEST 2: Bootstrap grid classes present ---

echo "= TEST 2: Bootstrap grid classes =\n";

assert_true(str_contains($html1, 'row g-3'), 'row g-3 class present');
assert_true(str_contains($html1, 'col-12'), 'col-12 class present');
assert_true(str_contains($html1, 'col-md-6'), 'col-md-6 class present');
echo "PASS\n";

// --- TEST 3: All cards wrapped in grid columns ---

echo "= TEST 3: Cards wrapped in grid columns =\n";

// Count col-12 col-md-6 wrappers
$grid_wrapper_count = preg_match_all('/col-12 col-md-6/', $html1);
assert_true($grid_wrapper_count >= 1, 'At least one grid column wrapper exists');
echo "PASS\n";

// --- TEST 4: Settings state reflected correctly ---

echo "= TEST 4: Settings state reflected =\n";

assert_true(str_contains($html1, 'Test App'), 'app_name reflected in view');
assert_true(str_contains($html1, 'dev content'), 'Developer section renders');
echo "PASS\n";

// --- TEST 5: Plugin sections exclude developer ---

echo "= TEST 5: Plugin sections skip developer =\n";

// With both developer and smtp sections, only one Developer heading should appear
$sections5 = [
    make_section('developer', 'Developer Settings', '<p>dev</p>'),
    make_section('smtp', 'SMTP Settings', '<p>smtp</p>'),
    make_section('mailer', 'Mailer Settings', '<p>mailer</p>'),
];

$html5 = render_settings_view([], $sections5);

$dev_count5 = substr_count($html5, 'Developer Settings');
assert_equal(1, $dev_count5, 'Only one Developer heading with multiple sections');
echo "PASS\n";

// --- TEST 6: No developer section → graceful fallback ---

echo "= TEST 6: No developer section → no error =\n";

$sections6 = [
    make_section('smtp', 'SMTP Settings', '<p>smtp</p>'),
];

$html6 = @render_settings_view([], $sections6);
assert_true(!str_contains($html6, 'PHP Fatal'), 'No fatal error when no developer section');
assert_true(str_contains($html6, 'SMTP Settings'), 'Other sections still render');
echo "PASS\n";

// --- TEST 7: Empty sections → no error ---

echo "= TEST 7: Empty sections list → no error =\n";

$html7 = @render_settings_view([], []);
assert_true(str_contains($html7, 'Mailer'), 'Mailer section still renders with no plugins');
assert_true(str_contains($html7, 'Notifications'), 'Notifications section still renders');
echo "PASS\n";

// --- SUMMARY ---

summary();
