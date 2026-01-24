<?php

/**
 * Array Compiler
 * Handles compilation of array literals and operations
 */

class ArrayCompiler {
    private $emitter;
    private $expressionParser;
    private $variableResolver;

    public function __construct($emitter, $expressionParser, $variableResolver) {
        $this->emitter = $emitter;
        $this->expressionParser = $expressionParser;
        $this->variableResolver = $variableResolver;
    }

    public function compileArrayAssignment($tokens, $start, $varName) {
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
        $addr = $this->variableResolver->getAddress($varName);
        
        // Parse array elements
        $this->parseArrayElements($arrayContent, $addr);
    }

    private function parseArrayElements($arrayContent, $arrayAddr) {
        $elemStart = 0;
        $bracketDepth = 0;
        
        for ($idx = 0; $idx <= count($arrayContent); $idx++) {
            if ($idx < count($arrayContent)) {
                if ($arrayContent[$idx] === '[') {
                    $bracketDepth++;
                } elseif ($arrayContent[$idx] === ']') {
                    $bracketDepth--;
                }
            }
            
            if ($idx == count($arrayContent) || ($arrayContent[$idx] === ',' && $bracketDepth == 0)) {
                if ($idx > $elemStart) {
                    $element = array_slice($arrayContent, $elemStart, $idx - $elemStart);
                    if (!empty($element)) {
                        $this->compileArrayElement($element, $arrayAddr);
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
        $arrowPos = $this->findArrowOperator($element);
        
        if ($arrowPos !== -1) {
            // key => value format
            $keyPart = array_slice($element, 0, $arrowPos);
            $valuePart = array_slice($element, $arrowPos + 1);
            
            $this->emitter->emit('PUSH', $arrayAddr);
            $this->expressionParser->parse($keyPart);
            
            if ($this->isNestedArray($valuePart)) {
                $this->compileNestedArrayExpression($valuePart);
            } else {
                $this->expressionParser->parse($valuePart);
            }
        } else {
            // Just a value
            $this->emitter->emit('PUSH', $arrayAddr);
            
            if ($this->isNestedArray($element)) {
                $this->compileNestedArrayExpression($element);
            } else {
                $this->expressionParser->parse($element);
            }
        }
        
        $this->emitter->emit('ASET');
    }

    private function isNestedArray($tokens) {
        $filtered = array_filter($tokens, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $filtered = array_values($filtered);
        
        return count($filtered) > 0 && $filtered[0] === '[';
    }

    private function compileNestedArrayExpression($tokens) {
        $filtered = array_filter($tokens, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $filtered = array_values($filtered);
        
        if (empty($filtered) || $filtered[0] !== '[') {
            $this->expressionParser->parse($tokens);
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
        
        $arrayContent = array_slice($filtered, 1, $endIdx - 1);
        
        // Create the array
        $this->emitter->emit('NEWARR');
        $this->emitter->emit('STORE', 998);
        
        // Parse elements
        $this->parseNestedArrayElements($arrayContent);
        
        // Push array ID back to stack
        $this->emitter->emit('LOAD', 998);
    }

    private function parseNestedArrayElements($arrayContent) {
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
    }

    private function compileNestedArrayElement($element) {
        $element = array_filter($element, function($t) {
            return !is_array($t) || $t[0] != T_WHITESPACE;
        });
        $element = array_values($element);
        
        if (empty($element)) return;
        
        $arrowPos = $this->findArrowOperator($element);
        
        if ($arrowPos !== -1) {
            $keyPart = array_slice($element, 0, $arrowPos);
            $valuePart = array_slice($element, $arrowPos + 1);
            
            $this->emitter->emit('LOAD', 998);
            $this->expressionParser->parse($keyPart);
            $this->expressionParser->parse($valuePart);
            $this->emitter->emit('ASET');
        } else {
            $this->emitter->emit('LOAD', 998);
            $this->expressionParser->parse($element);
            $this->emitter->emit('ASET');
        }
    }

    private function findArrowOperator($tokens) {
        for ($i = 0; $i < count($tokens); $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] == T_DOUBLE_ARROW) {
                return $i;
            }
        }
        return -1;
    }
}
