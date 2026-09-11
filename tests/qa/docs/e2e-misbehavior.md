# E2E misbehavior log

Short log of unexpected changes that break autotests without actually affecting the end user - excludes confirmed product defects, which are tracked as bugs in Jira.

## Table of contents

- [V4.1.3](#v413)
  - [Dev-related misbehaviors (v4.1.3)](#dev-related-misbehaviors-v413)
  - [PayPal-related misbehaviors (v4.1.3)](#paypal-related-misbehaviors-v413)
  - [QA blunders (v4.1.3)](#qa-blunders-v413)

## V4.1.3

### Dev-related misbehaviors (v4.1.3)

- [Claude] ACDC classic checkout renders via `paypal.CardFields`/`CardFieldsRenderer.js`, not the legacy `HostedFieldsRenderer.js` — SDK auto-selects based on `paypal.CardFields` availability, independent of this plugin's own v5/v6 flag.
- Block checkout's ACDC "save payment method" checkbox differs entirely between v5 and v6: v5 has no `.wc-block-components-payment-methods__save-card-info` wrapper, id `save`, label "Save your card"; v6 has the wrapper, a generated id (`checkbox-control-*`), label "Save payment information to my account for future purchases." Neither class nor label is stable across both — only "exactly one checkbox inside the ACDC container" holds for both.
- Partial integration of v6 for specific features is a headache and caused a lot of time to untangle.

### PayPal-related misbehaviors (v4.1.3)

- PayPal sandbox can show "Try again" after submit and close the popup without ever completing the order.
- Fastlane sandbox test persona ("Ryan") now returns a different stored shipping address than the fixture expects; billing/card data unaffected.
- Fastlane informational dialog appears for Gary after the customer's email address has been entered, overlapping the checkout form.
- Pay Later's CAP (loan agreement) component doesn't reliably render/appear in the popup on every run — root cause of the `contentFrame()` timing race noted below.
- Adding an ACDC card (4012000077777777) via My Account → Payment Methods → Add payment method (standalone tokenization, no purchase) fails to save — confirmed both in automated runs (`page.waitForURL` never redirects back, page/context closes instead) and by manually reproducing the same flow by hand.
- PCP-4596 (classic checkout, Fastlane "Gary" persona) intermittently times out waiting on `#billing_country_field`, with a screenshot once showing PayPal's Fastlane consent tooltip (SDK i18n keys `consent.tooltip.heading1/2/3`) overlapping the billing fields. Unconfirmed as the actual cause: neither an `Escape` press nor a role-based close-button click made a difference on rerun, and the popup couldn't be reproduced manually or in Playwright debug mode either. Root cause still open.

### QA blunders (v4.1.3)

- Assumed a live DOM check "proved" v5 uses `<paypal-button>` custom elements — the site was actually still stuck rendering v6 at the time. Reverted.
- Fixed the ACDC save-checkbox locator by label text ("Save your card") without checking v6 — broke on v6, which uses a different label entirely. Switched to matching by input type within the already-scoped container instead of any label/class.
- `pcp-sdk-version-flag` plugin could only force v6 on (`__return_true`); deactivating never forced v5, just fell back to whatever the server default already was.
- `setup:vaulting;` never explicitly disables Fastlane, so a leftover-enabled Fastlane silently replaces the ACDC payment option.
