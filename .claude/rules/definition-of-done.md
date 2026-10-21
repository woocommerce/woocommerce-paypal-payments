---
description: When a task is complete
---

# Definition of done

A task is complete when both are true:

1. New or changed behavior has test coverage. Write the tests through the `write-php-unit-test` and `write-js-unit-test` agents.
2. The relevant tests and lint passed. The developer runs them, or asks you to run them through the `ci` agent. Do not run them unasked; name the suites that must run instead:
   - PHP change: PHP unit tests, PHPCS, PHPStan.
   - JS change: JS unit tests, `lint-js` on the changed files.
   - Checkout, payment, onboarding, or webhook change: also the integration tests.

Integration tests write to the DDEV site database and can delete settings (merchant connection, reCAPTCHA keys, tax settings). Before they run, say so, so the developer can take a `ddev snapshot`.
