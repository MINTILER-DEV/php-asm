<?php

/**
 * PHC Unified Command-Line Tool
 * Combines compilation, assembly, and execution
 */

require_once __DIR__ . '/PHPCompiler.php';
require_once __DIR__ . '/Assembler/PHCAssembler.php';
require_once __DIR__ . '/VM/PHCVM.php';
require_once __DIR__ . '/MemoryLayout.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

function showHelp() {
    echo "PHC - PHP Bytecode Compiler & Virtual Machine\n";
    echo "\n";
    echo "Usage:\n";
    echo "  php phc.php compile <input.php> [output.phas]    Compile PHP to assembly\n";
    echo "  php phc.php assemble <input.phas> [output.phc]   Assemble to bytecode\n";
    echo "  php phc.php run <file.phc>                       Run bytecode\n";
    echo "  php phc.php build <input.php>                    Compile & assemble\n";
    echo "  php phc.php exec <input.php>                     Compile, assemble & run\n";
    echo "\n";
    echo "Options:\n";
    echo "  --verbose, -v    Show verbose output\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php phc.php compile test.php test.phas\n";
    echo "  php phc.php assemble test.phas test.phc\n";
    echo "  php phc.php run test.phc\n";
    echo "  php phc.php exec test.php    # Compile, assemble, and run in one step\n";
    exit(0);
}

if ($argc < 2) {
    showHelp();
}

$command = $argv[1];
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

try {
    switch ($command) {
        case 'compile':
            if ($argc < 3) {
                echo "Error: Input file required\n";
                exit(1);
            }
            $input = $argv[2];
            $output = $argv[3] ?? str_replace('.php', '.phas', $input);
            
            if (!file_exists($input)) {
                echo "Error: Input file not found: $input\n";
                exit(1);
            }
            
            $compiler = new PHPCompiler();
            $phpCode = file_get_contents($input);
            $assembly = $compiler->compile($phpCode);
            file_put_contents($output, $assembly);
            echo "✓ Compiled: $output\n";
            break;

        case 'assemble':
            if ($argc < 3) {
                echo "Error: Input file required\n";
                exit(1);
            }
            $input = $argv[2];
            $output = $argv[3] ?? str_replace('.phas', '.phc', $input);
            
            if (!file_exists($input)) {
                echo "Error: Input file not found: $input\n";
                exit(1);
            }
            
            $assembler = new PHCAssembler();
            $assembler->assemble($input, $output);
            break;

        case 'run':
            if ($argc < 3) {
                echo "Error: Bytecode file required\n";
                exit(1);
            }
            $file = $argv[2];
            
            if (!file_exists($file)) {
                echo "Error: Bytecode file not found: $file\n";
                exit(1);
            }
            
            $vm = new PHCVM();
            $vm->setVerbose($verbose);
            $vm->load($file);
            
            if ($verbose) echo "Running $file...\n";
            $vm->run();
            if ($verbose) echo "\nExecution complete.\n";
            break;

        case 'build':
            if ($argc < 3) {
                echo "Error: Input file required\n";
                exit(1);
            }
            $input = $argv[2];
            
            if (!file_exists($input)) {
                echo "Error: Input file not found: $input\n";
                exit(1);
            }
            
            $phasFile = str_replace('.php', '.phas', $input);
            $phcFile = str_replace('.php', '.phc', $input);
            
            // Compile
            $compiler = new PHPCompiler();
            $phpCode = file_get_contents($input);
            $assembly = $compiler->compile($phpCode);
            file_put_contents($phasFile, $assembly);
            echo "✓ Compiled: $phasFile\n";
            
            // Assemble
            $assembler = new PHCAssembler();
            $assembler->assemble($phasFile, $phcFile);
            echo "✓ Built: $phcFile\n";
            break;

        case 'exec':
            if ($argc < 3) {
                echo "Error: Input file required\n";
                exit(1);
            }
            $input = $argv[2];
            
            if (!file_exists($input)) {
                echo "Error: Input file not found: $input\n";
                exit(1);
            }
            
            $phasFile = str_replace('.php', '.phas', $input);
            $phcFile = str_replace('.php', '.phc', $input);
            
            // Compile
            if ($verbose) echo "Compiling...\n";
            $compiler = new PHPCompiler();
            $phpCode = file_get_contents($input);
            $assembly = $compiler->compile($phpCode);
            file_put_contents($phasFile, $assembly);
            if ($verbose) echo "✓ Compiled: $phasFile\n";
            
            // Assemble
            if ($verbose) echo "Assembling...\n";
            $assembler = new PHCAssembler();
            $assembler->assemble($phasFile, $phcFile);
            if ($verbose) echo "✓ Assembled: $phcFile\n";
            
            // Run
            if ($verbose) echo "Running...\n\n";
            $vm = new PHCVM();
            $vm->setVerbose(false);
            $vm->load($phcFile);
            $vm->run();
            if ($verbose) echo "\n✓ Execution complete\n";
            break;

        case 'help':
        case '--help':
        case '-h':
            showHelp();
            break;

        default:
            echo "Unknown command: $command\n";
            echo "Use 'php phc.php help' for usage information\n";
            exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($verbose) {
        echo "\nStack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}
