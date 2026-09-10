# SDK v6 documentation

Contributor documentation for `modules/ppcp-sdk-v6`, the PayPal Web SDK v6 integration.

| Page                                                  | Subject                                                                        |
|-------------------------------------------------------|--------------------------------------------------------------------------------|
| [SDK loading](sdk-loading.md)                         | The client token, the component list, and the one instance a page gets         |
| [Wallets](wallets.md)                                 | Apple Pay and Google Pay: eligibility, placement, the payment sequence         |
| [Wallet shipping and tax](wallet-shipping-and-tax.md) | Who collects the shipping address, and why a sheet total can be an estimate    |
| [3D Secure](three-d-secure.md)                        | Where the contingency comes from, which methods send it, the payer-action fork |
| [Fastlane](fastlane.md)                               | How `ppcp-axo` takes Fastlane off the v6 instance                              |

[`payment-test.html`](payment-test.html) is a standalone harness for driving the v6 flows without WordPress. Load it via the local dev environment, enter sandbox credentials, and pay.
