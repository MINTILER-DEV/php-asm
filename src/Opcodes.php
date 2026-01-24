<?php

/**
 * PHC Opcode Definitions
 * Shared between compiler, assembler, and VM
 */

class Opcodes {
    const PUSH    = 0x01;
    const POP     = 0x02;
    const ADD     = 0x03;
    const SUB     = 0x04;
    const MUL     = 0x05;
    const DIV     = 0x06;
    const MOD     = 0x07;
    const PRINT   = 0x08;
    const JMP     = 0x09;
    const JZ      = 0x0A;  // Jump if zero
    const JNZ     = 0x0B;  // Jump if not zero
    const CMP     = 0x0C;  // Compare top two stack values
    const LOAD    = 0x0D;  // Load from memory
    const STORE   = 0x0E;  // Store to memory
    const CALL    = 0x0F;  // Call function
    const RET     = 0x10;  // Return from function
    const LT      = 0x11;  // Less than
    const GT      = 0x12;  // Greater than
    const LTE     = 0x13;  // Less than or equal
    const GTE     = 0x14;  // Greater than or equal
    const EQ      = 0x15;  // Equal
    const NEQ     = 0x16;  // Not equal
    const PUSHC   = 0x17;  // Push constant (string/array)
    const CONCAT  = 0x18;  // String concatenation
    const AGET    = 0x19;  // Array get
    const ASET    = 0x1A;  // Array set
    const NEWARR  = 0x1B;  // New array
    const GLOAD   = 0x1C;  // Load from global scope by name
    const GSTORE  = 0x1D;  // Store to global scope by name
    const SYSCALL = 0x1E;  // Call system function (built-in)
    const HALT    = 0xFF;

    public static function getName($opcode) {
        $reflection = new ReflectionClass(__CLASS__);
        $constants = $reflection->getConstants();
        foreach ($constants as $name => $value) {
            if ($value === $opcode) {
                return $name;
            }
        }
        return 'UNKNOWN';
    }

    public static function getOpcodeMap() {
        return [
            'PUSH'    => self::PUSH,
            'POP'     => self::POP,
            'ADD'     => self::ADD,
            'SUB'     => self::SUB,
            'MUL'     => self::MUL,
            'DIV'     => self::DIV,
            'MOD'     => self::MOD,
            'PRINT'   => self::PRINT,
            'JMP'     => self::JMP,
            'JZ'      => self::JZ,
            'JNZ'     => self::JNZ,
            'CMP'     => self::CMP,
            'LOAD'    => self::LOAD,
            'STORE'   => self::STORE,
            'CALL'    => self::CALL,
            'RET'     => self::RET,
            'LT'      => self::LT,
            'GT'      => self::GT,
            'LTE'     => self::LTE,
            'GTE'     => self::GTE,
            'EQ'      => self::EQ,
            'NEQ'     => self::NEQ,
            'PUSHC'   => self::PUSHC,
            'CONCAT'  => self::CONCAT,
            'AGET'    => self::AGET,
            'ASET'    => self::ASET,
            'NEWARR'  => self::NEWARR,
            'GLOAD'   => self::GLOAD,
            'GSTORE'  => self::GSTORE,
            'SYSCALL' => self::SYSCALL,
            'HALT'    => self::HALT,
        ];
    }
}
