# Seller Status Filter

## Overview
Two WordPress filters allow external plugins to override or replace the PayPal merchant-integrations API response. This is useful when the API is unavailable (e.g., Stage environment returning 404) or when testing specific feature eligibility.

## Filters

### `woocommerce_paypal_payments_seller_status`
Filters the `SellerStatus` object on every return from `PartnersEndpoint::seller_status()` — whether from cache or a fresh API call.

```php
add_filter( 'woocommerce_paypal_payments_seller_status', function ( SellerStatus $status ): SellerStatus {
    // Modify and return.
    return $status;
} );
```

### `woocommerce_paypal_payments_seller_status_fallback`
Provides a fallback `SellerStatus` when the API call fails. Return a `SellerStatus` to use it; return `null` to let the exception propagate.

```php
add_filter( 'woocommerce_paypal_payments_seller_status_fallback', function () {
    return new SellerStatus( array(), array(), 'US' );
} );
```

## Example: Enable ACDC + Vaulting on Stage
```php
add_filter( 'woocommerce_paypal_payments_seller_status_fallback', function () {
    return new SellerStatus( array(), array(), 'US' );
} );

add_filter( 'woocommerce_paypal_payments_seller_status', function ( SellerStatus $status ): SellerStatus {
    // Build modified products/capabilities and return a new SellerStatus.
} );
```
