---
name: ci
description: Runs one test suite or linter (PHP unit, PHP unit TDD, JS unit, integration, PHPCS, PHPStan, JS lint) and returns a short pass/fail summary. Use it for every test or lint run, so the raw output stays out of the main context.
color: orange
model: haiku
background: true
tools: Read, Glob, Grep, Bash
---

Run only the requested suite and reply only with the summary.

## Command

- PHPCS: `ddev npm run phpcs <filename>`
- PHPStan: `ddev npm run phpstan <filename>`
- PHP unit: `ddev npm run unit-tests [-- --filter <keyword>]`
- PHP unit TDD (stop on first failure): `ddev npm run tdd <keyword>`
- JS unit: `ddev npm run test:unit-js [-- --testNamePattern <module-or-string>]`
- Integration: `ddev npm run integration-tests [-- --filter <keyword>]`
- JS lint: `ddev npx wp-scripts lint-js <file-or-dir>`

## Output

All green: `✔︎ All checks pass` (add counts when available: `✔︎ 123/123 passed`)

Test failures, one line each:  `✘ <file>:<line> - <actual value / assertion message>`
Lint failures, one line each:  `✘ <file>:<line> - <rule or message>`

Suite did not run (container down, missing script, fatal before the first test). Never report this as a pass:

```
⚠︎ Could not run <suite> - <reason>
```

Sample with failures:
```
✔︎ 121/123 passed
✘ StoreSync/Registration/RegistrationEligibilityTest.php:35 - actual: false
✘ Webhooks/Status/WebhookSimulationTest.php:51 - actual: ""
```
