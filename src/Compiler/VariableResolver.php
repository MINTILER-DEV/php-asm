<?php

require_once __DIR__ . '/../MemoryLayout.php';

/**
 * Variable Resolver
 * Maps variable names to memory addresses
 */

class VariableResolver {
    private $variables = [];
    private $varCounter;

    public function __construct() {
        $this->varCounter = MemoryLayout::getUserVarStart();
    }

    public function getAddress($varName) {
        // Check if it's a superglobal
        $addr = MemoryLayout::getSuperglobalAddress($varName);
        if ($addr !== null) {
            return $addr;
        }
        
        // User variable
        if (!isset($this->variables[$varName])) {
            $this->variables[$varName] = $this->varCounter++;
        }
        return $this->variables[$varName];
    }

    public function getAllVariables() {
        return $this->variables;
    }
}
