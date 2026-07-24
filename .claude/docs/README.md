# `.claude/` tooling

Team reference for the custom Claude Code tooling in this repo: the slash-command **skills**, the
**agents** they dispatch, the **hooks** that fire automatically, the auto-loaded **rules**, and the
shared coding **standards**.

> Claude tooling is shared via the repo.
> On first use after pulling, Claude Code asks each teammate to approve the hooks (workspace trust).
> The hook scripts need `jq`; without it, they degrade to no-ops rather than breaking.

---

## Typical workflows

**Finishing your own change**

1. Edit PHP. The hooks auto-run php-review and Claude applies cleanliness fixes.
2. `/pr-readiness` for the readiness gate.
3. `/describe-pr` to draft and (on approval) publish the PR description.

**Reviewing a teammate's PR:** `/code-review <PR-number>`, then your manual review on top.

**Writing tests:** the unit-test-writer agent owns `tests/PHPUnit/`; the test rule routes work to it
automatically.


---

## Skills

_Slash-commands that we manually invoke._

> Claude Docs: https://code.claude.com/docs/en/skills

### `/understand`

Run **before** starting a complex task, to research an area and capture what you learn. Args:
`[module-or-topic] [JIRA-ID]`, both optional.

Recalls any existing notes, researches the code, cross-references your assumptions against it,
offers
an optional interview, then dispatches `note-taker` to file durable facts and task context as
local notes.

Source: [skills/understand/SKILL.md](../skills/understand/SKILL.md)

### `/pr-readiness`

Run on **your own** branch before opening a PR. Args: `[JIRA-ID] [base-branch]`, both optional.

Dispatches the pr-readiness agent to check test coverage, docs, and code quality, then give a ready
or not-ready verdict. Does not write the PR description.

Source: [skills/pr-readiness/SKILL.md](../skills/pr-readiness/SKILL.md)

### `/describe-pr`

Run after `/pr-readiness`. Args: `[PR-number] [JIRA-ID]`, both optional.

Drafts a reviewer-focused PR title and description, and updates the PR via `gh pr edit` only after
you approve.

Source: [skills/describe-pr/SKILL.md](../skills/describe-pr/SKILL.md)

### `/code-review`

Run on **someone else's** PR, before your manual review. Arg: `[PR-number]`.

Automated first pass against the shared quality standard, plus bug and test notes. Writes a report
to `./temp/code-reviews`.

Source: [skills/code-review/SKILL.md](../skills/code-review/SKILL.md)

### Disabling skills you don't use

Every listed skill's name and description is fed into Claude's context each session, which costs a
few tokens per skill. To trim skills you don't use, add a `skillOverrides` map to your personal
`.claude/settings.local.json` (gitignored, so it stays yours and does not affect the team):

```json
{
	"skillOverrides": {
		"code-review": "off",
		"pr-readiness": "name-only"
	}
}
```

| Value                 | In context                | Manual `/name` | Claude can invoke |
|-----------------------|---------------------------|----------------|-------------------|
| `on` (default)        | name + description        | yes            | yes               |
| `name-only`           | name only, no description | yes            | yes               |
| `user-invocable-only` | nothing                   | yes            | no                |
| `off`                 | nothing                   | no             | no                |

Keys are skill names (e.g. `understand`, `describe-pr`), not paths. Omit a skill to leave it `on`.

---

## Agents

_Automatically invoked by skills or hooks; can also be manually dispatched ("Use agent X for
TASK")._

> Claude Docs: https://code.claude.com/docs/en/sub-agents

### `php-review`

Haiku, read-only. Dispatched by the Stop hook or manually.

Reviews the PHP lines you changed this session for cleanliness (function focus, comment noise,
coupling, over-engineering) and returns fixes. Never edits.

Source: [agents/php-review.md](../agents/php-review.md)

### `pr-readiness`

Sonnet, read-only. Dispatched only by the `/pr-readiness` skill.

Audits the branch diff. Shares its name with the skill on purpose: the skill gathers context, the
agent reviews.

Source: [agents/pr-readiness.md](../agents/pr-readiness.md)

### `unit-test-writer`

Writes and updates PHPUnit tests in `tests/PHPUnit/`, following the project's conventions.
Dispatched manually or via the test rule below.

Source: [agents/unit-test-writer.md](../agents/unit-test-writer.md)

### `js-unit-test-writer`

Writes and updates Jest tests (the colocated `*.test.js` files next to module `resources/js`
sources), following the existing JS suite's conventions. Dispatched manually.

Source: [agents/js-unit-test-writer.md](../agents/js-unit-test-writer.md)

### `copy-editor`

Haiku, read-only. Canonicalizes a user-facing string (sentence case, brand capitalization,
concision) against the copy guidelines and returns a suggestion. The caller passes the draft plus
its context; a human still has the final word. Dispatch when writing or changing UI copy.

Source: [agents/copy-editor.md](../agents/copy-editor.md)

### `note-reader`

Haiku, read-only. Recalls the developer's local research notes - crisp canonical knowledge per
module/topic, plus throwaway per-task context. Returns the relevant notes or reports none exist.
Dispatched by `/understand`, by other skills, or manually to recall a note.

Source: [agents/note-reader.md](../agents/note-reader.md)

### `note-taker`

Sonnet. Records an insight into the developer's local research notes and owns their conventions:
files durable facts into a crisp per-module/topic domain note (deduped and pruned), and loose per-task
context into a throwaway task note. Dispatched by `/understand`, by other skills, or manually to
record a note.

Source: [agents/note-taker.md](../agents/note-taker.md)

---

## Hooks

_Fire automatically when Claude performs certain actions._

> Claude Docs: https://code.claude.com/docs/en/hooks  
> Registered in [settings.json](../settings.json)

### `php-review-track.sh`

PostToolUse (Edit / Write). Records which PHP files you touch this session, so only those become
review candidates. Pre-existing files are left alone.

Source: [hooks/php-review-track.sh](../hooks/php-review-track.sh)

### `php-review-stop.sh`

Stop. When your turn settles, asks Claude to review each touched PHP file whose content changed
since its last review. Each state is reviewed once, so the loop terminates.

Source: [hooks/php-review-stop.sh](../hooks/php-review-stop.sh)

---

## Rules

_Autoloaded by Claude, passive context._

> Claude Docs:
> [https://code.claude.com/docs/en/memory](https://code.claude.com/docs/en/memory#organize-rules-with-claude/rules/)

### `unit-test-conventions.md`

Loads on first read of a `tests/PHPUnit/**/*.php` file. Routes all test writing through the
unit-test-writer agent instead of inline test code.

Source: [rules/unit-test-conventions.md](../rules/unit-test-conventions.md)

### `js-test-conventions.md`

Loads on first read of a `modules/**/*.test.js` file. Routes all JS test writing through the
js-unit-test-writer agent instead of inline test code.

Source: [rules/js-test-conventions.md](../rules/js-test-conventions.md)

---

## Standards

_Our conventions, not a regular Claude Code folder._

### code-quality.md

The shared code-quality rules. Single source of truth for `/code-review` and `/pr-readiness`. The
php-review agent keeps a tuned subset inline and does not read this file.

Source: [code-quality.md](./code-quality.md)
