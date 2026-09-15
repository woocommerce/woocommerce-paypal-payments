# `.claude/` tooling

Team reference for the custom Claude Code tooling in this repo: the slash-command **skills**, the **agents** they dispatch, the **hooks** that fire automatically, the auto-loaded **rules**, and the shared coding **standards**.

> Claude tooling is shared via the repo.
> On first use after pulling, Claude Code might ask you to approve the hooks (workspace trust). The hook scripts need `jq`; without it, they quietly do nothing instead of erroring.

---

## Typical workflows

**Finishing your own change**

1. Edit PHP. The hooks auto-run the cleanliness reviewer and Claude applies its fixes.
2. `/review-branch-before-pr` for the readiness gate.
3. `/describe-pr` to draft and (on approval) publish the PR description.

**Writing tests:** the test-writer agents own `tests/PHPUnit/` and the colocated Jest suites; the test rules route work to them automatically.

---

## Skills

_Slash-commands that we manually invoke._

> Claude Docs: https://code.claude.com/docs/en/skills

### `/review-branch-before-pr`

Run on **your own** branch before opening a PR. Args: `[JIRA-ID] [base-branch]`, both optional.

Gathers the branch diff, commit list, and optional Jira context, then dispatches the `is-branch-ready-for-pr` agent to check test coverage, docs, and code quality and give a ready or not-ready verdict. Does not write the PR description.

Source: [skills/review-branch-before-pr/SKILL.md](../skills/review-branch-before-pr/SKILL.md)

### `/describe-pr`

Run after `/review-branch-before-pr`. Args: `[JIRA-ID] [base-branch]`, both optional.

Drafts a reviewer-focused PR title and description, then creates a draft PR (`gh pr create -d`) or updates the existing one (`gh pr edit`) - only after you explicitly approve. The full house style lives next to the skill in `guideline.md`.

Source: [skills/describe-pr/SKILL.md](../skills/describe-pr/SKILL.md)

### `/clean-up-code`

Run on a scope of code to make it easier to understand. Without a scope, it falls back to all uncommitted changes.

Prefers renaming variables and functions over adding comments, and trims verbose comments down. Files over 400 lines are flagged for a possible split, never split automatically.

Source: [skills/clean-up-code/SKILL.md](../skills/clean-up-code/SKILL.md)

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

Read-only. Reviews PHP for cleanliness a linter cannot catch (function focus, comment noise, coupling, over-engineering) and suggests fixes; never edits. Runs automatically after you edit PHP (via the Stop hook), and can be dispatched manually with `review CHANGES in <file>` or `review FULL <file>`.

Source: [agents/review-php-code-cleanliness.md](../agents/review-php-code-cleanliness.md)

### `verify-user-facing-copy`

Read-only. Canonicalizes a user-facing string (sentence case, brand capitalization, concision) against the copy guidelines and returns a suggestion. The caller passes the draft plus its context; a human still has the final word. Dispatch when writing or changing UI copy.

Source: [agents/verify-user-facing-copy.md](../agents/verify-user-facing-copy.md)

### `write-php-unit-test`

Writes and updates PHPUnit tests in `tests/PHPUnit/`, following the project's conventions. Dispatched manually or via the test rule below.

Source: [agents/write-php-unit-test.md](../agents/write-php-unit-test.md)

### `write-js-unit-test`

Writes and updates Jest tests (the colocated `*.test.js` files next to module `resources/js` sources), following the existing JS suite's conventions. Dispatched manually or via the test rule below.

Source: [agents/write-js-unit-test.md](../agents/write-js-unit-test.md)

### `ci`

Runs one requested suite (PHP unit, PHP unit TDD, JS unit, integration, PHPCS, PHPStan) and distills the output to a crisp pass/fail summary, keeping heavy tool output out of the main context. Use it for any test or lint run; the test-writer agents hand off verification to it.

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

Stop. When your turn settles, asks Claude to review each touched PHP file whose content changed since its last review. Each state is reviewed once, so the loop terminates.

Source: [hooks/review-edited-php-files.sh](../hooks/review-edited-php-files.sh)

### Disabling the PHP review hooks

Both hooks read `hooks/config.local.json` (gitignored). Copy `config.example.json` to `config.local.json` and set `"php-review": false` to turn them off locally. No config file, or a missing key, means enabled. Dispatching `review-php-code-cleanliness` by hand is unaffected and always runs.

---

## Rules

_Autoloaded by Claude, passive context._

> Claude Docs:
> [https://code.claude.com/docs/en/memory](https://code.claude.com/docs/en/memory#organize-rules-with-claude/rules/)

### `php-test-conventions.md`

Loads on first read of a `tests/PHPUnit/**/*.php` file. Routes all PHP test writing through the `write-php-unit-test` agent instead of inline test code.

Source: [rules/php-test-conventions.md](../rules/php-test-conventions.md)

### `js-test-conventions.md`

Loads on first read of a `modules/**/*.test.js` file. Routes all JS test writing through the `write-js-unit-test` agent instead of inline test code.

Source: [rules/js-test-conventions.md](../rules/js-test-conventions.md)

---

## Standards

_Our conventions, not a regular Claude Code folder._

### php-code-quality.md

The shared code-quality rules, and the quality lens the `is-branch-ready-for-pr` agent reads before reviewing. The `review-php-code-cleanliness` agent keeps a tuned subset inline and does not read this file.

Source: [php-code-quality.md](./php-code-quality.md)
