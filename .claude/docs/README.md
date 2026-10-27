# `.claude/` tooling

Team reference for the custom Claude Code tooling in this repo: the slash-command **skills**, the **agents** they dispatch, the **hooks** that fire automatically, and the auto-loaded **rules**, including the shared PHP quality rules.

> Claude tooling is shared via the repo.
> On first use after pulling, Claude Code might ask you to approve the hooks (workspace trust). The hook scripts need `jq`; without it, they quietly do nothing instead of erroring. The PHPCS part of the Stop hook also needs a running DDEV.

---

## Typical workflows

**Finishing your own change**

1. Edit PHP. The hooks auto-run PHPCS and the cleanliness reviewer, and Claude applies the fixes.
2. `/commit-loop` to split the changes into small commits.
3. `/review-branch-before-pr` for the readiness gate.
4. `/describe-pr` to draft and (on approval) publish the PR description.

**Writing tests:** the test-writer agents own `tests/PHPUnit/`, `tests/integration/`, and the colocated Jest suites; the test rules route work to them automatically.

---

## Skills

_Slash-commands that we manually invoke._

> Claude Docs: https://code.claude.com/docs/en/skills

### `/review-branch-before-pr`

Run on **your own** branch before opening a PR. Args: `[base-branch] [context]`, both optional. Context is free text about the goal of the change, e.g. the issue description.

Gathers the branch diff, commit list, and optional context, then dispatches the `is-branch-ready-for-pr` agent to check test coverage, docs, and code quality and give a ready or not-ready verdict. Does not write the PR description.

Source: [skills/review-branch-before-pr/SKILL.md](../skills/review-branch-before-pr/SKILL.md)

### `/describe-pr`

Run after `/review-branch-before-pr`. Args: `[ISSUE-ID] [base-branch] [context]`, all optional. Without an issue ID argument, it looks for one in the branch name and adds its number to the PR title.

Drafts a reviewer-focused PR title and description, then creates a draft PR (`gh pr create -d`) or updates the existing one (`gh pr edit`) - only after you explicitly approve. The full house style lives next to the skill in `guideline.md`.

Source: [skills/describe-pr/SKILL.md](../skills/describe-pr/SKILL.md)

### `/clean-up-code`

Run on a scope of code to make it easier to understand. Without a scope, it falls back to all uncommitted changes.

Prefers renaming over comments, and deletes, rewrites, or keeps each comment; it never only shortens one. Files over 400 lines are flagged for a possible split, never split automatically.

Source: [skills/clean-up-code/SKILL.md](../skills/clean-up-code/SKILL.md)

### `/commit-loop`

Run when the work is done and the changes are uncommitted. Splits them into small, isolated units and stages one unit at a time, with a suggested commit message.

You review and commit each unit by hand. Claude never commits, amends, or pushes, and does not run lint or tests unless you ask.

Source: [skills/commit-loop/SKILL.md](../skills/commit-loop/SKILL.md)

### `/md-writer`

Our Markdown house style: purpose and brevity, sentence-case headers, line breaks, table formatting.

Path-scoped to `**/*.md`, so Claude also loads it on its own whenever it creates or updates Markdown.

Source: [skills/md-writer/SKILL.md](../skills/md-writer/SKILL.md)

### Disabling skills you don't use

Every listed skill's name and description is fed into Claude's context each session, which costs a few tokens per skill. To trim skills you don't use, add a `skillOverrides` map to your personal `.claude/settings.local.json` (gitignored, so it stays yours and does not affect the team):

```json
{
	"skillOverrides": {
		"clean-up-code": "off",
		"review-branch-before-pr": "name-only"
	}
}
```

| Value                 | In context                | Manual `/name` | Claude can invoke |
|-----------------------|---------------------------|----------------|-------------------|
| `on` (default)        | name + description        | yes            | yes               |
| `name-only`           | name only, no description | yes            | yes               |
| `user-invocable-only` | nothing                   | yes            | no                |
| `off`                 | nothing                   | no             | no                |

Keys are skill names (e.g. `describe-pr`), not paths. Omit a skill to leave it `on`. The `/skills` menu shows `user-invocable-only` as `user-only`. Plugin skills ignore this setting; manage those through `/plugin`.

---

## Agents

_Automatically invoked by skills or hooks; can also be manually dispatched ("Use agent X for TASK")._

> Claude Docs: https://code.claude.com/docs/en/sub-agents

### `is-branch-ready-for-pr`

Read-only. Dispatched only by the `/review-branch-before-pr` skill.

Audits the branch diff and returns a fixed-shape readiness report.

Source: [agents/is-branch-ready-for-pr.md](../agents/is-branch-ready-for-pr.md)

### `review-php-code-cleanliness`

Read-only. Reviews the comments and names in PHP for clarity, which a linter cannot judge, and suggests fixes; never edits. PHPCS covers the mechanical checks, and design is the main model's job through the quality rule. Runs automatically after you edit PHP (via the Stop hook), and can be dispatched manually with `review CHANGES in <file>` or `review FULL <file>`.

Source: [agents/review-php-code-cleanliness.md](../agents/review-php-code-cleanliness.md)

### `verify-user-facing-copy`

Read-only. Canonicalizes a user-facing string (sentence case, brand capitalization, concision) against the copy guidelines and returns a suggestion. The caller passes the draft plus its context; a human still has the final word. Dispatch when writing or changing UI copy.

Source: [agents/verify-user-facing-copy.md](../agents/verify-user-facing-copy.md)

### `write-php-unit-test`

Writes and updates PHPUnit tests in `tests/PHPUnit/` and integration tests in `tests/integration/`, following the project's conventions. Dispatched manually or via the test rule below.

Source: [agents/write-php-unit-test.md](../agents/write-php-unit-test.md)

### `write-js-unit-test`

Writes and updates Jest tests (the colocated `*.test.js` files next to module `resources/js` sources), following the existing JS suite's conventions. Dispatched manually or via the test rule below.

Source: [agents/write-js-unit-test.md](../agents/write-js-unit-test.md)

### `ci`

Runs one requested suite (PHP unit, PHP unit TDD, JS unit, integration, PHPCS, PHPStan, JS lint) and distills the output to a crisp pass/fail summary, keeping heavy tool output out of the main context. Claude uses it when you ask for a test or lint run.

Source: [agents/ci.md](../agents/ci.md)

---

## Hooks

_Fire automatically when Claude performs certain actions._

> Claude Docs: https://code.claude.com/docs/en/hooks  
> Registered in [settings.json](../settings.json)

### `track-edited-php-files.sh`

PostToolUse (Edit / Write). Records which PHP files you touch this session, so only those become review candidates. Pre-existing files are left alone.

Source: [hooks/track-edited-php-files.sh](../hooks/track-edited-php-files.sh)

### `review-edited-php-files.sh`

Stop. When your turn settles, asks Claude to review each touched PHP file whose content changed since its last review. It also runs PHPCS on those files inside DDEV and adds the report, so Claude fixes the issues in the code it changed. Each state is reviewed once, so the loop terminates.

Source: [hooks/review-edited-php-files.sh](../hooks/review-edited-php-files.sh)

### `git-state.sh`

Stop (`save`) and UserPromptSubmit (`check`). Saves the working tree (a content hash per changed file), the index, and `HEAD` when Claude's turn ends. When you send the next prompt and you edited files (in your IDE or another session), changed what is staged, committed, amended, or switched branches in the meantime, it adds a short note so Claude re-reads them instead of relying on what it saw before. Each session has its own state file; the first prompt of a session only saves the state.

Source: [hooks/git-state.sh](../hooks/git-state.sh)

### Disabling hooks

All hooks read `hooks/config.local.json` (gitignored). Copy `config.example.json` to `config.local.json` and set a feature to `false` to turn it off locally: `"php-review"` for the PHP review hooks, `"stale-check"` for `git-state.sh`. No config file, or a missing key, means enabled. Dispatching `review-php-code-cleanliness` by hand is unaffected and always runs.

---

## Rules

_Autoloaded by Claude, passive context._

> Claude Docs:
> [https://code.claude.com/docs/en/memory](https://code.claude.com/docs/en/memory#organize-rules-with-claude/rules/)

### `php-code-quality.md`

Loads on first read of a plugin PHP file (`src/`, `modules/`, root). The shared PHP quality rules: comments, design, simplicity, modularity, data integrity. They apply to the main model and to every agent that reads PHP; `is-branch-ready-for-pr` reads the file explicitly.

Source: [rules/php-code-quality.md](../rules/php-code-quality.md)

### `definition-of-done.md`

Loads at session start. A task is done when it has test coverage and the relevant tests and lint passed. You run the checks yourself, or ask Claude to run them through the `ci` agent; Claude does not run them unasked.

Source: [rules/definition-of-done.md](../rules/definition-of-done.md)

### `php-test-conventions.md`

Loads on first read of a `tests/PHPUnit/**/*.php` or `tests/integration/**/*.php` file. Routes all PHP test writing through the `write-php-unit-test` agent instead of inline test code.

Source: [rules/php-test-conventions.md](../rules/php-test-conventions.md)

### `js-test-conventions.md`

Loads on first read of a `modules/**/*.test.js` file. Routes all JS test writing through the `write-js-unit-test` agent instead of inline test code.

Source: [rules/js-test-conventions.md](../rules/js-test-conventions.md)

