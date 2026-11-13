# Mutation tests

[Infection](https://infection.github.io/) changes small parts of the source code (mutants) and runs the PHP unit tests against each change. When no test fails, the mutant "escapes": the tests do not check that code.

Infection runs the unit tests in `tests/PHPUnit`. It has no tests of its own.

### Set up the test environment

- DDEV must run PHP 8.2 or later. Infection needs PHP 8.2, but the plugin still supports PHP 7.4.
- Infection has its own `composer.json` in this folder, apart from the plugin dependencies. The `mutate` script installs it on the first run.

### How to run the tests

- Run one file or folder: `ddev npm run mutate -- --filter=modules/ppcp-store-sync/src`
- Run on all source code: `ddev npm run mutate` (slow)

The script turns Xdebug on for the coverage run and off again at the end.

### Results

- HTML report: https://woocommerce-paypal-payments.ddev.site/infection/
- Text logs: `infection/infection.log` and `infection/summary.log` in the project root

Not every escaped mutant is a gap in the tests. Some changes have no effect that a test can see, for example a removed `(string)` cast. Check each escaped mutant before you add a test for it.

### Brain Monkey and Patchwork

Infection loads a mutant through a file stream wrapper that replaces the original file. Patchwork, which Brain Monkey uses, reads the source code past that wrapper, so it would compile the original file. During Infection runs, `tests/PHPUnit/bootstrap.php` gives the mutant code to Patchwork instead. Normal test runs do not use this code.

### Troubleshooting

Infection first runs the full unit test suite with coverage, in random order. It stops when a test fails, or when any test writes to STDERR. Typical causes:

- A test passes alone but fails in random order: it depends on a function or state that another test sets up.
- A test fails only in slow runs: a data provider computes a value from the current time. PHPUnit runs all data providers before the first test.
- The run stops after an error message, but no test fails: a test or the code under test writes to STDERR, for example through `error_log()` or a PHP deprecation notice.
- `Class "..." not found` before the first mutant: a source class extends a WordPress or WooCommerce class that has no stub in `tests/stubs`.

To repeat the test order of a failed run, pass the seed from its output: `ddev npm run mutate -- --test-framework-options="--random-order-seed=<seed>"`
