<?php

echo "\n=== PHP-ASM Modular Compiler Test Suite ===\n\n";

$php = 'D:\\Applications\\PHP\\current\\php.exe';
$phc = __DIR__ . '\\..\\src\\phc.php';

$tests = [
    'test_fib_simple.php' => '8',
    'test_simple_function.php' => 'Hello from function!',
    'test_function_params.php' => '8',
    'test_multiple_functions.php' => '42 5',
];

$passed = 0;
$failed = 0;

foreach ($tests as $test => $expected) {
    echo "Testing: $test... ";
    
    $output = shell_exec("$php $phc exec $test 2>&1");
    $output = trim($output);
    
    // Remove ANSI codes and progress messages
    $output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
    $output = preg_replace('/#< CLIXML.*$/s', '', $output);
    $output = trim($output);
    
    if (strpos($output, $expected) !== false || $output === $expected) {
        echo "✓ PASSED\n";
        $passed++;
    } else {
        echo "✗ FAILED\n";
        echo "  Expected: $expected\n";
        echo "  Got: $output\n";
        $failed++;
    }
}

echo "\n=== Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n🎉 All tests passed!\n";
} else {
    echo "\n⚠️ Some tests failed.\n";
}
