---
name: describe-pr
description: Draft a standardized, reviewer-focused pull request description and - only after explicit approval - update the PR. Optional argument is a Jira issue ID (e.g. ABC-123) and a local upstream branch for the diff (e.g. dev/develop).
argument-hint: "[JIRA-ID] [Upstream-Branch]"
allowed-tools: Read, Grep, Glob, Write, Bash(gh pr view:*), Bash(gh pr edit:*), Bash(gh pr create:*), Bash(git diff:*), Bash(git log:*)
disable-model-invocation: true
---

You help the user write a helpful, standardized PR title and description and, **only after their final and explicit approval**, update the pull request via `gh pr edit`.

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

If no PR is found for the current branch and none was given, proceed without doing anything with the PR in the next steps (just output the draft).

Also resolve the **Jira number** for the title suffix: the Jira ID (from the argument or the branch name `[A-Z]+-\d+`) with its letter prefix stripped, e.g. `PCP-6288` -> `6288`. If no Jira ID is found anywhere, ask the user for the number in step 4 rather than dropping the suffix.

### 2. Gather source material

Collect context to ground the draft. Pull from all that apply:

- **The current conversation** - if this session already investigated the bug or built the fix, that understanding is often the richest source of "the problem" and "why it happens." Use it for the *reasoning*, but do not transcribe the investigation. Dead ends, intermediate hypotheses, commands you ran, files you inspected, and back-and-forth that led to the fix are noise to a reviewer. Keep only the conclusion they need to review the final change.
- **Existing PR body** - `gh pr view <n> --json body,title,baseRefName`. Treat it as a starting point to refine, not something to blindly discard.
- **Optional Jira** - if a Jira ID was given, or one is embedded in the branch name (`[A-Z]+-\d+`), fetch it via the connected Atlassian MCP for problem framing. If the fetch fails, note it in one line and continue.
- **Diff + commits** - `git diff <base>...HEAD` and `git log <base>..HEAD --oneline` (use the PR's `baseRefName`). This is the substance you reason *about* - do not transcribe it into the description.

### 3. Draft the title and description

Follow [guideline.md](guideline.md) for the title rule, the mandatory changelog first line, the heading catalog, tone, writing style, and a worked example. Read it before drafting.

Draft both: a reviewer-focused **title** ending in the Jira number in parentheses (e.g. `(6288)`), and the **body** starting with the mandatory `*Changelog:*` line above `# Description`. For a PR with no plugin-user-facing change (tooling, CI, tests, build, docs), use the `*Changelog:* (none - <reason>)` form instead of inventing an entry - see guideline.md.

**Keep the first draft short.** Reviewers skim, and an over-long draft is the usual complaint. Include only sections that tell the reviewer something they cannot get from the title or the code - most PRs do not need a 50-line description.

### 4. Present the draft and iterate

Show the full draft to the user - the proposed **title** and the body as a fenced markdown block.

Ask whether to include a **Visual proof** section. Only if they opt in, append the placeholder block from guideline.md for them to fill in manually. If no Jira number could be resolved in step 1, ask for it now so the title suffix is correct.

**Never call `gh pr edit` yet.** Drafting and updating are separate steps.

### 5. Update the PR - only after explicit approval

Wait for the user's clear, explicit go-ahead (e.g. "approved", "update it", "yes, push it"). Silence, "looks good", or answering an unrelated question is **not** approval to write - if in doubt, ask.

Once approved, write the body to a temp file (to avoid shell-escaping issues) and update the PR, setting the title in the same call:

```bash
gh pr edit <number> --title "<title>" --body-file <path>
```

Then confirm by re-fetching and report the PR URL back to the user:

```bash
gh pr view <number> --json body,url
```
