# Testing Guide

## 🎯 Overview

The PHP-ASM test suite validates that compiled code behaves **exactly** like native PHP. Tests pass when PHC output matches PHP output character-for-character.

## 🚀 Quick Start

### Run All Tests

```bash
cd tests
php run_all_tests.php
```

### Validate a Single Test

```bash
php validate_test.php test_fib_simple.php
```

### Add a New Test

```bash
php add_test.php my_feature "Description of what this tests"
```

## 📁 Test Files

### Testing Helper Scripts

- **`run_all_tests.php`** - Runs all tests, compares outputs
- **`validate_test.php`** - Validates a single test in detail
- **`add_test.php`** - Creates a new test from template

### Test Categories

#### Basic Functions

- `test_simple_function.php` - Function with no parameters
- `test_function_params.php` - Function with parameters
- `test_multiple_functions.php` - Multiple function definitions

#### Recursive Functions (Previously Broken, Now Fixed!)

- `test_fib_simple.php` - Fibonacci recursion
- `test_function_recursive.php` - General recursion
- `test_same_func_twice.php` - Same function called multiple times
- `test_two_calls_with_args.php` - Complex argument expressions

#### Expressions

- `test_simple_add.php` - Basic arithmetic
- `test_call_in_expr.php` - Function call in expression
- `test_two_calls_in_expr.php` - Multiple calls in expression

#### Built-in Functions

- `test_builtins.php` - Built-in function usage

#### Comprehensive

- `test_all_features.php` - Tests multiple features together

## ✅ How Tests Work

### The Testing Process

```
1. Run test with native PHP → Get expected output
2. Compile and run with PHC → Get actual output
3. Compare outputs → Test passes if identical
```

### Example

**Test File (`test_fib_simple.php`):**

```php
<?php
function fib($n) {
    if ($n <= 1) return 1;
    return fib($n - 1) + fib($n - 2);
}
echo fib(5);
```

**Native PHP Output:**

```
8
```

**PHC Compiled Output:**

```
8
```

**Result:** ✅ PASS (outputs match!)

## 📝 Writing Tests

### Test Template

Use `add_test.php` to create a new test:

```bash
php add_test.php array_operations "Test array manipulation"
```

This creates `test_array_operations.php`:

```php
<?php

/**
 * Test: Array Operations
 * Test array manipulation
 */

// Your test code here
// The output should be identical to native PHP execution

echo "Test array_operations\n";

// Add your test cases
$arr = [1, 2, 3];
echo count($arr);
```

### Test Guidelines

1. **Single Responsibility** - Test one feature at a time
2. **Clear Output** - Use `echo` to show results
3. **No Side Effects** - Don't modify external state
4. **Deterministic** - Same input = same output every time
5. **No External Dependencies** - No file I/O, network, etc.

### Good Test Examples

✅ **Good**: Clear, deterministic output

```php
<?php
function add($a, $b) {
    return $a + $b;
}
echo add(5, 3);  // Always outputs: 8
```

✅ **Good**: Tests specific feature

```php
<?php
$arr = [1, 2, 3];
echo count($arr);  // Tests built-in count()
```

❌ **Bad**: Non-deterministic (time-based)

```php
<?php
echo date('Y-m-d H:i:s');  // Different every time!
```

❌ **Bad**: Multiple features, unclear which is tested

```php
<?php
function complex() {
    $arr = [1, 2];
    for ($i = 0; $i < count($arr); $i++) {
        echo $arr[$i] + time();  // Too much at once!
    }
}
```

## 🔍 Validating Tests

### Using `validate_test.php`

```bash
php validate_test.php test_my_feature.php
```

**Output Example:**

```
╔════════════════════════════════════════════════════════════╗
║  Test Validator                                            ║
╚════════════════════════════════════════════════════════════╝

Test file: test_my_feature.php

Running with native PHP...
Running with PHC compiler...

┌────────────────────────────────────────────────────────────┐
│ PHP Output (12.45 ms)
├────────────────────────────────────────────────────────────┤
│ Result: 42
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│ PHC Output (156.78 ms)
├────────────────────────────────────────────────────────────┤
│ Result: 42
└────────────────────────────────────────────────────────────┘

╔════════════════════════════════════════════════════════════╗
║  ✓ TEST PASSED                                             ║
╚════════════════════════════════════════════════════════════╝

The outputs match perfectly! 🎉
```

### What Gets Compared

The validator compares the **actual program output** only. It filters out:

- Compilation status messages (`✓ Compiled:`, `✓ Assembled:`)
- Progress bars and system messages
- ANSI color codes
- Empty lines

Only your `echo` statements and program output are compared.

## 🐛 Debugging Failed Tests

### When a Test Fails

```
╔════════════════════════════════════════════════════════════╗
║  ✗ TEST FAILED                                             ║
╚════════════════════════════════════════════════════════════╝

The outputs differ:

┌────────────────────────────────────────────────────────────┐
│ Diff (- PHP, + PHC)
├────────────────────────────────────────────────────────────┤
│ - Expected: 42
│ + Got: 0
└────────────────────────────────────────────────────────────┘
```

### Debugging Steps

1. **Check PHP Output**

   ```bash
   D:\Applications\PHP\current\php.exe test_my_feature.php
   ```

2. **Check Compilation**

   ```bash
   php ../src/phc-new.php compile test_my_feature.php test.phas
   cat test.phas  # View assembly
   ```

3. **Check Assembly Output**
   Look at the `.phas` file to see what was generated

4. **Run with Verbose**

   ```bash
   php ../src/phc-new.php exec test_my_feature.php --verbose
   ```

5. **Add Debug Output**

   ```php
   // In your test
   echo "DEBUG: Variable = $x\n";
   ```

### Common Issues

**Issue**: PHC outputs nothing

- **Cause**: Compilation error
- **Fix**: Check the assembly output, look for syntax errors

**Issue**: PHC outputs wrong value

- **Cause**: Bug in compiler/VM
- **Fix**: Check the assembly, trace through VM execution

**Issue**: Test is flaky (passes sometimes)

- **Cause**: Non-deterministic code (time, random, etc.)
- **Fix**: Make test deterministic

## 📊 Running Test Suite

### Full Test Run

```bash
php run_all_tests.php
```

**Output:**

```
╔════════════════════════════════════════════════════════════╗
║  PHP-ASM Modular Compiler Test Suite                      ║
╚════════════════════════════════════════════════════════════╝

Testing: test_simple_function.php              ✓ PASS
Testing: test_function_params.php              ✓ PASS
Testing: test_fib_simple.php                   ✓ PASS
Testing: test_builtins.php                     ✓ PASS
...

╔════════════════════════════════════════════════════════════╗
║  Test Results                                              ║
╚════════════════════════════════════════════════════════════╝

Passed:  15 tests
Failed:   0 tests
Total:   15 tests

🎉 All tests passed! The compiler output matches PHP perfectly.
```

### Continuous Integration

You can use the exit code in CI/CD:

```bash
php run_all_tests.php
if [ $? -eq 0 ]; then
    echo "All tests passed!"
else
    echo "Tests failed!"
    exit 1
fi
```

## 🎯 Test Coverage

### What's Tested

✅ **Language Features**

- Variables and assignments
- Arithmetic operations
- String operations
- Array operations (indexed and associative)
- Conditionals (if/elseif/else)
- Loops (while, for)
- User-defined functions
- Recursive functions
- Function parameters and return values
- Built-in functions

✅ **Edge Cases**

- Empty arrays
- Nested function calls
- Complex expressions
- Multiple functions in one expression

### What's Not Tested Yet

⏳ **Future Test Areas**

- Error handling
- Type juggling edge cases
- Very large numbers
- Unicode strings
- Deep recursion limits
- Memory limits

## 📚 Best Practices

### DO ✅

- Write small, focused tests
- Test one feature at a time
- Use clear, descriptive names
- Echo clear output
- Keep tests deterministic
- Run full suite before committing

### DON'T ❌

- Mix multiple features in one test
- Use time-based or random values
- Rely on external files
- Create tests with no output
- Modify global state
- Skip validation

## 🔧 Advanced Testing

### Testing Specific Opcodes

If you're adding a new opcode, create a minimal test:

```php
<?php
// Test: MY_OPCODE
$result = my_operation(5);  // Uses new opcode
echo $result;
```

### Testing Built-in Functions

When adding a new built-in:

```php
<?php
// Test: my_builtin
echo my_builtin("test");
echo my_builtin(123);
echo my_builtin([1, 2, 3]);
```

### Regression Testing

When you fix a bug:

1. Create a test that reproduces the bug
2. Verify it fails with old code
3. Fix the bug
4. Verify test now passes
5. Keep test to prevent regression

## 🎓 Examples

### Example 1: Simple Function Test

```php
<?php
function greet($name) {
    return "Hello, " . $name;
}
echo greet("World");
```

**Expected Output:** `Hello, World`

### Example 2: Recursive Function Test

```php
<?php
function factorial($n) {
    if ($n <= 1) return 1;
    return $n * factorial($n - 1);
}
echo factorial(5);
```

**Expected Output:** `120`

### Example 3: Array Test

```php
<?php
$arr = ['a' => 1, 'b' => 2];
echo $arr['a'] + $arr['b'];
```

**Expected Output:** `3`

## 🚀 Quick Reference

```bash
# Run all tests
php run_all_tests.php

# Validate one test
php validate_test.php test_name.php

# Create new test
php add_test.php feature_name "Description"

# Manual test
D:\Applications\PHP\current\php.exe test_name.php
php ../src/phc-new.php exec test_name.php

# Debug compilation
php ../src/phc-new.php compile test_name.php test.phas
php ../src/phc-new.php assemble test.phas test.phc
php ../src/phc-new.php run test.phc --verbose
```

---

**Remember**: A good test suite is your safety net. Write tests, run tests, trust tests! 🎯
