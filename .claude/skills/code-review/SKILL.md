---
name: code-review
description: Reviews code changes for quality, security, and adherence to project standards. Use when reviewing PRs, commits, or staged changes.
argument-hint: "[PR-number]"
---

# Code Review

## Instructions

When invoked, perform a thorough code review following these steps:

### 1. Identify Changes to Review

- If a PR number is provided as argument (e.g., `/code-review 123`), fetch the PR diff using `gh pr diff <number>`
- If no argument provided, review staged changes with `git diff --cached`, or if nothing staged, review uncommitted changes with `git diff`
- If a branch name is provided, compare against the main branch with `git diff develop...<branch>`

### 2. Analyze PR

- Read the PR description and comments for context
- For each file with changes, evaluate:
  - Code Quality: see [code-quality.md](../../docs/code-quality.md)
  - Check if there is any bug in the changes of the PR
  - Testing Considerations

### 3. Output Format

Create a Markdown file report with the results and add it to ./temp/code-reviews including the PR id and title in the file name
