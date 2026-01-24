<?php

/**
 * Code Emitter
 * Handles assembly code generation
 */

class CodeEmitter {
    private $assembly = [];
    private $labelCounter = 0;

    public function emit($instruction, $operand = null) {
        if ($operand !== null) {
            $this->assembly[] = "$instruction $operand";
        } else {
            $this->assembly[] = $instruction;
        }
    }

    public function emitLabel($label) {
        $this->assembly[] = $label . ':';
    }

    public function getLabel($prefix = 'L') {
        return $prefix . ($this->labelCounter++);
    }

    public function getAssembly() {
        return implode("\n", $this->assembly);
    }

    public function getAssemblyLines() {
        return $this->assembly;
    }
}
