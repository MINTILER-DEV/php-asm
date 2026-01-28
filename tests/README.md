# User-Defined Functions Test Suite

This directory contains comprehensive tests for PHP user-defined function support in the PHP-to-Bytecode compiler.

## Test Files

### Basic Function Tests

1. **test_simple_function.php**
   - Basic function definition with no parameters
   - Function called from main code
   - Expected output: "Hello from function!"

2. **test_function_params.php**
   - Function with two parameters
   - Parameter passing and return value
   - Expected output: "8" (result of 5 + 3)

3. **test_multiple_functions.php**
   - Multiple function definitions
   - Different operations in each function
   - Expected output: "42 5" (6*7 and 20/4)

### Expression Tests

1. **test_simple_add.php**
   - Return statement with arithmetic expression
   - Expected output: "3" (1 + 2)

2. **test_call_in_expr.php**
   - Function call inside arithmetic expression
   - Expected output: "8" (5 + 3)

3. **test_two_calls_in_expr.php**
   - Multiple function calls in one expression
   - Expected output: "8" (5 + 3)

4. **test_two_calls_with_args.php**
   - Function calls with arguments in expressions
   - Expected output: "8" (6 + 2 = (5+1) + (3+1) -- note: add1 is $x + 1, called with add1(5) + add1(3))

### Built-in Function Tests

1. **test_builtins.php**
   - Test interaction with built-in PHP functions
   - Examples: strlen(), trim()

2. **test_call_in_expr.php**
   - Function calls combined with built-in operations

### Complex Scenarios

1. **test_all_features.php**
    - Comprehensive test combining all major features
    - Basic functions, parameters, returns
    - Expected output: "Hello8425"

## Known Limitations

### Recursive Function Calls with Expression Arguments

There is a known parsing issue with recursive or multiple calls to the same function where the arguments contain expressions. For example:

```php
function fib($n) {
    if ($n <= 1) {
        return $n;
    }
    return fib($n - 1) + fib($n - 2);  // This doesn't compile correctly
}
```

The parser incorrectly handles the expression `fib($n - 1) + fib($n - 2)` when both calls are to the same function or have complex argument expressions.

**Test files affected:**

- test_function_recursive.php
- test_fib_simple.php  
- test_same_func_twice.php
- test_different_funcs_with_args.php

These tests are included to demonstrate the limitation but will not produce correct results.

## New Testing Workflow (Easier!)

### Quick Test Validation

The new test suite automatically compares PHP native output vs PHC compiled output:

```bash
# Run all tests
php run_all_tests.php

# Validate a specific test
php validate_test.php test_fib_simple.php

# Add a new test
php add_test.php my_feature "Test description"
```

### How It Works

1. **Runs your test with native PHP** - Gets the expected output
2. **Compiles and runs with PHC** - Gets the actual output  
3. **Compares outputs** - Test passes if they're identical
4. **Shows differences** - If they differ, shows a clear diff

### Adding New Tests

```bash
# 1. Create a new test
php add_test.php string_functions

# 2. Edit the test file
# Add your PHP code to test_string_functions.php

# 3. Validate it works
php validate_test.php test_string_functions.php

# 4. Run all tests to ensure no regressions
php run_all_tests.php
```

Your test passes when the PHC output exactly matches PHP output!

## Old Testing Workflow (Manual)

### Compiling and Running Tests

### Compile a test to assembly

```bash
php ../src/compiler.php test_simple_function.php test_simple_function.phas
```

### Assemble to bytecode

```bash
php ../src/phc.php assemble test_simple_function.phas test_simple_function.phc
```

### Run the bytecode

```bash
php ../src/phc.php run test_simple_function.phc
```

### One-line compile and run

```bash
php ../src/compiler.php test_simple_function.php test.phas && \
php ../src/phc.php assemble test.phas test.phc && \
php ../src/phc.php run test.phc
```

## Feature Support Summary

✅ **Fully Supported:**

- Function definitions with parameters
- Simple function calls  
- Return statements
- Parameter passing
- Return value handling
- Multiple functions in one program
- Function calls in arithmetic expressions (for simple cases)
- Basic recursion (single function call in recursion)

❌ **Not Supported / Buggy:**

- Multiple recursive calls in same expression with complex arguments
- Default parameter values
- Variable number of arguments (varargs)
- Pass by reference
- Return type hints
- Function declarations before definition (order-dependent)

## Implementation Notes

The user-defined function implementation uses:

1. **Function Definition Parsing:** Regex-based extraction of function signatures (names and parameters)

2. **Code Organization:**
   - All function definitions compiled first
   - Initial JMP instruction skips over all functions
   - Main code executes, functions called as needed

3. **Parameter Handling:**
   - Parameters stored in memory (starting at address 100)
   - STORE instructions at function entry pop arguments from stack

4. **Return Values:**
   - Return expression compiled to stack
   - RET opcode returns to caller

5. **Call Stack:**
   - CALL opcode pushes return address
   - RET opcode pops return address and jumps

## See Also

- [Compiler Documentation](../README.md)
- [Assembly Format](../example.phas)
- [Bytecode Format](../example.phc)
