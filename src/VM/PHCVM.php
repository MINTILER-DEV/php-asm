<?php

require_once __DIR__ . '/../Opcodes.php';
require_once __DIR__ . '/Stack.php';
require_once __DIR__ . '/Memory.php';
require_once __DIR__ . '/SyscallHandler.php';
require_once __DIR__ . '/BytecodeLoader.php';

/**
 * PHC Virtual Machine
 * Executes PHC bytecode
 */

class PHCVM {
    private $stack;
    private $memory;
    private $syscallHandler;
    private $callStack = [];
    private $ip = 0;
    private $bytecode = [];
    private $constants = [];
    private $running = false;
    private $verbose = false;

    public function __construct() {
        $this->stack = new Stack();
        $this->memory = new Memory();
        $this->syscallHandler = new SyscallHandler($this->memory);
    }

    public function load($file) {
        $loader = new BytecodeLoader();
        $data = $loader->load($file);
        
        $this->bytecode = $data['bytecode'];
        $this->constants = $data['constants'];
        
        if ($this->verbose) {
            echo "Loaded bytecode: " . count($this->bytecode) . " bytes (" . 
                 count($this->constants) . " constants)\n";
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
            case Opcodes::PUSH:
                $value = $this->bytecode[$this->ip++];
                $this->stack->push($value);
                break;

            case Opcodes::POP:
                $this->stack->pop();
                break;

            case Opcodes::ADD:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a + $b);
                break;

            case Opcodes::SUB:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a - $b);
                break;

            case Opcodes::MUL:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a * $b);
                break;

            case Opcodes::DIV:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push(intdiv($a, $b));
                break;

            case Opcodes::MOD:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a % $b);
                break;

            case Opcodes::PRINT:
                echo $this->stack->pop();
                break;

            case Opcodes::JMP:
                $address = $this->bytecode[$this->ip++];
                $this->ip = $address;
                break;

            case Opcodes::JZ:
                $address = $this->bytecode[$this->ip++];
                $value = $this->stack->pop();
                if ($value == 0) {
                    $this->ip = $address;
                }
                break;

            case Opcodes::JNZ:
                $address = $this->bytecode[$this->ip++];
                $value = $this->stack->pop();
                if ($value != 0) {
                    $this->ip = $address;
                }
                break;

            case Opcodes::CMP:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a - $b);
                break;

            case Opcodes::LOAD:
                $address = $this->bytecode[$this->ip++];
                $this->stack->push($this->memory->load($address));
                break;

            case Opcodes::STORE:
                $address = $this->bytecode[$this->ip++];
                $value = $this->stack->pop();
                $this->memory->store($address, $value);
                break;

            case Opcodes::CALL:
                $address = $this->bytecode[$this->ip++];
                array_push($this->callStack, $this->ip);
                $this->ip = $address;
                break;

            case Opcodes::RET:
                if (empty($this->callStack)) {
                    throw new Exception("Call stack underflow at IP " . ($this->ip - 1));
                }
                $this->ip = array_pop($this->callStack);
                break;

            case Opcodes::LT:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a < $b ? 1 : 0);
                break;

            case Opcodes::GT:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a > $b ? 1 : 0);
                break;

            case Opcodes::LTE:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a <= $b ? 1 : 0);
                break;

            case Opcodes::GTE:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a >= $b ? 1 : 0);
                break;

            case Opcodes::EQ:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a == $b ? 1 : 0);
                break;

            case Opcodes::NEQ:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a != $b ? 1 : 0);
                break;

            case Opcodes::PUSHC:
                $constantIdx = $this->bytecode[$this->ip++];
                $value = $this->constants[$constantIdx] ?? null;
                $this->stack->push($value);
                break;

            case Opcodes::CONCAT:
                $b = $this->stack->pop();
                $a = $this->stack->pop();
                $this->stack->push($a . $b);
                break;

            case Opcodes::AGET:
                $key = $this->stack->pop();
                $arrayId = $this->stack->pop();
                $result = $this->memory->arrayGet($arrayId, $key);
                $this->stack->push($result);
                break;

            case Opcodes::ASET:
                $value = $this->stack->pop();
                $key = $this->stack->pop();
                $varAddrOrArrayId = $this->stack->pop();
                $this->memory->arraySet($varAddrOrArrayId, $key, $value);
                break;

            case Opcodes::NEWARR:
                $arrayId = $this->memory->createArray();
                $this->stack->push($arrayId);
                break;

            case Opcodes::GLOAD:
                $nameLen = $this->bytecode[$this->ip++];
                $name = '';
                for ($j = 0; $j < $nameLen; $j++) {
                    $name .= chr($this->bytecode[$this->ip++]);
                }
                $addr = MemoryLayout::getSuperglobalAddress($name);
                $value = $addr !== null ? $this->memory->load($addr) : null;
                $this->stack->push($value);
                break;

            case Opcodes::GSTORE:
                $value = $this->stack->pop();
                $nameLen = $this->bytecode[$this->ip++];
                $name = '';
                for ($j = 0; $j < $nameLen; $j++) {
                    $name .= chr($this->bytecode[$this->ip++]);
                }
                $addr = MemoryLayout::getSuperglobalAddress($name);
                if ($addr !== null) {
                    $this->memory->store($addr, $value);
                }
                break;

            case Opcodes::SYSCALL:
                $syscallId = $this->bytecode[$this->ip++];
                $argCount = $this->bytecode[$this->ip++];
                
                $args = [];
                for ($j = 0; $j < $argCount; $j++) {
                    array_unshift($args, $this->stack->pop());
                }
                
                $result = $this->syscallHandler->call($syscallId, $args);
                $this->stack->push($result);
                break;

            case Opcodes::HALT:
                $this->running = false;
                break;

            default:
                throw new Exception("Unknown opcode: 0x" . dechex($opcode) . " at IP " . ($this->ip - 1));
        }
    }

    public function getStack() {
        return $this->stack->getAll();
    }

    public function getMemory() {
        return $this->memory->getAllMemory();
    }
}
