<?php

/**
 * PHP to PHC Compiler
 * Compiles a subset of PHP to PHC assembly
 */

class PHPCompiler {
    private $assembly = [];
    private $variables = [];
    private $varCounter = 100;  // Start user variables at address 100
    private $labelCounter = 0;
    private $functions = [];
    
    // Memory layout:
    // 0-19: Superglobals ($_GET, $_POST, $_SERVER, etc.)
    // 20-99: Built-in functions/constants
    // 100+: User variables
    private $superglobals = [
        '$_GET' => 0,
        '$_POST' => 1,
        '$_SERVER' => 2,
        '$_REQUEST' => 3,
        '$_COOKIE' => 4,
        '$_SESSION' => 5,
        '$_FILES' => 6,
        '$_ENV' => 7,
        '$GLOBALS' => 8,
        '$argc' => 9,
        '$argv' => 10,
        '$_HEADER' => 11,
        '$php_errormsg' => 12,
        '$http_response_header' => 13,
    ];
    
    // Built-in function mappings (SYSCALL IDs)
    private $builtinFunctions = [
        'isset' => 0,
        'empty' => 1,
        'strlen' => 2,
        'trim' => 3,
        'ltrim' => 4,
        'rtrim' => 5,
        'count' => 6,
        'is_array' => 7,
        'is_string' => 8,
        'is_numeric' => 9,
        'strpos' => 10,
        'substr' => 11,
        'str_replace' => 12,
        'strtolower' => 13,
        'strtoupper' => 14,
        'explode' => 15,
        'implode' => 16,
        'print_r' => 17,
        'var_dump' => 18,
        'abs' => 19,
        'round' => 20,
        'floor' => 21,
        'ceil' => 22,
        'intval' => 23,
        'floatval' => 24,
        'min' => 25,
        'max' => 26,
        'in_array' => 27,
        'array_keys' => 28,
        'array_values' => 29,
        'array_push' => 30,
        'array_pop' => 31,
        'array_shift' => 32,
        'array_unshift' => 33,
        'array_merge' => 34,
        'array_slice' => 35,
        'array_search' => 36,
        'strrev' => 37,
        'str_repeat' => 38,
        'ucfirst' => 39,
        'lcfirst' => 40,
        'ucwords' => 41,
        'is_int' => 42,
        'is_float' => 43,
        'is_bool' => 44,
        'is_null' => 45,
        'array_key_exists' => 46,
        'json_encode' => 47,
        'json_decode' => 48,
        'md5' => 49,
        'sha1' => 50,
        'base64_encode' => 51,
        'base64_decode' => 52,
        'time' => 53,
        'date' => 54,
        'strtotime' => 55,
        'preg_match' => 56,
        'preg_replace' => 57,
    ];
    
    // User-defined functions: name => ['params' => [...], 'label' => 'label_name']
    private $userFunctions = [];

    public function compile($phpCode) {
        // Remove <?php tags
        $phpCode = preg_replace('/<\?php|\?>/', '', $phpCode);
        
        // First pass: collect function definitions
        $this->collectFunctionDefinitions($phpCode);
        
        // If there are functions, emit a jump to skip them
        $mainLabel = null;
        if (!empty($this->userFunctions)) {
            $mainLabel = $this->getLabel('main');
            $this->emit('JMP', $mainLabel);
            
            // Compile all function definitions (we'll parse them manually)
            $this->compileAllFunctions($phpCode);
            
            // Emit main label
            $this->assembly[] = $mainLabel . ':';
        }
        
        // Compile main code (without function definitions)
        $this->compileMainCode($phpCode);
        
        // Add HALT at the end
        $this->emit('HALT');
        
        return implode("\n", $this->assembly);
    }
    
    private function compileAllFunctions($code) {
        // Find and compile all function definitions
        $tokens = token_get_all("<?php " . $code);
        
        $i = 0;
        while ($i < count($tokens)) {
            if (is_array($tokens[$i]) && $tokens[$i][0] == T_FUNCTION) {
                $i = $this->compileFunction($tokens, $i);
            } else {
                $i++;
            }
        }
    }
    
    private function compileMainCode($code) {
        // Remove function definitions from code before parsing
        $code = preg_replace('/function\s+\w+\s*\([^)]*\)\s*\{(?:[^{}]|(?:\{[^}]*\}))*\}/s', '', $code);
        $this->parseStatements($code);
    }    
    private function collectFunctionDefinitions($code) {
        // Find all function definitions
        if (preg_match_all('/function\s+(\w+)\s*\(\s*(.*?)\s*\)\s*\{/s', $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $idx => $nameMatch) {
                $funcName = $nameMatch[0];
                $params = preg_split('/\s*,\s*/', trim($matches[2][$idx][0]));
                $params = array_filter(array_map('trim', $params));
                
                // Create function entry point label
                $label = $this->getLabel('func_' . $funcName);
                $this->userFunctions[$funcName] = [
                    'params' => $params,
                    'label' => $label,
                ];
            }
        }
    }

    private function emit($instruction, $operand = null) {
        if ($operand !== null) {
            $this->assembly[] = "$instruction $operand";
        } else {
            $this->assembly[] = $instruction;
        }
    }

    private function getVarAddress($varName) {
        // Check if it's a superglobal
        if (isset($this->superglobals[$varName])) {
            return $this->superglobals[$varName];
        }
        
        // User variable
        if (!isset($this->variables[$varName])) {
            $this->variables[$varName] = $this->varCounter++;
        }
        return $this->variables[$varName];
    }

    private function getLabel($prefix = 'L') {
        return $prefix . ($this->labelCounter++);
    }

    private function parseStatements($code) {
        // Split by semicolons but be careful with strings and control structures
        $tokens = token_get_all("<?php " . $code);
        
        $i = 0;
        while ($i < count($tokens)) {
            $token = $tokens[$i];
            
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_ECHO:
                        $i = $this->compileEcho($tokens, $i);
                        break;
                    
                    case T_VARIABLE:
                        $i = $this->compileAssignment($tokens, $i);
                        break;
                    
                    case T_IF:
                        $i = $this->compileIf($tokens, $i);
                        break;
                    
                    case T_WHILE:
                        $i = $this->compileWhile($tokens, $i);
                        break;
                    
                    case T_FOR:
                        $i = $this->compileFor($tokens, $i);
                        break;
                    
                    case T_FUNCTION:
                        $i = $this->compileFunction($tokens, $i);
                        break;
                    
                    case T_RETURN:
                        $i = $this->compileReturn($tokens, $i);
                        break;
                    
                    case T_STRING:
                        // Could be a function call like greet();
                        // Look ahead to see if it's followed by (
                        if ($i + 1 < count($tokens) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] == T_WHITESPACE) {
                            // Skip whitespace
                            $nextIdx = $i + 2;
                        } else {
                            $nextIdx = $i + 1;
                        }
                        
                        if ($nextIdx < count($tokens) && $tokens[$nextIdx] === '(') {
                            // This is a function call
                            $i = $this->compileFunctionCallStatement($tokens, $i);
                        }
                        break;
                }
            }
            
            $i++;
        }
    }

    private function compileFunctionCallStatement($tokens, $start) {
        // Function call as a statement: funcName(args);
        // Find the semicolon and compile as an expression
        $i = $start;
        while ($i < count($tokens) && $tokens[$i] !== ';') {
            $i++;
        }
        
        // Extract the function call tokens
        $funcCallTokens = array_slice($tokens, $start, $i - $start);
        $this->compileExpression($funcCallTokens);
        
        return $i; // Return past the function call (semicolon will be handled by main loop)
    }

    private function compileEcho($tokens, $start) {
        $i = $start + 1;
        $exprStart = $i;
        
        // Find the semicolon
        while ($i < count($tokens) && !($tokens[$i] === ';')) {
            $i++;
        }
        
        $expr = array_slice($tokens, $exprStart, $i - $exprStart);
        $this->compileExpression($expr);
        $this->emit('PRINT');
        
        return $i;
    }

    private function compileAssignment($tokens, $start) {
        $varName = $tokens[$start][1];
        $i = $start + 1;
        
        // Skip whitespace
        while ($i < count($tokens) && is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE) {
            $i++;
        }
        
        // Check for assignment operator
        if ($tokens[$i] === '=') {
            $i++; // Skip '='
            
            // Skip whitespace
            while ($i < count($tokens) && is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE) {
                $i++;
            }
            
            // Check if it's an array literal
            if ($tokens[$i] === '[') {
                $this->compileArrayAssignment($tokens, $start, $varName);
                // Find semicolon
                while ($i < count($tokens) && !($tokens[$i] === ';')) {
                    $i++;
                }
                return $i;
            }
            
            // Find semicolon
            $exprStart = $i;
            while ($i < count($tokens) && !($tokens[$i] === ';')) {
                $i++;
            }
            
            $expr = array_slice($tokens, $exprStart, $i - $exprStart);
            $this->compileExpression($expr);
            
            $addr = $this->getVarAddress($varName);
            $this->emit('STORE', $addr);
        }
        
        return $i;
    }

    private function compileArrayAssignment($tokens, $start, $varName) {
        // Find the opening [
        $i = $start + 1;
        while ($i < count($tokens) && $tokens[$i] !== '[') {
            $i++;
        }
        $i++; // Skip '['
        
        // Find the closing ]
        $depth = 1;
        $arrayStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '[') $depth++;
            if ($tokens[$i] === ']') $depth--;
            $i++;
        }
        
        $arrayContent = array_slice($tokens, $arrayStart, $i - $arrayStart - 1);
        
        // Get the variable address
        $addr = $this->getVarAddress($varName);
        
        // First, we need to initialize the array (allocate an array ID)
        // This will be done in the VM via a special instruction or convention
        // For now, we'll just start populating the array at address $addr
        
        // Parse array elements: key => value pairs
        // Need to split by commas at the top level only (not inside nested arrays)
        $elemStart = 0;
        $bracketDepth = 0;
        for ($idx = 0; $idx <= count($arrayContent); $idx++) {
            // Track bracket depth
            if ($idx < count($arrayContent)) {
                if ($arrayContent[$idx] === '[' || (is_array($arrayContent[$idx]) && strpos((string)($arrayContent[$idx][1] ?? ''), '[') !== false)) {
                    $bracketDepth++;
                } elseif ($arrayContent[$idx] === ']' || (is_array($arrayContent[$idx]) && strpos((string)($arrayContent[$idx][1] ?? ''), ']') !== false)) {
                    $bracketDepth--;
                }
            }
            
            if ($idx == count($arrayContent) || ($arrayContent[$idx] === ',' && $bracketDepth == 0)) {
                if ($idx > $elemStart) {  // Only process if there's content
                    $element = array_slice($arrayContent, $elemStart, $idx - $elemStart);
                    if (!empty($element)) {
                        $this->compileArrayElement($element, $addr);
                    }
                }
                $elemStart = $idx + 1;
            }
        }
    }

    private function compileArrayElement($element, $arrayAddr) {
        // Filter whitespace
        $element = array_filter($element, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $element = array_values($element);
        
        if (empty($element)) return;
        
        // Find the => operator
        $arrowPos = -1;
        for ($i = 0; $i < count($element); $i++) {
            if (is_array($element[$i]) && $element[$i][0] == T_DOUBLE_ARROW) {
                $arrowPos = $i;
                break;
            }
        }
        
        if ($arrowPos !== -1) {
            // key => value format
            $keyPart = array_slice($element, 0, $arrowPos);
            $valuePart = array_slice($element, $arrowPos + 1);
            
            // Emit array address
            $this->emit('PUSH', $arrayAddr);
            // Emit key
            $this->compileExpression($keyPart);
            // Emit value - check if it's a nested array
            if ($this->isNestedArray($valuePart)) {
                $this->compileNestedArrayExpression($valuePart);
            } else {
                $this->compileExpression($valuePart);
            }
        } else {
            // Just a value (indexed array)
            $this->emit('PUSH', $arrayAddr);
            if ($this->isNestedArray($element)) {
                $this->compileNestedArrayExpression($element);
            } else {
                $this->compileExpression($element);
            }
        }
        
        $this->emit('ASET');
    }

    private function isNestedArray($tokens) {
        // Filter whitespace
        $filtered = array_filter($tokens, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $filtered = array_values($filtered);
        
        return count($filtered) > 0 && $filtered[0] === '[';
    }

    private function compileNestedArrayExpression($tokens) {
        // Find the opening and closing brackets
        $filtered = array_filter($tokens, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $filtered = array_values($filtered);
        
        if (empty($filtered) || $filtered[0] !== '[') {
            $this->compileExpression($tokens);
            return;
        }
        
        // Find matching ]
        $depth = 0;
        $endIdx = 0;
        for ($i = 0; $i < count($filtered); $i++) {
            if ($filtered[$i] === '[') $depth++;
            if ($filtered[$i] === ']') $depth--;
            if ($depth == 0) {
                $endIdx = $i;
                break;
            }
        }
        
        // Extract content between [ ]
        $arrayContent = array_slice($filtered, 1, $endIdx - 1);
        
        // Emit NEWARR to create the array - this pushes the array ID to the stack
        $this->emit('NEWARR');
        
        // Store the array ID for use in all elements
        $this->emit('STORE', 998);
        
        // Parse and emit array elements
        $elemStart = 0;
        for ($idx = 0; $idx <= count($arrayContent); $idx++) {
            if ($idx == count($arrayContent) || $arrayContent[$idx] === ',') {
                if ($idx > $elemStart) {
                    $element = array_slice($arrayContent, $elemStart, $idx - $elemStart);
                    if (!empty($element)) {
                        $this->compileNestedArrayElement($element);
                    }
                }
                $elemStart = $idx + 1;
            }
        }
        
        // After all elements, push the array ID back to the stack for use as a value
        $this->emit('LOAD', 998);
    }

    private function compileNestedArrayElement($element) {
        // Filter whitespace
        $element = array_filter($element, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $element = array_values($element);
        
        if (empty($element)) return;
        
        // Find the => operator
        $arrowPos = -1;
        for ($i = 0; $i < count($element); $i++) {
            if (is_array($element[$i]) && $element[$i][0] == T_DOUBLE_ARROW) {
                $arrowPos = $i;
                break;
            }
        }
        
        if ($arrowPos !== -1) {
            // key => value format
            $keyPart = array_slice($element, 0, $arrowPos);
            $valuePart = array_slice($element, $arrowPos + 1);
            
            // Array ID is stored at address 998
            // We need stack: [arrayID, key, value] for ASET
            
            // Load array ID
            $this->emit('LOAD', 998);
            // Compile key
            $this->compileExpression($keyPart);
            // Compile value
            $this->compileExpression($valuePart);
            // Now stack is [arrayID, key, value] - perfect for ASET
            $this->emit('ASET');
        } else {
            // Just a value - compile as regular expression
            // Load array ID and the value, then ASET
            $this->emit('LOAD', 998);
            $this->compileExpression($element);
            $this->emit('ASET');
        }
    }

    private function compileNestedArray($arrayContent, $arrayAddr) {
        // Parse array elements: key => value pairs
        $elemStart = 0;
        for ($idx = 0; $idx <= count($arrayContent); $idx++) {
            if ($idx == count($arrayContent) || $arrayContent[$idx] === ',') {
                if ($idx > $elemStart) {  // Only process if there's content
                    $element = array_slice($arrayContent, $elemStart, $idx - $elemStart);
                    if (!empty($element)) {
                        $this->compileArrayElement($element, $arrayAddr);
                    }
                }
                $elemStart = $idx + 1;
            }
        }
    }

    private function compileIf($tokens, $start) {
        $i = $start + 1;
        
        // Skip to opening parenthesis
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        // Find matching closing parenthesis
        $depth = 1;
        $condStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            $i++;
        }
        
        $condition = array_slice($tokens, $condStart, $i - $condStart - 1);
        
        // Compile condition
        $this->compileExpression($condition);
        
        $elseLabel = $this->getLabel('else');
        $endLabel = $this->getLabel('endif');
        
        $this->emit('JZ', $elseLabel);
        
        // Find the block
        while ($i < count($tokens) && $tokens[$i] !== '{') {
            $i++;
        }
        $i++; // Skip '{'
        
        $blockStart = $i;
        $depth = 1;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '{') $depth++;
            if ($tokens[$i] === '}') $depth--;
            $i++;
        }
        
        $block = array_slice($tokens, $blockStart, $i - $blockStart - 1);
        $this->parseStatements($this->tokensToCode($block));
        
        $this->emit('JMP', $endLabel);
        $this->emit($elseLabel . ':');
        
        // Check for elseif or else
        $j = $i;
        while ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] == T_WHITESPACE) {
            $j++;
        }
        
        if ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] == T_ELSEIF) {
            // Compile elseif as another if statement
            $i = $this->compileIf($tokens, $j);
        } elseif ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] == T_ELSE) {
            // Compile else block
            $i = $j + 1;
            
            // Find the block
            while ($i < count($tokens) && $tokens[$i] !== '{') {
                $i++;
            }
            $i++; // Skip '{'
            
            $blockStart = $i;
            $depth = 1;
            while ($i < count($tokens) && $depth > 0) {
                if ($tokens[$i] === '{') $depth++;
                if ($tokens[$i] === '}') $depth--;
                $i++;
            }
            
            $block = array_slice($tokens, $blockStart, $i - $blockStart - 1);
            $this->parseStatements($this->tokensToCode($block));
        }
        
        $this->emit($endLabel . ':');
        
        return $i;
    }

    private function compileWhile($tokens, $start) {
        $loopLabel = $this->getLabel('loop');
        $endLabel = $this->getLabel('endloop');
        
        $this->emit($loopLabel . ':');
        
        $i = $start + 1;
        
        // Skip to opening parenthesis
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        // Find matching closing parenthesis
        $depth = 1;
        $condStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            $i++;
        }
        
        $condition = array_slice($tokens, $condStart, $i - $condStart - 1);
        
        // Compile condition
        $this->compileExpression($condition);
        $this->emit('JZ', $endLabel);
        
        // Find the block
        while ($i < count($tokens) && $tokens[$i] !== '{') {
            $i++;
        }
        $i++; // Skip '{'
        
        $blockStart = $i;
        $depth = 1;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '{') $depth++;
            if ($tokens[$i] === '}') $depth--;
            $i++;
        }
        
        $block = array_slice($tokens, $blockStart, $i - $blockStart - 1);
        $this->parseStatements($this->tokensToCode($block));
        
        $this->emit('JMP', $loopLabel);
        $this->emit($endLabel . ':');
        
        return $i;
    }

    private function compileFor($tokens, $start) {
        // for ($i = 0; $i < 10; $i++)
        $i = $start + 1;
        
        // Skip to opening parenthesis
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        // Parse initialization
        $initStart = $i;
        while ($i < count($tokens) && $tokens[$i] !== ';') {
            $i++;
        }
        $init = array_slice($tokens, $initStart, $i - $initStart);
        $this->parseStatements($this->tokensToCode($init));
        $i++; // Skip ';'
        
        $loopLabel = $this->getLabel('forloop');
        $endLabel = $this->getLabel('endfor');
        
        $this->emit($loopLabel . ':');
        
        // Parse condition
        $condStart = $i;
        while ($i < count($tokens) && $tokens[$i] !== ';') {
            $i++;
        }
        $condition = array_slice($tokens, $condStart, $i - $condStart);
        $this->compileExpression($condition);
        $this->emit('JZ', $endLabel);
        $i++; // Skip ';'
        
        // Save increment for later
        $incStart = $i;
        while ($i < count($tokens) && $tokens[$i] !== ')') {
            $i++;
        }
        $increment = array_slice($tokens, $incStart, $i - $incStart);
        $i++; // Skip ')'
        
        // Find the block
        while ($i < count($tokens) && $tokens[$i] !== '{') {
            $i++;
        }
        $i++; // Skip '{'
        
        $blockStart = $i;
        $depth = 1;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '{') $depth++;
            if ($tokens[$i] === '}') $depth--;
            $i++;
        }
        
        $block = array_slice($tokens, $blockStart, $i - $blockStart - 1);
        $this->parseStatements($this->tokensToCode($block));
        
        // Add increment
        $this->parseStatements($this->tokensToCode($increment));
        
        $this->emit('JMP', $loopLabel);
        $this->emit($endLabel . ':');
        
        return $i;
    }

    private function compileFunction($tokens, $start) {
        // We need to:
        // 1. Extract function name and parameters
        // 2. Emit the function label
        // 3. Store parameters in memory from stack
        // 4. Compile the function body
        // 5. Emit RET
        // 6. Return position after closing brace
        
        $i = $start + 1;
        
        // Skip whitespace
        while ($i < count($tokens) && is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE) {
            $i++;
        }
        
        // Get function name
        $funcName = $tokens[$i][1];
        $i++;
        
        // Skip to opening (
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        // Skip to closing )
        $depth = 1;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            $i++;
        }
        
        // Skip to opening {
        while ($i < count($tokens) && $tokens[$i] !== '{') {
            $i++;
        }
        $i++; // Skip '{'
        
        // Find matching closing }
        $depth = 1;
        $bodyStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '{') $depth++;
            if ($tokens[$i] === '}') $depth--;
            $i++;
        }
        
        // Emit function label
        $funcLabel = $this->userFunctions[$funcName]['label'];
        $this->assembly[] = $funcLabel . ':';
        
        // Get function parameters
        $params = $this->userFunctions[$funcName]['params'];
        
        // Store parameters from stack into memory
        // Parameters are pushed in order, so we need to store them in reverse order
        foreach (array_reverse($params) as $param) {
            // Remove $ from parameter name if present
            $paramName = str_replace('$', '', $param);
            $addr = $this->getVarAddress('$' . $paramName);
            $this->emit('STORE', $addr);
        }
        
        // Compile function body
        $body = array_slice($tokens, $bodyStart, $i - $bodyStart - 1);
        $this->parseStatements($this->tokensToCode($body));
        
        // Check if the last instruction is RET, if not add implicit return 0
        $lastAssembly = end($this->assembly);
        if (strpos($lastAssembly, 'RET') === false) {
            $this->emit('PUSH', '0');
            $this->emit('RET');
        }
        
        return $i;
    }
    
    private function compileReturn($tokens, $start) {
        // return expr;
        // Find the semicolon
        $i = $start + 1;
        $depth = 0;
        $exprStart = $i;
        
        while ($i < count($tokens) && ($tokens[$i] !== ';' || $depth > 0)) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            if ($tokens[$i] === '[') $depth++;
            if ($tokens[$i] === ']') $depth--;
            if ($tokens[$i] === '{') $depth++;
            if ($tokens[$i] === '}') $depth--;
            $i++;
        }
        
        // Compile the expression
        $expr = array_slice($tokens, $exprStart, $i - $exprStart);
        $this->compileExpression($expr);
        
        // Emit RET - the return value is on the stack
        $this->emit('RET');
        
        return $i + 1; // Skip past semicolon
    }
    
    private function tokensToCode($tokens) {
        $code = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }
        return $code;
    }

    private function compileExpression($tokens) {
        // Filter out whitespace
        $tokens = array_filter($tokens, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $tokens = array_values($tokens);
        
        if (empty($tokens)) return;
        
        // Check for isset() function (special case - not a regular expression)
        if (count($tokens) > 0 && is_array($tokens[0]) && $tokens[0][0] == T_ISSET) {
            $this->compileIsset($tokens);
            return;
        }
        
        // For all other cases, use the recursive descent parser
        // This handles: function calls, arithmetic, comparisons, etc.
        $this->parseComparison($tokens, 0, count($tokens));
    }

    private function compileBuiltinFunction($funcName, $tokens) {
        // Parse function arguments
        // tokens[0] = function name, tokens[1] = '(', tokens[2..n-1] = arguments, tokens[n] = ')'
        $i = 2;  // Skip function name and '('
        $depth = 1;
        $args = [];
        $currentArg = [];
        
        while ($i < count($tokens) && $depth > 0) {
            $token = $tokens[$i];
            
            if ($token === '(') {
                $depth++;
                $currentArg[] = $token;
            } elseif ($token === ')') {
                $depth--;
                if ($depth > 0) {
                    $currentArg[] = $token;
                }
            } elseif ($token === ',' && $depth == 1) {
                // End of argument
                if (!empty($currentArg)) {
                    $args[] = $currentArg;
                    $currentArg = [];
                }
            } else {
                $currentArg[] = $token;
            }
            $i++;
        }
        
        // Don't forget the last argument
        if (!empty($currentArg)) {
            $args[] = $currentArg;
        }
        
        // Compile arguments to stack
        foreach ($args as $arg) {
            $this->compileExpression($arg);
        }
        
        // Emit SYSCALL
        $syscallId = $this->builtinFunctions[$funcName];
        $this->emit('SYSCALL', $syscallId . ' ' . count($args));
    }

    private function compileUserFunction($funcName, $tokens) {
        // Parse function arguments
        $i = 2;  // Skip function name and '('
        $depth = 1;
        $args = [];
        $currentArg = [];
        
        while ($i < count($tokens) && $depth > 0) {
            $token = $tokens[$i];
            
            if ($token === '(') {
                $depth++;
                $currentArg[] = $token;
            } elseif ($token === ')') {
                $depth--;
                if ($depth > 0) {
                    $currentArg[] = $token;
                }
            } elseif ($token === ',' && $depth == 1) {
                // End of argument
                if (!empty($currentArg)) {
                    $args[] = $currentArg;
                    $currentArg = [];
                }
            } else {
                $currentArg[] = $token;
            }
            $i++;
        }
        
        // Don't forget the last argument
        if (!empty($currentArg)) {
            $args[] = $currentArg;
        }
        
        // Compile arguments to stack
        foreach ($args as $arg) {
            $this->compileExpression($arg);
        }
        
        // Emit CALL with function label
        $funcLabel = $this->userFunctions[$funcName]['label'];
        $this->emit('CALL', $funcLabel);
    }

    private function compileIsset($tokens) {
        // isset($var) or isset($arr[key])
        // Find the opening (
        $i = 1;
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        // Find the closing )
        $depth = 1;
        $argStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            $i++;
        }
        
        $arg = array_slice($tokens, $argStart, $i - $argStart - 1);
        
        // Compile the argument as an expression to check if it exists
        $this->compileExpression($arg);
        
        // isset returns 1 if the value is not 0/empty, 0 otherwise
        // We'll use a simple heuristic: if the stack has a value, it's set
        // This is a simplification - proper isset would check existence, not truthiness
        // But for now, a non-zero/non-empty value = set
    }

    private function parseComparison($tokens, $start, $end) {
        $this->parseAddSub($tokens, $start, $end);
        
        // Look for comparison operators
        for ($i = $start; $i < $end; $i++) {
            if (is_array($tokens[$i])) {
                switch ($tokens[$i][0]) {
                    case T_IS_SMALLER_OR_EQUAL:
                        $this->parseAddSub($tokens, $i + 1, $end);
                        $this->emit('LTE');
                        return;
                    case T_IS_GREATER_OR_EQUAL:
                        $this->parseAddSub($tokens, $i + 1, $end);
                        $this->emit('GTE');
                        return;
                    case T_IS_EQUAL:
                        $this->parseAddSub($tokens, $i + 1, $end);
                        $this->emit('EQ');
                        return;
                    case T_IS_NOT_EQUAL:
                        $this->parseAddSub($tokens, $i + 1, $end);
                        $this->emit('NEQ');
                        return;
                }
            } elseif ($tokens[$i] === '<') {
                $this->parseAddSub($tokens, $i + 1, $end);
                $this->emit('LT');
                return;
            } elseif ($tokens[$i] === '>') {
                $this->parseAddSub($tokens, $i + 1, $end);
                $this->emit('GT');
                return;
            }
        }
    }

    private function parseAddSub($tokens, $start, $end) {
        $this->parseConcat($tokens, $start, $end);
        
        // Look for + or -
        for ($i = $start; $i < $end; $i++) {
            if ($tokens[$i] === '+') {
                $this->parseConcat($tokens, $i + 1, $end);
                $this->emit('ADD');
                return;
            } elseif ($tokens[$i] === '-') {
                $this->parseConcat($tokens, $i + 1, $end);
                $this->emit('SUB');
                return;
            }
        }
    }

    private function parseConcat($tokens, $start, $end) {
        $this->parseMulDiv($tokens, $start, $end);
        
        // Look for . (string concatenation)
        for ($i = $start; $i < $end; $i++) {
            if ($tokens[$i] === '.') {
                $this->parseMulDiv($tokens, $i + 1, $end);
                $this->emit('CONCAT');
                return;
            }
        }
    }

    private function parseMulDiv($tokens, $start, $end) {
        $this->parsePrimary($tokens, $start, $end);
        
        // Look for * or /
        for ($i = $start; $i < $end; $i++) {
            if ($tokens[$i] === '*') {
                $this->parsePrimary($tokens, $i + 1, $end);
                $this->emit('MUL');
                return;
            } elseif ($tokens[$i] === '/') {
                $this->parsePrimary($tokens, $i + 1, $end);
                $this->emit('DIV');
                return;
            } elseif ($tokens[$i] === '%') {
                $this->parsePrimary($tokens, $i + 1, $end);
                $this->emit('MOD');
                return;
            }
        }
    }

    private function parsePrimary($tokens, $start, $end) {
        if ($start >= $end) return;
        
        $token = $tokens[$start];
        $nextIndex = $start + 1;
        
        if (is_array($token)) {
            switch ($token[0]) {
                case T_STRING:
                    // Could be a function call
                    if ($nextIndex < $end && $tokens[$nextIndex] === '(') {
                        $funcName = $token[1];
                        
                        // Find the matching closing parenthesis
                        $depth = 1;
                        $i = $nextIndex + 1;
                        while ($i < $end && $depth > 0) {
                            if ($tokens[$i] === '(') $depth++;
                            if ($tokens[$i] === ')') $depth--;
                            $i++;
                        }
                        
                        if (isset($this->builtinFunctions[$funcName])) {
                            // Extract function call tokens
                            $funcTokens = array_slice($tokens, $start, $i - $start);
                            $this->compileBuiltinFunction($funcName, $funcTokens);
                        } elseif (isset($this->userFunctions[$funcName])) {
                            // Extract function call tokens
                            $funcTokens = array_slice($tokens, $start, $i - $start);
                            $this->compileUserFunction($funcName, $funcTokens);
                        }
                    }
                    break;
                
                case T_LNUMBER:
                    $this->emit('PUSH', $token[1]);
                    break;
                
                case T_VARIABLE:
                    $varName = $token[1];
                    $addr = $this->getVarAddress($varName);
                    $this->emit('LOAD', $addr);
                    
                    // Check for array access $var[key] or $var[key1][key2]...
                    while ($nextIndex < $end && $tokens[$nextIndex] === '[') {
                        $i = $nextIndex + 1;
                        $depth = 1;
                        $keyStart = $i;
                        while ($i < $end && $depth > 0) {
                            if ($tokens[$i] === '[') $depth++;
                            if ($tokens[$i] === ']') $depth--;
                            $i++;
                        }
                        $key = array_slice($tokens, $keyStart, $i - $keyStart - 1);
                        $this->compileExpression($key);
                        $this->emit('AGET');
                        $nextIndex = $i;  // Move past this array access
                    }
                    
                    // Check for ++ or --
                    if (isset($tokens[$nextIndex])) {
                        if (is_array($tokens[$nextIndex]) && $tokens[$nextIndex][0] == T_INC) {
                            $this->emit('PUSH', 1);
                            $this->emit('ADD');
                            $this->emit('STORE', $addr);
                        } elseif (is_array($tokens[$nextIndex]) && $tokens[$nextIndex][0] == T_DEC) {
                            $this->emit('PUSH', 1);
                            $this->emit('SUB');
                            $this->emit('STORE', $addr);
                        }
                    }
                    break;
                
                case T_CONSTANT_ENCAPSED_STRING:
                    // String literal - push as constant
                    $str = trim($token[1], '"\'');
                    $this->emit('PUSHC', '"' . $str . '"');
                    break;
            }
        } elseif ($token === '(') {
            // Find matching closing parenthesis
            $depth = 1;
            $i = $start + 1;
            while ($i < $end && $depth > 0) {
                if ($tokens[$i] === '(') $depth++;
                if ($tokens[$i] === ')') $depth--;
                $i++;
            }
            $this->compileExpression(array_slice($tokens, $start + 1, $i - $start - 2));
        }
    }
}

// CLI Interface
if (php_sapi_name() === 'cli') {
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
