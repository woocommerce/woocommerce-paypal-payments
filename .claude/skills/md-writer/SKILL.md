---
name: md-writer
description: Write Markdown documentation in alignment with project standards. Always use this skill to create or update .md files.
paths:
- "**/*.md"
---

## Purpose and brevity

Aim for evergreen documentation that requires little to no maintenance when code changes.
When referencing code parts, do not repeat logic in the text body, but point to classes/functions instead.

Markdown documentation should provide context and an overview over the code, it should not replace the need to read relevant code files.

## Text style

- All headers use sentence case
- Active language, short sentences that are easy to understand
- Write all text in American English

## Linebreaks

NEVER add a soft-wrap that breaks text based on a character limit.
Only use line breaks for functional purposes: Separate list elements, start a new paragraph, etc.

## Empty lines

Always add an empty line around block elements: Before/after a header, separate text paragraphs from lists or tables, etc

## Tables

Always align table borders:

```wrong
| Header | Description |
|---|---|
| 1 | Content |
```

```correct
| Header | Description |
|--------|-------------|
| 1      | Content     |
```

Use tables for short and tabular data; create no more than 4 columns, and no row must have more than 150 characters! Longer rows will cause unexpected column sizing and line breaks that make the table hard to read.

Ways to keep the data readable: Split a single table into multiple smaller ones (allows to omit a column), introduce intentional breaks via <br> or a list inside a cell for better visual control; if content needs more details, then consider if a table is the right tool, and a different layout would be better.
