<?php

require_once __DIR__ . '/../Opcodes.php';
require_once __DIR__ . '/../BuiltinFunctions.php';

/**
 * Expression Parser
 * Handles recursive descent parsing of expressions with proper precedence
 */

class ExpressionParser {
    private $emitter;
    private $variableResolver;
    private $userFunctions;

    public function __construct($emitter, $variableResolver, $userFunctions) {
        $this->emitter = $emitter;
        $this->variableResolver = $variableResolver;
        $this->userFunctions = $userFunctions;
    }

    public function parse($tokens) {
        // Filter out whitespace
        $tokens = array_filter($tokens, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $tokens = array_values($tokens);
        
        if (empty($tokens)) return;
        
        // Check for isset() function (special case)
        if (count($tokens) > 0 && is_array($tokens[0]) && $tokens[0][0] == T_ISSET) {
            $this->parseIsset($tokens);
            return;
        }
        
        // Use recursive descent parser for all other expressions
        $this->parseComparison($tokens, 0, count($tokens));
    }

    private function parseIsset($tokens) {
        // isset($var) or isset($arr[key])
        $i = 1;
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        $depth = 1;
        $argStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            $i++;
        }
        
        $arg = array_slice($tokens, $argStart, $i - $argStart - 1);
        $this->parse($arg);
    }

    private function parseComparison($tokens, $start, $end) {
        $this->parseAddSub($tokens, $start, $end);
        
        // Look for comparison operators (scan from left to right)
        for ($i = $start; $i < $end; $i++) {
            $op = null;
            if (is_array($tokens[$i])) {
                switch ($tokens[$i][0]) {
                    case T_IS_SMALLER_OR_EQUAL: $op = 'LTE'; break;
                    case T_IS_GREATER_OR_EQUAL: $op = 'GTE'; break;
                    case T_IS_EQUAL: $op = 'EQ'; break;
                    case T_IS_NOT_EQUAL: $op = 'NEQ'; break;
                }
            } elseif ($tokens[$i] === '<') {
                $op = 'LT';
            } elseif ($tokens[$i] === '>') {
                $op = 'GT';
            }
            
            if ($op !== null) {
                $this->parseAddSub($tokens, $i + 1, $end);
                $this->emitter->emit($op);
                return;
            }
        }
    }

    private function parseAddSub($tokens, $start, $end) {
        $this->parseConcat($tokens, $start, $end);
        
        // Scan for + or - from LEFT to RIGHT
        for ($i = $start; $i < $end; $i++) {
            // Skip tokens inside function calls
            if ($this->isInsideParens($tokens, $start, $i, $end)) {
                continue;
            }
            
            if ($tokens[$i] === '+') {
                $this->parseConcat($tokens, $i + 1, $end);
                $this->emitter->emit('ADD');
                return;
            } elseif ($tokens[$i] === '-' && !$this->isUnaryMinus($tokens, $i)) {
                $this->parseConcat($tokens, $i + 1, $end);
                $this->emitter->emit('SUB');
                return;
            }
        }
    }

    private function parseConcat($tokens, $start, $end) {
        $this->parseMulDiv($tokens, $start, $end);
        
        for ($i = $start; $i < $end; $i++) {
            if ($this->isInsideParens($tokens, $start, $i, $end)) {
                continue;
            }
            
            if ($tokens[$i] === '.') {
                $this->parseMulDiv($tokens, $i + 1, $end);
                $this->emitter->emit('CONCAT');
                return;
            }
        }
    }

    private function parseMulDiv($tokens, $start, $end) {
        $this->parsePrimary($tokens, $start, $end);
        
        for ($i = $start; $i < $end; $i++) {
            if ($this->isInsideParens($tokens, $start, $i, $end)) {
                continue;
            }
            
            if ($tokens[$i] === '*') {
                $this->parsePrimary($tokens, $i + 1, $end);
                $this->emitter->emit('MUL');
                return;
            } elseif ($tokens[$i] === '/') {
                $this->parsePrimary($tokens, $i + 1, $end);
                $this->emitter->emit('DIV');
                return;
            } elseif ($tokens[$i] === '%') {
                $this->parsePrimary($tokens, $i + 1, $end);
                $this->emitter->emit('MOD');
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
                    // Function call
                    if ($nextIndex < $end && $tokens[$nextIndex] === '(') {
                        $funcName = $token[1];
                        
                        // Find matching closing parenthesis
                        $depth = 1;
                        $i = $nextIndex + 1;
                        while ($i < $end && $depth > 0) {
                            if ($tokens[$i] === '(') $depth++;
                            if ($tokens[$i] === ')') $depth--;
                            $i++;
                        }
                        
                        $funcTokens = array_slice($tokens, $start, $i - $start);
                        $this->parseFunctionCall($funcName, $funcTokens);
                    }
                    break;
                
                case T_LNUMBER:
                case T_DNUMBER:
                    $this->emitter->emit('PUSH', $token[1]);
                    break;
                
                case T_VARIABLE:
                    $this->parseVariable($tokens, $start, $end);
                    break;
                
                case T_CONSTANT_ENCAPSED_STRING:
                    $str = trim($token[1], '"\'');
                    $this->emitter->emit('PUSHC', '"' . $str . '"');
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
            $this->parse(array_slice($tokens, $start + 1, $i - $start - 2));
        }
    }

    private function parseVariable($tokens, $start, $end) {
        $varName = $tokens[$start][1];
        $addr = $this->variableResolver->getAddress($varName);
        $this->emitter->emit('LOAD', $addr);
        
        $nextIndex = $start + 1;
        
        // Handle array access
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
            $this->parse($key);
            $this->emitter->emit('AGET');
            $nextIndex = $i;
        }
        
        // Handle ++ or --
        if ($nextIndex < $end && is_array($tokens[$nextIndex])) {
            if ($tokens[$nextIndex][0] == T_INC) {
                $this->emitter->emit('PUSH', 1);
                $this->emitter->emit('ADD');
                $this->emitter->emit('STORE', $addr);
            } elseif ($tokens[$nextIndex][0] == T_DEC) {
                $this->emitter->emit('PUSH', 1);
                $this->emitter->emit('SUB');
                $this->emitter->emit('STORE', $addr);
            }
        }
    }

    private function parseFunctionCall($funcName, $tokens) {
        // Parse arguments
        $args = $this->extractFunctionArgs($tokens);
        
        // Compile each argument
        foreach ($args as $arg) {
            $this->parse($arg);
        }
        
        // Emit the call
        if (BuiltinFunctions::exists($funcName)) {
            $syscallId = BuiltinFunctions::getId($funcName);
            $this->emitter->emit('SYSCALL', $syscallId . ' ' . count($args));
        } elseif (isset($this->userFunctions[$funcName])) {
            $funcLabel = $this->userFunctions[$funcName]['label'];
            $this->emitter->emit('CALL', $funcLabel);
        }
    }

    private function extractFunctionArgs($tokens) {
        // tokens[0] = function name, tokens[1] = '('
        $i = 2;
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
                if (!empty($currentArg)) {
                    $args[] = $currentArg;
                    $currentArg = [];
                }
            } else {
                $currentArg[] = $token;
            }
            $i++;
        }
        
        if (!empty($currentArg)) {
            $args[] = $currentArg;
        }
        
        return $args;
    }

    private function isInsideParens($tokens, $start, $pos, $end) {
        $depth = 0;
        for ($i = $start; $i < $pos && $i < $end; $i++) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
        }
        return $depth > 0;
    }

    private function isUnaryMinus($tokens, $pos) {
        if ($pos == 0) return true;
        $prev = $tokens[$pos - 1];
        return $prev === '(' || $prev === ',' || 
               (is_array($prev) && in_array($prev[0], [T_DOUBLE_ARROW]));
    }
}
