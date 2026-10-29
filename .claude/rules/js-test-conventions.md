---
description: JS/Jest test writing conventions
paths:
  - "modules/**/*.test.js"
---

Write and update JS tests only through the `write-js-unit-test` agent, and give it full context. Do not write test code yourself, not even helpers or mocks: tests written outside the agent miss the suite's conventions. The agent does not run its tests. Show the test list from its report to the user unchanged.
