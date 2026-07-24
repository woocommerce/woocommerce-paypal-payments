---
name: understand
description: Research and understand a module, folder, or problem before implementing, capturing what you learn as local notes. Use before starting a complex task or to deepen understanding of an area. Optional argument is a module path/name and/or a Jira issue ID (e.g. ABC-123).
argument-hint: "[module-or-topic] [JIRA-ID]"
invocation: user
---

# Understand

You research a module, folder, or problem so the developer starts implementation with a clear
picture, and you capture what is learned as local notes for reuse. You do the research and reasoning
yourself; the `note-reader` and `note-taker` agents own all note I/O - you never read or write the
note directly.

The goal is understanding, not a deliverable document. Keep the session interactive and let the
developer steer what matters.

**This skill produces understanding and notes - it does not implement the change.** When the notes
capture the picture, stop and hand back to the developer. Beginning the implementation is a separate,
explicit decision they make; never slide from understanding into editing code.

**This skill writes files, so it cannot run in plan mode.** The notes are its whole output, and plan
mode blocks every write - including the `note-taker` agent's. If plan mode is active, say so up
front and ask the developer to exit it (or approve the plan) before you research; never take the
developer through the whole session only to finish with nothing written.

**Write notes as you go, not at the end.** The moment an insight crystallizes - during research,
cross-referencing, or the interview - record it via `note-taker` so the developer watches the
`domain.` and `task.` notes grow. Never batch note-taking into a single final step: a session that
reaches its conclusion with no notes written has failed its one job.

## Argument Parsing

Arguments after `/understand` (both optional, order does not matter):

- An identifier matching `[A-Z]+-\d+` is a **Jira issue ID**.
- Anything else is the **target**: a module path/name, folder, or topic.

If no target is given, ask the developer what to focus on before proceeding.

## Steps

### 1. Recall existing context

Before researching, dispatch the `note-reader` agent to pull any notes that already exist for the
target. Use what it returns to seed your understanding - do not re-derive what is already recorded.
Also read any notes the developer references explicitly.

````payload-template
Target: {module / topic / problem the developer named}
Looking for: existing domain and task notes relevant to this work.
````

### 2. Research the code

Explore the target with your built-in tools. If the surface is large enough that reading it inline
would flood context, spawn the built-in **Explore** agent and work from its findings instead.

### 3. Fetch task material

If a Jira ID was given (or embedded in the branch name, `[A-Z]+-\d+`), fetch it via the connected
Atlassian MCP - title, description, type. If the fetch fails, note it in one line and continue.
Gather the developer's manual notes and any observed symptoms from the conversation.

### 4. Cross-reference and draft

Check the developer's stated assumptions against the actual code and surface contradictions
explicitly, e.g. "your code cancels the whole Order, but you said partial cancellation is possible -
which is right?" Present a condensed understanding of how the area works and where the task fits.

As soon as the picture is solid enough to be useful, dispatch `note-taker` to file a first pass
(see step 6). Do not wait for the interview to finish - the note should already exist and then grow.

### 5. Optional interview

Offer a gap-filling interview - Jira gaps, constraints, decisions, edge cases - and run it only if
the developer opts in. Walk one branch of the decision tree at a time, and for each question give
your recommended answer. If a question can be answered from the code, answer it from the code.

As each question resolves, update the relevant note immediately rather than saving them for the end.

### 6. Persist (throughout, not only here)

Record insights the moment they crystallize during steps 2-5 - not batched into this step. Each time,
dispatch `note-taker`:

- Durable, canonical architecture facts -> `domain:<topic>` (kept crisp by the agent).
- Task-specific context - Jira, symptoms, scratch findings -> `task:<name>`.

When the domain/task split is ambiguous, confirm it with the developer before filing. Before you
finish, do a final sweep so every insight from the session is captured in a note.

````payload-template
Target: domain:{topic}   # or task:{name}
Insight:
{the crisp fact(s) to record, or the task context to append}
````

### 7. Report

Tell the developer which notes were written or updated, using the one-line confirmations `note-taker`
returned. Do not paste the full note content back.

Then stop. Do not begin implementing the change - if the developer wants to proceed to
implementation, that is a new, separate instruction from them.
