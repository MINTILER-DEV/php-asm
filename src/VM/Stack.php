<?php

/**
 * Stack Manager
 * Handles stack operations for the VM
 */

class Stack {
    private $stack = [];

    public function push($value) {
        array_push($this->stack, $value);
    }

    public function pop() {
        if (empty($this->stack)) {
            throw new Exception("Stack underflow");
        }
        return array_pop($this->stack);
    }

    public function peek() {
        if (empty($this->stack)) {
            return null;
        }
        return end($this->stack);
    }

    public function isEmpty() {
        return empty($this->stack);
    }

    public function size() {
        return count($this->stack);
    }

    public function getAll() {
        return $this->stack;
    }

    public function clear() {
        $this->stack = [];
    }
}
