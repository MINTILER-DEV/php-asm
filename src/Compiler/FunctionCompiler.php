<?php

/**
 * Function Compiler
 * Handles user-defined function compilation
 */

class FunctionCompiler {
    private $emitter;
    private $statementCompiler;
    private $variableResolver;
    private $userFunctions = [];

    public function __construct($emitter, $statementCompiler, $variableResolver) {
        $this->emitter = $emitter;
        $this->statementCompiler = $statementCompiler;
        $this->variableResolver = $variableResolver;
    }

    public function getUserFunctions() {
        return $this->userFunctions;
    }

    public function setUserFunctions($functions) {
        $this->userFunctions = $functions;
    }

    public function setStatementCompiler($statementCompiler) {
        $this->statementCompiler = $statementCompiler;
    }

    public function collectFunctionDefinitions($code) {
        if (preg_match_all('/function\s+(\w+)\s*\(\s*(.*?)\s*\)\s*\{/s', $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $idx => $nameMatch) {
                $funcName = $nameMatch[0];
                $params = preg_split('/\s*,\s*/', trim($matches[2][$idx][0]));
                $params = array_filter(array_map('trim', $params));
                
                $label = $this->emitter->getLabel('func_' . $funcName);
                $this->userFunctions[$funcName] = [
                    'params' => $params,
                    'label' => $label,
                ];
            }
        }
    }

    public function compileAllFunctions($code) {
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

    public function compileFunction($tokens, $start) {
        $i = $start + 1;
        
        // Skip whitespace
        while ($i < count($tokens) && is_array($tokens[$i]) && $tokens[$i][0] == T_WHITESPACE) {
            $i++;
        }
        
        // Get function name
        if (!is_array($tokens[$i]) || $tokens[$i][0] != T_STRING) {
            return $i;
        }
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
        if (!isset($this->userFunctions[$funcName])) {
            return $i;
        }
        
        $funcLabel = $this->userFunctions[$funcName]['label'];
        $this->emitter->emitLabel($funcLabel);
        
        // Store parameters from stack
        $params = $this->userFunctions[$funcName]['params'];
        foreach (array_reverse($params) as $param) {
            $paramName = str_replace('$', '', $param);
            $addr = $this->variableResolver->getAddress('$' . $paramName);
            $this->emitter->emit('STORE', $addr);
        }
        
        // Compile function body
        $body = array_slice($tokens, $bodyStart, $i - $bodyStart - 1);
        $this->statementCompiler->parseStatements($this->tokensToCode($body));
        
        // Add implicit return if needed
        $assemblyLines = $this->emitter->getAssemblyLines();
        $lastAssembly = !empty($assemblyLines) ? end($assemblyLines) : '';
        if (strpos($lastAssembly, 'RET') === false) {
            $this->emitter->emit('PUSH', '0');
            $this->emitter->emit('RET');
        }
        
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
}
