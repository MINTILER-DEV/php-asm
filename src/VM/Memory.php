<?php

require_once __DIR__ . '/../MemoryLayout.php';

/**
 * Memory Manager
 * Handles variable storage and array management
 */

class Memory {
    private $memory = [];
    private $arrays = [];
    private $nextArrayId = 1000;

    public function __construct() {
        // Initialize superglobals
        $superglobals = MemoryLayout::getSuperglobals();
        foreach ($superglobals as $name => $address) {
            $this->memory[$address] = [];
        }
    }

    public function load($address) {
        return $this->memory[$address] ?? 0;
    }

    public function store($address, $value) {
        $this->memory[$address] = $value;
    }

    public function createArray() {
        $arrayId = $this->nextArrayId++;
        $this->arrays[$arrayId] = [];
        return $arrayId;
    }

    public function arrayGet($arrayId, $key) {
        if (is_numeric($arrayId) && isset($this->arrays[$arrayId])) {
            return $this->arrays[$arrayId][$key] ?? 0;
        }
        return 0;
    }

    public function arraySet($arrayId, $key, $value) {
        if (!is_numeric($arrayId) || $arrayId < 1000) {
            // This is a memory address, not an array ID
            $arrayId = $this->memory[$arrayId] ?? null;
            if (!is_numeric($arrayId) || !isset($this->arrays[$arrayId])) {
                $arrayId = $this->createArray();
                $this->memory[$arrayId] = $arrayId;
            }
        }
        
        if (!isset($this->arrays[$arrayId])) {
            $this->arrays[$arrayId] = [];
        }
        
        $this->arrays[$arrayId][$key] = $value;
    }

    public function getArray($arrayId) {
        return $this->arrays[$arrayId] ?? null;
    }

    public function isArray($arrayId) {
        return is_numeric($arrayId) && isset($this->arrays[$arrayId]);
    }

    public function getAllMemory() {
        return $this->memory;
    }

    public function getAllArrays() {
        return $this->arrays;
    }
}
