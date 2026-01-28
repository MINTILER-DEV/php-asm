<?php

/**
 * Test Validator
 * Validates a single test file by comparing PHP vs PHC output
 */

if ($argc < 2) {
    echo "Test Validator\n";
    echo "Usage: php validate_test.php <test_file.php>\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php validate_test.php test_fib_simple.php\n";
    echo "  php validate_test.php test_my_feature.php\n";
    echo "\n";
    exit(1);
}

$testFile = $argv[1];

if (!file_exists($testFile)) {
    echo "Error: Test file not found: $testFile\n";
    exit(1);
}

$php = 'php';
$phc = __DIR__ . '\\..\\src\\phc.php';

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  Test Validator                                            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Test file: " . basename($testFile) . "\n\n";

echo "Running with native PHP...\n";
$startTime = microtime(true);
$phpOutput = shell_exec("$php \"$testFile\" 2>&1");
$phpTime = microtime(true) - $startTime;
$phpOutput = cleanOutput($phpOutput);

echo "Running with PHC compiler...\n";
$startTime = microtime(true);
$phcOutput = shell_exec("$php \"$phc\" exec \"$testFile\" 2>&1");
$phcTime = microtime(true) - $startTime;
$phcOutput = cleanOutput($phcOutput);

echo "\n";
echo "┌────────────────────────────────────────────────────────────┐\n";
echo "│ PHP Output (" . number_format($phpTime * 1000, 2) . " ms)\n";
echo "├────────────────────────────────────────────────────────────┤\n";
echo formatOutput($phpOutput);
echo "└────────────────────────────────────────────────────────────┘\n\n";

echo "┌────────────────────────────────────────────────────────────┐\n";
echo "│ PHC Output (" . number_format($phcTime * 1000, 2) . " ms)\n";
echo "├────────────────────────────────────────────────────────────┤\n";
echo formatOutput($phcOutput);
echo "└────────────────────────────────────────────────────────────┘\n\n";

// Compare
if ($phpOutput === $phcOutput) {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✓ TEST PASSED                                             ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\nThe outputs match perfectly! 🎉\n";
    exit(0);
} else {
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  ✗ TEST FAILED                                             ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    echo "\nThe outputs differ:\n\n";
    
    echo "┌────────────────────────────────────────────────────────────┐\n";
    echo "│ Diff (- PHP, + PHC)\n";
    echo "├────────────────────────────────────────────────────────────┤\n";
    showDiff($phpOutput, $phcOutput);
    echo "└────────────────────────────────────────────────────────────┘\n";
    
    // Analyze the differences
    echo "\nAnalysis:\n";
    $phpLength = strlen($phpOutput);
    $phcLength = strlen($phcOutput);
    echo "- PHP output length: $phpLength bytes\n";
    echo "- PHC output length: $phcLength bytes\n";
    echo "- Difference: " . abs($phpLength - $phcLength) . " bytes\n";
    
    if (empty($phpOutput)) {
        echo "\n⚠️  PHP produced no output. Check if the test file is valid.\n";
    }
    if (empty($phcOutput)) {
        echo "\n⚠️  PHC produced no output. There may be a compilation error.\n";
    }
    
    exit(1);
}

// Helper Functions

function cleanOutput($output) {
    // Remove CLIXML wrapper
    $output = preg_replace('/#< CLIXML.*$/s', '', $output);
    
    // Remove ANSI color codes
    $output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
    
    // Remove Windows progress bars
    $output = preg_replace('/<Objs.*?<\/Objs>/s', '', $output);
    
    // Remove status messages from PHC
    $lines = explode("\n", $output);
    $lines = array_filter($lines, function($line) {
        $line = trim($line);
        // Filter out compilation status messages
        if (strpos($line, '✓ Compiled:') === 0) return false;
        if (strpos($line, '✓ Assembled:') === 0) return false;
        if (strpos($line, 'Assembled successfully:') === 0) return false;
        if (strpos($line, 'Running') === 0 && strpos($line, '...') !== false) return false;
        if (strpos($line, 'Execution complete') === 0) return false;
        if (empty($line)) return false;
        return true;
    });
    $output = implode("\n", $lines);
    
    return trim($output);
}

function formatOutput($output) {
    if (empty($output)) {
        return "│ (no output)\n";
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
                echo "│ - " . $expLine . "\n";
            }
            if ($actLine !== '') {
                echo "│ + " . $actLine . "\n";
            }
        }
    }
}
