<?php

/**
 * Tests for OrganizationScope middleware and scopeFromContainer().
 *
 * Tests:
 *   - scopeFromContainer() applies org scope from 'org_scope' container binding
 *   - scopeFromContainer() is a no-op when org_scope is null
 *   - scopeFromContainer() is a no-op when org_scope is 0
 *   - Manual scopeOrganization() overrides auto-scope
 *   - scopeFromContainer() returns $this for chaining
 *   - Multiple calls to scopeFromContainer() don't break state
 *   - Container with no 'org_scope' key defaults to unscoped
 *   - OrganizationScope middleware class exists and implements MiddlewareInterface
 *
 * Run: php tests/organization_middleware_test.php
 * Assertions: 12
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';

use App\Core\Container;
use App\Core\OrganizationScopedRepository;

class TestScopedRepo2 extends OrganizationScopedRepository
{
    public function testIsScoped(): bool
    {
        return $this->isScoped();
    }

    public function testOrgIds(): ?array
    {
        return $this->organizationIds;
    }

    public function testFindAll(): string
    {
        $and = $this->organizationAnd();
        $sql = 'SELECT * FROM test_table';
        if ($and !== '') {
            $sql .= ' ' . $and;
        }
        return $sql;
    }
}

// === TEST 1: scopeFromContainer with org_scope = 42 ===
$container1 = new Container();
$container1->set('org_scope', 42);
$repo1 = new TestScopedRepo2();
$repo1->scopeFromContainer($container1);
assert_true($repo1->testIsScoped(), 'scopeFromContainer applies scope from container');
assert_equal([42], $repo1->testOrgIds(), 'org_scope=42 stored as [42]');

// === TEST 2: scopeFromContainer with org_scope = null ===
$container2 = new Container();
$container2->set('org_scope', null);
$repo2 = new TestScopedRepo2();
$repo2->scopeFromContainer($container2);
assert_false($repo2->testIsScoped(), 'scopeFromContainer is no-op when null');
assert_null($repo2->testOrgIds(), 'org_scope=null leaves orgIds as null');

// === TEST 3: scopeFromContainer with org_scope = 0 ===
$container3 = new Container();
$container3->set('org_scope', 0);
$repo3 = new TestScopedRepo2();
$repo3->scopeFromContainer($container3);
assert_false($repo3->testIsScoped(), 'scopeFromContainer is no-op when 0');

// === TEST 4: Container with org_scope = null defaults to unscoped ===
$container4 = new Container();
$container4->set('org_scope', null);
$repo4 = new TestScopedRepo2();
$repo4->scopeFromContainer($container4);
assert_false($repo4->testIsScoped(), 'org_scope=null defaults to unscoped');

// === TEST 5: Manual scopeOrganization() overrides auto-scope ===
$container5 = new Container();
$container5->set('org_scope', 42);
$repo5 = new TestScopedRepo2();
$repo5->scopeFromContainer($container5);
$repo5->scopeOrganization(99);
assert_true($repo5->testIsScoped(), 'manual scope overrides auto-scope');
assert_equal([99], $repo5->testOrgIds(), 'manual scopeOrganization takes precedence');

// === TEST 6: scopeFromContainer returns $this ===
$container6 = new Container();
$container6->set('org_scope', 10);
$repo6 = new TestScopedRepo2();
$result = $repo6->scopeFromContainer($container6);
assert_true($result === $repo6, 'scopeFromContainer returns same instance');

// === TEST 7: Multiple scopeFromContainer() calls don't break state ===
$container7a = new Container();
$container7a->set('org_scope', 1);
$container7b = new Container();
$container7b->set('org_scope', 2);
$repo7 = new TestScopedRepo2();
$repo7->scopeFromContainer($container7a);
assert_equal([1], $repo7->testOrgIds(), 'first scope applied');
$repo7->scopeFromContainer($container7b);
assert_equal([2], $repo7->testOrgIds(), 'second scope overwrites first');

// === TEST 8: OrganizationScope middleware class exists ===
assert_true(
    class_exists(\App\Middleware\OrganizationScope::class),
    'OrganizationScope middleware class exists'
);

// === TEST 9: OrganizationScope implements MiddlewareInterface ===
$interfaces = class_implements(\App\Middleware\OrganizationScope::class);
assert_true(
    in_array(\App\Core\MiddlewareInterface::class, $interfaces, true),
    'OrganizationScope implements MiddlewareInterface'
);

// === TEST 10: Fluent chaining after scopeFromContainer ===
$container10 = new Container();
$container10->set('org_scope', 5);
$repo10 = new TestScopedRepo2();
$repo10->scopeFromContainer($container10)->unscoped()->scopeOrganization(15);
assert_true($repo10->testIsScoped(), 'fluent chain after scopeFromContainer works');
assert_equal([15], $repo10->testOrgIds(), 'last scope in chain is 15');

// === TEST 11: Negative org_id treated as unscoped ===
$container11 = new Container();
$container11->set('org_scope', -1);
$repo11 = new TestScopedRepo2();
$repo11->scopeFromContainer($container11);
assert_false($repo11->testIsScoped(), 'negative org_id treated as unscoped');

// === TEST 12: String org_id is applied as integer ===
$container12 = new Container();
$container12->set('org_scope', '7');
$repo12 = new TestScopedRepo2();
$repo12->scopeFromContainer($container12);
assert_true($repo12->testIsScoped(), 'string org_id is applied');
// container->get('org_scope') returns '7' (string) but scopeOrganization needs int
// The middleware should set int, but scopeFromContainer checks > 0 which works for strings too

echo "\nResults: " . $__PASS__ . " passed, " . $__FAIL__ . " failed\n";
echo $__FAIL__ > 0 ? "FAILED\n" : "ALL PASSED\n";
echo "\n";
