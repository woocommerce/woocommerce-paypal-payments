---
name: clean-up-code
description: Make a scope of code self-explanatory: rename unclear symbols, delete comments that restate the code, and untangle clever code that only works with a comment attached. Defaults to all uncommitted changes.
argument-hint: "[file-dir-or-scope]"
---

Make the given scope easy to understand. Without a scope, use all uncommitted changes. The goal is maintainability, not brevity.

## Code first

- Rename a symbol when the better name makes a comment unnecessary.
- Rewrite clever code into plain code that needs no comment.
- Flag files over 400 lines that do several jobs, and propose a split. Never split a file without the user's approval.
- When the user flags one occurrence of a pattern, fix every occurrence in the scope in the same pass, and name the ones outside it.

## Each comment: delete, rewrite, or keep

Read the code the comment describes, then decide. Delete is the default.

- **Delete** a comment that restates the code, repeats the symbol's name as a sentence, explains the current change, refers to history or versions ("moved from", "v6" inside a v6 module), or talks to the user or to yourself. Never shorten a comment that should not exist.
- **Rewrite** a needed comment that is wrong, unclear, or uses words from your own investigation ("absent", "narrow", "the safe answer") instead of the code's own terms.
- **Keep** a comment that explains why code exists, a non-obvious mechanism, or a constraint someone would otherwise break. State a cross-file rule once, where it is produced.
- **Add** a short comment only where code stays ambiguous after renaming and simplifying, and only to say why it exists.
- Never add a comment that explains a change: that belongs in the PR.
- Check every factual claim against the code it names (open the constant, grep the callers), not against memory.
- Describe what a list means, never its members, and generalize instead of listing options: both go stale when the code grows.

## Keyed structures

- Never comment a key inline: a reorder or merge moves the comment to the wrong key.
- Only ambiguous keys get a line. Put the lines in one block inside the opening brace, each line naming its key.
- Remove a key's line together with the key.
- A nested structure gets its own block inside its own brace.

## Style of a kept comment

One line where possible; more only to prevent real code rot. Evergreen, no hedging, no em dashes. It must make sense to a reader who does not know today's conversation.

Finally, re-read every comment you wrote or kept as if you see the code for the first time. A term that needs today's context to understand is a defect, not a style point.

Linters, tests, and review agents cannot check prose. Do not report them as verification, and do not mention CI runs.
