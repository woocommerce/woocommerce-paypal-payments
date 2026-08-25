# Wallets

Apple Pay and Google Pay in the v6 SDK integration.

Both are **merchant-presented** wallets: PayPal opens no popup and runs no approval flow of its own, so the store builds the payment sheet, drives it, and reports the outcome back. Everything below follows from that.

## What has to be true for a button to appear

The following conditions, all of them required, checked in different places:

1. **The merchant is eligible for the wallet.** Country, currency, and the state of the PayPal account. The v6 module never judges this itself, it asks the wallet's own module, so the answer is the same one v5 would get.
2. **The wallet is enabled in the settings for that location.** Each location has its own styling and its own list of shown payment methods, and a wallet absent from that list does not render there.
3. **A live eligibility call includes it.** [`checkEligibility()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/eligibility.js) asks the SDK through `findEligibleMethods()` on every page load, with the page's currency, country and amount. A wallet can be eligible on one page and not the next.
4. The device has to support the wallet. [Google Pay](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/googlePay.js) asks before rendering and removes its button when the answer is no; [Apple Pay](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/applePay.js) asks before loading anything at all, so an unsupported browser touches neither the SDK nor the DOM.

Condition 3 and 4 cause wallet buttons to appear with a short delay after loading the page; they are not present in DOM on page load.

## The payment sequence

```mermaid
flowchart TD
    Click["Buyer clicks the wallet button"] --> Total["Resolve the total"]
    Total --> Sheet["The sheet opens"]
    Sheet --> Shipping["Shipping callbacks"]
    Shipping --> Authorize["Buyer authorizes"]
    Authorize --> Create["1. Create the PayPal order"]
    Create --> Confirm["2. Confirm it with the wallet token"]
    Confirm --> Approve["3. Approve it into a WooCommerce order"]
    Approve --> Done(["Order received"])

    Total -. "no total" .-> NoOpen(["The sheet never opens"])
    Sheet -. "Apple domain not validated" .-> Closed(["The sheet closes again"])
    Shipping -. "no rates" .-> Rejected(["Address rejected, sheet stays open"])
    Confirm -. "not approved" .-> Failed(["Payment fails, no order created"])

    classDef exit stroke-dasharray: 4 3
    class NoOpen,Closed,Rejected,Failed exit
```

The shipping callbacks fire once per address or shipping-option change, so the middle of the diagram repeats as often as the buyer edits the sheet. The three numbered steps are [the store's own](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/walletPayment.js). Only the last one creates anything in WooCommerce, which is why a failure before it leaves no order behind. The sheet's own state differs by wallet: Apple keeps it open until the outcome is reported back, while Google's closes the moment the buyer authorizes.

Cancelling is not a failure. The sheet closes, the payment records are released, and the page is left as it was.

[Failures](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/utils/errorHandler.js) split by who can act on them. A message the server marked shopper-facing is shown in the sheet, so the buyer can correct something and try again. Anything else is internal and the buyer sees the wallet's own wording instead, since a technical detail gives them nothing to act on.

## How the two wallets differ

The shipping and pricing behavior is identical, and the per-wallet files translate sheet protocols and nothing more. Four differences change what a maintainer may do:

- **Apple builds its session synchronously inside the click handler.** Nothing may be awaited before the sheet opens, so anything the sheet needs must already be resolved. Google may await freely.
- **[Apple's merchant-domain validation](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/applePayValidation.js) is the store's job.** The sheet asks mid-payment and the store answers from PayPal. Google has no equivalent step.
- **Apple learns the buyer's street only at authorization.** Its shipping callbacks carry a [redacted address](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/walletContacts.js), which is enough to resolve a shipping zone but not to complete an order.
- **Each wallet loads its own platform SDK**, separately from the PayPal SDK, and a wallet whose script fails to load simply does not render.

---

Related: [Wallet: Shipping and tax](wallet-shipping-and-tax.md)
