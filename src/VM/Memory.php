<?php

require_once __DIR__ . '/../MemoryLayout.php';

/**
 * Memory Manager with Stack Frames
 * Handles variable storage, array management, and call stack frames
 */

class Memory {
    private $memory = [];
    private $arrays = [];
    private $nextArrayId = 1000;
    
    // Stack frame management
    private $frameStack = [];
    private $currentFrame = 0;
    private $frameSize = 100; // Each frame gets 100 memory slots
    private $frameBase = 1000; // Frames start at address 1000

    public function __construct() {
        // Initialize superglobals (global memory, not in frames)
        $superglobals = MemoryLayout::getSuperglobals();
        foreach ($superglobals as $name => $address) {
            $this->memory[$address] = [];
        }
        
        // Create initial stack frame
        $this->pushFrame();
    }

    public function pushFrame() {
        // Save current frame index
        array_push($this->frameStack, $this->currentFrame);
        
        // Move to new frame
        $this->currentFrame = count($this->frameStack);
    }

    public function popFrame() {
        if (empty($this->frameStack)) {
            throw new Exception("Cannot pop frame: stack is empty");
        }
        
        // Restore previous frame
        $this->currentFrame = array_pop($this->frameStack);
    }

    private function getFrameAddress($localAddr) {
        // Convert local address to global frame address
        return $this->frameBase + ($this->currentFrame * $this->frameSize) + $localAddr;
    }

    public function load($address) {
        // Check if it's a superglobal (addresses 0-99)
        if ($address < MemoryLayout::getUserVarStart()) {
            return $this->memory[$address] ?? 0;
        }
        
        // It's a frame-local address
        $frameAddr = $this->getFrameAddress($address - MemoryLayout::getUserVarStart());
        return $this->memory[$frameAddr] ?? 0;
    }

    public function store($address, $value) {
        // Check if it's a superglobal
        if ($address < MemoryLayout::getUserVarStart()) {
            $this->memory[$address] = $value;
            return;
        }
        
        // It's a frame-local address
        $frameAddr = $this->getFrameAddress($address - MemoryLayout::getUserVarStart());
        $this->memory[$frameAddr] = $value;
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
            $arrayId = $this->load($arrayId);
            if (!is_numeric($arrayId) || !isset($this->arrays[$arrayId])) {
                $arrayId = $this->createArray();
                $this->store($arrayId, $arrayId);
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
