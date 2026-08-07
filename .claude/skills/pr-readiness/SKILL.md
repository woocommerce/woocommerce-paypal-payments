---
name: pr-readiness
description: Run a PR readiness check on the current branch before opening or finalizing a pull request. Optional first argument is a Jira issue ID (e.g. ABC-123). Optional second argument is a base branch name.
argument-hint: "[JIRA-ID] [base-branch]"
invocation: user
---

You dispatch the `pr-readiness` sub-agent to review the current branch. You do NOT perform the review yourself — your job is to gather context and hand it off.

## Argument Parsing

Arguments after `/pr-readiness`:
- An identifier matching `[A-Z]+-\d+` is a Jira issue ID.
- Any other argument is treated as the base branch name.

Both are optional. Order does not matter.

## Steps

### 1. Resolve Base Branch

If a base branch was passed as an argument, use it.

Otherwise, run `git symbolic-ref refs/remotes/origin/HEAD` and strip the `refs/remotes/origin/` prefix. Fall back to `main` if that fails.

### 2. Gather Branch Context

Run and capture output from:

- `git rev-parse --abbrev-ref HEAD` — current branch
- `git diff --stat <base>...HEAD` — file/line summary
- `git diff <base>...HEAD` — full diff
- `git log <base>..HEAD --oneline` — commit list

If the diff is empty, stop and tell the user there is nothing to review.

### 3. Fetch Jira Context

Skip this step entirely if no Jira ID was provided.

Use the connected Atlassian/Jira MCP to fetch the issue. Capture: key, title, description, issue type.

If the fetch fails, mention it in a single line and continue without Jira context — do NOT abort.

### 4. Dispatch Agent

Invoke the `pr-readiness` sub-agent via the Task tool. Pass a single structured payload following this template:

````payload-template
Branch: {current} → {base}

Jira: {key} — {title}
{description}
[omit the entire Jira block if no issue context was fetched]

Diff summary:
{git diff --stat output}

Commit list:
{git log --oneline output}

Full diff:
{git diff output}
````

### 5. Return Report

Pass the sub-agent's report back to the user verbatim.
Do not summarize, rewrite, or comment on it.
The developer needs the full report to act on.
