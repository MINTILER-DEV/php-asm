<?php

/**
 * Built-in Functions Registry
 * Maps PHP built-in functions to SYSCALL IDs
 */

class BuiltinFunctions {
    private static $functions = [
        'isset' => 0,
        'empty' => 1,
        'strlen' => 2,
        'trim' => 3,
        'ltrim' => 4,
        'rtrim' => 5,
        'count' => 6,
        'is_array' => 7,
        'is_string' => 8,
        'is_numeric' => 9,
        'strpos' => 10,
        'substr' => 11,
        'str_replace' => 12,
        'strtolower' => 13,
        'strtoupper' => 14,
        'explode' => 15,
        'implode' => 16,
        'print_r' => 17,
        'var_dump' => 18,
        'abs' => 19,
        'round' => 20,
        'floor' => 21,
        'ceil' => 22,
        'intval' => 23,
        'floatval' => 24,
        'min' => 25,
        'max' => 26,
        'in_array' => 27,
        'array_keys' => 28,
        'array_values' => 29,
        'array_push' => 30,
        'array_pop' => 31,
        'array_shift' => 32,
        'array_unshift' => 33,
        'array_merge' => 34,
        'array_slice' => 35,
        'array_search' => 36,
        'strrev' => 37,
        'str_repeat' => 38,
        'ucfirst' => 39,
        'lcfirst' => 40,
        'ucwords' => 41,
        'is_int' => 42,
        'is_float' => 43,
        'is_bool' => 44,
        'is_null' => 45,
        'array_key_exists' => 46,
        'json_encode' => 47,
        'json_decode' => 48,
        'md5' => 49,
        'sha1' => 50,
        'base64_encode' => 51,
        'base64_decode' => 52,
        'time' => 53,
        'date' => 54,
        'strtotime' => 55,
        'preg_match' => 56,
        'preg_replace' => 57,
    ];

    public static function getAll() {
        return self::$functions;
    }

    public static function getId($name) {
        return self::$functions[$name] ?? null;
    }

    public static function getName($id) {
        $flipped = array_flip(self::$functions);
        return $flipped[$id] ?? null;
    }

    public static function exists($name) {
        return isset(self::$functions[$name]);
    }
}
