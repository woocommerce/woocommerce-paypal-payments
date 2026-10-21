---
description: PHP test writing conventions
paths:
  - "tests/PHPUnit/**/*.php"
  - "tests/integration/**/*.php"
---

Write and update PHP tests only through the `write-php-unit-test` agent, and give it full context. Do not write test code yourself, not even helpers or stubs: tests written outside the agent miss its conventions and edge cases. The agent does not run its tests. Show the test list from its report to the user unchanged.
