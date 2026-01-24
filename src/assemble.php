<?php

/**
 * PHC Assembler - Command-line interface
 */

require_once __DIR__ . '/Assembler/PHCAssembler.php';

function assembleFile($input, $output) {
    $assembler = new PHCAssembler();
    $assembler->assemble($input, $output);
    echo "✓ Assembled to bytecode: $output\n";
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    if ($argc < 2) {
        echo "PHC Assembler\n";
        echo "Usage: php assemble.php <input.phas> [output.phc]\n";
        exit(1);
    }

    $input = $argv[1];
    $output = $argv[2] ?? str_replace('.phas', '.phc', $input);

    if (!file_exists($input)) {
        echo "Error: Input file not found: $input\n";
        exit(1);
    }

    try {
        assembleFile($input, $output);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
