#!/usr/bin/env php
<?php

/**
 * Test runner — discovers and runs all *_test.php files in the tests/ directory.
 *
 * Usage:
 *   php tests/run.php              — run all tests
 *   php tests/run.php router       — run only router_test.php
 *   php tests/run.php plugin       — run only plugin_test.php
 *
 * Zero dependencies. No PHPUnit required.
 */

$testsDir = __DIR__;
$allTests = glob($testsDir . '/*_test.php');

if ($allTests === []) {
    echo "No test files found in {$testsDir}/\n";
    exit(1);
}

// Filter by name if a positional arg is provided
if ($argc > 1) {
    $filter = $argv[1];
    $allTests = array_filter($allTests, fn($f) => str_contains($f, $filter . '_test.php'));
    if ($allTests === []) {
        echo "No test files matching '{$filter}' found in {$testsDir}/\n";
        exit(1);
    }
}

sort($allTests);

$passedAll = true;
foreach ($allTests as $testFile) {
    $basename = basename($testFile, '_test.php');
    echo "Running: {$basename}... ";

    // Capture output + exit code
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open("php '{$testFile}'", $descriptors, $pipes);

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    if ($exitCode === 0) {
        // Extract test result from last line of output
        $lines = explode("\n", trim($output));
        $resultLine = end($lines);
        echo "{$resultLine}\n";
    } else {
        echo "FAILED (exit {$exitCode})\n";
        echo $output;
        if ($errors) {
            echo "STDERR:\n{$errors}\n";
        }
        $passedAll = false;
    }
}

echo "\n";
echo $passedAll ? "All test suites passed.\n" : "Some test suites failed.\n";
exit($passedAll ? 0 : 1);
