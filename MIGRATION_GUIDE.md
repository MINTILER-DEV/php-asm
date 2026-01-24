# Migration Guide: Old to New Modular System

## Overview

The PHP-ASM compiler has been completely refactored into a modular architecture. This guide helps you transition from the old monolithic system to the new one.

## What Changed?

### Old Structure (Monolithic)
```
src/
├── compiler.php  (~1000 lines - everything in one class)
└── phc.php       (~800 lines - assembler + VM in one file)
```

### New Structure (Modular)
```
src/
├── Opcodes.php, BuiltinFunctions.php, MemoryLayout.php
├── Compiler/ (6 focused modules)
├── Assembler/ (clean assembler)
├── VM/ (5 VM components)
└── phc.php (unified CLI)
```

## Command Changes

### Old Commands
```bash
# Compile
php src/compiler.php input.php output.phas

# Assemble
php src/phc.php assemble output.phas output.phc

# Run
php src/phc.php run output.phc
```

### New Commands (Backward Compatible)
```bash
# Same commands work!
php src/phc.php compile input.php output.phas
php src/phc.php assemble output.phas output.phc
php src/phc.php run output.phc

# NEW: One-step execution
php src/phc.php exec input.php

# NEW: Compile + assemble
php src/phc.php build input.php
```

## API Changes (For Developers)

### Old Way
```php
require_once 'src/compiler.php';
$compiler = new PHPCompiler();
$assembly = $compiler->compile($code);
```

### New Way  
```php
require_once 'src/PHPCompiler.php';
$compiler = new PHPCompiler();
$assembly = $compiler->compile($code);  // Same interface!
```

The public API is **100% backward compatible**. The changes are all internal.

## What's Fixed?

### 1. Recursive Functions with Complex Expressions
**Before**: ❌ Broken
```php
return fib($n - 1) + fib($n - 2);  // Parser failed
```

**After**: ✅ Works!
```php
return fib($n - 1) + fib($n - 2);  // Perfect!
```

### 2. Multiple Function Calls in Expressions
**Before**: ❌ Unreliable
```php
$result = func1(a) + func2(b);  // Sometimes failed
```

**After**: ✅ Reliable
```php
$result = func1(a) + func2(b);  // Always works
```

### 3. Nested Expressions
**Before**: ❌ Limited
```php
$x = (a + b) * (c - d);  // Hit and miss
```

**After**: ✅ Full Support
```php
$x = (a + b) * (c - d);  // Always correct
```

## Benefits of the New Architecture

### For Users
- ✅ More reliable compilation
- ✅ Better error messages
- ✅ Faster development of new features
- ✅ Same command-line interface

### For Developers
- ✅ Easier to understand codebase
- ✅ Easier to add features
- ✅ Better testing capabilities
- ✅ Clear module boundaries
- ✅ No 1000-line files!

## Testing Your Code

### Quick Test
```bash
# Old compiler
php src/compiler.php test.php test.phas
php src/phc.php assemble test.phas test.phc
php src/phc.php run test.phc

# New compiler (one command!)
php src/phc-new.php exec test.php
```

### Run Test Suite
```bash
cd tests
php run_all_tests.php
```

## Troubleshooting

### Q: My old scripts don't work anymore
**A**: The new compiler is backward compatible. If you find code that worked before but doesn't now, please report it as a bug!

### Q: Can I still use the old compiler?
**A**: Yes! The old files `compiler.php` and `phc.php` are still in `src/`. They haven't been removed. But we recommend migrating to `phc-new.php` for the bug fixes.

### Q: How do I know which version I'm using?
**A**: 
- Old: `php src/compiler.php` or `php src/phc.php`
- New: `php src/phc-new.php`

### Q: Will the old files be removed?
**A**: Eventually, yes. Once the new system is proven stable, we'll rename `phc-new.php` to `phc.php` and archive the old files.

## Module Responsibilities

### Compiler Modules
- **CodeEmitter**: Generates assembly instructions
- **VariableResolver**: Maps variables to memory addresses
- **ExpressionParser**: Parses expressions with proper precedence (THE BIG FIX!)
- **ArrayCompiler**: Handles array literals
- **StatementCompiler**: Compiles if/while/for/return
- **FunctionCompiler**: Handles user-defined functions

### VM Modules
- **Stack**: Stack operations
- **Memory**: Variable and array storage
- **SyscallHandler**: Built-in function implementations
- **BytecodeLoader**: Loads .phc files
- **PHCVM**: Main execution loop

## Example: Adding a New Built-in Function

### Old Way (Scattered Across File)
```php
// In compiler.php line 45
private $builtinFunctions = [
    'myFunc' => 58,
    // ...
];

// In phc.php line 320
case 58: // myFunc
    return $this->handleMyFunc($args);
    
// In phc.php line 890
private function handleMyFunc($args) {
    // implementation
}
```

### New Way (Clean Separation)
```php
// In BuiltinFunctions.php
'myFunc' => 58,

// In VM/SyscallHandler.php
case 58: return $this->myFunc_($args);

private function myFunc_($args) {
    // implementation
}
```

Much cleaner!

## Getting Help

1. Check `MODULAR_README.md` for architecture details
2. Look at `tests/README.md` for test documentation
3. Examine the examples in `examples/`
4. Read the inline code comments

## Summary

- ✅ **Same interface**, better internals
- ✅ **Fixes critical bugs** in expression parsing
- ✅ **Easier to extend** and maintain
- ✅ **Better organized** code
- ✅ **Full backward compatibility**

**Recommendation**: Start using `phc-new.php` today!
