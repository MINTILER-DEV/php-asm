<?php

/**
 * Bytecode Loader
 * Loads .phc bytecode files and extracts constants
 */

class BytecodeLoader {
    private $bytecode = [];
    private $constants = [];

    public function load($file) {
        if (!file_exists($file)) {
            throw new Exception("Bytecode file not found: $file");
        }

        $data = file_get_contents($file);
        $bytes = array_values(unpack('C*', $data));
        
        $pos = 0;
        
        // Read constant count
        $constantCount = $bytes[$pos++];
        
        // Read constants
        for ($i = 0; $i < $constantCount; $i++) {
            $len = $bytes[$pos] | ($bytes[$pos + 1] << 8);
            $pos += 2;
            $str = '';
            for ($j = 0; $j < $len; $j++) {
                $str .= chr($bytes[$pos++]);
            }
            $this->constants[] = $str;
        }
        
        // Rest is bytecode
        $this->bytecode = array_slice($bytes, $pos);
        
        return [
            'bytecode' => $this->bytecode,
            'constants' => $this->constants,
        ];
    }

    public function getBytecode() {
        return $this->bytecode;
    }

    public function getConstants() {
        return $this->constants;
    }
}
