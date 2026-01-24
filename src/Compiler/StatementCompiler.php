<?php

/**
 * Statement Compiler
 * Handles compilation of PHP statements (if, while, for, etc.)
 */

class StatementCompiler {
    private $emitter;
    private $expressionParser;
    private $variableResolver;
    private $arrayCompiler;
    private $functionCompiler;

    public function __construct($emitter, $expressionParser, $variableResolver, $arrayCompiler) {
        $this->emitter = $emitter;
        $this->expressionParser = $expressionParser;
        $this->variableResolver = $variableResolver;
        $this->arrayCompiler = $arrayCompiler;
    }

    public function setFunctionCompiler($functionCompiler) {
        $this->functionCompiler = $functionCompiler;
    }

    public function parseStatements($code) {
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
                        if ($this->functionCompiler) {
                            $i = $this->functionCompiler->compileFunction($tokens, $i);
                        }
                        break;
                    
                    case T_RETURN:
                        $i = $this->compileReturn($tokens, $i);
                        break;
                    
                    case T_STRING:
                        // Function call as statement
                        $nextIdx = $this->skipWhitespace($tokens, $i + 1);
                        if ($nextIdx < count($tokens) && $tokens[$nextIdx] === '(') {
                            $i = $this->compileFunctionCallStatement($tokens, $i);
                        }
                        break;
                }
            }
            
            $i++;
        }
    }

    private function compileEcho($tokens, $start) {
        $i = $start + 1;
        $exprStart = $i;
        
        while ($i < count($tokens) && !($tokens[$i] === ';')) {
            $i++;
        }
        
        $expr = array_slice($tokens, $exprStart, $i - $exprStart);
        $this->expressionParser->parse($expr);
        $this->emitter->emit('PRINT');
        
        return $i;
    }

    private function compileAssignment($tokens, $start) {
        $varName = $tokens[$start][1];
        $i = $this->skipWhitespace($tokens, $start + 1);
        
        if ($i < count($tokens) && $tokens[$i] === '=') {
            $i = $this->skipWhitespace($tokens, $i + 1);
            
            // Check for array literal
            if ($i < count($tokens) && $tokens[$i] === '[') {
                $this->arrayCompiler->compileArrayAssignment($tokens, $start, $varName);
                while ($i < count($tokens) && !($tokens[$i] === ';')) {
                    $i++;
                }
                return $i;
            }
            
            // Regular assignment
            $exprStart = $i;
            while ($i < count($tokens) && !($tokens[$i] === ';')) {
                $i++;
            }
            
            $expr = array_slice($tokens, $exprStart, $i - $exprStart);
            $this->expressionParser->parse($expr);
            
            $addr = $this->variableResolver->getAddress($varName);
            $this->emitter->emit('STORE', $addr);
        }
        
        return $i;
    }

    private function compileIf($tokens, $start) {
        $i = $start + 1;
        
        // Find condition
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        $depth = 1;
        $condStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            $i++;
        }
        
        $condition = array_slice($tokens, $condStart, $i - $condStart - 1);
        $this->expressionParser->parse($condition);
        
        $elseLabel = $this->emitter->getLabel('else');
        $endLabel = $this->emitter->getLabel('endif');
        
        $this->emitter->emit('JZ', $elseLabel);
        
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
        
        $this->emitter->emit('JMP', $endLabel);
        $this->emitter->emitLabel($elseLabel);
        
        // Check for elseif or else
        $j = $this->skipWhitespace($tokens, $i);
        
        if ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] == T_ELSEIF) {
            $i = $this->compileIf($tokens, $j);
        } elseif ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] == T_ELSE) {
            $i = $this->compileElse($tokens, $j);
        }
        
        $this->emitter->emitLabel($endLabel);
        
        return $i;
    }

    private function compileElse($tokens, $start) {
        $i = $start + 1;
        
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
        
        return $i;
    }

    private function compileWhile($tokens, $start) {
        $loopLabel = $this->emitter->getLabel('loop');
        $endLabel = $this->emitter->getLabel('endloop');
        
        $this->emitter->emitLabel($loopLabel);
        
        $i = $start + 1;
        
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        $i++; // Skip '('
        
        $depth = 1;
        $condStart = $i;
        while ($i < count($tokens) && $depth > 0) {
            if ($tokens[$i] === '(') $depth++;
            if ($tokens[$i] === ')') $depth--;
            $i++;
        }
        
        $condition = array_slice($tokens, $condStart, $i - $condStart - 1);
        $this->expressionParser->parse($condition);
        $this->emitter->emit('JZ', $endLabel);
        
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
        
        $this->emitter->emit('JMP', $loopLabel);
        $this->emitter->emitLabel($endLabel);
        
        return $i;
    }

    private function compileFor($tokens, $start) {
        $i = $start + 1;
        
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
        
        $loopLabel = $this->emitter->getLabel('forloop');
        $endLabel = $this->emitter->getLabel('endfor');
        
        $this->emitter->emitLabel($loopLabel);
        
        // Parse condition
        $condStart = $i;
        while ($i < count($tokens) && $tokens[$i] !== ';') {
            $i++;
        }
        $condition = array_slice($tokens, $condStart, $i - $condStart);
        $this->expressionParser->parse($condition);
        $this->emitter->emit('JZ', $endLabel);
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
        
        $this->emitter->emit('JMP', $loopLabel);
        $this->emitter->emitLabel($endLabel);
        
        return $i;
    }

    private function compileReturn($tokens, $start) {
        $i = $start + 1;
        $depth = 0;
        $exprStart = $i;
        
        while ($i < count($tokens) && ($tokens[$i] !== ';' || $depth > 0)) {
            if ($tokens[$i] === '(' || $tokens[$i] === '[' || $tokens[$i] === '{') $depth++;
            if ($tokens[$i] === ')' || $tokens[$i] === ']' || $tokens[$i] === '}') $depth--;
            $i++;
        }
        
        $expr = array_slice($tokens, $exprStart, $i - $exprStart);
        $this->expressionParser->parse($expr);
        $this->emitter->emit('RET');
        
        return $i + 1;
    }

    private function compileFunctionCallStatement($tokens, $start) {
        $i = $start;
        while ($i < count($tokens) && $tokens[$i] !== ';') {
            $i++;
        }
        
        $funcCallTokens = array_slice($tokens, $start, $i - $start);
        $this->expressionParser->parse($funcCallTokens);
        
        return $i;
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

    private function skipWhitespace($tokens, $index) {
        while ($index < count($tokens) && is_array($tokens[$index]) && $tokens[$index][0] == T_WHITESPACE) {
            $index++;
        }
        return $index;
    }
}
