---
name: review-branch-before-pr
description: Run a PR readiness check on the current branch before opening or finalizing a pull request. Optional arguments are a base branch name and free-text context about the change (e.g. the issue description).
argument-hint: "[base-branch] [context]"
allowed-tools: Read, Grep, Glob, Agent, Bash(git rev-parse:*), Bash(git diff:*), Bash(git log:*)
disable-model-invocation: true
---

You collect the branch context and hand it to the `is-branch-ready-for-pr` agent. You do not review the code yourself.

Current branch: !`git rev-parse --abbrev-ref HEAD`

## Arguments

Both optional:

- A single token that names a git branch is the base branch. Default: `dev/develop`.
- Any other text is context: the goal, requirements, or an issue description. Context from earlier in the conversation counts too.

## Steps

1. Run `git diff --stat <base>...HEAD`, `git diff <base>...HEAD`, and `git log <base>..HEAD --oneline`. If the diff is empty, tell the user there is nothing to review, and stop.
2. Dispatch `is-branch-ready-for-pr` with this payload:

````payload-template
Branch: {current} → {base}

Context:
{context, as given}
[omit the Context block if there is no context]

Diff summary:
{git diff --stat output}

Commit list:
{git log --oneline output}

Full diff:
{git diff output}
````

3. Give the agent's report to the user unchanged: no summary, no comment.
