<?php

require_once __DIR__ . '/../BuiltinFunctions.php';

/**
 * Syscall Handler
 * Implements built-in PHP functions for the VM
 */

class SyscallHandler {
    private $memory;

    public function __construct($memory) {
        $this->memory = $memory;
    }

    public function call($syscallId, $args) {
        $funcName = BuiltinFunctions::getName($syscallId);
        
        switch ($syscallId) {
            case 0: return $this->isset_($args);
            case 1: return $this->empty_($args);
            case 2: return $this->strlen_($args);
            case 3: return $this->trim_($args);
            case 4: return $this->ltrim_($args);
            case 5: return $this->rtrim_($args);
            case 6: return $this->count_($args);
            case 7: return $this->is_array_($args);
            case 8: return $this->is_string_($args);
            case 9: return $this->is_numeric_($args);
            case 10: return $this->strpos_($args);
            case 11: return $this->substr_($args);
            case 12: return $this->str_replace_($args);
            case 13: return $this->strtolower_($args);
            case 14: return $this->strtoupper_($args);
            case 15: return $this->explode_($args);
            case 16: return $this->implode_($args);
            case 17: return $this->print_r_($args);
            case 18: return $this->var_dump_($args);
            case 19: return $this->abs_($args);
            case 20: return $this->round_($args);
            case 21: return $this->floor_($args);
            case 22: return $this->ceil_($args);
            case 23: return $this->intval_($args);
            case 24: return $this->floatval_($args);
            case 25: return $this->min_($args);
            case 26: return $this->max_($args);
            case 27: return $this->in_array_($args);
            case 28: return $this->array_keys_($args);
            case 29: return $this->array_values_($args);
            case 30: return $this->array_push_($args);
            case 31: return $this->array_pop_($args);
            case 32: return $this->array_shift_($args);
            case 33: return $this->array_unshift_($args);
            case 34: return $this->array_merge_($args);
            case 35: return $this->array_slice_($args);
            case 36: return $this->array_search_($args);
            case 37: return $this->strrev_($args);
            case 38: return $this->str_repeat_($args);
            case 39: return $this->ucfirst_($args);
            case 40: return $this->lcfirst_($args);
            case 41: return $this->ucwords_($args);
            case 42: return $this->is_int_($args);
            case 43: return $this->is_float_($args);
            case 44: return $this->is_bool_($args);
            case 45: return $this->is_null_($args);
            case 46: return $this->array_key_exists_($args);
            case 47: return $this->json_encode_($args);
            case 48: return $this->json_decode_($args);
            case 49: return $this->md5_($args);
            case 50: return $this->sha1_($args);
            case 51: return $this->base64_encode_($args);
            case 52: return $this->base64_decode_($args);
            case 53: return $this->time_($args);
            case 54: return $this->date_($args);
            case 55: return $this->strtotime_($args);
            case 56: return $this->preg_match_($args);
            case 57: return $this->preg_replace_($args);
            default: return 0;
        }
    }

    // Implementation of built-in functions
    private function isset_($args) {
        $arg = $args[0] ?? null;
        return ($arg !== null && $arg !== 0 && $arg !== '') ? 1 : 0;
    }

    private function empty_($args) {
        return empty($args[0] ?? null) ? 1 : 0;
    }

    private function strlen_($args) {
        return strlen((string)($args[0] ?? ''));
    }

    private function trim_($args) {
        return trim((string)($args[0] ?? ''));
    }

    private function ltrim_($args) {
        return ltrim((string)($args[0] ?? ''));
    }

    private function rtrim_($args) {
        return rtrim((string)($args[0] ?? ''));
    }

    private function count_($args) {
        $arrayId = $args[0] ?? null;
        if ($this->memory->isArray($arrayId)) {
            return count($this->memory->getArray($arrayId));
        }
        return 0;
    }

    private function is_array_($args) {
        return $this->memory->isArray($args[0] ?? null) ? 1 : 0;
    }

    private function is_string_($args) {
        return is_string($args[0] ?? null) ? 1 : 0;
    }

    private function is_numeric_($args) {
        return is_numeric($args[0] ?? null) ? 1 : 0;
    }

    private function strpos_($args) {
        $pos = strpos((string)($args[0] ?? ''), (string)($args[1] ?? ''));
        return $pos !== false ? $pos : -1;
    }

    private function substr_($args) {
        $str = $args[0] ?? '';
        $start = $args[1] ?? 0;
        $length = $args[2] ?? null;
        return substr((string)$str, (int)$start, $length !== null ? (int)$length : null);
    }

    private function str_replace_($args) {
        return str_replace((string)($args[0] ?? ''), (string)($args[1] ?? ''), (string)($args[2] ?? ''));
    }

    private function strtolower_($args) {
        return strtolower((string)($args[0] ?? ''));
    }

    private function strtoupper_($args) {
        return strtoupper((string)($args[0] ?? ''));
    }

    private function explode_($args) {
        $parts = explode((string)($args[0] ?? ''), (string)($args[1] ?? ''));
        $arrayId = $this->memory->createArray();
        $array = $this->memory->getArray($arrayId);
        foreach ($parts as $i => $part) {
            $this->memory->arraySet($arrayId, $i, $part);
        }
        return $arrayId;
    }

    private function implode_($args) {
        $glue = $args[0] ?? '';
        $arrayId = $args[1] ?? null;
        if ($this->memory->isArray($arrayId)) {
            return implode((string)$glue, $this->memory->getArray($arrayId));
        }
        return '';
    }

    private function print_r_($args) {
        $arg = $args[0] ?? null;
        if ($this->memory->isArray($arg)) {
            print_r($this->memory->getArray($arg));
        } else {
            print_r($arg);
        }
        return 1;
    }

    private function var_dump_($args) {
        $arg = $args[0] ?? null;
        if ($this->memory->isArray($arg)) {
            var_dump($this->memory->getArray($arg));
        } else {
            var_dump($arg);
        }
        return 1;
    }

    private function abs_($args) {
        return abs((float)($args[0] ?? 0));
    }

    private function round_($args) {
        return round((float)($args[0] ?? 0), (int)($args[1] ?? 0));
    }

    private function floor_($args) {
        return floor((float)($args[0] ?? 0));
    }

    private function ceil_($args) {
        return ceil((float)($args[0] ?? 0));
    }

    private function intval_($args) {
        return (int)($args[0] ?? 0);
    }

    private function floatval_($args) {
        return (float)($args[0] ?? 0);
    }

    private function min_($args) {
        $values = array_filter($args, 'is_numeric');
        return empty($values) ? 0 : min($values);
    }

    private function max_($args) {
        $values = array_filter($args, 'is_numeric');
        return empty($values) ? 0 : max($values);
    }

    private function in_array_($args) {
        $needle = $args[0] ?? null;
        $arrayId = $args[1] ?? null;
        if ($this->memory->isArray($arrayId)) {
            return in_array($needle, $this->memory->getArray($arrayId)) ? 1 : 0;
        }
        return 0;
    }

    private function array_keys_($args) {
        $arrayId = $args[0] ?? null;
        if ($this->memory->isArray($arrayId)) {
            $keys = array_keys($this->memory->getArray($arrayId));
            $newArrayId = $this->memory->createArray();
            foreach ($keys as $i => $key) {
                $this->memory->arraySet($newArrayId, $i, $key);
            }
            return $newArrayId;
        }
        return 0;
    }

    private function array_values_($args) {
        $arrayId = $args[0] ?? null;
        if ($this->memory->isArray($arrayId)) {
            $values = array_values($this->memory->getArray($arrayId));
            $newArrayId = $this->memory->createArray();
            foreach ($values as $i => $value) {
                $this->memory->arraySet($newArrayId, $i, $value);
            }
            return $newArrayId;
        }
        return 0;
    }

    private function array_push_($args) {
        $arrayId = array_shift($args);
        if ($this->memory->isArray($arrayId)) {
            $array = $this->memory->getArray($arrayId);
            foreach ($args as $value) {
                $this->memory->arraySet($arrayId, count($array), $value);
                $array = $this->memory->getArray($arrayId);
            }
            return count($array);
        }
        return 0;
    }

    private function array_pop_($args) {
        $arrayId = $args[0] ?? null;
        if ($this->memory->isArray($arrayId)) {
            $array = $this->memory->getArray($arrayId);
            if (!empty($array)) {
                return array_pop($array);
            }
        }
        return null;
    }

    private function array_shift_($args) {
        $arrayId = $args[0] ?? null;
        if ($this->memory->isArray($arrayId)) {
            $array = $this->memory->getArray($arrayId);
            if (!empty($array)) {
                return array_shift($array);
            }
        }
        return null;
    }

    private function array_unshift_($args) {
        $arrayId = array_shift($args);
        if ($this->memory->isArray($arrayId)) {
            $array = $this->memory->getArray($arrayId);
            $count = count($array);
            foreach (array_reverse($args) as $value) {
                array_unshift($array, $value);
            }
            return count($array);
        }
        return 0;
    }

    private function array_merge_($args) {
        $arrays = [];
        foreach ($args as $arrayId) {
            if ($this->memory->isArray($arrayId)) {
                $arrays[] = $this->memory->getArray($arrayId);
            }
        }
        if (empty($arrays)) return 0;
        
        $merged = array_merge(...$arrays);
        $newArrayId = $this->memory->createArray();
        foreach ($merged as $key => $value) {
            $this->memory->arraySet($newArrayId, $key, $value);
        }
        return $newArrayId;
    }

    private function array_slice_($args) {
        $arrayId = $args[0] ?? null;
        $offset = (int)($args[1] ?? 0);
        $length = isset($args[2]) ? (int)$args[2] : null;
        
        if ($this->memory->isArray($arrayId)) {
            $sliced = array_slice($this->memory->getArray($arrayId), $offset, $length);
            $newArrayId = $this->memory->createArray();
            foreach ($sliced as $key => $value) {
                $this->memory->arraySet($newArrayId, $key, $value);
            }
            return $newArrayId;
        }
        return 0;
    }

    private function array_search_($args) {
        $needle = $args[0] ?? null;
        $arrayId = $args[1] ?? null;
        if ($this->memory->isArray($arrayId)) {
            $key = array_search($needle, $this->memory->getArray($arrayId));
            return $key !== false ? $key : -1;
        }
        return -1;
    }

    private function strrev_($args) {
        return strrev((string)($args[0] ?? ''));
    }

    private function str_repeat_($args) {
        return str_repeat((string)($args[0] ?? ''), (int)($args[1] ?? 0));
    }

    private function ucfirst_($args) {
        return ucfirst((string)($args[0] ?? ''));
    }

    private function lcfirst_($args) {
        return lcfirst((string)($args[0] ?? ''));
    }

    private function ucwords_($args) {
        return ucwords((string)($args[0] ?? ''));
    }

    private function is_int_($args) {
        return is_int($args[0] ?? null) ? 1 : 0;
    }

    private function is_float_($args) {
        return is_float($args[0] ?? null) ? 1 : 0;
    }

    private function is_bool_($args) {
        return is_bool($args[0] ?? null) ? 1 : 0;
    }

    private function is_null_($args) {
        return is_null($args[0] ?? null) ? 1 : 0;
    }

    private function array_key_exists_($args) {
        $key = $args[0] ?? null;
        $arrayId = $args[1] ?? null;
        if ($this->memory->isArray($arrayId)) {
            return array_key_exists($key, $this->memory->getArray($arrayId)) ? 1 : 0;
        }
        return 0;
    }

    private function json_encode_($args) {
        $arg = $args[0] ?? null;
        if ($this->memory->isArray($arg)) {
            return json_encode($this->memory->getArray($arg));
        }
        return json_encode($arg);
    }

    private function json_decode_($args) {
        $json = $args[0] ?? '{}';
        $assoc = (int)($args[1] ?? 0);
        $decoded = json_decode((string)$json, $assoc ? true : false);
        if ($assoc && is_array($decoded)) {
            $arrayId = $this->memory->createArray();
            foreach ($decoded as $key => $value) {
                $this->memory->arraySet($arrayId, $key, $value);
            }
            return $arrayId;
        }
        return $decoded;
    }

    private function md5_($args) {
        return md5((string)($args[0] ?? ''));
    }

    private function sha1_($args) {
        return sha1((string)($args[0] ?? ''));
    }

    private function base64_encode_($args) {
        return base64_encode((string)($args[0] ?? ''));
    }

    private function base64_decode_($args) {
        return base64_decode((string)($args[0] ?? ''), true);
    }

    private function time_($args) {
        return time();
    }

    private function date_($args) {
        $format = $args[0] ?? 'Y-m-d H:i:s';
        $timestamp = isset($args[1]) ? (int)$args[1] : time();
        return date((string)$format, $timestamp);
    }

    private function strtotime_($args) {
        $str = $args[0] ?? 'now';
        $baseTime = isset($args[1]) ? (int)$args[1] : time();
        $result = strtotime((string)$str, $baseTime);
        return $result !== false ? $result : 0;
    }

    private function preg_match_($args) {
        $pattern = $args[0] ?? '';
        $subject = $args[1] ?? '';
        return preg_match((string)$pattern, (string)$subject);
    }

    private function preg_replace_($args) {
        return preg_replace((string)($args[0] ?? ''), (string)($args[1] ?? ''), (string)($args[2] ?? ''));
    }
}
