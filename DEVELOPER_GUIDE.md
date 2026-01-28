# Developer Quick Start Guide

## 🎯 Want to Contribute?

This guide gets you started modifying and extending the PHP-ASM compiler.

## 📖 Understanding the Architecture

### The Compilation Pipeline

```
PHP Code → Compiler → Assembly → Assembler → Bytecode → VM → Output
```

1. **Compiler**: Transforms PHP → Assembly (`.phas`)
2. **Assembler**: Transforms Assembly → Bytecode (`.phc`)
3. **VM**: Executes Bytecode

### Key Modules

#### Compiler (`src/Compiler/`)

- **ExpressionParser.php** - Parses expressions with proper precedence
- **StatementCompiler.php** - Compiles statements (if, while, for, etc.)
- **FunctionCompiler.php** - Handles user-defined functions
- **ArrayCompiler.php** - Compiles array literals
- **CodeEmitter.php** - Generates assembly code
- **VariableResolver.php** - Maps variables to memory

#### VM (`src/VM/`)

- **PHCVM.php** - Main execution loop
- **Stack.php** - Stack operations
- **Memory.php** - Memory and array management
- **SyscallHandler.php** - Built-in function implementations
- **BytecodeLoader.php** - Loads `.phc` files

#### Shared (`src/`)

- **Opcodes.php** - Opcode definitions
- **BuiltinFunctions.php** - Built-in function registry
- **MemoryLayout.php** - Memory layout configuration

## 🛠️ Common Tasks

### Adding a New Opcode

1. **Define the opcode** (`Opcodes.php`):

```php
class Opcodes {
    const MY_OP = 0x20;  // Pick next available number
}
```

1. **Update the map** (`Opcodes.php`):

```php
public static function getOpcodeMap() {
    return [
        // ...
        'MY_OP' => self::MY_OP,
    ];
}
```

1. **Emit it from compiler** (`Compiler/ExpressionParser.php` or `StatementCompiler.php`):

```php
$this->emitter->emit('MY_OP', $operand);
```

1. **Handle it in VM** (`VM/PHCVM.php`):

```php
case Opcodes::MY_OP:
    $operand = $this->bytecode[$this->ip++];
    // Do something
    break;
```

### Adding a Built-in Function

1. **Register the function** (`BuiltinFunctions.php`):

```php
private static $functions = [
    // ...
    'my_func' => 59,  // Next available ID
];
```

1. **Implement it** (`VM/SyscallHandler.php`):

```php
public function call($syscallId, $args) {
    switch ($syscallId) {
        // ...
        case 59: return $this->my_func_($args);
    }
}

private function my_func_($args) {
    // Implementation
    $arg1 = $args[0] ?? null;
    $result = // ... do something
    return $result;
}
```

1. **Test it**:

```php
<?php
echo my_func(123);
```

### Adding a New Statement Type

1. **Add to StatementCompiler** (`Compiler/StatementCompiler.php`):

```php
public function parseStatements($code) {
    // ...
    switch ($token[0]) {
        case T_MY_STATEMENT:
            $i = $this->compileMyStatement($tokens, $i);
            break;
    }
}

private function compileMyStatement($tokens, $start) {
    // Parse the statement
    // Emit assembly
    return $newPosition;
}
```

### Improving Expression Parsing

The expression parser (`Compiler/ExpressionParser.php`) uses recursive descent:

```
parseComparison()           # Lowest precedence
    → parseAddSub()
        → parseConcat()
            → parseMulDiv()
                → parsePrimary()  # Highest precedence
```

To add a new operator:

1. **Find the right precedence level**
2. **Add to the appropriate parse method**:

```php
private function parseAddSub($tokens, $start, $end) {
    $this->parseConcat($tokens, $start, $end);
    
    for ($i = $start; $i < $end; $i++) {
        if ($tokens[$i] === 'MY_OP') {
            $this->parseConcat($tokens, $i + 1, $end);
            $this->emitter->emit('MY_OPCODE');
            return;
        }
    }
}
```

## 🧪 Testing Your Changes

### Quick Test

```bash
# Create a test file
echo '<?php echo 1 + 2;' > test.php

# Compile and run
php src/phc-new.php exec test.php
```

### Run Existing Tests

```bash
cd tests
php run_all_tests.php
```

### Add a New Test

```bash
# 1. Create test file
echo '<?php /* your test */' > tests/test_my_feature.php

# 2. Run it
php src/phc-new.php exec tests/test_my_feature.php

# 3. Add to test suite (optional)
# Edit tests/run_all_tests.php
```

## 🐛 Debugging

### Enable Verbose Mode

```bash
php src/phc-new.php exec test.php --verbose
```

### Inspect Assembly

```bash
# Compile to assembly
php src/phc-new.php compile test.php test.phas

# View the assembly
cat test.phas
```

### Inspect Bytecode

```bash
# Assemble to bytecode
php src/phc-new.php assemble test.phas test.phc

# View bytes (hex dump)
hexdump -C test.phc
```

### Add Debug Output

```php
// In any module
echo "DEBUG: Variable = " . var_export($variable, true) . "\n";
```

## 📝 Code Style

### Naming Conventions

- Classes: `PascalCase`
- Methods: `camelCase`
- Private methods: `camelCase` or `camelCase_` (for syscalls)
- Constants: `UPPER_CASE`
- Variables: `camelCase` or `$snake_case`

### Documentation

- Add docblocks to classes
- Comment complex logic
- Explain "why", not "what"

### File Organization

- One class per file
- Filename matches class name
- Group related functionality

## 🎓 Learning Path

### Beginner

1. Read `MODULAR_README.md`
2. Run examples in `examples/`
3. Trace execution with verbose mode
4. Add a simple built-in function

### Intermediate

1. Study `ExpressionParser.php`
2. Add a new statement type
3. Add a new opcode
4. Write comprehensive tests

### Advanced

1. Optimize bytecode generation
2. Add variable scoping
3. Implement classes/objects
4. Add debugging support

## 🔍 Common Pitfalls

### 1. Forgetting to Update Assembly in phc-new.php

**Problem**: You use `PHPAssembler` instead of `PHCAssembler`

```php
// Wrong
$assembler = new PHPAssembler();

// Right
$assembler = new PHCAssembler();
```

### 2. Not Handling Array IDs

**Problem**: Confusing array IDs (≥1000) with memory addresses (<1000)

```php
// Check first
if ($this->memory->isArray($value)) {
    $array = $this->memory->getArray($value);
}
```

### 3. Incorrect Operator Precedence

**Problem**: Adding operators at wrong precedence level
**Solution**: Follow math rules: `*/%` before `+-`, `+-` before comparisons

### 4. Forgetting to Pop Arguments

**Problem**: Stack grows unbounded

```php
// After using values, pop them
$b = $this->stack->pop();
$a = $this->stack->pop();
$this->stack->push($a + $b);
```

## 🚀 Quick Reference

### Emit Assembly

```php
$this->emitter->emit('PUSH', 42);
$this->emitter->emit('ADD');
$this->emitter->emitLabel('loop');
```

### VM Stack Operations

```php
$this->stack->push($value);
$value = $this->stack->pop();
```

### Memory Operations

```php
$this->memory->store($address, $value);
$value = $this->memory->load($address);
```

### Array Operations

```php
$arrayId = $this->memory->createArray();
$this->memory->arraySet($arrayId, $key, $value);
$value = $this->memory->arrayGet($arrayId, $key);
```

## 💬 Getting Help

1. **Read the code** - It's well-commented!
2. **Check examples** - See how features are used
3. **Run tests** - Learn from existing tests
4. **Experiment** - Try things and see what happens

## 🎯 Project Ideas

### Easy

- Add more built-in string functions
- Add more built-in array functions
- Improve error messages
- Add more examples

### Medium

- Add support for `switch` statements
- Add support for `do-while` loops
- Add support for ternary operator `? :`
- Add variable scoping

### Hard

- Add class/object support
- Add exception handling
- Add optimization passes
- Add a debugger

## 📚 Resources

- **PHP Manual**: <https://www.php.net/manual/>
- **Compiler Theory**: Dragon Book (classic reference)
- **VM Design**: Crafting Interpreters (free online)
- **Project Docs**: Start with `MODULAR_README.md`

---

**Happy hacking! The modular architecture makes adding features a breeze.** 🚀
