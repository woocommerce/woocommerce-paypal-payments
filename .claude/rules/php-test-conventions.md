---
description: PHP test writing conventions
paths:
  - "tests/PHPUnit/**/*.php"
---

Hard rules about how to write tests. Violations waste time and money.

**Why:** Tests written outside the `write-php-unit-test` agent do not cover all conventions, documentation requirements and edge cases.

**How to apply:** Whenever tests need to be written or updated, spawn the `write-php-unit-test` agent with full context. Do not write test code inline, not even helpers or stubs. That agent never runs what it writes: verify its output with the `ci` agent.
