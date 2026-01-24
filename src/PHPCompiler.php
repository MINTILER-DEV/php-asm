<?php

require_once __DIR__ . '/Compiler/CodeEmitter.php';
require_once __DIR__ . '/Compiler/VariableResolver.php';
require_once __DIR__ . '/Compiler/ExpressionParser.php';
require_once __DIR__ . '/Compiler/ArrayCompiler.php';
require_once __DIR__ . '/Compiler/StatementCompiler.php';
require_once __DIR__ . '/Compiler/FunctionCompiler.php';

/**
 * PHP to PHC Compiler (Main Entry Point)
 * Orchestrates the compilation process
 */

class PHPCompiler {
    private $emitter;
    private $variableResolver;
    private $expressionParser;
    private $arrayCompiler;
    private $statementCompiler;
    private $functionCompiler;

    public function __construct() {
        $this->emitter = new CodeEmitter();
        $this->variableResolver = new VariableResolver();
    }

    public function compile($phpCode) {
        // Remove <?php tags
        $phpCode = preg_replace('/<\?php|\?>/', '', $phpCode);
        
        // Initialize function compiler first
        $this->functionCompiler = new FunctionCompiler(
            $this->emitter,
            null,  // Will be set after statement compiler is created
            $this->variableResolver
        );
        
        // Collect function definitions
        $this->functionCompiler->collectFunctionDefinitions($phpCode);
        $userFunctions = $this->functionCompiler->getUserFunctions();
        
        // Initialize expression parser
        $this->expressionParser = new ExpressionParser(
            $this->emitter,
            $this->variableResolver,
            $userFunctions
        );
        
        // Initialize array compiler
        $this->arrayCompiler = new ArrayCompiler(
            $this->emitter,
            $this->expressionParser,
            $this->variableResolver
        );
        
        // Initialize statement compiler
        $this->statementCompiler = new StatementCompiler(
            $this->emitter,
            $this->expressionParser,
            $this->variableResolver,
            $this->arrayCompiler
        );
        
        // Set circular dependency
        $this->functionCompiler = new FunctionCompiler(
            $this->emitter,
            $this->statementCompiler,
            $this->variableResolver
        );
        $this->functionCompiler->collectFunctionDefinitions($phpCode);
        $this->statementCompiler->setFunctionCompiler($this->functionCompiler);
        
        // Update expression parser with correct user functions
        $userFunctions = $this->functionCompiler->getUserFunctions();
        $this->expressionParser = new ExpressionParser(
            $this->emitter,
            $this->variableResolver,
            $userFunctions
        );
        
        // Re-create array compiler with updated expression parser
        $this->arrayCompiler = new ArrayCompiler(
            $this->emitter,
            $this->expressionParser,
            $this->variableResolver
        );
        
        // Re-create statement compiler with updated components
        $this->statementCompiler = new StatementCompiler(
            $this->emitter,
            $this->expressionParser,
            $this->variableResolver,
            $this->arrayCompiler
        );
        $this->statementCompiler->setFunctionCompiler($this->functionCompiler);
        
        // If there are functions, emit jump to skip them
        if (!empty($userFunctions)) {
            $mainLabel = $this->emitter->getLabel('main');
            $this->emitter->emit('JMP', $mainLabel);
            
            // Compile all function definitions
            $this->functionCompiler->compileAllFunctions($phpCode);
            
            // Emit main label
            $this->emitter->emitLabel($mainLabel);
        }
        
        // Compile main code (without function definitions)
        $this->compileMainCode($phpCode);
        
        // Add HALT
        $this->emitter->emit('HALT');
        
        return $this->emitter->getAssembly();
    }

    private function compileMainCode($code) {
        // Remove function definitions
        $code = preg_replace('/function\s+\w+\s*\([^)]*\)\s*\{(?:[^{}]|(?:\{[^}]*\}))*\}/s', '', $code);
        $this->statementCompiler->parseStatements($code);
    }
}

// CLI Interface
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    if ($argc < 2) {
        echo "PHP to PHC Compiler\n";
        echo "Usage: php compiler.php <input.php> [output.phas]\n";
        exit(1);
    }

    $input = $argv[1];
    $output = $argv[2] ?? str_replace('.php', '.phas', $input);

    if (!file_exists($input)) {
        echo "Error: Input file not found: $input\n";
        exit(1);
    }

    try {
        $compiler = new PHPCompiler();
        $phpCode = file_get_contents($input);
        $assembly = $compiler->compile($phpCode);
        
        file_put_contents($output, $assembly);
        echo "Compiled to assembly: $output\n";
        
        // Optionally assemble to bytecode
        if (isset($argv[3]) && $argv[3] === '--assemble') {
            $phcFile = str_replace('.phas', '.phc', $output);
            define('PHC_LIBRARY', true);
            require_once 'phc.php';
            $assembler = new PHCAssembler();
            $assembler->assemble($output, $phcFile);
        }
        
    } catch (Exception $e) {
        echo "Compilation error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
