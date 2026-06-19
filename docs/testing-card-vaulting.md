# Card vaulting & vaulted checkout: testing notes

Vaulting tests need PayPal's "**save payment methods**" test cards, which are **not
interchangeable** with the "purchase flow" cards. Using the wrong table is the most common mistake
here.

## Sandbox setup

To exercise the 3DS path at all, set the plugin's **3D Secure** option to **ALWAYS**. The sandbox
does not require 3DS by default, so with any other setting the test cards below authenticate without
triggering the flow you want to test.

## 3DS modes

3D Secure authentication has two possible behaviors. Both must work identically; the only difference
is whether the shopper has to enter an OTP.

|                  | Frictionless ("no challenge")             | Step-up ("challenge")                |
|------------------|-------------------------------------------|--------------------------------------|
| **What it is**   | No user input; the bank approves silently | A manual OTP step is required        |
| **New card**     | Overlay closes itself after a few seconds | Overlay expands into an OTP form     |
| **Vaulted card** | Redirects out, then back to the shop      | Redirects out, returns after the OTP |

- "New card" = entering card details at checkout or adding one in My Account.
- "Vaulted card" = paying with a saved card at checkout.
- "Checkout" = Block checkout or Classic checkout, both are identical in this aspect.

## Cards to use for vaulting tests

Current "Save payment methods" table (expiry = `01/current year + 3`).

| Network    | Frictionless       | Step-up            |
|------------|--------------------|--------------------|
| Visa       | `4000000000002701` | `4000000000002503` |
| Mastercard | `5200000000002235` | `5200000000002151` |

Do **not** use purchase-flow cards (e.g. `4868719196829038`, `4868719166101368`,
`5329879707824603`) for vaulting. They mint a token that looks valid (every request returns `2xx`),
but the later charge fails with a very visible `403 PERMISSION_DENIED`.

## References

- [Cards for "Save payment methods"](https://developer.paypal.com/docs/checkout/advanced/customize/3d-secure/test/#test-cases-and-card-details-for-save-payment-methods) ←
  *what we need for vaulting!*
- [Cards for "Purchase flow"](https://developer.paypal.com/docs/checkout/advanced/customize/3d-secure/test/#test-cases-and-card-details-for-purchase-flows) -
  *not usable for vaulting, added for distinction*

## Test coverage

The 3DS authentication itself runs in the browser SDK and on PayPal's servers, so the end-to-end
save + 3DS + charge flow can only be verified **manually** (real browser, with the sandbox setup and
save-flow cards above). A live-API integration test would be non-deterministic.

The surrounding plugin logic is covered by automated tests that mock the PayPal HTTP boundary and
assert what our code sends and does with responses:

- [Integration: VaultedCard3dsTransactionTest.php](../tests/integration/PHPUnit/Transaction/VaultedCard3dsTransactionTest.php):
  resume-nonce / capture / authorize state machine (uses a `payer-action` link to represent
  "3DS required").
- [Integration: WooCommercePaymentTokensTest.php](../tests/integration/PHPUnit/Vaulting/WooCommercePaymentTokensTest.php):
  response → `WC_Payment_Token_CC` persistence.
- [Unit: CaptureCardPaymentTest.php](../tests/PHPUnit/WcGateway/Endpoint/CaptureCardPaymentTest.php):
  vaulted-charge order-body construction.
- [Unit: CreateSetupTokenTest.php](../tests/PHPUnit/SavePaymentMethods/Endpoint/CreateSetupTokenTest.php)
  and [Unit: CreatePaymentTokenTest.php](../tests/PHPUnit/SavePaymentMethods/Endpoint/CreatePaymentTokenTest.php):
  vault-token setup-request body and exchange-response handling.
