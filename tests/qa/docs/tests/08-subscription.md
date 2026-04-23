
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

- **Vaulting subscription:** renewal using _vaulted_ payment method (default, by WooCommerce). Available for merchants with reference transactions.

- **PayPal subscription:** renewal controlled by PayPal. Steps to enable:
    - After onboarding with Subscriptions - turn off vaulting. Alternative (not available at the moment): onboard merchant without reference transactions.
    - Create subscription products, connected to PayPal plan.
    - Only for payments via PayPal.

Additional cases:

- Guest should be automatically registered as a customer after purchasing subscription product.
- Should be possible to pay for the _free trial_ subscription product with 0 initial cost.

See also [Confluence page](https://inpsyde.atlassian.net/wiki/spaces/PPCP/pages/5939528519/Subscriptions) and [diagrams](https://app.diagrams.net/#G1GvnwmqQrGyliKOQvN_NUcKmFqdEnttQj#%7B%22pageId%22%3A%22dQ8vNgNpgCCAkG_TZ67i%22%7D).

## Tests data

- Order (type `ShopOrder`) with tested subscription product.
- Payment details (type `Pcp.Payment`).
- Guest or registered customer (type `WooCommerce.CreateCustomer`).
- Tested subscription ptoduct.

## Preconditions

- Before all tests:
    - Install WooCommerce Subscriptions plugin.
    - Create subscription products:
        - Simple subscription product (for _Vaulting subscription_ tests).
        - Simple subscription product connected to PayPal plan (for _PayPal subscription_ tests).
        - Simple subscription product with free trial (for _Vaulting subscription_ tests).
        - Simple subscription product with free trial connected to PayPal plan (for _PayPal subscription_ tests).

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

#### Approximate scenario for guest

1. As a guest make order of subscription product using tested payment method.

2. Assert details on Order Received page.

3. Assert customer's subscription payment method. 

4. Assert that guest is automatically registered as a customer and logged in.

5. Assert payment method
	
	5.1 For vaulting subsctiption: is saved on customer's My Account and checkout pages.

	5.2 For PayPal subsctiption: is not saved on customer's My Account and checkout pages.

#### Approximate scenario for customer

1. Login as customer (use precreated storage state).

2. Assert customer has no saved payment methods.

3. Make order of subscription product using tested payment method.

4. Assert details on Order Received page.

5. Assert customer's subscription payment method. 

6. Assert payment method
	
	6.1 For vaulting subsctiption: is saved on customer's My Account and checkout pages.

	6.2 For PayPal subsctiption: is not saved on customer's My Account and checkout pages.

### Subscription order on other pages _TODO_

> Note 1: similar for Classic cart, Cart, Product, (Minicart?).

> Note 2: Only for PayPal, since ACDC is only available on checkout pages.

- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Order by guest
- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Free trial order by guest

- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Order by customer
- PCP-0000 | Vaulting subscription - Transaction - Cart - PayPal - Free trial order by customer

- PCP-0000 | PayPal subscription - Transaction - Cart - Order by customer
- PCP-0000 | PayPal subscription - Transaction - Cart - Free trial order by customer

Approximate scenarios are similar to Checkout section.

### Subscription Renewal _TODO_

- PCP-0000 | Vaulting subscription - PayPal - Order renewal
- PCP-0000 | Vaulting subscription - ACDC - Order renewal

- PCP-0000 | Vaulting subscription - PayPal - Free trial order renewal
- PCP-0000 | Vaulting subscription - ACDC - Free trial order renewal

    - PCP-0000 | PayPal subscription - Order renewal
- PCP-0000 | PayPal subscription - Free trial order renewal

#### Approximate scenario for renewal

1. Create active subscription order for customer

2. **For non-free-trial and non-PayPal subscription** get order and payment details via PayPal API and assert data has been transferred correctly (PayPal account or card number).

3. Assert details on WooCommerce Order Edit page including Related Orders table(order status, PayPal fees, payout, related subscription and initial order, etc.).

4. Assert details on WooCommerce Subscription Edit page including Related Orders table (Subscription status, parent order, etc.).

5. Trigger subscription renewal for the tested renewal type and payment method.

6. Assert details on WooCommerce Order Edit page including Related Orders table (order status, PayPal fees, payout, related subscription and initial order, etc.).

7. Assert details on WooCommerce Subscription Edit page including Related Orders table (Subscription status, parent order, renewal order, etc.).

8. Assert subscription on custommer's Subscriptions page including Related Orders table.
