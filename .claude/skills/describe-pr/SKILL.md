---
name: describe-pr
description: Draft a standardized, reviewer-focused pull request description and - only after explicit approval - update the PR. Optional argument is a Jira issue ID (e.g. ABC-123) and a base branch for the diff (e.g. dev/develop).
argument-hint: "[JIRA-ID] [base-branch]"
allowed-tools: Read, Grep, Glob, Write, Bash(gh pr view:*), Bash(gh pr edit:*), Bash(gh pr create:*), Bash(git rev-parse:*), Bash(git diff:*), Bash(git log:*), Bash(echo:*)
disable-model-invocation: true
---

You help the user write a helpful, standardized PR title and description and, **only after their final and explicit approval**, create (`gh pr create -d`) or update (`gh pr edit`) the PR. Always create PRs as draft, and only after direct user instruction.

## Argument Parsing

Possible $ARGUMENTS - all optional:
- Jira issue ID ([A-Z]+-\d+), eg "PCP-1234"
- Base branch name ([\/\w]+), eg "dev/develop" (the "baseRefName")

## Steps

### 1. Collect data

[ ] Inspect linked PR - already fetched below; `no PR yet` means this branch has none, so step 5 creates one rather than editing:

!`gh pr view --json number,title,body,url,headRefName,baseRefName,state 2>/dev/null || echo "no PR yet"`

[ ] Current branch (resolved already, do not re-run): !`git rev-parse --abbrev-ref HEAD`

[ ] Base branch IS known: create the diff `git diff <baseRefName>...HEAD`
[ ] Base branch NOT known: diff against default branch `git diff dev/develop...HEAD`
      Three dots on purpose: it shows only what this branch introduced, the way GitHub renders a PR. Two dots would fold in everything that landed on the base since you branched.
[ ] Inspect recent commits: for context run `git log <baseRefName>..HEAD --oneline`
[ ] No Jira ID given: Extract from current branch name prefix (eg `dev/ABC-1234-some-fix` points to issue `ABC-1234`)
[ ] Jira ID known: Read the ticket details for context
[ ] Extract "Jira Number" from the issue ID: strip letter prefix from ID, e.g. `PCP-6288` -> `6288` (used for title)

### 2. Understand the change

Based on all checks done in phase 1, understand the PR's substance. Treat existing PR content (if available) as draft that should be rewritten.

What problem(s) does the PR address (bug fix, feature, refactoring, ...)?
Which files contain the core change?
Does it touch or introduce business logic?
Severity of the PR - eg. is it documentation or cosmetic (low), impact admin UI or front-end copy (mid), does it impact money flow (high)?
Which is the single most important entry point that a reviewer should see?

### 3. Draft the title and description

Draft a crisp, and easy to understand PR description. Follow `${CLAUDE_SKILL_DIR}/guideline.md` for the title rule, the mandatory changelog first line, the heading catalog, tone, writing style, and a worked example. Read it before drafting.

Draft both: a reviewer-focused **title** ending in the Jira number in parentheses (e.g. `(6288)`), and the **body** starting with the mandatory `*Changelog:*` line above `# Description`. For a PR with no plugin-user-facing change (tooling, CI, tests, build, docs), use the `*Changelog:* (none - <reason>)` form instead of inventing an entry - see guideline.md.

**Keep the first draft short.** Reviewers skim, and an over-long draft is the usual complaint. Include only sections that tell the reviewer something they cannot get from the title or the code - most PRs do not need a 50-line description.

Critically review the draft before presenting it, asking which statements or parts are not needed by a reviewer, and which parts can be simplified. If unclear, conversational language wins over technical accuracy.

### 4. Present the draft and iterate

Show the final draft to the user - the proposed **title** and the body as a fenced markdown block.

Ask whether to include a **Visual proof** section. Only if they opt in, append the placeholder block from guideline.md for them to fill in manually. If no Jira number could be resolved in step 1, ask for it now so the title suffix is correct.

**Never call `gh pr edit` or `gh pr create` yet.** Drafting and updating are separate steps.

### 5. Update the PR - only after explicit approval

Wait for the user's clear, explicit go-ahead (e.g. "approved", "create/update it", "yes, push it"). Silence, "looks good", or answering an unrelated question is **not** approval to write - if in doubt, ask.

Once approved, write the body to a temp file (to avoid shell-escaping issues) and update the PR, setting the title in the same call:

```bash
gh pr create -d --title "<title>" --body-file <path>
gh pr edit <number> --title "<title>" --body-file <path>
```

Then confirm by re-fetching and report the PR URL back to the user:

```bash
gh pr view <number> --json body,url
```
