---
name: md-writer
description: Write Markdown documentation in alignment with project standards. Always use this skill to create or update .md files.
paths:
- "**/*.md"
---

## Content

Docs give context and an overview; they do not replace reading the code. Point to classes and functions instead of repeating their logic, so the doc stays correct when the code changes.

## Style

- Sentence case for all headings.
- Active voice, short sentences, American English.
- No soft wrapping: break a line only for a new paragraph, list item, or block.
- An empty line before and after every block element (heading, list, table, code block).

## Tables

Only for short, tabular data: at most 4 columns and 150 characters per row. Align the borders:

```correct
| Header | Description |
|--------|-------------|
| 1      | Content     |
```

When a table gets too wide, split it into smaller tables, use `<br>` or a list inside a cell, or use a different layout.
