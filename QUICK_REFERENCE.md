# Quick Reference Card

## 🚀 Common Commands

### Compile & Run (One Step)

```bash
php src/phc-new.php exec test.php
```

### Individual Steps

```bash
# Compile to assembly
php src/phc-new.php compile test.php test.phas

# Assemble to bytecode
php src/phc-new.php assemble test.phas test.phc

# Run bytecode
php src/phc-new.php run test.phc
```

### Build (Compile + Assemble)

```bash
php src/phc-new.php build test.php
```

### With Verbose Output

```bash
php src/phc-new.php exec test.php --verbose
```

## 🧪 Testing Commands

### Run All Tests

```bash
cd tests
php run_all_tests.php
```

### Validate One Test

```bash
php validate_test.php test_name.php
```

### Create New Test

```bash
php add_test.php feature_name "Description"
```

### Manual Test

```bash
# Run with native PHP
php test_name.php

# Run with PHC
php ../src/phc-new.php exec test_name.php
```

## 📁 File Locations

### Source Files

- **Compiler**: `src/Compiler/*.php`
- **Assembler**: `src/Assembler/*.php`
- **VM**: `src/VM/*.php`
- **Main CLI**: `src/phc-new.php`

### Examples

- **Examples**: `examples/*.php`
- **Compiled**: `examples/compiled/*.phas`
- **Assembled**: `examples/assembled/*.phc`

### Tests

- **Test Files**: `tests/test_*.php`
- **Test Helpers**: `tests/*.php` (run_all, validate, add)

### Documentation

- **Architecture**: `MODULAR_README.md`
- **Migration**: `MIGRATION_GUIDE.md`
- **Development**: `DEVELOPER_GUIDE.md`
- **Testing**: `TESTING_GUIDE.md`
- **Changelog**: `CHANGELOG.md`

## 🔧 Development Commands

### Check Syntax

```bash
D:\Applications\PHP\current\php.exe -l file.php
```

### View Assembly

```bash
cat file.phas
# or
type file.phas
```

### View Bytecode (Hex)

```bash
hexdump -C file.phc
# or on Windows
certutil -encodehex file.phc output.txt
```

### Debug Compilation

```bash
# Step by step with inspection
php src/phc-new.php compile test.php test.phas
cat test.phas
php src/phc-new.php assemble test.phas test.phc
php src/phc-new.php run test.phc --verbose
```

## 🎯 Quick Examples

### Hello World

```php
<?php
echo "Hello, World!";
```

### Function

```php
<?php
function greet($name) {
    return "Hello, " . $name;
}
echo greet("World");
```

### Recursion

```php
<?php
function fib($n) {
    if ($n <= 1) return 1;
    return fib($n - 1) + fib($n - 2);
}
echo fib(5);
```

### Array

```php
<?php
$arr = ['a' => 1, 'b' => 2];
echo $arr['a'] + $arr['b'];
```

### Loop

```php
<?php
for ($i = 0; $i < 5; $i++) {
    echo $i . "\n";
}
```

## 🐛 Troubleshooting

### No Output

```bash
# Check compilation
php src/phc-new.php compile test.php test.phas
cat test.phas

# Check for errors
php src/phc-new.php exec test.php --verbose 2>&1
```

### Wrong Output

```bash
# Compare with PHP
php test.php
php src/phc-new.php exec test.php

# Validate test
cd tests
php validate_test.php test.php
```

### Compilation Error

```bash
# View assembly to see what was generated
php src/phc-new.php compile test.php test.phas
cat test.phas
```

## 📚 Documentation Quick Links

| Topic | File |
|-------|------|
| Architecture Overview | `MODULAR_README.md` |
| How to Migrate | `MIGRATION_GUIDE.md` |
| How to Contribute | `DEVELOPER_GUIDE.md` |
| How to Test | `TESTING_GUIDE.md` |
| What Changed | `REFACTORING_SUMMARY.md` |
| Testing Tools | `TESTING_SYSTEM.md` |
| Version History | `CHANGELOG.md` |
| Project Structure | `PROJECT_STRUCTURE.txt` |

## 🎓 Learning Path

1. Read `MODULAR_README.md` for architecture
2. Try examples in `examples/`
3. Create a test with `add_test.php`
4. Read `DEVELOPER_GUIDE.md` to contribute

## 💡 Tips

- Use `--verbose` for detailed output
- Check assembly (`.phas`) when debugging
- Run all tests before committing
- Keep tests small and focused
- Document new features

## 🆘 Getting Help

1. Check documentation in project root
2. Look at examples in `examples/`
3. Check test files in `tests/`
4. Read inline code comments

---

**Keep this card handy for quick reference!** 📋
