---
description: JS/Jest test writing conventions
paths:
  - "modules/**/*.test.js"
---

Hard rules about how to write JS unit tests. Violations waste time and money.

**Why:** Jest tests written outside the js-unit-test-writer agent do not follow the existing suite's conventions (colocation, alias imports, describe/test naming, behavior-over-plumbing assertions).

**How to apply:** Whenever JS/TS unit tests need to be written or updated, spawn the `js-unit-test-writer` agent with full context. Do not write test code inline, not even helpers or mocks.
