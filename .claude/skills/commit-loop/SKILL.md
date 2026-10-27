---
name: commit-loop
description: Turn the uncommitted changes into small, isolated commits, one at a time. Stage one unit, suggest a commit message, then wait while the user reviews and commits by hand. Use when the user wants to wrap up a session, split work into commits, or says "stage the next unit".
disable-model-invocation: true
---

The user owns each commit. You prepare it; the user reviews, lints, and commits.

## Plan

1. Read all uncommitted changes (`git status`, `git diff`, untracked files).
2. Split them into units. A unit is small, logical, and isolated: it has one purpose and the reviewer can understand it alone. Many small commits are better than few large ones.
3. Order the units so that each commit builds on the commits before it. Put a dependency (a helper, a stub, a new class) before its first consumer.
4. Keep code and its tests in separate units, the test unit directly after its code unit, unless the project history shows a different pattern.

Do not show the full plan unless the user asks for it. Tell the user the number of units, then give a one-line preview of the next units.

## Each iteration

1. Run `git log -1` and `git diff --cached --stat`. Make sure that the previous unit is committed and that nothing is staged. If the commit is not there (for example, a pre-commit hook still runs), stop and tell the user. Do not stage more.
2. Stage one unit only. If a file contains changes for more than one unit, stage only the related hunks. Leave the rest unstaged. `git add -p` is interactive and does not work here: write the hunks to a patch file and run `git apply --cached <patch>`.
3. Report:
   - The staged files with their `+/-` counts.
   - The commit message in a code block. Follow the commit-message convention of the project. Write a subject line only, no body, 50 characters or fewer.
   - Two to five bullets: what the unit does.
   - A note only when it is necessary, for example: a test fails at this commit until the next unit, or the unit changes a public contract.
4. Stop and wait.

## User replies

- **"Committed, next"**: start the next iteration.
- **The user changed a staged file**: read the change and give your opinion. Do not overwrite or revert it. If you see a problem, explain it and let the user decide.
- **The user asks a question about the staged code**: answer it. If the answer leads to a change, unstage, make the change, and stage the unit again with a new report.
- **The user amended a commit**: check the result with `git log` and `git show --stat`. Tell the user about unwanted side effects, for example a changed subject line or a file in the wrong commit.
- **A lint or test failure**: fix only the cause. Tell the user which commit the fix belongs to (the staged unit, or an amend to a previous commit).

## Rules

- Do not commit, amend, rebase, or push. The user does this.
- Do not stage changes that are not part of the current unit.
- Do not run lint, tests, or builds unless the user tells you to. The user does the verification.
- Do not start code-review agents, also when a hook asks for one. The code is written, and the manual review of the user is the review.
- Do not change code that is not necessary for the current unit.
- When all units are committed, tell the user and run `git status` to show that the tree is clean.
