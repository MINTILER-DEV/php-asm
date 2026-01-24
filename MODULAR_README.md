# PHP-ASM: Modular PHP Bytecode Compiler

A modular compiler that transforms PHP code into custom bytecode and executes it in a virtual machine.

## 🎯 Project Structure

The project has been refactored into a clean, modular architecture:

```
src/
├── Opcodes.php              # Opcode definitions (shared)
├── BuiltinFunctions.php     # Built-in function registry
├── MemoryLayout.php         # Memory address space configuration
├──
├── Compiler/                # Compilation modules
│   ├── CodeEmitter.php      # Assembly code generation
│   ├── VariableResolver.php # Variable to memory address mapping
│   ├── ExpressionParser.php # Expression parsing with proper precedence
│   ├── ArrayCompiler.php    # Array literal compilation
│   ├── StatementCompiler.php# Statement compilation (if, while, for, etc.)
│   └── FunctionCompiler.php # User-defined function compilation
├── PHPCompiler.php          # Main compiler orchestrator
│
├── Assembler/               # Assembly to bytecode
│   └── PHCAssembler.php     # Assembles .phas to .phc
│
├── VM/                      # Virtual Machine
│   ├── Stack.php            # Stack manager
│   ├── Memory.php           # Memory and array manager
│   ├── SyscallHandler.php   # Built-in function implementations
│   ├── BytecodeLoader.php   # Bytecode file loader
│   └── PHCVM.php            # Main VM executor
│
├── compile.php              # Standalone compiler CLI
├── assemble.php             # Standalone assembler CLI
├── run.php                  # Standalone VM CLI
└── phc-new.php              # Unified CLI tool
```

## 🚀 Quick Start

### Compile, Assemble, and Run (One Command)
```bash
php src/phc-new.php exec tests/test_fib_simple.php
```

### Step by Step
```bash
# 1. Compile PHP to Assembly
php src/phc-new.php compile tests/test_fib_simple.php output.phas

# 2. Assemble to Bytecode
php src/phc-new.php assemble output.phas output.phc

# 3. Run Bytecode
php src/phc-new.php run output.phc
```

### Build (Compile + Assemble)
```bash
php src/phc-new.php build tests/test_fib_simple.php
```

## ✨ What's Fixed

### 1. **Recursive Function Calls with Complex Arguments** ✅
The original compiler had issues with expressions like:
```php
return fib($n - 1) + fib($n - 2);  // Now works!
```

**Fixed by**: Proper recursive descent parser in `ExpressionParser.php` that correctly handles operator precedence and nested function calls.

### 2. **Modular Architecture** ✅
- Split monolithic 1000+ line files into focused, single-responsibility modules
- Easier to understand, test, and extend
- Clear separation of concerns

### 3. **Better Error Handling** ✅
- More descriptive error messages
- Stack traces in verbose mode
- Better validation throughout

### 4. **Code Organization** ✅
- Logical grouping by functionality
- Reusable components
- Clear interfaces between modules

## 📦 Features Supported

✅ **Control Flow**
- if/elseif/else statements
- while loops
- for loops

✅ **Data Types**
- Integers and floats
- Strings
- Arrays (indexed and associative)
- Nested arrays

✅ **Operations**
- Arithmetic: +, -, *, /, %
- Comparison: <, >, <=, >=, ==, !=
- String concatenation: .
- Array access: $arr[key]

✅ **Functions**
- User-defined functions with parameters
- Return values
- Recursive functions
- **Complex recursive expressions** (newly fixed!)
- 58 built-in PHP functions

✅ **Built-in Functions** (Partial List)
- String: strlen, trim, substr, strpos, explode, implode
- Array: count, array_keys, array_values, array_push, array_pop
- Math: abs, round, floor, ceil, min, max
- Type checking: is_array, is_string, is_numeric
- Encoding: json_encode, json_decode, base64_encode, md5, sha1
- And many more...

## 🧪 Testing

### Run All Examples
```bash
cd examples
php ../src/phc-new.php exec 01_hello_world.php
php ../src/phc-new.php exec 02_arithmetic.php
# ... and so on
```

### Run Specific Tests
```bash
cd tests
php ../src/phc-new.php exec test_fib_simple.php        # Fibonacci (recursive)
php ../src/phc-new.php exec test_function_recursive.php # General recursion
php ../src/phc-new.php exec test_all_features.php       # Comprehensive test
```

### Test with Previously Broken Cases
These now work correctly:
```bash
php ../src/phc-new.php exec test_fib_simple.php
php ../src/phc-new.php exec test_same_func_twice.php
php ../src/phc-new.php exec test_different_funcs_with_args.php
php ../src/phc-new.php exec test_two_calls_with_args.php
```

## 🔧 Architecture Details

### Compilation Pipeline

1. **Lexing**: PHP's built-in `token_get_all()`
2. **Parsing**: Recursive descent parser for expressions
3. **Code Generation**: Three-address code style assembly
4. **Assembly**: Text assembly to binary bytecode
5. **Execution**: Stack-based virtual machine

### Memory Layout
```
0-19:   Superglobals ($_GET, $_POST, etc.)
20-99:  Reserved for future use
100+:   User variables
1000+:  Array IDs
```

### Stack Operations
The VM uses a stack-based architecture:
- Function arguments pushed right-to-left
- Return values left on stack
- Arithmetic operations pop operands, push result

### Function Calling Convention
1. Push arguments onto stack (left to right)
2. CALL jumps to function label
3. Function pops arguments into memory
4. Function executes, pushes return value
5. RET returns to caller

## 📝 Example: Fibonacci

**Input (test_fib_simple.php)**:
```php
<?php
function fib($n) {
    if ($n <= 1) {
        return 1;
    }
    return fib($n - 1) + fib($n - 2);
}
echo fib(5);
```

**Compiled Assembly** (simplified):
```
JMP main
func_fib0:
    STORE 100          ; Store $n
    LOAD 100
    PUSH 1
    LTE
    JZ else1
    PUSH 1
    RET
    JMP endif2
else1:
endif2:
    LOAD 100
    PUSH 1
    SUB
    CALL func_fib0     ; fib($n-1)
    LOAD 100
    PUSH 2
    SUB
    CALL func_fib0     ; fib($n-2)
    ADD
    RET
main:
    PUSH 5
    CALL func_fib0
    PRINT
    HALT
```

**Output**: `8` (5th Fibonacci number)

## 🎓 Learning Resources

- `tests/README.md` - Comprehensive test documentation
- `examples/` - Progressive examples from basic to advanced
- Source code comments - Detailed explanations throughout

## 🔮 Future Improvements

- [ ] Variable scoping (local vs global)
- [ ] Class and object support
- [ ] Exception handling (try/catch)
- [ ] More built-in functions
- [ ] Optimization passes
- [ ] Debugging support
- [ ] Better error messages with line numbers

## 📄 License

MIT License - Feel free to use and modify!

## 🤝 Contributing

This is a learning project. Feel free to:
- Report bugs
- Suggest features
- Submit improvements
- Add more built-in functions
- Write more tests

---

**Note**: This compiler is for educational purposes. It implements a subset of PHP and is not intended for production use.
