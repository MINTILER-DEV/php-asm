<?php

/**
 * PHP-ASM Test Suite
 * Compares PHP native output vs PHC compiled output
 */

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  PHP-ASM Modular Compiler Test Suite                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$php = 'php';
$phc = __DIR__ . '\\..\\src\\phc.php';

// Auto-discover all test files
$testFiles = glob(__DIR__ . '/test_*.php');

$passed = 0;
$failed = 0;
$errors = [];

foreach ($testFiles as $testFile) {
    $testName = basename($testFile);
    echo "Testing: " . str_pad($testName, 40) . " ";
    
    // Run with native PHP
    $phpOutput = shell_exec("$php \"$testFile\" 2>&1");
    $phpOutput = trim($phpOutput);
    
    // Run with PHC compiler
    $phcOutput = shell_exec("$php \"$phc\" exec \"$testFile\" 2>&1");
    $phcOutput = trim($phcOutput);
    
    // Clean up outputs (remove progress bars, ANSI codes, etc.)
    $phpOutput = cleanOutput($phpOutput);
    $phcOutput = cleanOutput($phcOutput);
    
    /*
    echo "\nPHP Output:\n";
    echo "┌──────────────────────────────────────────────────────────┐\n";
    echo formatOutput($phpOutput);
    echo "└──────────────────────────────────────────────────────────┘\n\n";
    echo "PHC Output:\n";
    echo "┌──────────────────────────────────────────────────────────┐\n";
    echo formatOutput($phcOutput);
    echo "└──────────────────────────────────────────────────────────┘\n\n";
    */

    // Compare outputs
    if ($phpOutput === $phcOutput) {
        echo "✓ PASS\n";
        $passed++;
    } else {
        echo "✗ FAIL\n";
        $failed++;
        $errors[] = [
            'test' => $testName,
            'php' => $phpOutput,
            'phc' => $phcOutput
        ];
    }
}

// Print results
echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  Test Results                                              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Passed: " . str_pad($passed, 3) . " tests\n";
echo "Failed: " . str_pad($failed, 3) . " tests\n";
echo "Total:  " . str_pad($passed + $failed, 3) . " tests\n";

if ($failed > 0) {
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Failed Tests Details                                      ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    foreach ($errors as $error) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Test: {$error['test']}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        echo "PHP Output:\n";
        echo "┌──────────────────────────────────────────────────────────┐\n";
        echo formatOutput($error['php']);
        echo "└──────────────────────────────────────────────────────────┘\n\n";
        
        echo "PHC Output:\n";
        echo "┌──────────────────────────────────────────────────────────┐\n";
        echo formatOutput($error['phc']);
        echo "└──────────────────────────────────────────────────────────┘\n\n";
        
        // Show diff if outputs are different
        if ($error['php'] !== $error['phc']) {
            echo "Diff:\n";
            echo "┌──────────────────────────────────────────────────────────┐\n";
            showDiff($error['php'], $error['phc']);
            echo "└──────────────────────────────────────────────────────────┘\n\n";
        }
    }
}

echo "\n";
if ($failed === 0) {
    echo "🎉 All tests passed! The compiler output matches PHP perfectly.\n";
} else {
    echo "⚠️  Some tests failed. Review the differences above.\n";
}

echo "\n";
exit($failed > 0 ? 1 : 0);

// Helper Functions

function cleanOutput($output) {
    // Remove CLIXML wrapper
    $output = preg_replace('/#< CLIXML.*$/s', '', $output);
    
    // Remove ANSI color codes
    $output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
    
    // Remove Windows progress bars
    $output = preg_replace('/<Objs.*?<\/Objs>/s', '', $output);
    
    // Remove empty lines
    $lines = explode("\n", $output);
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });
    $output = implode("\n", $lines);
    
    return trim($output);
}

function formatOutput($output) {
    if (empty($output)) {
        return "│ (empty output)\n";
    }
    
    $lines = explode("\n", $output);
    $formatted = '';
    foreach ($lines as $line) {
        $formatted .= '│ ' . $line . "\n";
    }
    return $formatted;
}

function showDiff($expected, $actual) {
    $expectedLines = explode("\n", $expected);
    $actualLines = explode("\n", $actual);
    
    $maxLines = max(count($expectedLines), count($actualLines));
    
    for ($i = 0; $i < $maxLines; $i++) {
        $expLine = $expectedLines[$i] ?? '';
        $actLine = $actualLines[$i] ?? '';
        
        if ($expLine === $actLine) {
            echo "│   " . $expLine . "\n";
        } else {
            if ($expLine !== '') {
                echo "│ - " . $expLine . " (PHP)\n";
            }
            if ($actLine !== '') {
                echo "│ + " . $actLine . " (PHC)\n";
            }
        }
    }
}
