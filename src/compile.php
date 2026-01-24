<?php

/**
 * PHP to PHC Compiler - New Modular Version
 * Command-line interface for compilation
 */

require_once __DIR__ . '/PHPCompiler.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

if ($argc < 2) {
    echo "PHP to PHC Compiler (Modular Version)\n";
    echo "Usage: php compile.php <input.php> [output.phas]\n";
    echo "\n";
    echo "Options:\n";
    echo "  --assemble    Also assemble to bytecode (.phc)\n";
    exit(1);
}

$input = $argv[1];
$output = $argv[2] ?? str_replace('.php', '.phas', $input);
$assemble = in_array('--assemble', $argv);

if (!file_exists($input)) {
    echo "Error: Input file not found: $input\n";
    exit(1);
}

try {
    $compiler = new PHPCompiler();
    $phpCode = file_get_contents($input);
    $assembly = $compiler->compile($phpCode);
    
    file_put_contents($output, $assembly);
    echo "✓ Compiled to assembly: $output\n";
    
    if ($assemble) {
        $phcFile = str_replace('.phas', '.phc', $output);
        require_once __DIR__ . '/assemble.php';
        assembleFile($output, $phcFile);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
