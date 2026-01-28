<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/PHPCompiler.php';

$testFile = 'test_fib_simple.php';
$code = file_get_contents($testFile);

echo "Compiling $testFile...\n";
echo "Code:\n";
echo $code . "\n";
echo "=====\n\n";

try {
    $compiler = new PHPCompiler();
    $assembly = $compiler->compile($code);
    
    echo "Assembly:\n";
    echo $assembly . "\n";
    
    file_put_contents('debug_output.phas', $assembly);
    echo "\nSaved to debug_output.phas\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
