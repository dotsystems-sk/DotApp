# Guide to Creating Tests for DotApp Framework Modules

This guide provides simple steps for creating tests for your modules in the DotApp Framework (version 1.7 FREE) using the `Tester` class. It is designed for module developers familiar with the framework’s modular structure, showing how to write tests in `app/modules/MODULE_NAME/tests/` using the `Dotsystems\App\Modules\MODULE_NAME\tests` namespace. Tests are run using the built-in `dotapper.php` CLI tool.

## Table of Contents

1. [Introduction](#introduction)
2. [Creating Tests](#creating-tests)
   - [Basic Test](#basic-test)
   - [Test Result Format](#test-result-format)
3. [Organizing Tests](#organizing-tests)
4. [Running Tests with `dotapper.php`](#running-tests-with-dotapperphp)
5. [Tips and Best Practices](#tips-and-best-practices)
6. [Troubleshooting](#troubleshooting)

## Introduction

The `Tester` class allows you to write tests for your DotApp Framework modules. Tests are registered using `Tester::addTest` and placed in your module’s `tests/` directory. The framework’s autoloader handles dependencies, requiring only `use Dotsystems\App\Parts\Tester;` in test files. This guide shows how to create a simple test for a module named `MODULE_NAME` (e.g., `Blog`, `Shop`) and run it using `dotapper.php`.

## Creating Tests

### Basic Test

Tests are written as PHP files in `app/modules/MODULE_NAME/tests/` using the `Dotsystems\App\Modules\MODULE_NAME\tests` namespace. Each test is a callback function registered with `Tester::addTest`.

Example of a basic test (`app/modules/MODULE_NAME/tests/ExampleTest.php`):

```php
<?php
namespace Dotsystems\App\Modules\MODULE_NAME\tests;

use Dotsystems\App\Parts\Tester;

Tester::addTest('Example test', function () {
    $result = 2 + 2 === 4;
    return [
        'status' => $result ? 1 : 0,
        'info' => $result ? '2 + 2 equals 4' : '2 + 2 does not equal 4',
        'test_name' => 'Example test',
        'context' => ['module' => 'MODULE_NAME', 'method' => 'addition', 'test_type' => 'unit']
    ];
});
?>
```

### Test Result Format

The callback function must return an array with:

- **`status`** (int): Test status:
  - `1`: Passed (OK).
  - `0`: Failed (NOT OK).
  - `2`: Skipped (SKIPPED).
- **`info`** (string): Description of the result (e.g., why the test failed).
- **`test_name`** (string): Test name (usually matches `addTest` name).
- **`context`** (array, optional): Metadata (e.g., module, method, test type).

## Organizing Tests

- Place all tests in `app/modules/MODULE_NAME/tests/`, where `MODULE_NAME` is your module’s name (e.g., `Blog`, `Shop`).
- Use descriptive file names, e.g., `ExampleTest.php`, `OrderTest.php`.
- Use the namespace `Dotsystems\App\Modules\MODULE_NAME\tests` for all test files.

## Running Tests with `dotapper.php`

Tests are executed using the built-in `dotapper.php` CLI tool from the project’s root directory. Supported commands:

- **Run all tests (core + all modules)**:
  ```bash
  php dotapper.php --test
  ```

- **Run all module tests (no core tests)**:
  ```bash
  php dotapper.php --test-modules
  ```

- **Run tests for a specific module**:
  ```bash
  php dotapper.php --module=MODULE_NAME --test
  ```

The output includes for each test:
- **Test Name** (`test_name`).
- **Status** (`OK`, `NOT OK`, `SKIPPED`).
- **Description** (`info`).
- **Duration** (in seconds).
- **Memory Usage** (`memory_delta` in KB).
- **Context** (JSON-encoded array).

Example output:

```
Test: Example test
Status: OK
Info: 2 + 2 equals 4
Duration: 0.000123s
Memory Delta: 256.50 KB
Context: {"module":"MODULE_NAME","method":"addition","test_type":"unit"}
----------------------------------------
Summary: 1/1 tests passed (0 skipped, 0 failed)
```

## Tips and Best Practices

1. **Use Descriptive Test Names**:
   Names like `Example test` or `Order processes payment` make it easier to identify issues.

2. **Include Context**:
   Add metadata in the `context` array, such as module name, tested method, or test type (e.g., `unit`, `integration`).

3. **Test Edge Cases**:
   Test normal scenarios and error conditions when expanding beyond simple tests.

4. **Optimize Test Execution**:
   Run specific module tests with `--module=MODULE_NAME --test` to save time.

5. **Integrate with CI/CD**:
   Add `dotapper.php` commands to your CI/CD pipeline (e.g., GitHub Actions) for automated testing.

6. **Log Results**:
   Configure `dotapper.php` to save results to a file (e.g., `app/runtime/logs/tests.log`) for analysis.

## Troubleshooting

- **Tests Not Loading**:
  - Ensure test files are in `app/modules/MODULE_NAME/tests/`.
  - Verify the namespace is `Dotsystems\App\Modules\MODULE_NAME\tests`.
  - Check that the module name in `--module=MODULE_NAME` matches exactly.

- **Exceptions in Tests**:
  - Check the `info` field in the test output for the exception message.
  - Ensure the callback function returns the correct result format.

- **High Memory Usage**:
  - Use `gc_collect_cycles()` within tests to free memory if needed.

---

**Author**: Štefan Miščík  
**Company**: Dotsystems s.r.o.  
**License**: MIT License  
**Version**: 1.7 FREE  
**Date**: 2014 - 2025