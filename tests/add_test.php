<?php

/**
 * Add New Test Helper
 * Creates a new test file with proper structure
 */

if ($argc < 2) {
    echo "Add New Test Helper\n";
    echo "Usage: php add_test.php <test_name> [description]\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php add_test.php string_functions \"Test string manipulation\"\n";
    echo "  php add_test.php array_operations\n";
    echo "\n";
    exit(1);
}

$testName = $argv[1];
$description = $argv[2] ?? '';

// Sanitize test name
$testName = preg_replace('/[^a-z0-9_]/', '_', strtolower($testName));
$testName = preg_replace('/_+/', '_', $testName);
$testName = trim($testName, '_');

// Create filename
$filename = __DIR__ . "/test_{$testName}.php";

if (file_exists($filename)) {
    echo "Error: Test file already exists: $filename\n";
    exit(1);
}

// Generate test template
$template = <<<'PHP'
<?php

/**
 * Test: {TEST_TITLE}
 * {DESCRIPTION}
 */

// Your test code here
// The output should be identical to native PHP execution

echo "Test {TEST_NAME}\n";

// Example test cases:
// - Test basic functionality
// - Test edge cases
// - Test error handling

// Add your assertions here
$result = 1 + 1;
echo "Result: $result\n";

PHP;

// Replace placeholders
$testTitle = ucwords(str_replace('_', ' ', $testName));
$template = str_replace('{TEST_TITLE}', $testTitle, $template);
$template = str_replace('{TEST_NAME}', $testName, $template);
$template = str_replace('{DESCRIPTION}', $description ?: 'Add description here', $template);

// Write file
file_put_contents($filename, $template);

echo "✓ Created test file: $filename\n";
echo "\n";
echo "Next steps:\n";
echo "1. Edit the test file with your test code\n";
echo "2. Run: php $filename (to test with native PHP)\n";
echo "3. Run: php ../src/phc-new.php exec $filename (to test with PHC)\n";
echo "4. Run: php run_all_tests.php (to verify it passes)\n";
echo "\n";
echo "The test passes when PHP output === PHC output\n";
