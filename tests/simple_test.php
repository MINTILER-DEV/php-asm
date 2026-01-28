<?php
require_once __DIR__ . '/../src/PHPCompiler.php';

$code = file_get_contents('test_fib_simple.php');
$compiler = new PHPCompiler();
echo "=== COMPILING ===\n";
$assembly = $compiler->compile($code);
echo "\n=== ASSEMBLY ===\n";
echo $assembly . "\n";
