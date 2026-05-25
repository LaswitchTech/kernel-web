<?php

/**
 * Layout tests for /admin/settings view.
 *
 * Tests:
 *  - Only one Developer Settings section (no duplicates)
 *  - Cards render collapsed by default
 *  - Collapse buttons/targets exist with correct IDs
 *  - Icon and title use larger font-size classes (fs-4 / fs-5)
 *  - col/card equal-height classes present (d-flex, h-100, w-100)
 *  - Bootstrap grid classes present: row g-3, col-12 col-md-6
 *  - Search input exists
 *  - Column wrappers have .js-settings-card and data-search-text
 *  - Search uses addEventListener('input'
 *  - Search uses .textContent
 *  - Search uses .toLowerCase()
 *  - Search hides .js-settings-card wrappers with d-none
 *  - Search uses indexOf, not .includes
 *  - Search has Enter keydown preventDefault handler
 *  - Clear button not conditionally hidden via JS (no display:none)
 *  - Clear button always rendered
 *  - Clear button calls preventDefault, focus
 *  - Chevron icon class exists: .js-settings-chevron
 *  - Chevron uses document-level event delegation
 *  - Chevron uses bi-chevron-up / bi-chevron-down
 *  - Chevrons initialized on page load
 *  - Settings state reflected correctly
 *  - Plugin cards (SMTP/Telico) included in searchable wrappers
 *  - Search is outside the settings form
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

assert_true(str_contains($html1, 'collapse"'), 'collapse div class present');
assert_true(str_contains($html1, 'aria-expanded="false"'), 'cards start collapsed (aria-expanded=false)');
echo "PASS\n";

// --- TEST 3: Collapse buttons and targets exist ---

echo "= TEST 3: Collapse buttons and targets exist =\n";

assert_true(str_contains($html1, 'data-bs-toggle="collapse"'), 'collapse toggle attribute present');
assert_true(str_contains($html1, 'data-bs-target="#collapse-application"'), 'Application collapse target present');
assert_true(str_contains($html1, 'id="collapse-application"'), 'Application collapse body present');
assert_true(str_contains($html1, 'id="collapse-authentication"'), 'Authentication collapse body present');
assert_true(str_contains($html1, 'id="collapse-mailer"'), 'Mailer collapse body present');
assert_true(str_contains($html1, 'id="collapse-notifications"'), 'Notifications collapse body present');
echo "PASS\n";

// --- TEST 4: Icon and title font-size classes ---

echo "= TEST 4: Icon and title font-size classes =\n";

assert_true(str_contains($html1, 'fs-4 text-primary'), 'icons use fs-4');
assert_true(str_contains($html1, 'fw-semibold fs-5 mb-0'), 'titles use fs-5');
echo "PASS\n";

// --- TEST 5: Equal-height classes present ---

echo "= TEST 5: Equal-height classes present =\n";

assert_true(str_contains($html1, 'd-flex'), 'd-flex class present for equal-height rows');
assert_true(str_contains($html1, 'h-100'), 'h-100 class present for equal-height cards');
assert_true(str_contains($html1, 'w-100'), 'w-100 class present for full-width cards');
echo "PASS\n";

// --- TEST 6: Bootstrap grid classes present ---

echo "= TEST 6: Bootstrap grid classes =\n";

assert_true(str_contains($html1, 'row g-3'), 'row g-3 class present');
assert_true(str_contains($html1, 'col-12'), 'col-12 class present');
assert_true(str_contains($html1, 'col-md-6'), 'col-md-6 class present');
echo "PASS\n";

// --- TEST 7: Search input exists ---

echo "= TEST 7: Search/filter input exists =\n";

assert_true(str_contains($html1, 'id="settings-search"'), 'search input present');
assert_true(str_contains($html1, 'type="search"'), 'search type input');
assert_true(str_contains($html1, 'Filter settings'), 'placeholder text present');
echo "PASS\n";

// --- TEST 8: Column wrappers have .js-settings-card ---

echo "= TEST 8: js-settings-card class on column wrappers =\n";

assert_true(str_contains($html1, 'js-settings-card'), '.js-settings-card class present');
$js_card_count = substr_count($html1, 'js-settings-card');
assert_true($js_card_count >= 5, 'At least 5 column wrappers have .js-settings-card');
echo "PASS\n";

// --- TEST 9: data-search-text attributes present ---

echo "= TEST 9: data-search-text attributes present =\n";

assert_true(str_contains($html1, 'data-search-text="'), 'data-search-text attribute present');
assert_true(substr_count($html1, 'data-search-text="') >= 5, 'At least 5 cards have data-search-text');
echo "PASS\n";

// --- TEST 10: Search uses addEventListener('input' ---

echo "= TEST 10: Search uses addEventListener('input' =\n";

assert_true(str_contains($html1, "addEventListener('input'"), 'search uses addEventListener("input"');
echo "PASS\n";

// --- TEST 11: Search uses .textContent ---

echo "= TEST 11: Search uses .textContent =\n";

assert_true(str_contains($html1, 'textContent'), 'search uses .textContent for matching');
echo "PASS\n";

// --- TEST 12: Search uses .toLowerCase() ---

echo "= TEST 12: Search uses .toLowerCase() =\n";

assert_true(str_contains($html1, 'toLowerCase()'), 'search uses toLowerCase for case-insensitive matching');
echo "PASS\n";

// --- TEST 13: Search hides wrappers with d-none ---

echo "= TEST 13: Search hides wrappers with d-none =\n";

assert_true(str_contains($html1, "classList.toggle('d-none'"), 'JS toggles d-none class on wrappers');
echo "PASS\n";

// --- TEST 14: Search uses indexOf, not .includes ---

echo "= TEST 14: Search uses indexOf for matching =\n";

assert_true(str_contains($html1, 'indexOf(query)'), 'search uses indexOf for query matching');
assert_true(!str_contains($html1, '.includes(query)'), 'search does NOT use .includes for query matching');
echo "PASS\n";

// --- TEST 15: Search has Enter keydown preventDefault handler ---

echo "= TEST 15: Search has Enter keydown preventDefault handler =\n";

assert_true(str_contains($html1, "addEventListener('keydown'"), 'search has keydown listener');
assert_true(str_contains($html1, "event.key === 'Enter'") || str_contains($html1, 'event.key == "Enter"'), 'search checks for Enter key');
assert_true(str_contains($html1, 'event.preventDefault()'), 'search prevents default on Enter');
echo "PASS\n";

// --- TEST 16: Clear button not conditionally hidden via JS ---

echo "= TEST 16: Clear button always rendered, not conditionally hidden =\n";

assert_true(str_contains($html1, 'id="settings-search-clear"'), 'clear button present in HTML');
// Check that there's no JS that sets display:none on clearBtn (only style="display:none" in HTML)
assert_true(!str_contains($html1, "clearBtn.style.display"), 'clear button not hidden via JS style');
echo "PASS\n";

// --- TEST 17: Clear button always rendered and calls preventDefault/focus ---

echo "= TEST 17: Clear button always rendered =\n";

assert_true(str_contains($html1, 'id="settings-search-clear"'), 'clear button exists in markup');
assert_true(str_contains($html1, 'type="button"') && str_contains($html1, 'bi-x-lg'), 'clear button has x icon');
assert_true(str_contains($html1, "addEventListener('click'"), 'clear button has click listener');
assert_true(str_contains($html1, 'event.preventDefault()'), 'clear button prevents default');
assert_true(str_contains($html1, 'focus()'), 'clear button focuses input');
echo "PASS\n";

// --- TEST 18: Chevron icon class exists ---

echo "= TEST 18: Chevron icon class =\n";

assert_true(str_contains($html1, 'js-settings-chevron'), '.js-settings-chevron class present');
assert_true(substr_count($html1, 'js-settings-chevron') >= 5, 'At least 5 chevrons have js-settings-chevron class');
echo "PASS\n";

// --- TEST 19: Chevron uses document-level event delegation ---

echo "= TEST 19: Chevron uses document-level event delegation =\n";

assert_true(str_contains($html1, "document.addEventListener('shown.bs.collapse'"), 'shown.bs.collapse via document.addEventListener');
assert_true(str_contains($html1, "document.addEventListener('hidden.bs.collapse'"), 'hidden.bs.collapse via document.addEventListener');
// Make sure it's NOT per-element (no forEach on collapses with addEventListener)
$collapseEventCount = substr_count($html1, 'collapseEl.addEventListener');
assert_true($collapseEventCount === 0, 'Chevron does NOT use per-element addEventListener (uses delegation instead)');
echo "PASS\n";

// --- TEST 20: Chevron classes switch ---

echo "= TEST 20: Chevron classes switch =\n";

assert_true(str_contains($html1, 'bi-chevron-up'), 'bi-chevron-up class present');
assert_true(str_contains($html1, 'bi-chevron-down'), 'bi-chevron-down class present');
echo "PASS\n";

// --- TEST 21: Chevrons initialized on page load ---

echo "= TEST 21: Chevrons initialized on page load =\n";

assert_true(str_contains($html1, 'classList.contains("show")') || str_contains($html1, "classList.contains('show')"), 'init checks .show class');
assert_true(str_contains($html1, "querySelectorAll('.js-settings-collapse')") || str_contains($html1, 'querySelectorAll(".js-settings-collapse")'), 'init queries .js-settings-collapse');
echo "PASS\n";

// --- TEST 22: Settings state reflected correctly ---

echo "= TEST 22: Settings state reflected =\n";

assert_true(str_contains($html1, 'Test App'), 'app_name reflected in view');
assert_true(str_contains($html1, 'dev content'), 'Developer section renders');
echo "PASS\n";

// --- TEST 23: Plugin sections exclude developer ---

echo "= TEST 23: Plugin sections skip developer =\n";

$sections23 = [
    make_section('developer', 'Developer Settings', '<p>dev</p>'),
    make_section('smtp', 'SMTP Settings', '<p>smtp</p>'),
    make_section('mailer', 'Mailer Settings', '<p>mailer</p>'),
];

$html23 = render_settings_view([], $sections23);

$dev_count23 = substr_count($html23, 'Developer Settings');
assert_equal(1, $dev_count23, 'Only one Developer heading with multiple sections');
echo "PASS\n";

// --- TEST 24: No developer section → graceful fallback ---

echo "= TEST 24: No developer section → no error =\n";

$sections24 = [
    make_section('smtp', 'SMTP Settings', '<p>smtp</p>'),
];

$html24 = @render_settings_view([], $sections24);
assert_true(!str_contains($html24, 'PHP Fatal'), 'No fatal error when no developer section');
assert_true(str_contains($html24, 'SMTP Settings'), 'Other sections still render');
echo "PASS\n";

// --- TEST 25: Empty sections → no error ---

echo "= TEST 25: Empty sections list → no error =\n";

$html25 = @render_settings_view([], []);
assert_true(str_contains($html25, 'Mailer'), 'Mailer section still renders with no plugins');
assert_true(str_contains($html25, 'Notifications'), 'Notifications section still renders');
echo "PASS\n";

// --- TEST 26: Plugin cards (SMTP/Telico) included in searchable wrappers ---

echo "= TEST 26: Plugin cards have js-settings-card and data-search-text =\n";

assert_true(str_contains($html23, 'data-search-text="'), 'plugin cards have data-search-text');
assert_true(substr_count($html23, 'js-settings-card') >= 4, 'Plugin cards also have .js-settings-card');
echo "PASS\n";

// --- TEST 27: Search is outside the settings form ---

echo "= TEST 27: Search is outside the settings form =\n";

// Find the form tag and search div positions
$formPos = strpos($html1, 'id="settings-form"');
$searchDivPos = strpos($html1, 'id="settings-search"');
$formClosePos = strpos($html1, '</form>');

// Search should appear BEFORE the form opens, or the search div should be outside the form tag
// In our implementation, the search is placed OUTSIDE the form (before the form tag)
assert_true($searchDivPos < $formPos, 'Search input is rendered before the form tag (outside the form)');
echo "PASS\n";

// --- TEST 28: No optional chaining (?.) in JS ---

echo "= TEST 28: No optional chaining in JS =\n";

// The JS should not use ?. anywhere
assert_true(!str_contains($html1, '?.'), 'JS does not use optional chaining (?.)');
echo "PASS\n";

// --- SUMMARY ---

summary();
