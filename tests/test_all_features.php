<?php
// Summary test file for user-defined functions

// Test 1: Simple function with no arguments
function greet() {
    echo "Hello";
}

greet();

// Test 2: Function with parameters and simple return
function add($a, $b) {
    return $a + $b;
}

echo add(5, 3);

// Test 3: Multiple functions
function multiply($x, $y) {
    return $x * $y;
}

function divide($a, $b) {
    return $a / $b;
}

echo multiply(6, 7);
echo divide(20, 4);
