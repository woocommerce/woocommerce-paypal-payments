
# Subscriptions automated tests

## Table of Content

- [Overview](#overview)
- [Tests data](#tests-data)
- [Preconditions](#preconditions)
- [Main test scenarios](#main-test-scenarios)
	- [Subscription order on checkout](#subscription-order-on-checkout)
		- [Approximate scenario for guest](#approximate-scenario-for-guest)
		- [Approximate scenario for customer](#approximate-scenario-for-customer)
	- [Subscription order on other pages](#subscription-order-on-other-pages)
	- [Subscription Renewal](#subscription-renewal)
		- [Approximate scenario for renewal](#approximate-scenario-for-renewal)

## Overview

WooCommerce Subscriptions plugin provides functionality for products of _subscription_ type. Once a subscription product is purchased it is expected to be automatically reordered after certain period of time (_renewal process_), using payment method provided for the initial order.

There are 2 main types of _renewal process_:
- **Vaulting subscription:** renewal using _vaulted_ payment method (by WooCommerce). Available for merchants **with** reference transactions.
- **PayPal subscription:** renewal controlled by PayPal. Only available for
	- Merchants **without** reference transactions. _TODO_
	- Products connected to PayPal plan. _TODO_
	- Payments with PayPal.

Additional cases:
- Guest should be automatically registered as a customer after purchasing subscription product.
- Should be possible to pay for the _free trial_ subscription product with 0 initial cost.

See also [Confluence page](https://inpsyde.atlassian.net/wiki/spaces/PPCP/pages/5939528519/Subscriptions) and [diagrams](https://app.diagrams.net/#G1GvnwmqQrGyliKOQvN_NUcKmFqdEnttQj#%7B%22pageId%22%3A%22dQ8vNgNpgCCAkG_TZ67i%22%7D).

## Tests data

- Order (type `ShopOrder`) with tested subscription product. _TODO_
- Payment details (type `Pcp.Payment`).
- Guest or registered customer (type `WooCommerce.CreateCustomer`).
- Merchant (with or without reference transaction). _TODO_

## Preconditions

- Before all tests:
	- Install WooCommerce Subscriptions plugin.
	- Create subscription products:
		- Simple subscription product (for _Vaulting subscription_ tests) _TODO_
		- Simple subscription product connected to PayPal plan (for _PayPal subscription_ tests) _TODO_
		- Simple subscription product with free trial (for _Vaulting subscription_ tests) _TODO_
		- Simple subscription product with free trial connected to PayPal plan (for _PayPal subscription_ tests) _TODO_

- `beforeAll` hook (in the spec):
	- Activate WooCommerce Subscriptions plugin.
	- Setup store: USA, USD, default taxes, shipping, etc.
	- Plugin settings
		- Install/activate PCP
		- Reset DB
		- Connect tested merchant with subscription products, enabled APMs.

- For tests with registered customer - additional `beforeAll` hook to recreate the customer before the test case and restore his storage state.

- For renewal tests (inside of the test) - make initial subscription transaction.

## Main test scenarios

### Subscription order on checkout

> Note: similar for Classic checkout Checkout, Pay for Order.

- PCP-0000 | Vaulting subscription - Transaction - Checkout - PayPal - Order by guest
- PCP-0000 | Vaulting subscription - Transaction - Checkout - ACDC - Order by guest
- PCP-0000 | Vaulting subscription - Transaction - Checkout - PayPal - Free trial order by guest
- PCP-0000 | Vaulting subscription - Transaction - Checkout - ACDC - Free trial order by guest

- PCP-0000 | Vaulting subscription - Transaction - Checkout - PayPal - Order by customer
- PCP-0000 | Vaulting subscription - Transaction - Checkout - ACDC - Order by customer
- PCP-0000 | Vaulting subscription - Transaction - Checkout - PayPal - Free trial order by customer
- PCP-0000 | Vaulting subscription - Transaction - Checkout - ACDC - Free trial order by customer

- PCP-0000 | PayPal subscription - Transaction - Checkout - Order by guest
- PCP-0000 | PayPal subscription - Transaction - Checkout - Free trial order by guest

- PCP-0000 | PayPal subscription - Transaction - Checkout - Order by customer
- PCP-0000 | PayPal subscription - Transaction - Checkout - Free trial order by customer

#### Approximate scenario for guest:

1. As a guest make order of subscription product using tested payment method.

2. Assert details on Order Received page.

3. Assert that guest is automatically registered as a customer and logged in.

4. Assert payment method is saved on customer's My Account page.

5. Assert subscription on custommer's Subscriptions page.

6. Get order and payment details via PayPal API and assert data has been transferred correctly (PayPal account or card number).

7. Assert details on WooCommerce Order Edit page (order status, PayPal fees, payout, related subscription and initial order, etc.).

8. Assert details on WooCommerce Subscription Edit page (Subscription status, parent order, etc.).

#### Approximate scenario for customer:

1. Login as customer (use precreated storage state).

2. Assert customer has no saved payment methods.

3. Make order of subscription product using tested payment method.

4. Assert details on Order Received page.

5. Assert payment method is saved on customer's My Account page.

6. Assert subscription on custommer's Subscriptions page.

7. Get order and payment details via PayPal API and assert data has been transferred correctly (PayPal account or card number).

8. Assert details on WooCommerce Order Edit page (order status, PayPal fees, payout, related subscription and initial order, etc.).

9. Assert details on WooCommerce Subscription Edit page (Subscription status, parent order, etc.).

### Subscription order on other pages

> Note 1: similar for Classic cart, Cart, Product, (Minicart?).

> Note 2: Only for PayPal, since ACDC is only available on checkout pages.

- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Order by guest
- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Free trial order by guest

- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Order by customer
- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Free trial order by customer

- PCP-0000 | PayPal subscription - Transaction - Cart - Order by customer
- PCP-0000 | PayPal subscription - Transaction - Cart - Free trial order by customer

Approximate scenarios are similar to Checkout section.

### Subscription Renewal

- PCP-0000 | Vaulting subscription - PayPal - Order renewal
- PCP-0000 | Vaulting subscription - ACDC - Order renewal
- PCP-0000 | Vaulting subscription - PayPal - Free trial order renewal
- PCP-0000 | Vaulting subscription - ACDC - Free trial order renewal

- PCP-0000 | PayPal subscription - Order renewal
- PCP-0000 | PayPal subscription - Free trial order renewal

#### Approximate scenario for renewal:

1. Provide customer with active subscription order

2. Trigger subscription renewal for the tested renewal type and payment method. _TODO_

3. Assert subscription on custommer's Subscriptions page.

4. Get order and payment details via PayPal API and assert data has been transferred correctly (PayPal account or card number).

5. Assert details on WooCommerce Order Edit page (order status, PayPal fees, payout, related subscription and initial order, etc.).

6. Assert details on WooCommerce Subscription Edit page (Subscription status, parent order, renewal order, etc.).
