---
name: clean-up-code
description: Ensures that code is well documented in an efficient way. Prefers to rename variables or functions to add clarity over adding comments. Trims verbose comments to shorter versions if possible.
---

Carefully review the defined code scope and make sure it's easy to understand. If no scope provided, then fall back to reviewing all uncommitted changes.

## Keyed structures

- Never comment a key inline; position-bound comments rebind to the wrong key on a reorder or merge
- Collect them in one block comment inside the opening brace, one terse line per key, each line naming its key
- Only ambiguous keys earn a line; when removing a key, remove its line
- Nested structures get their own block, inside their own brace when they need multiple comments

## Every comment is a claim

- Check each factual assertion against the symbol it names — open the constant, grep the importers, never against your memory alone
- Never restate a list that exists in code; describe the meaning, not the members. An enumeration goes stale the next time the list grows
- Delete any summary line that is the symbol's own name as a sentence
- Replace shorthand from your own investigation ("absent", "narrow", "the safe answer") with the vocabulary the code itself uses
- Delete comments that are part of a dialogue with the user or yourself. Comments must strictly describe code
- Never restate code in plain english; only describe reason or effect of the code if it's not obvious
- Re-read every comment you wrote or kept as if seeing the code for the first time; a term that needs today's context to parse is a defect, not a style nit

## Static analysis

- Linters, type checkers, tests and review agents cannot evaluate prose. Never report them as verification of comments
- In fact, the user is not interested in CI details, do not even mention it, if anything ran

## Style

- No em dashes
- Terse, compact, skimmable
- No hedging, no process notes, only evergreen content
- Generalize rather than listing mutliple options to reduce maintenance overhead

## Approved Cleanup

You have the following permissions:

- Remove as many comments as possible
- Only add absolute minimal code comments to clarify a truly ambiguous or unclear parts; focus on explaining WHY this code exists
- Rename symbold to make them self explanatory when this helps to remove a comment
- Justify every comment that already exists; remove comments that are not needed, shorten comments that are too verbose
- Resolve smart-code into maintainable code so it does not need documentation

The ideal comment is one that does not exist, because the code itself is clear enough. The next best thing is a single sentence or line. Only add multi-line comments when they are actually needed to prevent future code rot.

## Flag only

Critically review files that are longer than 400 lines, evaluating if the module is doing too many things; consider refactoring that code into multiple focused files. Never split files automatically, always confirm with user
