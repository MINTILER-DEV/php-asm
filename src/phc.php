<?php

/**
 * PHC Virtual Machine and Assembler
 * Compiles .phas (assembly) to .phc (bytecode)
 * Executes .phc bytecode files
 */

class PHCAssembler {
    private $opcodes = [
        'PUSH'    => 0x01,
        'POP'     => 0x02,
        'ADD'     => 0x03,
        'SUB'     => 0x04,
        'MUL'     => 0x05,
        'DIV'     => 0x06,
        'MOD'     => 0x07,
        'PRINT'   => 0x08,
        'JMP'     => 0x09,
        'JZ'      => 0x0A,  // Jump if zero
        'JNZ'     => 0x0B,  // Jump if not zero
        'CMP'     => 0x0C,  // Compare top two stack values
        'LOAD'    => 0x0D,  // Load from memory
        'STORE'   => 0x0E,  // Store to memory
        'CALL'    => 0x0F,  // Call function
        'RET'     => 0x10,  // Return from function
        'LT'      => 0x11,  // Less than
        'GT'      => 0x12,  // Greater than
        'LTE'     => 0x13,  // Less than or equal
        'GTE'     => 0x14,  // Greater than or equal
        'EQ'      => 0x15,  // Equal
        'NEQ'     => 0x16,  // Not equal
        'PUSHC'   => 0x17,  // Push constant (string/array)
        'CONCAT'  => 0x18,  // String concatenation
        'AGET'    => 0x19,  // Array get: pop key, pop array index, push value
        'ASET'    => 0x1A,  // Array set: pop value, pop key, pop array index, store
        'NEWARR'  => 0x1B,  // New array: create array, push array ID to stack
        'GLOAD'   => 0x1C,  // Load from global scope by name
        'GSTORE'  => 0x1D,  // Store to global scope by name
        'SYSCALL' => 0x1E,  // Call system function (built-in)
        'HALT'    => 0xFF,
    ];

    public function assemble($sourceFile, $outputFile) {
        if (!file_exists($sourceFile)) {
            throw new Exception("Source file not found: $sourceFile");
        }

        $source = file_get_contents($sourceFile);
        $lines = explode("\n", $source);
        $bytecode = [];
        $labels = [];
        $address = 0;
        $constants = [];

        // First pass: collect labels and string constants
        foreach ($lines as $line) {
            $line = trim($line);
            // Remove inline comments
            if (strpos($line, ';') !== false) {
                $line = trim(substr($line, 0, strpos($line, ';')));
            }
            if (empty($line)) continue;

            if (preg_match('/^(\w+):$/', $line, $matches)) {
                $labels[$matches[1]] = $address;
                continue;
            }

            // Handle string constants
            if (preg_match('/^PUSHC\s+"([^"]*)"/', $line, $matches)) {
                $str = $matches[1];
                if (!in_array($str, $constants)) {
                    $constants[] = $str;
                }
            }

            $parts = preg_split('/\s+/', $line, 2);
            $instruction = strtoupper($parts[0]);
            
            if (isset($this->opcodes[$instruction])) {
                $address++;
                if (isset($parts[1])) {
                    $address++; // Space for operand
                }
            }
        }

        // Second pass: generate bytecode
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            // Remove inline comments
            if (strpos($line, ';') !== false) {
                $line = trim(substr($line, 0, strpos($line, ';')));
            }
            if (empty($line) || preg_match('/^\w+:$/', $line)) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            $instruction = strtoupper($parts[0]);

            if (!isset($this->opcodes[$instruction])) {
                throw new Exception("Unknown instruction '$instruction' on line " . ($lineNum + 1));
            }

            $bytecode[] = $this->opcodes[$instruction];

            if (isset($parts[1])) {
                $operand = trim($parts[1]);
                
                // Handle special opcodes with variable-length arguments
                if ($instruction === 'PUSHC' && preg_match('/^"([^"]*)"/', $operand, $matches)) {
                    $str = $matches[1];
                    $constIdx = array_search($str, $constants);
                    $bytecode[] = $constIdx;
                } elseif ($instruction === 'GLOAD' || $instruction === 'GSTORE') {
                    // Format: GLOAD "$_GET" or GSTORE "$_POST"
                    if (preg_match('/^\$[\w_]+/', $operand)) {
                        // Encode name length and name
                        $bytecode[] = strlen($operand);
                        for ($j = 0; $j < strlen($operand); $j++) {
                            $bytecode[] = ord($operand[$j]);
                        }
                    } else {
                        throw new Exception("Invalid operand '$operand' for $instruction on line " . ($lineNum + 1));
                    }
                } elseif ($instruction === 'SYSCALL') {
                    // Format: SYSCALL 2 1 (function id, arg count)
                    $syscallParts = preg_split('/\s+/', $operand);
                    $syscallId = (int)$syscallParts[0];
                    $argCount = isset($syscallParts[1]) ? (int)$syscallParts[1] : 0;
                    $bytecode[] = $syscallId;
                    $bytecode[] = $argCount;
                } else {
                    // Check if operand is a label
                    if (isset($labels[$operand])) {
                        $operand = $labels[$operand];
                    }

                    if (is_numeric($operand)) {
                        $bytecode[] = (int)$operand;
                    } else {
                        throw new Exception("Invalid operand '$operand' on line " . ($lineNum + 1));
                    }
                }
            }
        }

        // Write bytecode with embedded constants to file
        $finalBytecode = [];
        
        // Write constant count
        $finalBytecode[] = count($constants);
        
        // Write each constant with length prefix
        foreach ($constants as $constant) {
            $len = strlen($constant);
            $finalBytecode[] = $len & 0xFF; // Low byte
            $finalBytecode[] = ($len >> 8) & 0xFF; // High byte
            for ($j = 0; $j < $len; $j++) {
                $finalBytecode[] = ord($constant[$j]);
            }
        }
        
        // Write bytecode instructions
        foreach ($bytecode as $byte) {
            $finalBytecode[] = $byte;
        }
        
        // Write to file
        $data = pack('C*', ...$finalBytecode);
        file_put_contents($outputFile, $data);
        
        echo "Assembled successfully: $outputFile (" . count($finalBytecode) . " bytes)\n";
        return true;
    }
}

class PHCVM {
    private $stack = [];
    private $memory = [];
    private $arrays = [];  // Separate storage for arrays
    private $nextArrayId = 1000;  // Array IDs start at 1000 to avoid conflicts
    private $ip = 0; // Instruction pointer
    private $bytecode = [];
    private $callStack = [];
    private $running = false;
    private $verbose = false; // Control whether to print status messages
    private $constants = []; // String and array constants from metadata
    
    // Memory layout (populated in constructor):
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
    
    // Built-in function IDs (for SYSCALL)
    private $builtinFunctions = [
        0 => 'isset',
        1 => 'empty',
        2 => 'strlen',
        3 => 'trim',
        4 => 'ltrim',
        5 => 'rtrim',
        6 => 'count',
        7 => 'is_array',
        8 => 'is_string',
        9 => 'is_numeric',
        10 => 'strpos',
        11 => 'substr',
        12 => 'str_replace',
        13 => 'strtolower',
        14 => 'strtoupper',
        15 => 'explode',
        16 => 'implode',
        17 => 'print_r',
        18 => 'var_dump',
        19 => 'abs',
        20 => 'round',
        21 => 'floor',
        22 => 'ceil',
        23 => 'intval',
        24 => 'floatval',
        25 => 'min',
        26 => 'max',
        27 => 'in_array',
        28 => 'array_keys',
        29 => 'array_values',
        30 => 'array_push',
        31 => 'array_pop',
        32 => 'array_shift',
        33 => 'array_unshift',
        34 => 'array_merge',
        35 => 'array_slice',
        36 => 'array_search',
        37 => 'strrev',
        38 => 'str_repeat',
        39 => 'ucfirst',
        40 => 'lcfirst',
        41 => 'ucwords',
        42 => 'is_int',
        43 => 'is_float',
        44 => 'is_bool',
        45 => 'is_null',
        46 => 'array_key_exists',
        47 => 'json_encode',
        48 => 'json_decode',
        49 => 'md5',
        50 => 'sha1',
        51 => 'base64_encode',
        52 => 'base64_decode',
        53 => 'time',
        54 => 'date',
        55 => 'strtotime',
        56 => 'preg_match',
        57 => 'preg_replace',
    ];

    public function __construct() {
        // Initialize superglobals in memory
        foreach ($this->superglobals as $name => $address) {
            $this->memory[$address] = [];  // Initialize as empty arrays
        }
    }

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
        
        if ($this->verbose) {
            echo "Loaded bytecode: " . count($this->bytecode) . " bytes (" . count($this->constants) . " constants)\n";
        }
    }

    public function setVerbose($verbose) {
        $this->verbose = $verbose;
    }

    public function run() {
        $this->running = true;
        $this->ip = 0;

        while ($this->running && $this->ip < count($this->bytecode)) {
            $this->executeInstruction();
        }
    }

    private function executeInstruction() {
        $opcode = $this->bytecode[$this->ip++];

        switch ($opcode) {
            case 0x01: // PUSH
                $value = $this->bytecode[$this->ip++];
                array_push($this->stack, $value);
                break;

            case 0x02: // POP
                if (empty($this->stack)) {
                    throw new Exception("Stack underflow at IP " . ($this->ip - 1));
                }
                array_pop($this->stack);
                break;

            case 0x03: // ADD
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a + $b);
                break;

            case 0x04: // SUB
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a - $b);
                break;

            case 0x05: // MUL
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a * $b);
                break;

            case 0x06: // DIV
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, intdiv($a, $b));
                break;

            case 0x07: // MOD
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a % $b);
                break;

            case 0x08: // PRINT
                $value = array_pop($this->stack);
                echo $value;
                break;

            case 0x09: // JMP
                $address = $this->bytecode[$this->ip++];
                $this->ip = $address;
                break;

            case 0x0A: // JZ (Jump if Zero)
                $address = $this->bytecode[$this->ip++];
                $value = array_pop($this->stack);
                if ($value == 0) {
                    $this->ip = $address;
                }
                break;

            case 0x0B: // JNZ (Jump if Not Zero)
                $address = $this->bytecode[$this->ip++];
                $value = array_pop($this->stack);
                if ($value != 0) {
                    $this->ip = $address;
                }
                break;

            case 0x0C: // CMP
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a - $b);
                break;

            case 0x0D: // LOAD
                $address = $this->bytecode[$this->ip++];
                $value = isset($this->memory[$address]) ? $this->memory[$address] : 0;
                array_push($this->stack, $value);
                break;

            case 0x0E: // STORE
                $address = $this->bytecode[$this->ip++];
                $value = array_pop($this->stack);
                $this->memory[$address] = $value;
                break;

            case 0x0F: // CALL
                $address = $this->bytecode[$this->ip++];
                array_push($this->callStack, $this->ip);
                $this->ip = $address;
                break;

            case 0x10: // RET
                if (empty($this->callStack)) {
                    throw new Exception("Call stack underflow at IP " . ($this->ip - 1));
                }
                $this->ip = array_pop($this->callStack);
                break;

            case 0x11: // LT (Less than)
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a < $b ? 1 : 0);
                break;

            case 0x12: // GT (Greater than)
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a > $b ? 1 : 0);
                break;

            case 0x13: // LTE (Less than or equal)
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a <= $b ? 1 : 0);
                break;

            case 0x14: // GTE (Greater than or equal)
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a >= $b ? 1 : 0);
                break;

            case 0x15: // EQ (Equal)
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a == $b ? 1 : 0);
                break;

            case 0x16: // NEQ (Not equal)
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a != $b ? 1 : 0);
                break;

            case 0x17: // PUSHC (Push constant)
                $constantIdx = $this->bytecode[$this->ip++];
                $value = $this->constants[$constantIdx] ?? null;
                array_push($this->stack, $value);
                break;

            case 0x18: // CONCAT (String concatenation)
                $b = array_pop($this->stack);
                $a = array_pop($this->stack);
                array_push($this->stack, $a . $b);
                break;

            case 0x19: // AGET (Array get)
                $key = array_pop($this->stack);
                $arrayId = array_pop($this->stack);
                
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    $result = $this->arrays[$arrayId][$key] ?? 0;
                } else {
                    $result = 0;
                }
                array_push($this->stack, $result);
                break;

            case 0x1A: // ASET (Array set)
                $value = array_pop($this->stack);
                $key = array_pop($this->stack);
                $varAddrOrArrayId = array_pop($this->stack);
                
                // Check if this is an arrayID (>= 1000) or a memory address
                if (is_numeric($varAddrOrArrayId) && $varAddrOrArrayId >= 1000) {
                    // Direct array ID from NEWARR
                    $arrayId = $varAddrOrArrayId;
                    if (!isset($this->arrays[$arrayId])) {
                        $this->arrays[$arrayId] = [];
                    }
                } else {
                    // Memory address - get or create array ID
                    $varAddr = $varAddrOrArrayId;
                    $arrayId = $this->memory[$varAddr] ?? null;
                    if (!is_numeric($arrayId) || !isset($this->arrays[$arrayId])) {
                        $arrayId = $this->nextArrayId++;
                        $this->memory[$varAddr] = $arrayId;
                        $this->arrays[$arrayId] = [];
                    }
                }
                
                $this->arrays[$arrayId][$key] = $value;
                break;

            case 0x1B: // NEWARR (Create new array)
                // Create a new array and push its ID to the stack
                $arrayId = $this->nextArrayId++;
                $this->arrays[$arrayId] = [];
                array_push($this->stack, $arrayId);
                break;

            case 0x1C: // GLOAD (Load from global scope by name)
                // Next byte is name length, then name string
                $nameLen = $this->bytecode[$this->ip++];
                $name = '';
                for ($j = 0; $j < $nameLen; $j++) {
                    $name .= chr($this->bytecode[$this->ip++]);
                }
                
                if (isset($this->superglobals[$name])) {
                    $addr = $this->superglobals[$name];
                    $value = $this->memory[$addr] ?? null;
                } else {
                    $value = null;
                }
                array_push($this->stack, $value);
                break;

            case 0x1D: // GSTORE (Store to global scope by name)
                $value = array_pop($this->stack);
                // Next byte is name length, then name string
                $nameLen = $this->bytecode[$this->ip++];
                $name = '';
                for ($j = 0; $j < $nameLen; $j++) {
                    $name .= chr($this->bytecode[$this->ip++]);
                }
                
                if (isset($this->superglobals[$name])) {
                    $addr = $this->superglobals[$name];
                    $this->memory[$addr] = $value;
                }
                break;

            case 0x1E: // SYSCALL (Call system function)
                $syscallId = $this->bytecode[$this->ip++];
                $argCount = $this->bytecode[$this->ip++];
                
                // Pop arguments from stack
                $args = [];
                for ($j = 0; $j < $argCount; $j++) {
                    array_unshift($args, array_pop($this->stack));
                }
                
                // Call the built-in function
                $result = $this->callBuiltinFunction($syscallId, $args);
                array_push($this->stack, $result);
                break;

            case 0xFF: // HALT
                $this->running = false;
                break;

            default:
                throw new Exception("Unknown opcode: 0x" . dechex($opcode) . " at IP " . ($this->ip - 1));
        }
    }

    private function callBuiltinFunction($syscallId, $args) {
        $funcName = $this->builtinFunctions[$syscallId] ?? 'unknown';
        
        switch ($syscallId) {
            case 0: // isset
                $arg = $args[0] ?? null;
                return ($arg !== null && $arg !== 0 && $arg !== '') ? 1 : 0;
                
            case 1: // empty
                $arg = $args[0] ?? null;
                return empty($arg) ? 1 : 0;
                
            case 2: // strlen
                $arg = $args[0] ?? '';
                return strlen((string)$arg);
                
            case 3: // trim
                $arg = $args[0] ?? '';
                return trim((string)$arg);
                
            case 4: // ltrim
                $arg = $args[0] ?? '';
                return ltrim((string)$arg);
                
            case 5: // rtrim
                $arg = $args[0] ?? '';
                return rtrim((string)$arg);
                
            case 6: // count
                $arg = $args[0] ?? [];
                if (is_numeric($arg) && isset($this->arrays[$arg])) {
                    return count($this->arrays[$arg]);
                }
                return 0;
                
            case 7: // is_array
                $arg = $args[0] ?? null;
                return (is_numeric($arg) && isset($this->arrays[$arg])) ? 1 : 0;
                
            case 8: // is_string
                $arg = $args[0] ?? null;
                return is_string($arg) ? 1 : 0;
                
            case 9: // is_numeric
                $arg = $args[0] ?? null;
                return is_numeric($arg) ? 1 : 0;
                
            case 10: // strpos
                $haystack = $args[0] ?? '';
                $needle = $args[1] ?? '';
                $pos = strpos((string)$haystack, (string)$needle);
                return $pos !== false ? $pos : -1;
                
            case 11: // substr
                $str = $args[0] ?? '';
                $start = $args[1] ?? 0;
                $length = isset($args[2]) ? $args[2] : null;
                return substr((string)$str, (int)$start, $length !== null ? (int)$length : null);
                
            case 12: // str_replace
                $search = $args[0] ?? '';
                $replace = $args[1] ?? '';
                $subject = $args[2] ?? '';
                return str_replace((string)$search, (string)$replace, (string)$subject);
                
            case 13: // strtolower
                $arg = $args[0] ?? '';
                return strtolower((string)$arg);
                
            case 14: // strtoupper
                $arg = $args[0] ?? '';
                return strtoupper((string)$arg);
                
            case 15: // explode
                $delimiter = $args[0] ?? '';
                $string = $args[1] ?? '';
                $parts = explode((string)$delimiter, (string)$string);
                // Return array ID
                $arrayId = $this->nextArrayId++;
                $this->arrays[$arrayId] = array_combine(range(0, count($parts) - 1), $parts);
                return $arrayId;
                
            case 16: // implode
                $glue = $args[0] ?? '';
                $arrayId = $args[1] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    return implode((string)$glue, (array)$this->arrays[$arrayId]);
                }
                return '';
                
            case 17: // print_r
                $arg = $args[0] ?? null;
                if (is_numeric($arg) && isset($this->arrays[$arg])) {
                    print_r($this->arrays[$arg]);
                } else {
                    print_r($arg);
                }
                return 1;
                
            case 18: // var_dump
                $arg = $args[0] ?? null;
                if (is_numeric($arg) && isset($this->arrays[$arg])) {
                    var_dump($this->arrays[$arg]);
                } else {
                    var_dump($arg);
                }
                return 1;
                
            case 19: // abs
                $arg = $args[0] ?? 0;
                return abs((float)$arg);
                
            case 20: // round
                $arg = $args[0] ?? 0;
                $precision = isset($args[1]) ? (int)$args[1] : 0;
                return round((float)$arg, $precision);
                
            case 21: // floor
                $arg = $args[0] ?? 0;
                return floor((float)$arg);
                
            case 22: // ceil
                $arg = $args[0] ?? 0;
                return ceil((float)$arg);
                
            case 23: // intval
                $arg = $args[0] ?? 0;
                return (int)$arg;
                
            case 24: // floatval
                $arg = $args[0] ?? 0;
                return (float)$arg;
                
            case 25: // min
                if (empty($args)) return 0;
                $values = [];
                foreach ($args as $arg) {
                    if (is_numeric($arg)) {
                        $values[] = $arg;
                    }
                }
                return empty($values) ? 0 : min($values);
                
            case 26: // max
                if (empty($args)) return 0;
                $values = [];
                foreach ($args as $arg) {
                    if (is_numeric($arg)) {
                        $values[] = $arg;
                    }
                }
                return empty($values) ? 0 : max($values);
                
            case 27: // in_array
                $needle = $args[0] ?? null;
                $arrayId = $args[1] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    return in_array($needle, $this->arrays[$arrayId]) ? 1 : 0;
                }
                return 0;
                
            case 28: // array_keys
                $arrayId = $args[0] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    $keys = array_keys($this->arrays[$arrayId]);
                    $newArrayId = $this->nextArrayId++;
                    $this->arrays[$newArrayId] = array_combine(range(0, count($keys) - 1), $keys);
                    return $newArrayId;
                }
                return 0;
                
            case 29: // array_values
                $arrayId = $args[0] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    $values = array_values($this->arrays[$arrayId]);
                    $newArrayId = $this->nextArrayId++;
                    $this->arrays[$newArrayId] = array_combine(range(0, count($values) - 1), $values);
                    return $newArrayId;
                }
                return 0;
                
            case 30: // array_push
                $arrayId = $args[0] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    $values = array_slice($args, 1);
                    foreach ($values as $value) {
                        $this->arrays[$arrayId][] = $value;
                    }
                    return count($this->arrays[$arrayId]);
                }
                return 0;
                
            case 31: // array_pop
                $arrayId = $args[0] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId]) && !empty($this->arrays[$arrayId])) {
                    return array_pop($this->arrays[$arrayId]);
                }
                return null;
                
            case 32: // array_shift
                $arrayId = $args[0] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId]) && !empty($this->arrays[$arrayId])) {
                    return array_shift($this->arrays[$arrayId]);
                }
                return null;
                
            case 33: // array_unshift
                $arrayId = $args[0] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    $values = array_slice($args, 1);
                    foreach (array_reverse($values) as $value) {
                        array_unshift($this->arrays[$arrayId], $value);
                    }
                    return count($this->arrays[$arrayId]);
                }
                return 0;
                
            case 34: // array_merge
                $arrays = [];
                foreach ($args as $arrayId) {
                    if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                        $arrays[] = $this->arrays[$arrayId];
                    }
                }
                $merged = array_merge(...$arrays);
                $newArrayId = $this->nextArrayId++;
                $this->arrays[$newArrayId] = $merged;
                return $newArrayId;
                
            case 35: // array_slice
                $arrayId = $args[0] ?? null;
                $offset = isset($args[1]) ? (int)$args[1] : 0;
                $length = isset($args[2]) ? (int)$args[2] : null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    $sliced = array_slice($this->arrays[$arrayId], $offset, $length);
                    $newArrayId = $this->nextArrayId++;
                    $this->arrays[$newArrayId] = $sliced;
                    return $newArrayId;
                }
                return 0;
                
            case 36: // array_search
                $needle = $args[0] ?? null;
                $arrayId = $args[1] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    $key = array_search($needle, $this->arrays[$arrayId]);
                    return $key !== false ? $key : -1;
                }
                return -1;
                
            case 37: // strrev
                $arg = $args[0] ?? '';
                return strrev((string)$arg);
                
            case 38: // str_repeat
                $str = $args[0] ?? '';
                $count = isset($args[1]) ? (int)$args[1] : 0;
                return str_repeat((string)$str, $count);
                
            case 39: // ucfirst
                $arg = $args[0] ?? '';
                return ucfirst((string)$arg);
                
            case 40: // lcfirst
                $arg = $args[0] ?? '';
                return lcfirst((string)$arg);
                
            case 41: // ucwords
                $arg = $args[0] ?? '';
                return ucwords((string)$arg);
                
            case 42: // is_int
                $arg = $args[0] ?? null;
                return is_int($arg) ? 1 : 0;
                
            case 43: // is_float
                $arg = $args[0] ?? null;
                return is_float($arg) ? 1 : 0;
                
            case 44: // is_bool
                $arg = $args[0] ?? null;
                return is_bool($arg) ? 1 : 0;
                
            case 45: // is_null
                $arg = $args[0] ?? null;
                return is_null($arg) ? 1 : 0;
                
            case 46: // array_key_exists
                $key = $args[0] ?? null;
                $arrayId = $args[1] ?? null;
                if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
                    return array_key_exists($key, $this->arrays[$arrayId]) ? 1 : 0;
                }
                return 0;
                
            case 47: // json_encode
                $arg = $args[0] ?? null;
                if (is_numeric($arg) && isset($this->arrays[$arg])) {
                    return json_encode($this->arrays[$arg]);
                }
                return json_encode($arg);
                
            case 48: // json_decode
                $json = $args[0] ?? '{}';
                $assoc = isset($args[1]) ? (int)$args[1] : 0;
                $decoded = json_decode((string)$json, $assoc ? true : false);
                if ($assoc && is_array($decoded)) {
                    $arrayId = $this->nextArrayId++;
                    $this->arrays[$arrayId] = $decoded;
                    return $arrayId;
                }
                return $decoded;
                
            case 49: // md5
                $arg = $args[0] ?? '';
                return md5((string)$arg);
                
            case 50: // sha1
                $arg = $args[0] ?? '';
                return sha1((string)$arg);
                
            case 51: // base64_encode
                $arg = $args[0] ?? '';
                return base64_encode((string)$arg);
                
            case 52: // base64_decode
                $arg = $args[0] ?? '';
                return base64_decode((string)$arg, true);
                
            case 53: // time
                return time();
                
            case 54: // date
                $format = $args[0] ?? 'Y-m-d H:i:s';
                $timestamp = isset($args[1]) ? (int)$args[1] : time();
                return date((string)$format, $timestamp);
                
            case 55: // strtotime
                $str = $args[0] ?? 'now';
                $baseTime = isset($args[1]) ? (int)$args[1] : time();
                $result = strtotime((string)$str, $baseTime);
                return $result !== false ? $result : 0;
                
            case 56: // preg_match
                $pattern = $args[0] ?? '';
                $subject = $args[1] ?? '';
                $matches = [];
                $result = preg_match((string)$pattern, (string)$subject, $matches);
                if (isset($args[2]) && is_numeric($args[2])) {
                    $arrayId = $this->nextArrayId++;
                    $this->arrays[$arrayId] = $matches;
                    // Store the array ID in the third argument (this is simplified)
                }
                return $result;
                
            case 57: // preg_replace
                $pattern = $args[0] ?? '';
                $replacement = $args[1] ?? '';
                $subject = $args[2] ?? '';
                return preg_replace((string)$pattern, (string)$replacement, (string)$subject);
                
            default:
                return 0;
        }
    }

    public function getStack() {
        return $this->stack;
    }

    public function getMemory() {
        return $this->memory;
    }
}

// CLI Interface
if (php_sapi_name() === 'cli' && !defined('PHC_LIBRARY')) {
    if ($argc < 2) {
        echo "PHC Assembler and Virtual Machine\n";
        echo "Usage:\n";
        echo "  Assemble: php " . $argv[0] . " assemble <input.phas> [output.phc]\n";
        echo "  Run:      php " . $argv[0] . " run <file.phc>\n";
        exit(1);
    }

    $command = $argv[1];

    try {
        if ($command === 'assemble') {
            if ($argc < 3) {
                echo "Error: Input file required\n";
                exit(1);
            }
            $input = $argv[2];
            $output = $argv[3] ?? str_replace('.phas', '.phc', $input);
            
            $assembler = new PHCAssembler();
            $assembler->assemble($input, $output);
            
        } elseif ($command === 'run') {
            if ($argc < 3) {
                echo "Error: Bytecode file required\n";
                exit(1);
            }
            $file = $argv[2];
            $verbose = in_array('--output', $argv) || in_array('-o', $argv);
            
            $vm = new PHCVM();
            $vm->setVerbose($verbose);
            $vm->load($file);
            if ($verbose) {
                echo "Running...\n";
            }
            $vm->run();
            if ($verbose) {
                echo "Execution complete.\n";
            }
            
        } else {
            echo "Unknown command: $command\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
