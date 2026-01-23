# PHP to Bytecode Compiler and Virtual Machine

A complete PHP-to-bytecode compiler and stack-based virtual machine supporting a subset of PHP.

## Overview

This project consists of three main components:

1. **PHP Compiler** (compiler.php) - Compiles PHP to PHC assembly
2. **Assembler** (phc.php) - Assembles .phas files to binary bytecode
3. **Virtual Machine** (phc.php) - Executes binary bytecode

## Supported PHP Features

### Variables and Data Types

- ✅ Variables ($var)
- ✅ Numbers (integers)
- ✅ Strings (with automatic constant embedding)
- ✅ Arrays (single-level and nested)
- ✅ Superglobals ($_GET, $_POST, $_SERVER, $_REQUEST, $_COOKIE, $_SESSION, $_FILES, $_ENV, $GLOBALS, $argc, $argv, etc.)

### Operators

- ✅ Arithmetic: +, -, *, /, %
- ✅ Comparison: <, >, <=, >=, ==, !=
- ✅ Logical: (as comparison results)
- ✅ String: . (concatenation)
- ✅ Assignment: =
- ✅ Array: [] (access and assignment)

### Control Structures

- ✅ if / elseif / else
- ✅ while loops
- ✅ for loops (basic)

### Functions

- ✅ User-defined functions
- ✅ Function parameters and return values
- ✅ 58 built-in PHP functions
- ✅ Recursion (basic cases)

### Built-in Functions (58 total)

- **String**: strlen, trim, ltrim, rtrim, strpos, substr, str_replace, strtolower, strtoupper, strrev, str_repeat, ucfirst, lcfirst, ucwords
- **Type Checking**: isset, empty, is_array, is_string, is_numeric, is_int, is_float, is_bool, is_null
- **Math**: abs, round, floor, ceil, min, max, intval, floatval
- **Array**: count, in_array, array_keys, array_values, array_push, array_pop, array_shift, array_unshift, array_merge, array_slice, array_search, array_key_exists, explode, implode
- **Encoding**: json_encode, json_decode, md5, sha1, base64_encode, base64_decode
- **Date/Time**: time, date, strtotime
- **Regex**: preg_match, preg_replace
- **Debug**: print_r, var_dump

## Architecture

### Memory Layout

- **0-19**: Superglobals
- **20-99**: Built-in functions (reserved)
- **100+**: User variables
- **1000+**: Array storage (dynamically allocated)

### Bytecode Format

```
[ConstCount] [Const1_Len] [Const1_Data...] [Const2_Len] [Const2_Data...] [Instructions...]
```

### 31 Opcodes

- **Stack Operations**: PUSH, POP
- **Arithmetic**: ADD, SUB, MUL, DIV, MOD
- **Comparisons**: LT, GT, LTE, GTE, EQ, NEQ
- **Logic**: CMP
- **Control Flow**: JMP, JZ, JNZ, CALL, RET
- **Memory**: LOAD, STORE, GLOAD, GSTORE
- **Arrays**: AGET, ASET, NEWARR
- **Strings**: PUSHC, CONCAT
- **Built-ins**: SYSCALL
- **I/O**: PRINT, HALT

## Usage

### Compile PHP to Assembly

```bash
php src/compiler.php input.php output.phas
```

### Assemble to Bytecode

```bash
php src/phc.php assemble output.phas output.phc
```

### Run Bytecode

```bash
php src/phc.php run output.phc
```

### One-line Complete Build

```bash
php src/compiler.php test.php test.phas && \
php src/phc.php assemble test.phas test.phc && \
php src/phc.php run test.phc
```

## Examples

See the `examples/` directory for 10 complete example programs demonstrating all compiler features.

## Tests

See the `tests/` directory for comprehensive user-defined function tests and examples.

## Project Structure

```
php-asm/
├── src/
│   ├── compiler.php      # PHP to PHC assembly compiler
│   └── phc.php          # Assembler and VM
├── examples/            # 10 example PHP programs
│   ├── compiled/        # Generated .phas assembly files
│   └── assembled/       # Generated .phc bytecode files
├── tests/               # User-defined function test suite
└── README.md           # This file
```

## Compiler Implementation Details

### PHP Tokenization

Uses PHP's built-in `token_get_all()` for proper tokenization.

### Parsing Strategy

- **Two-pass for functions**: First pass collects function definitions, second pass compiles
- **Expression parsing**: Recursive descent parser with operator precedence
- **Control flow**: Nested label generation for jumps

### Assembly Generation

Stack-based: all operations push/pop from evaluation stack.

## Features and Limitations

### Working Well

- Basic PHP statements and expressions
- Function definitions and calls
- Arrays and nested arrays
- Built-in functions
- Control structures (if/else, loops)
- String operations
- Arithmetic operations

### Known Issues

- Recursive function calls with complex argument expressions may not compile correctly
- No support for classes, namespaces, or advanced OOP features
- No exception handling (try/catch)
- Limited string literal support (only double-quoted strings with basic escaping)
- No support for references or closures

## Technical Notes

1. **Function Organization**: Functions are compiled first, with an initial JMP to skip them. Main code executes, functions called as needed via CALL.

2. **Parameter Passing**: Parameters are pushed to stack before CALL, then popped and stored in memory at function entry.

3. **Return Values**: Return expression evaluated on stack, RET instruction jumps back to caller.

4. **Constant Embedding**: String constants are extracted during assembly and embedded in bytecode with index references.

5. **Array Implementation**: Arrays stored in separate hash table (ID 1000+), separate from main memory.

## See Also

- [Test Suite Documentation](tests/README.md)
- [Examples](examples/)

## License

Educational project - free to use and modify.

## Example Programs

See `example.phas` for a demonstration that:

- Performs arithmetic calculations
- Uses loops with labels
- Stores values in memory
- Prints results

## File Extensions

- `.phas` - PHC Assembly Source (human-readable assembly code)
- `.phc` - PHC Compiled bytecode (binary executable format)
