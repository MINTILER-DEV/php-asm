<?php

require_once __DIR__ . '/../Opcodes.php';

/**
 * PHC Assembler
 * Converts .phas (assembly) to .phc (bytecode)
 */

class PHCAssembler {
    private $opcodes;
    private $constants = [];
    private $labels = [];

    public function __construct() {
        $this->opcodes = Opcodes::getOpcodeMap();
    }

    public function assemble($sourceFile, $outputFile) {
        if (!file_exists($sourceFile)) {
            throw new Exception("Source file not found: $sourceFile");
        }

        $source = file_get_contents($sourceFile);
        $lines = explode("\n", $source);
        
        // First pass: collect labels and constants
        $address = 0;
        foreach ($lines as $line) {
            $line = $this->stripComments($line);
            if (empty($line)) continue;

            if ($this->isLabel($line)) {
                $labelName = substr(trim($line), 0, -1);
                $this->labels[$labelName] = $address;
                continue;
            }

            if ($this->isConstantPush($line)) {
                $this->extractConstant($line);
            }

            $address += $this->getInstructionSize($line);
        }

        // Second pass: generate bytecode
        $bytecode = $this->generateBytecode($lines);
        
        // Write to file
        $finalBytecode = $this->packBytecode($bytecode);
        file_put_contents($outputFile, $finalBytecode);
        
        echo "Assembled successfully: $outputFile (" . strlen($finalBytecode) . " bytes)\n";
        return true;
    }

    private function stripComments($line) {
        $line = trim($line);
        if (strpos($line, ';') !== false) {
            $line = trim(substr($line, 0, strpos($line, ';')));
        }
        return $line;
    }

    private function isLabel($line) {
        return preg_match('/^\w+:$/', $line);
    }

    private function isConstantPush($line) {
        return preg_match('/^PUSHC\s+"([^"]*)"/', $line);
    }

    private function extractConstant($line) {
        if (preg_match('/^PUSHC\s+"([^"]*)"/', $line, $matches)) {
            $str = $matches[1];
            if (!in_array($str, $this->constants)) {
                $this->constants[] = $str;
            }
        }
    }

    private function getInstructionSize($line) {
        $parts = preg_split('/\s+/', $line, 2);
        $instruction = strtoupper($parts[0]);
        
        if (!isset($this->opcodes[$instruction])) {
            return 0;
        }

        $size = 1; // Opcode
        
        if (isset($parts[1])) {
            $operand = trim($parts[1]);
            
            if ($instruction === 'SYSCALL') {
                $size += 2; // ID + arg count
            } elseif ($instruction === 'GLOAD' || $instruction === 'GSTORE') {
                $size += 1 + strlen($operand); // length + name
            } else {
                $size += 1; // Single operand
            }
        }
        
        return $size;
    }

    private function generateBytecode($lines) {
        $bytecode = [];
        
        foreach ($lines as $lineNum => $line) {
            $line = $this->stripComments($line);
            if (empty($line) || $this->isLabel($line)) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            $instruction = strtoupper($parts[0]);

            if (!isset($this->opcodes[$instruction])) {
                throw new Exception("Unknown instruction '$instruction' on line " . ($lineNum + 1));
            }

            $bytecode[] = $this->opcodes[$instruction];

            if (isset($parts[1])) {
                $this->encodeOperand($instruction, trim($parts[1]), $bytecode, $lineNum);
            }
        }
        
        return $bytecode;
    }

    private function encodeOperand($instruction, $operand, &$bytecode, $lineNum) {
        if ($instruction === 'PUSHC' && preg_match('/^"([^"]*)"/', $operand, $matches)) {
            $str = $matches[1];
            $constIdx = array_search($str, $this->constants);
            $bytecode[] = $constIdx;
            
        } elseif ($instruction === 'GLOAD' || $instruction === 'GSTORE') {
            if (preg_match('/^\$[\w_]+/', $operand)) {
                $bytecode[] = strlen($operand);
                for ($j = 0; $j < strlen($operand); $j++) {
                    $bytecode[] = ord($operand[$j]);
                }
            } else {
                throw new Exception("Invalid operand '$operand' for $instruction on line " . ($lineNum + 1));
            }
            
        } elseif ($instruction === 'SYSCALL') {
            $syscallParts = preg_split('/\s+/', $operand);
            $syscallId = (int)$syscallParts[0];
            $argCount = isset($syscallParts[1]) ? (int)$syscallParts[1] : 0;
            $bytecode[] = $syscallId;
            $bytecode[] = $argCount;
            
        } else {
            // Check if operand is a label
            if (isset($this->labels[$operand])) {
                $operand = $this->labels[$operand];
            }

            if (is_numeric($operand)) {
                $bytecode[] = (int)$operand;
            } else {
                throw new Exception("Invalid operand '$operand' on line " . ($lineNum + 1));
            }
        }
    }

    private function packBytecode($bytecode) {
        $finalBytecode = [];
        
        // Write constant count
        $finalBytecode[] = count($this->constants);
        
        // Write each constant with length prefix
        foreach ($this->constants as $constant) {
            $len = strlen($constant);
            $finalBytecode[] = $len & 0xFF;
            $finalBytecode[] = ($len >> 8) & 0xFF;
            for ($j = 0; $j < $len; $j++) {
                $finalBytecode[] = ord($constant[$j]);
            }
        }
        
        // Write bytecode
        foreach ($bytecode as $byte) {
            $finalBytecode[] = $byte;
        }
        
        return pack('C*', ...$finalBytecode);
    }
}
