---
name: describe-pr
description: Draft a standardized, reviewer-focused pull request description and - only after explicit approval - update the PR. Optional arguments are an issue ID (e.g. ABC-123), a base branch for the diff (e.g. dev/develop), and free-text context about the change.
argument-hint: "[ISSUE-ID] [base-branch] [context]"
allowed-tools: Read, Grep, Glob, Write, Bash(gh pr view:*), Bash(gh pr edit:*), Bash(gh pr create:*), Bash(git rev-parse:*), Bash(git diff:*), Bash(git log:*), Bash(echo:*)
disable-model-invocation: true
---

You draft a PR title and description. You create (`gh pr create -d`, always as draft) or update (`gh pr edit`) the PR only after the user's explicit approval.

## Arguments

All optional:

- Issue ID (`[A-Z]+-\d+`), e.g. `ABC-1234`.
- Base branch (`[\/\w]+`), e.g. `dev/develop`.
- Any other text is context: the goal, requirements, or an issue description. Context from earlier in the conversation counts too.

## 1. Collect data

Linked PR (`no PR yet` means step 5 creates one):

!`gh pr view --json number,title,body,url,headRefName,baseRefName,state 2>/dev/null || echo "no PR yet"`

Current branch: !`git rev-parse --abbrev-ref HEAD`

- Diff: `git diff <base>...HEAD`, with `dev/develop` as the default base. Three dots show only what this branch added, as GitHub does.
- Commits: `git log <base>..HEAD --oneline`.
- Issue number: from the issue ID argument, else from the branch prefix (`dev/ABC-1234-some-fix`); strip the letters (`ABC-6288` becomes `6288`).

## 2. Understand the change

Treat an existing PR body as a draft to rewrite. Answer for yourself:

- What problem does the PR solve (bug fix, feature, refactoring)?
- Which files hold the core change, and which one entry point must a reviewer see?
- Does it touch business logic or the money flow?

## 3. Draft

Read `${CLAUDE_SKILL_DIR}/guideline.md` and follow it: title rule, changelog line, heading catalog, tone, and the example. Before you present the draft, remove every statement a reviewer does not need.

## 4. Present

Show the title and the body as a fenced Markdown block. Only for a visible UI change, ask whether to add a **Visual proof** placeholder (see guideline.md). Do not call `gh` yet.

## 5. Update the PR

Only after a clear go-ahead ("approved", "create it", "update it"). "Looks good", silence, or an answer to another question is not approval; if in doubt, ask.

Write the body to a temp file, then set title and body in one call:

```bash
gh pr create -d --title "<title>" --body-file <path>
gh pr edit <number> --title "<title>" --body-file <path>
```

After `gh pr create`, replace `#PR` in the changelog line with the new PR number (`gh pr edit <number> --body-file <path>`). Confirm with `gh pr view <number> --json body,url` and give the user the URL.
