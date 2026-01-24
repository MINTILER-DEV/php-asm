<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/PHPCompiler.php';

try {
    $compiler = new PHPCompiler();
    $code = file_get_contents('test_fib_simple.php');
    echo "Compiling...\n";
    $assembly = $compiler->compile($code);
    echo "Success!\n";
    echo $assembly;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
