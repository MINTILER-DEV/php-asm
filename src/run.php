<?php

/**
 * PHC Virtual Machine - Command-line interface
 */

require_once __DIR__ . '/VM/PHCVM.php';
require_once __DIR__ . '/MemoryLayout.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

if ($argc < 2) {
    echo "PHC Virtual Machine\n";
    echo "Usage: php run.php <file.phc>\n";
    echo "\n";
    echo "Options:\n";
    echo "  --verbose, -v    Show verbose output\n";
    exit(1);
}

$file = $argv[1];
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

if (!file_exists($file)) {
    echo "Error: Bytecode file not found: $file\n";
    exit(1);
}

try {
    $vm = new PHCVM();
    $vm->setVerbose($verbose);
    $vm->load($file);
    
    if ($verbose) {
        echo "Running...\n";
    }
    
    $vm->run();
    
    if ($verbose) {
        echo "\nExecution complete.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
