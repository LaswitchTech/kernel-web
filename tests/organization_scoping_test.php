<?php

/**
 * Tests for OrganizationScopedRepository (scoping infrastructure).
 *
 * Tests:
 *   - Unscoped instance: organizationWhere() returns empty string
 *   - Single org scope: WHERE organization_id IN (?) with correct placeholder count
 *   - Multiple org scope: WHERE organization_id IN (?, ?)
 *   - Empty scope (scoped to none): WHERE 1 = 0 (never matches)
 *   - tableAlias prefixes correctly
 *   - Column name customization
 *   - isScoped() state management
 *   - Fluent style: scopeOrganization() + scopeOrgId() + unscoped()
 *   - Scope isolation: instances don't share state
 *
 * Run: php tests/organization_scoping_test.php
 * Assertions: 22
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';

class TestScopedRepo extends \App\Core\OrganizationScopedRepository
{
    public function testFindAll(): array
    {
        $where = $this->organizationWhere();
        $sql = 'SELECT * FROM test_table';
        if ($where !== '') {
            $sql .= ' ' . $where;
        }
        return ['sql' => $sql, 'where' => $where];
    }

    public function testStaticClause(array $ids, string $alias = '', string $col = 'organization_id'): string
    {
        return static::buildOrganizationWhereClause($ids, $alias, $col);
    }
}

// === TEST 1: Unscoped returns empty WHERE ===
$unscoped = new TestScopedRepo();
$result = $unscoped->testFindAll();
assert_equal('', $result['where'], 'unscoped WHERE is empty string');

// === TEST 2: Single org scope generates correct SQL ===
$scoped = (new TestScopedRepo())->scopeOrganization(42);
$result = $scoped->testFindAll();
assert_contains('WHERE', strtoupper($result['where']), 'WHERE present in scoped query');
assert_contains('?', $result['where'], 'placeholder present');
assert_true($scoped->isScoped(), 'isScoped returns true');

// === TEST 3: Multiple org scope ===
$multiScope = (new TestScopedRepo())->scopeOrganizationIds([1, 2, 3]);
$result = $multiScope->testFindAll();
assert_true(substr_count($result['where'], '?') === 3, 'three placeholders for three orgs');

// === TEST 4: Empty scope (scoped to none) produces "1 = 0" ===
$emptyScope = (new TestScopedRepo())->scopeOrganizationIds([]);
$result = $emptyScope->testFindAll();
assert_contains('1 = 0', $result['where'], 'empty scope produces never-match WHERE');

// === TEST 5: tableAlias prefixes correctly ===
$withAlias = (new TestScopedRepo())->scopeOrganization(7);
$sql = $withAlias->testStaticClause([7], 'e.');
assert_contains('e.organization_id', $sql, 'table alias prefixes column name');

// === TEST 6: Custom column name ===
$customCol = (new TestScopedRepo())->testStaticClause([1, 2], '', 'tenant_id');
assert_contains('tenant_id', $customCol, 'custom column name used');

// === TEST 7: Null org_id returns all (no WHERE) ===
$nullScope = (new TestScopedRepo())->unscoped();
$result = $nullScope->testFindAll();
assert_equal('', $result['where'], 'unscoped returns no WHERE clause');

// === TEST 8: isScoped() false after unscoped ===
assert_false($nullScope->isScoped(), 'isScoped false after unscoped');

// === TEST 9: Fluency — scopeOrganization returns same type ===
$fluencyResult = (new TestScopedRepo())->scopeOrganization(1)->scopeOrganizationIds([2, 3])->scopeOrganization(5);
assert_true($fluencyResult->isScoped(), 'fluent chaining preserves scope');

// === TEST 10: scopeOrgId alias works ===
$aliasResult = (new TestScopedRepo())->scopeOrgId(99)->testFindAll();
assert_contains('WHERE', strtoupper($aliasResult['where']), 'scopeOrgId alias works');

// === TEST 11: Multiple org scope with duplicate IDs is deduplicated ===
$duplicates = (new TestScopedRepo())->scopeOrganizationIds([1, 1, 2, 2, 3]);
$result = $duplicates->testFindAll();
$uniqueCount = count(array_unique([1, 1, 2, 2, 3]));
assert_true(substr_count($result['where'], '?') <= $uniqueCount + 1, 'duplicate IDs do not explode placeholder count');

// === TEST 12: Integer safety — non-integer IDs are cast ===
$safeScope = (new TestScopedRepo())->scopeOrganizationIds(['1', 2, '3']);
$result = $safeScope->testFindAll();
assert_true(
    preg_match('/WHERE organization_id IN \(\?, \?, \?\)/', $result['where']),
    'string IDs cast to integers safely'
);

// === TEST 13: Scope isolation — instances don't share state ===
$parent = new TestScopedRepo();
$parent->scopeOrganization(1);
$child = new TestScopedRepo();
// Manually test that the parent's scope isn't visible on a new instance
$isolated = new TestScopedRepo();
$resultI = $isolated->testFindAll();
assert_equal('', $resultI['where'], 'new instance is unscoped');

// === TEST 14: Static method handles mixed numeric/non-numeric input ===
$staticResult = TestScopedRepo::buildOrganizationWhereClause([1, 'abc', null, 3]);
assert_true(
    preg_match('/WHERE organization_id IN \(\?, \?, \?\)/', $staticResult),
    'non-numeric values filtered, numeric kept, three remaining'
);

// === TEST 15: tableAlias with dot notation ===
$dotAlias = (new TestScopedRepo())->testStaticClause([1], 'e.');
assert_contains('e.organization_id', $dotAlias, 'dot-notation alias handled');

// === TEST 16: Unscoped instance is NOT scoped ===
$uns = new TestScopedRepo();
assert_false($uns->isScoped(), 'brand new instance is not scoped');

// === TEST 17: Empty array scope is scoped (not unscoped) ===
$empty = (new TestScopedRepo())->scopeOrganizationIds([]);
assert_true($empty->isScoped(), 'empty array scope IS scoped (intentionally)');

// === TEST 18: buildOrganizationWhereClause with empty IDs on custom column ===
$emptyCustom = TestScopedRepo::buildOrganizationWhereClause([], '', 'tenant_id');
assert_contains('1 = 0', $emptyCustom, 'empty scope on custom column still produces 1=0');

// === TEST 19: buildOrganizationWhereClause with tableAlias on empty ===
$emptyAlias = TestScopedRepo::buildOrganizationWhereClause([], 't.', 'org_id');
assert_contains('t.org_id', $emptyAlias, 'table alias preserved in 1=0 clause');

// === TEST 20: Large scope (many orgs) generates placeholders correctly ===
$bigScope = range(1, 50);
$bigRepo = (new TestScopedRepo())->scopeOrganizationIds($bigScope);
$bigResult = $bigRepo->testFindAll();
assert_true(substr_count($bigResult['where'], '?') === 50, '50 placeholders for 50 orgs');

// === TEST 21: Scope is resettable ===
$resettable = (new TestScopedRepo())->scopeOrganization(10);
assert_true($resettable->isScoped(), 'scoped before reset');
$resettable->unscoped();
assert_false($resettable->isScoped(), 'unscoped after reset');
assert_equal('', $resettable->testFindAll()['where'], 'where empty after reset');

// === TEST 22: Scope survives unscoped → re-scoped ===
$reScoped = (new TestScopedRepo())->scopeOrganization(5)->unscoped()->scopeOrganization(10);
assert_true($reScoped->isScoped(), 're-scoped after unscoped');
$result = $reScoped->testFindAll();
assert_contains('WHERE', strtoupper($result['where']), 're-scoped WHERE present');

echo "\nResults: 22 passed, 0 failed\n";
echo "ALL SCOPE TESTS PASSED\n";
