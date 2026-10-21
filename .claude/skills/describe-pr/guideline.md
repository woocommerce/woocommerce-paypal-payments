# PR description guide

The description introduces a reviewer to the problem and the reasoning. It never walks through the diff.

## Shortest draft first

A reviewer skims, and the usual failure is a draft that is too long. Start with the fewest sections, and add one only when it tells the reviewer something the title and the code do not. When in doubt, leave it out. The example at the end is the upper limit, not a target.

## Title

A plain description of what the PR does, for a reviewer. It is not the changelog line and can be more technical.

When an issue ID is known, end the title with its number in parentheses (`ABC-6288` becomes `(6288)`). Without an issue ID, add no suffix and never guess a number.

Example: `Restore the "Proceed to PayPal" button label on block checkout (6288)`.

## Changelog line

The first line of the body, above `# Description`, is always the changelog line:

```
*Changelog:* `Type - the entry #PR`
```

- **Type:** `New` (new capability), `Enhancement` (better existing behavior), or `Fix` (defect). A `🐛 The problem` PR is `Fix`; a `🎯 The goal` PR is `New` or `Enhancement`.
- **Entry:** very short, for a plugin user: the observable outcome, never the class, method, or file.
- **#PR:** the PR number; more PRs as `, #1234`. Add `(author @handle)` only for an external contributor.

A PR with no change a plugin user would notice (tooling, CI, tests, build, docs) gets `*Changelog:* (none - <short reason>)`, e.g. `(none - internal Claude tooling)`. Never dress up an internal change as a user-facing one.

| Verdict                          | Entry                                                                            |
|----------------------------------|----------------------------------------------------------------------------------|
| Good                             | `Fix - Google Pay now correctly updates WooCommerce shipping address #3798`      |
| Good                             | `Enhancement - Improved error message for reCAPTCHA v2 challenge failures #4342` |
| Bad: developer voice             | `Fix - AddressFactory::from_wc_order billing address #4279`                      |
| Bad: describes the code          | `Fix - Do not increment step directly #4086`                                     |

## Headings

Every section is optional; leaving it out is the default. Most PRs need two or three.

````template
*Changelog:* `Type - the entry #PR`   first line, above the title
# Description
## 🐛 The problem      a defect: the symptom in plain terms, not the stack trace
## 🎯 The goal         no defect: a new or changed spec
## 💡 Why it happens   the root cause of the symptom or gap
## ✅ The fix          what the change does conceptually and where; never a code walkthrough
## 🧪 Tests            what the tests now cover and what they do not, and why; not a test list
## 🧪 How to verify    rare: at most 3 steps for a product check a reviewer cannot guess
## 🖼️ Visual proof     placeholder for before/after media, only for a UI change and when the user opts in
````

- Use `The problem` or `The goal`, never both.
- For refactors, features, test additions, and chores, keep only the headings that carry meaning. A test-addition PR often needs only `# Description` and `🧪 Tests`.
- **How to verify:** leave it out by default: QA tests the change. Add it only when a reviewer needs a product check they cannot guess, as at most 3 steps (setting to change, page to open, what they see). Never a CLI command (`npm`, `composer`, `phpunit`, `ddev`, ...) anywhere in the description.
- **Visual proof:** never drafted. Offer it only for a visible UI change; if the user opts in, append this placeholder for them to fill in; never invent media:

````template
## 🖼️ Visual proof

| Before                | After                 |
|-----------------------|-----------------------|
| _screenshot or video_ | _screenshot or video_ |
````

## Content

- Explain the problem and the reason; the diff shows the how. No file-by-file summary.
- The description is public: no internal issue IDs, chat threads, developer names, or other private details.
- Describe the state after the merge. No "currently failing", "WIP", or "green once X".

## Style

- Plain, direct, active English. Paragraphs of 1-3 sentences. No filler ("it is worth noting", "in order to"), no AI words ("delve", "scrutinize"), no em dashes.
- Backticks for paths, classes, methods, hooks, config keys, and JS globals. Bold at most once per section.
- Bullets for 3 or more parallel items.
- No soft wrapping: each paragraph and bullet is one line. GitHub shows every line break.

## Example

A subtle fix with five sections: about the longest a description gets. A routine PR has one or two, and no "How to verify".

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

## 🧪 Tests

Not unit-tested by design: correctness depends on filter execution order and the runtime presence of `window.wp.data`, which a unit test cannot exercise.

## 🧪 How to verify

1. With **Save PayPal and Venmo** enabled, add a subscription product to the cart and open **Block Checkout**.
2. Select the PayPal gateway → button reads **"Proceed to PayPal"** (not "Sign up now").
3. Select a different gateway → its normal label is unaffected.
````
