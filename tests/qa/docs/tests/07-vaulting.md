
# Vaulting automated tests

## Tests data

### My Account tests

- Payment details (type `Pcp.Payment`)
- Registered customer (type `WooCommerce.CreateCustomer`)

### Transaction tests

- Default order (type `ShopOrder`) by registered customer.

- Payment details (type `Pcp.Payment`)

    - Additional flag `saveToAccount: boolean`. Always true for PayPal (should be always saved if vaulting is enabled). For ACDC, depending on test case, can be true or false resulting in saved or not saved card for the customer.

    - Additional flag `isVaulted: boolean` - to use vaulted payment method in `PayPalUi.makePayment` method.

- Registered customer (type `WooCommerce.CreateCustomer`)

## Preconditions

- `beforeAll` hook to setup store (USA, USD, default taxes, etc.) and plugin settings (install PCP, reset DB, connect merchant, enable APMs and vaulting settings).
- Second `beforeAll` hook to recreate the customer before the test case and restore his storage state.
- Optionally, depending on scenario, inside of the test - save payment method (for vaulted PayPal account session needs to be preserved).

## Main test scenarios

### My Account

- PCP-0000 | Vaulting - My Account - Payment Methods - PayPal - Save payment method
- PCP-0000 | Vaulting - My Account - Payment Methods - ACDC - Save payment method
- PCP-0000 | Vaulting - My Account - Payment Methods - PayPal - Delete payment method
- PCP-0000 | Vaulting - My Account - Payment Methods - ACDC - Delete payment method
- PCP-0000 | Vaulting - My Account - Payment Methods - PayPal - Unable to save additional account
- PCP-0000 | Vaulting - My Account - Payment Methods - ACDC - Save additional card
- PCP-0000 | Vaulting - My Account - Payment Methods - PayPal - Make default **(not yet automated)**
- PCP-0000 | Vaulting - My Account - Payment Methods - ACDC - Make default **(not yet automated)**

Approximate scenario for "Save payment method":

1. Assert customer has no saved payment method.

2. Save payment method via My Account > Payment methods > Add payment method.

3. Assert payment method has been saved.

### Vauting transaction on Checkout

> Note: similar for Classic checkout, Checkout, Pay for Order.

- PCP-0000 | Vaulting - Transaction - Classic checkout - PayPal - Save payment method
- PCP-0000 | Vaulting - Transaction - Classic checkout - ACDC - Do not save payment method
- PCP-0000 | Vaulting - Transaction - Classic checkout - ACDC - Save payment method
- PCP-0000 | Vaulting - Transaction - Classic checkout - ACDC - Pay with card other then saved, not saving it
- PCP-0000 | Vaulting - Transaction - Classic checkout - ACDC - Pay with card other then saved, saving it
- PCP-0000 | Vaulting - Transaction - Classic checkout - PayPal - Pay with saved payment method
- PCP-0000 | Vaulting - Transaction - Classic checkout - ACDC - Pay with saved payment method

#### Approximate scenario for "Save payment method"

1. Assert customer has no saved payment method.

2. Add product to cart and perform a transaction.

3. Assert payment method has been saved. Depends on specified `saveToAccount` flag.

#### Approximate scenario for "Pay with saved payment method"

1. Save payment method via My Account.

2. Add product to cart and perform a transaction, asserting the display of vaulted pyment method.

3. Assert details on Order Received page.

4. Get order and payment details via PayPal API and assert data has been transferred correctly (PayPal account or card number).

5. Assert details on WooCommerce Order Edit page (order status, PayPal fees, payout, etc.)

### Vauting transaction on other pages

> Note 1: similar for Classic cart, Cart, Product, (Minicart?).

> Note 2: Only for PayPal, since ACDC is only available on checkout pages.

- PCP-0000 | Vaulting - Transaction - Classic cart - PayPal - Save payment method
- PCP-0000 | Vaulting - Transaction - Classic cart - PayPal - Pay with saved payment method

Approximate scenarios are similar to Classic checout section.
