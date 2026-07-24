---
name: php-review
description: Manually run a PHP code-cleanliness review on a file, at higher quality than the automatic background hook. Optional first argument is FULL or CHANGES; the last argument is the PHP file path.
argument-hint: "[full|changes] <file>"
invocation: user
---

You dispatch the `php-review` sub-agent to review one PHP file. You do NOT perform the
review yourself - your job is to parse the arguments and hand off, forcing the stronger
model because this is a deliberate, manual review (the automatic hook runs the same agent
on its cheaper default).

## Argument Parsing

Arguments after `/php-review`:

- `FULL` or `CHANGES` (case-insensitive) selects the scope. Optional; defaults to `CHANGES`.
  - `CHANGES` - review only the git-diff hunks (same scope the background hook uses).
  - `FULL` - review the entire file.
- The remaining argument is the PHP file path.

If no file path is given, stop and ask the user which file to review.

## Dispatch

Invoke the `php-review` sub-agent via the Task tool with a **`sonnet` model override**
(the manual path always runs at the higher-quality model). Pass exactly this as the prompt,
and nothing else - the agent owns all review criteria:

- `CHANGES` scope: `review CHANGES in <file>`
- `FULL` scope: `review FULL <file>`

## Return Report

Pass the sub-agent's report back to the user verbatim.
Do not summarize, rewrite, or comment on it.
