# PR description guide

Reference for drafting in step 3. The goal is to introduce a reviewer to the problem and the reasoning - never to walk through the diff.

## Heading catalog

**Every section is optional.** Include a heading only when it adds something for the reviewer; omit the ones that do not apply. A trivial change might be one paragraph under `# Description`; a subtle fix warrants the full set.

````template
# Description
## 🐛 The problem      a defect: the symptom in plain terms, not the stack trace
## 🎯 The goal         no defect: a spec that changed or scope not covered until now
## 💡 Why it happens   the root cause that explains the symptom or gap
## ✅ The fix          what the change does conceptually and where it lives; never a code walkthrough
## 🧪 Tests            what the change now covers and what it deliberately does not; reasoning, not a test-by-test list
## 🔍 What to review   fragile assumptions, blast radius, what tests can't catch; be honest about weak points
## 🧪 How to verify    numbered steps that exercise the change
````

`The problem` and `The goal` are alternatives - pick one. Use `🐛 The problem` when something was broken; use `🎯 The goal` when nothing was defective and the change implements a new or revised spec.

`Tests` and `How to verify` are distinct: **Tests** explains what coverage the change adds (and what it leaves uncovered, and why); **How to verify** lists the concrete steps a reviewer runs.

**The catalog is framed for bug fixes.** For other PR types (test additions, refactors, features, chores), keep the headings that carry meaning and drop the rest. A test-addition PR, for example, often needs only `# Description`, `🔍 What to review`, and `🧪 How to verify` - forcing "Why it happens" / "The fix" onto it produces filler.

## Tone and content rules

- **Reasoning over mechanics.** Explain the problem and the *why*; let the diff explain the *how*.
- **Do not summarize the changes** file-by-file. If a reviewer wants the mechanics, they read the code.
- **Be candid in "What to review"** - surface the one fragile assumption, the load-order dependency, the thing not covered by tests. This is the most valuable section.
- Keep the user's voice and the emoji headings. Drop headings that would only contain filler.
- The PR description is public and must stand on its own: Never reference a Jira issue ID, Slack threads, developer names, or other sensitive details that should not be public.
- **Write evergreen.** Describe the end state the reviewer sees when this merges, not transient or in-progress status ("currently failing", "WIP", "will be green once X"). If something is genuinely temporary, it does not belong in the description.
- **"How to verify" lists actions, not narration.** Give the reviewer steps that exercise *this* change, using the project's real documented commands (check `CLAUDE.md` / `AGENTS.md` / `package.json`; never invent an invocation). Skip preconditions that already hold and environment boilerplate. Do not state expected pass/fail results - a test is expected to pass; saying so adds nothing.

## Writing style

- **Paragraphs:** 1-3 sentences max. Break at natural pauses.
- **Language:** plain English. No filler phrases ("it is worth noting", "in order to").
- **Inline code:** backticks for file paths, class names, method names, hook names, config keys, JS globals.
- **Bold:** one use per section max, for the single most important term or outcome.
- **Lists:** prefer bullets over prose when there are 3+ parallel items.
- **Avoid AI lingo** like "awkward", "scrutinize", "delve".
- No em-dashes, direct and active tone.

## Reference example

The canonical target style - note how it explains the problem and reasoning, names the single fragile assumption under "What to review", and never narrates the code:

````example
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
