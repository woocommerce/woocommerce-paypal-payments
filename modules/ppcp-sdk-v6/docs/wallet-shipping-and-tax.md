# Wallet shipping and tax

How Apple Pay and Google Pay behave when the buyer picks a shipping address inside the payment sheet, and why the charged total can differ from the one the sheet showed.

Context for contributors and for debugging. Both wallets behave identically here: [`applePayShipping`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/applePayShipping.js) and [`googlePayShipping`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/googlePayShipping.js) translate their own sheet protocol and nothing else, so there is no Apple-only or Google-only rule in this document.

## Who owns the shipping address

Only one surface may collect the shipping address for a payment. Where the page already collects it, the wallet does not, and the sheet only authorizes what the page displays. [`SdkV6Manager::shipping_for_context()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Assets/SdkV6Manager.php) decides per context, and the answer reaches the browser as `shipping.in_context`, which [`methodShippingRequired()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/methods/methodShipping.js) reads.

| Context                                 | Sheet collects shipping                       | Why                                              |
|-----------------------------------------|-----------------------------------------------|--------------------------------------------------|
| Classic checkout                        | No                                            | The page has its own address and shipping fields |
| Pay for order                           | No                                            | The order already carries both                   |
| Product page                            | Yes, unless virtual or downloadable           | The page has no address form                     |
| Cart, mini cart                         | Yes, when the cart needs shipping             | The page has no address form                     |
| Cart block, checkout block express area | Yes, when the block reports it needs shipping | The block asks live                              |

Consequence that surprises people: on the two contexts in the first rows the shipping callback is not implemented at all. A buyer who changes the address in the sheet there is not being ignored by a bug, the sheet is never given the chance to ask.

One setting overrides the whole table. With **Pay now** disabled the buyer reviews the order on PayPal, so no wallet sheet collects shipping in any context.

## Why the tax in the sheet is an estimate

Tax may depend on the buyer's billing country, and neither wallet reveals it before the buyer authorizes the payment. Apple Pay hands over `shippingContact` during the shipping callbacks and `billingContact` only in [`onpaymentauthorized`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/applePay.js); Google Pay hands over the shipping address in its `onPaymentDataChanged` callback and the card's billing address only in the [authorized payment data](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/googlePay.js). [`walletContacts`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/walletContacts.js) maps both shapes onto WooCommerce fields.

So everything the sheet shows is taxed on the **shipping** address. The billing address first exists at authorization, which is also the last moment before the order is created.

We chose to let WooCommerce price the order normally and reconcile afterwards, rather than force the wallet's billing country onto the order. Anyone tempted by the other route should know it was considered and rejected.

### Which stores this affects

Only one of WooCommerce's three tax settings is affected. [`record_tax_basis()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Endpoint/CartQuoteEndpoint.php) returns early unless the store taxes on billing, because in the other two cases the sheet already has everything the calculation needs.

| `woocommerce_tax_based_on`                     | Wallet impact                                                              |
|------------------------------------------------|----------------------------------------------------------------------------|
| Customer shipping address                      | None. The sheet collects that address, so the first quote is already right |
| Customer billing address (WooCommerce default) | Estimated from the shipping address, then reconciled at authorization      |
| Shop base address                              | None. The basis never depends on the buyer                                 |

### What happens when the real tax differs

At authorization [`commit()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/methods/methodShipping.js) re-prices the payment on the addresses the order will actually use, and sends along the total the sheet displayed. [`CartQuoteEndpoint`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Endpoint/CartQuoteEndpoint.php) compares them, so there is nothing in the browser to bypass.

| Real total            | Outcome  | What the buyer sees                                               |
|-----------------------|----------|-------------------------------------------------------------------|
| Same as the sheet     | Proceeds | Nothing. The common case                                          |
| Lower than the sheet  | Proceeds | Nothing during payment; an explanation on the order-received page |
| Higher than the sheet | Refused  | The sheet reports the failure and the corrected total             |

Two guarantees behind that table. **The buyer is never charged more than they approved**, not by a cent and not silently. And **being charged less needs no consent**, so a reduction never interrupts the payment.

The refusal message leads with the fact that no money moved:

> Nothing has been charged yet. Tax for Germany differs from our estimate, so your total is now €48.30. Please try again.

**The refusal is self-correcting.** [`RecordedTaxBasis`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Helper/RecordedTaxBasis.php) is written before the payment is refused, so the next attempt's very first quote is priced on the real billing basis and the sheet shows the correct total from the start. A buyer who retries is expected to succeed, and support can say "try once more" with confidence.

### Feedback on a reduced total

A payment that came out lower than quoted leaves a trail in two places, both from [`RecordedQuote`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Helper/RecordedQuote.php).

**The merchant** gets a private order note, and the sheet's total is kept in order meta `_ppcp_wallet_quoted_total`:

> The wallet payment sheet showed €52.10 and this order totals €48.30. The sheet estimated tax from the shipping address; the final amount uses the card's billing address.

**The buyer** gets a sentence appended to the order-received text:

> You saved €3.80. We estimated €52.10 at checkout, but the tax for your billing address is lower, so your actual charge is €48.30.

Known limitation: that sentence appears on the **classic** thank-you page only. It hangs off `woocommerce_thankyou_order_received_text`, which the blocks order-confirmation block does not use. A blocks buyer still gets the reduced charge and the merchant still gets the order note, there is simply no sentence explaining it.

## Decisions made in the sheet, and their lifetime

Three decisions taken inside the sheet have to survive until the order is created: the chosen shipping rate in [`RecordedShippingRate`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Helper/RecordedShippingRate.php), the tax basis in [`RecordedTaxBasis`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Helper/RecordedTaxBasis.php), and the total the sheet displayed in [`RecordedQuote`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Helper/RecordedQuote.php). All three extend [`SessionRecord`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/Helper/SessionRecord.php) and live in the WooCommerce session, not in the browser, because WooCommerce recalculates totals server-side and would discard a browser-held choice before the next request could read it.

A recorded decision must never affect anything but the wallet payment that created it:

- Each record expires 15 minutes after it was written.
- The filters that read them are registered by [`SdkV6Module`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/src/SdkV6Module.php) and active only during the plugin's own wallet AJAX requests.
- Cancelling the sheet drops all three, leaving the page behind it exactly as it was.
- The recorded shipping rate can only fill a selection WooCommerce itself just cleared. It can never override a rate the buyer actively chose.

## Sheet behavior worth knowing

- An address with no rates is reported against the address field. The sheet stays open with the last total that priced, so the buyer can correct it.
- A sheet that cannot price what it is about to charge is aborted rather than degraded. No stale total is charged.
- Rapid address or rate changes are chained rather than raced by [`createShippingController()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/methods/methodShipping.js), so the buyer always ends on the total for their newest choice.
- Apple presents the first shipping method in the list as the chosen one, so [`applePayShipping`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/applePayShipping.js) moves the selected rate to the front.
- Google renders no price beside a shipping option, so [`googlePayShipping`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-sdk-v6/resources/js/wallets/googlePayShipping.js) writes each option's cost into its description.

## Source layout

| Path                                        | Contains                                                 |
|---------------------------------------------|----------------------------------------------------------|
| `src/Endpoint/CartQuoteEndpoint.php`   | Prices a selection, refuses an increase                  |
| `src/Helper/SessionRecord.php`        | Session record base: write, read, expire, forget         |
| `src/Helper/RecordedShippingRate.php`       | The rate the buyer picked in the sheet                   |
| `src/Helper/RecordedTaxBasis.php`           | The address tax is calculated from                       |
| `src/Helper/RecordedQuote.php`              | The sheet's total, the order note, the buyer's message   |
| `resources/js/methods/methodShipping.js`    | Method-agnostic quoting, and the commit at authorization |
| `resources/js/methods/shippingQuote.js`     | The quote shape both sheets are built from               |
| `resources/js/wallets/walletContacts.js`    | Wallet contacts mapped to WooCommerce address fields     |
| `resources/js/wallets/applePayShipping.js`  | Apple sheet protocol only                                |
| `resources/js/wallets/googlePayShipping.js` | Google sheet protocol only                               |

---

Related: [Wallets (Overview)](wallets.md)
