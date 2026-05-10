<?php

/**
 * Minimal assertion helpers — zero dependencies.
 *
 * Usage:
 *   assert_true($condition, "message");
 *   assert_false($condition, "message");
 *   assert_equal($a, $b, "message");
 *   assert_null($val, "message");
 *   assert_not_null($val, "message");
 *   assert_instance_of($obj, MyClass::class, "message");
 *   assert_array_has_key($arr, 'key', "message");
 *   assert_array_has_length($arr, 3, "message");
 *   assert_raises(fn() => ..., Exception::class, "message");
 */

/** @var int */
$__PASS__ = 0;

/** @var int */
$__FAIL__ = 0;

/** @var string[] */
$__FAILURES__ = [];

function assert_true($value, string $message): void
{
    global $__PASS__, $__FAIL__, $__FAILURES__;
    if ($value === true) {
        $__PASS__++;
    } else {
        $__FAIL__++;
        $__FAILURES__[] = "FAIL: $message (expected true, got " . var_export($value, true) . ")";
    }
}

function assert_false($value, string $message): void
{
    assert_true($value === false, $message);
}

function assert_equal($expected, $actual, string $message): void
{
    global $__PASS__, $__FAIL__, $__FAILURES__;
    if ($expected === $actual) {
        $__PASS__++;
    } else {
        $__FAIL__++;
        $__FAILURES__[] = "FAIL: $message (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")";
    }
}

function assert_equal_types($expected, $actual, string $message): void
{
    global $__PASS__, $__FAIL__, $__FAILURES__;
    if ($expected == $actual && gettype($expected) === gettype($actual)) {
        $__PASS__++;
    } else {
        $__FAIL__++;
        $__FAILURES__[] = "FAIL: $message (expected $expected of type " . gettype($expected) . ", got $actual of type " . gettype($actual) . ")";
    }
}

function assert_null($value, string $message): void
{
    assert_true($value === null, $message);
}

function assert_not_null($value, string $message): void
{
    assert_true($value !== null, $message);
}

function assert_instance_of($object, string $className, string $message): void
{
    assert_true($object instanceof $className, $message);
}

function assert_array_has_key($array, $key, string $message): void
{
    assert_true(isset($array[$key]) || array_key_exists($key, $array), $message);
}

function assert_array_has_length($array, int $length, string $message): void
{
    assert_equal($length, count($array), $message);
}

function assert_contains($needle, $haystack, string $message): void
{
    if (is_string($haystack)) {
        assert_true(strpos($haystack, $needle) !== false, $message);
    } elseif (is_array($haystack)) {
        assert_true(in_array($needle, $haystack, true), $message);
    } else {
        assert_true(false, "assert_contains: haystack must be string or array, got " . gettype($haystack));
    }
}

function assert_raises(callable $fn, string $exceptionClass, string $message): void
{
    global $__PASS__, $__FAIL__, $__FAILURES__;
    try {
        $fn();
        $__FAIL__++;
        $__FAILURES__[] = "FAIL: $message (expected exception $exceptionClass, none thrown)";
    } catch (\Throwable $e) {
        if ($e instanceof $exceptionClass) {
            $__PASS__++;
        } else {
            $__FAIL__++;
            $__FAILURES__[] = "FAIL: $message (expected $exceptionClass, got " . get_class($e) . ": {$e->getMessage()})";
        }
    }
}

function assert_json_valid(string $json, string $message): void
{
    global $__PASS__, $__FAIL__, $__FAILURES__;
    json_decode($json);
    if (json_last_error() === JSON_ERROR_NONE) {
        $__PASS__++;
    } else {
        $__FAIL__++;
        $__FAILURES__[] = "FAIL: $message (JSON error: " . json_last_error_msg() . ")";
    }
}

function assert_class_exists(string $className, string $message): void
{
    assert_true(class_exists($className) || interface_exists($className), $message);
}

function summary(): void
{
    global $__PASS__, $__FAIL__, $__FAILURES__;
    echo "\n";
    foreach ($__FAILURES__ as $f) {
        echo "  $f\n";
    }
    echo "Results: $__PASS__ passed, $__FAIL__ failed\n";
    echo $__FAIL__ > 0 ? "FAILED\n" : "ALL PASSED\n";
    echo "\n";
}

function reset_counters(): void
{
    global $__PASS__, $__FAIL__, $__FAILURES__;
    $__PASS__ = 0;
    $__FAIL__ = 0;
    $__FAILURES__ = [];
}
