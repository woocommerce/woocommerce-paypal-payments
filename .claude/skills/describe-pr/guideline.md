# PR description guide

Reference for drafting in step 3. The goal is to introduce a reviewer to the problem and the
reasoning - never to walk through the diff.

## Concision comes first

**Default to the shortest draft that does the job.** A reviewer skims; every extra sentence costs
their attention. The most common failure is a first draft that is too long, so bias hard toward
less.

Start from the **minimum** set of sections and add one only when it tells the reviewer something
they cannot get from the code or the title. When unsure whether a section earns its place, leave
it out.

The example at the bottom is near the **upper bound** of acceptable length, not a target to fill.

## Title

Draft or refine the PR title as well as the body. The title is a clear, plain description of the
change **for a reviewer** - what this PR does. It is not the changelog line and may be more
technical or specific than the changelog.

The title **must always end** with the Jira number in parentheses: the Jira ID with its letter
prefix removed. `PCP-6288` becomes `(6288)`. Get the number from the Jira ID argument or the branch
name (`[A-Z]+-\d+`). If no Jira ID can be found anywhere, ask the user for the number rather than
guessing or dropping the suffix.

Example: `Restore the "Proceed to PayPal" button label on block checkout (6288)`.

## Changelog line

The **first line** of the description, above the `# Description` title, is always a changelog line.
It is mandatory. Exact format:

```
*Changelog:* `Type - the entry #PR`
```

The entry inside the backticks follows the project's `readme.txt` changelog format,
`Type - short summary #PR`:

- **Type** is one of `New` (a brand-new capability), `Enhancement` (improves existing behavior), or
  `Fix` (corrects a defect). Derive it from the PR: a defect PR (`🐛 The problem`) is `Fix`; a
  new-spec PR (`🎯 The goal`) is `New` or `Enhancement`.
- **Summary** is very short and written for a **regular plugin user**, not a developer. Describe the
  observable outcome or benefit, never the mechanism, class, method, or file. Plain English.
- **#PR** is the resolved PR number. Append extra PRs as `, #1234`. Append `(author @handle)` only
  for an external contributor.
- Use a plain hyphen `-`.

|                                               | Entry                                                                            |
|-----------------------------------------------|----------------------------------------------------------------------------------|
| Good (user-facing)                            | `Fix - Google Pay now correctly updates WooCommerce shipping address #3798`      |
| Good                                          | `Enhancement - Improved error message for reCAPTCHA v2 challenge failures #4342` |
| Bad (developer voice)                         | `Fix - AddressFactory::from_wc_order billing address #4279`                      |
| Bad (describes the code, not the user impact) | `Fix - Do not increment step directly #4086`                                     |

## Heading catalog

**Every section is optional, and omitting is the default.** This is a menu, not a form to fill in.
Include a heading only when it adds something a reviewer cannot get elsewhere. Most PRs land on two
or three sections; the full set is rare and reserved for a genuinely subtle change.

````template
*Changelog:* `Type - the entry #PR`   mandatory first line, above the title
# Description
## 🐛 The problem      a defect: the symptom in plain terms, not the stack trace
## 🎯 The goal         no defect: a spec that changed or scope not covered until now
## 💡 Why it happens   the root cause that explains the symptom or gap
## ✅ The fix          what the change does conceptually and where it lives; never a code walkthrough
## 🧪 Tests            what the change now covers and what it deliberately does not; reasoning, not a test-by-test list
## 🔍 What to review   fragile assumptions, blast radius, what tests can't catch; be honest about weak points
## 🧪 How to verify    numbered steps that exercise the change
## 🖼️ Visual proof     user-supplied before/after media; only when the user opts in (see below)
````

`The problem` and `The goal` are alternatives - pick one. Use `🐛 The problem` when something was
broken; use `🎯 The goal` when nothing was defective and the change implements a new or revised spec.

`Visual proof` is never drafted automatically. Offer it in step 4, and only when the user opts in
insert a placeholder block they fill in manually (see below).

`Tests` and `How to verify` are distinct: **Tests** explains what coverage the change adds (and what
it leaves uncovered, and why); **How to verify** lists the concrete steps a reviewer runs.

**The catalog is framed for bug fixes.** For other PR types (test additions, refactors, features,
chores), keep the headings that carry meaning and drop the rest. A test-addition PR, for example,
often needs only `# Description`, `🔍 What to review`, and `🧪 How to verify` - forcing "Why it
happens" / "The fix" onto it produces filler.

## Tone and content rules

- **Reasoning over mechanics.** Explain the problem and the *why*; let the diff explain the *how*.
- **Do not summarize the changes** file-by-file. If a reviewer wants the mechanics, they read the
  code.
- **Be candid in "What to review"** - surface the one fragile assumption, the load-order dependency,
  the thing not covered by tests. This is the most valuable section.
- Keep the user's voice and the emoji headings. Drop headings that would only contain filler.
- The PR description is public and must stand on its own: Never reference a Jira issue ID, Slack
  threads, developer names, or other sensitive details that should not be public.
- **Write evergreen.** Describe the end state the reviewer sees when this merges, not transient or
  in-progress status ("currently failing", "WIP", "will be green once X"). If something is genuinely
  temporary, it does not belong in the description.
- **"How to verify" lists actions, not narration.** Give the reviewer steps that exercise *this*
  change, using the project's real documented commands (check `CLAUDE.md` / `AGENTS.md` /
  `package.json`; never invent an invocation). Skip preconditions that already hold and environment
  boilerplate. Do not state expected pass/fail results - a test is expected to pass; saying so adds
  nothing.

## Writing style

- **Paragraphs:** 1-3 sentences max. Break at natural pauses.
- **Language:** plain English. No filler phrases ("it is worth noting", "in order to").
- **Inline code:** backticks for file paths, class names, method names, hook names, config keys, JS
  globals.
- **Bold:** one use per section max, for the single most important term or outcome.
- **Lists:** prefer bullets over prose when there are 3+ parallel items.
- **Avoid AI lingo** like "awkward", "scrutinize", "delve".
- No em-dashes, direct and active tone.

## Visual proof

For UI changes a before/after helps, but the skill cannot capture media. In step 4, after the draft,
ask the user whether to include a Visual proof section. Only if they opt in, append this placeholder
for them to fill in manually after the PR is updated - never fabricate or attach media:

````template
## 🖼️ Visual proof

| Before | After |
|---|---|
| _screenshot or video_ | _screenshot or video_ |
````

## Reference example

A subtle fix that justifies four body sections - this is roughly the **longest** a description
should get, not the norm. Note the mandatory changelog first line, a reviewer-focused title ending
in the Jira number, and how the body explains the problem and reasoning, names the single fragile
assumption under "What to review", and never narrates the code. A routine PR would trim this to one
or two sections:

````example
Title: Restore the "Proceed to PayPal" button label on block checkout (6288)

*Changelog:* `Fix - "Proceed to PayPal" button label restored on block checkout #4441`

# Description

## 🐛 The problem

On block checkout the PayPal place-order button is expected to read **"Proceed to PayPal"**. However, when a subscription product is in the cart, the button instead reads "Sign up now".

## 💡 Why it happens

WooCommerce Subscriptions registers a `placeOrderButtonLabel` checkout filter that *unconditionally rewrites the label* to its own text. PayPal only supplied its label through `registerPaymentMethod`, with no competing filter - so WCS always won.

## ✅ The fix

PayPal now registers its own `placeOrderButtonLabel` filter that restores "Proceed to PayPal" **only when PayPal is the active gateway**, and passes the value through untouched otherwise. One file: `modules/ppcp-blocks/resources/js/checkout-block.js`.

## 🔍 What to review

- **Load-order dependency.** Both filters run; the last one to register wins, and WCS's overwrites whatever it receives. This fix relies on PayPal's filter registering **after** WCS's, which holds because `checkout-block.js` enqueues later. If that ordering ever changes, the bug returns silently.
- **Not unit-tested by design.** Correctness depends on filter execution order and the runtime presence of `window.wp.data`, neither of which a unit test can exercise. Verified manually instead (steps below).

## 🧪 How to verify

1. With **Save PayPal and Venmo** enabled, add a subscription product to the cart and open **Block Checkout**.
2. Select the PayPal gateway → button reads **"Proceed to PayPal"** (not "Sign up now").
3. Select a different gateway → its normal label is unaffected.
````
