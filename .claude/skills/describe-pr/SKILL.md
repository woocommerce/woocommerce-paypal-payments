---
name: describe-pr
description: Draft a standardized, reviewer-focused pull request description and - only after explicit approval - update the PR. Optional first argument is a PR number (defaults to the current branch's open PR). Optional argument is a Jira issue ID (e.g. ABC-123).
argument-hint: "[PR-number] [JIRA-ID]"
invocation: user
---

You help the user write a helpful, standardized PR description and, **only after their final and explicit approval**, update the pull request via `gh pr edit`.

The description exists to **introduce a reviewer to the problem and the reasoning behind the change** - not to summarize the diff. How something was done is already obvious in the code.

## Argument Parsing

Arguments after `/describe-pr` (both optional, order does not matter):
- A bare integer is the **PR number**.
- An identifier matching `[A-Z]+-\d+` is a **Jira issue ID**.

## Steps

### 1. Resolve the target PR

If a PR number was passed, use it. Otherwise, resolve the current branch's open PR:

```bash
gh pr view --json number,title,body,url,headRefName,baseRefName,state
```

If no PR is found for the current branch and none was given, stop and tell the user - offer to proceed once they pass a PR number or push a branch with an open PR.

### 2. Gather source material

Collect context to ground the draft. Pull from all that apply:

- **The current conversation** - if this session already investigated the bug or built the fix, that understanding is often the richest source of "the problem" and "why it happens." Use it.
- **Existing PR body** - `gh pr view <n> --json body,title,baseRefName`. Treat it as a starting point to refine, not something to blindly discard.
- **Optional Jira** - if a Jira ID was given, or one is embedded in the branch name (`[A-Z]+-\d+`), fetch it via the connected Atlassian MCP for problem framing. If the fetch fails, note it in one line and continue.
- **Diff + commits** - `git diff <base>...HEAD` and `git log <base>..HEAD --oneline` (use the PR's `baseRefName`). This is the substance you reason *about* - do not transcribe it into the description.

### 3. Draft the description

Follow [guideline.md](guideline.md) for the heading catalog, tone, writing style, and a worked example. Read it before drafting.

### 4. Present the draft and iterate

Show the full draft to the user as a fenced markdown block. Invite edits. Iterate until they are satisfied.

**Do not call `gh pr edit` yet.** Drafting and updating are separate steps.

### 5. Update the PR - only after explicit approval

Wait for the user's clear, explicit go-ahead (e.g. "approved", "update it", "yes, push it"). Silence, "looks good", or answering an unrelated question is **not** approval to write - if in doubt, ask.

Once approved, write the body to a temp file and update the PR (avoids shell-escaping issues with multi-line markdown):

```bash
gh pr edit <number> --body-file <path>
```

Then confirm by re-fetching and report the PR URL back to the user:

```bash
gh pr view <number> --json body,url
```
