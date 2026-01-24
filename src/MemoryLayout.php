<?php

/**
 * Memory Layout Configuration
 * Defines the memory address space organization
 */

class MemoryLayout {
    // Memory regions
    const SUPERGLOBAL_START = 0;
    const SUPERGLOBAL_END = 19;
    const RESERVED_START = 20;
    const RESERVED_END = 99;
    const USER_VAR_START = 100;
    
    // Superglobal mappings
    private static $superglobals = [
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

    public static function getSuperglobals() {
        return self::$superglobals;
    }

    public static function getSuperglobalAddress($name) {
        return self::$superglobals[$name] ?? null;
    }

    public static function isSuperglobal($name) {
        return isset(self::$superglobals[$name]);
    }

    public static function getUserVarStart() {
        return self::USER_VAR_START;
    }
}
