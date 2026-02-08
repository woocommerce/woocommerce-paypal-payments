# Coupon Validator

Validates WooCommerce coupons and provides enhanced context to PayPal's Agentic Commerce API.

## Quick Reference

**Core validation features:**

1. Case-insensitive coupon code matching via `wc_sanitize_coupon_code()`
2. Comprehensive WooCommerce coupon validation (expiry, usage limits, restrictions)
3. Enhanced context for failed validations (eligible items, shortage amounts, discount comparisons)
4. Uses `WC_Discounts` for accurate discount calculation
5. Minimal resolution options by default (suggested alternatives disabled unless explicitly enabled via filter)

## How It Works

**What it validates:**
- Coupon existence and expiration
- Usage limits (global and per-user)
- Minimum/maximum order amounts
- Product/category restrictions
- Email restrictions
- Coupon stacking rules
- WooCommerce coupon system status

**Validation flow:**
1. Filter coupons with `APPLY` action only (skip `REMOVE` actions)
2. Normalize coupon codes using `wc_sanitize_coupon_code()` for case-insensitive matching
3. Check if coupons are enabled in WooCommerce via `wc_coupons_enabled()`
4. Check for coupon stacking conflicts (individual_use coupons)
5. Validate each coupon via WooCommerce's `WC_Discounts::is_coupon_valid()`
6. Capture numeric error codes via `woocommerce_coupon_error` filter and map to PayPal issue types
7. Build enhanced context via declarative `context_builders` configuration
8. Apply filters to enrich data

**WooCommerce Integration:**
- Uses `wc_sanitize_coupon_code()` to normalize coupon codes (case-insensitive matching)
- Uses `WC_Discounts` for validation and discount calculation
- Uses `WC_Coupon::is_valid_for_product()` for product eligibility checking
- Uses `wc_price()` for currency formatting, respecting merchant's currency position settings
- Uses `wc_format_decimal()` for consistent decimal formatting
- Automatically supports third-party plugins that extend WooCommerce coupon functionality
- Stacking conflicts: By default, WooCommerce allows multiple coupons unless "Individual use only" is enabled
- When `individual_use=true`, the coupon cannot be combined with ANY other coupons

## Issue Types

| Issue Type | Triggered When |
|------------|----------------|
| `COUPON_EXPIRED` | Expiration date passed |
| `COUPON_NOT_EXIST` | Invalid/non-existent code |
| `MINIMUM_ORDER_NOT_MET` | Cart total below minimum |
| `MAXIMUM_ORDER_EXCEEDED` | Cart total above maximum |
| `COUPON_NOT_APPLICABLE` | Product/category restrictions not met |
| `USAGE_LIMIT_EXCEEDED` | Global or per-user limit hit |
| `COUPON_STACKING_NOT_ALLOWED` | Can't combine coupons (individual_use) |
| `COUPON_ALREADY_APPLIED` | Duplicate application |
| `COUPON_EMAIL_RESTRICTED` | Email restriction failed |
| `COUPON_INVALID` | Generic invalid coupon |
| `COUPON_NOT_SUPPORTED` | Coupons disabled in WooCommerce |

**Error Mapping:**

The validator maps WooCommerce errors to PayPal issue types using numeric error constants (100-116) from `WC_Coupon`. We use the `woocommerce_coupon_error` filter to capture the numeric error code when `WC_Discounts::is_coupon_valid()` fails.

Since WooCommerce error messages are localized (`esc_html__`), we cannot rely on pattern matching - instead we capture the numeric error code via the filter, making the validation work correctly for all languages.

## Enhanced Context Fields

Context automatically includes:

```php
// Basic context (always included)
'specific_issue' => 'COUPON_EXPIRED',
'coupon_code'    => 'SUMMER50',

// For minimum order not met
'minimum_required' => '100.00',
'current_subtotal' => '19.99',
'shortage_amount'  => '80.01',

// For product restrictions
'eligible_items' => array( 'PRODUCT-A', 'PRODUCT-B' ),

// For stacking conflicts
'current_discount'   => '32.00',
'attempted_discount' => '9.99',
```

**Note:** Suggested alternative coupons are **disabled by default**. Enable them via filter:

```php
add_filter(
    'woocommerce_paypal_payments_agentic_commerce_suggested_alternative_coupons',
    function ( $alternatives, $failed_code, $specific_issue, $cart ) {
        // Return array of alternative coupon codes
        return array( 'SUMMER20', 'WELCOME15', 'FALL10' );
    },
    10,
    4
);
```

## API Response Examples

### Successful Coupon Application

```json
{
  "id": "CART-123",
  "status": "READY",
  "validation_status": "VALID",
  "validation_issues": [],
  "applied_coupons": [
    {
      "code": "GAMING20",
      "description": "20% off gaming accessories",
      "discount_amount": {"currency_code": "USD", "value": "32.00"}
    }
  ],
  "totals": {
    "item_total": {"currency_code": "USD", "value": "159.98"},
    "discount": {"currency_code": "USD", "value": "32.00"},
    "shipping": {"currency_code": "USD", "value": "9.99"},
    "tax_total": {"currency_code": "USD", "value": "11.52"},
    "amount": {"currency_code": "USD", "value": "127.98"}
  }
}
```

### Failed Coupon Validation (Default Response)

```json
{
  "id": "CART-123",
  "status": "INCOMPLETE",
  "validation_status": "INVALID",
  "validation_issues": [
    {
      "code": "PRICING_ERROR",
      "type": "BUSINESS_RULE",
      "message": "Coupon has expired",
      "user_message": "The coupon code 'SUMMER50' has expired.",
      "field": "coupons[0]",
      "context": {
        "specific_issue": "COUPON_EXPIRED",
        "coupon_code": "SUMMER50",
        "expiration_date": "2024-05-31T23:59:59Z"
      },
      "resolution_options": [
        {
          "action": "REMOVE_COUPON",
          "label": "Remove coupon and continue",
          "metadata": {"priority": "low"}
        }
      ]
    }
  ]
}
```

**Note:** By default, resolution options are minimal (usually just `REMOVE_COUPON`). Actions like `SUGGEST_ALTERNATIVE_COUPON`, `VIEW_AVAILABLE_COUPONS`, and `TRY_DIFFERENT_COUPON` are **not included by default** because:
- Most WooCommerce stores don't expose available coupons publicly
- Suggesting alternatives requires custom logic to determine which coupons are actually available
- These options should be explicitly enabled via the `woocommerce_paypal_payments_agentic_commerce_coupon_validation_resolutions` filter

## Filter Integration

Three filters allow third-party plugins (Advanced Coupons, Smart Coupons, etc.) to extend validation.

**These filters complement WooCommerce's native filters:**
- WooCommerce filters (`woocommerce_coupon_is_valid`, `woocommerce_coupon_error`, etc.) control core validation logic
- Our filters enrich the API response structure and provide AI agent guidance
- Third-party plugins can use both: WC filters for validation, our filters for Agentic Commerce features

### 1. Modify Resolution Options

Add or change resolution actions:

```php
add_filter(
    'woocommerce_paypal_payments_agentic_commerce_coupon_validation_resolutions',
    function ( $resolutions, $issue_type, $code, $wc_coupon, $cart, $context ) {
        if ( $issue_type === 'COUPON_NOT_APPLICABLE' ) {
            $resolutions[] = array(
                'action'   => 'EARN_POINTS',
                'label'    => 'Complete a purchase to earn points',
                'metadata' => array( 'priority' => 'high' ),
            );
        }
        return $resolutions;
    },
    10,
    6
);
```

### 2. Customize User Message

Personalize error messages (richer context than WooCommerce's `woocommerce_coupon_error`):

```php
add_filter(
    'woocommerce_paypal_payments_agentic_commerce_coupon_validation_user_message',
    function ( $user_message, $issue_type, $code, $wc_coupon, $cart, $context ) {
        $name = $cart->customer()['name'] ?? 'there';
        return "Hi {$name}! " . $user_message;
    },
    10,
    6
);
```

### 3. Control Alternative Coupons

Filter suggested alternatives:

```php
add_filter(
    'woocommerce_paypal_payments_agentic_commerce_suggested_alternative_coupons',
    function ( $alternatives, $failed_code, $specific_issue, $cart ) {
        if ( is_vip_customer( $cart ) ) {
            array_unshift( $alternatives, 'VIP25' );
        }
        return array_slice( $alternatives, 0, 5 );
    },
    10,
    4
);
```

## Complete Plugin Example

```php
<?php
/**
 * Plugin Name: Advanced Coupons - PayPal Agentic Integration
 */

class ACFW_PayPal_Agentic_Integration {

    public function __construct() {
        add_filter(
            'woocommerce_coupon_is_valid',
            array( $this, 'validate_bogo' ),
            10,
            3
        );

        add_filter(
            'woocommerce_paypal_payments_agentic_commerce_coupon_validation_resolutions',
            array( $this, 'add_bogo_resolutions' ),
            10,
            6
        );
    }

    public function validate_bogo( $valid, $wc_coupon, $discounts ) {
        $bogo = get_post_meta( $wc_coupon->get_id(), '_acfw_bogo_deals', true );

        if ( empty( $bogo ) ) {
            return $valid;
        }

        $cart_items = WC()->cart->get_cart();
        $has_trigger = false;

        foreach ( $cart_items as $item ) {
            if ( $item['product_id'] === $bogo['trigger_product'] ) {
                $has_trigger = true;
                break;
            }
        }

        if ( ! $has_trigger ) {
            throw new Exception(
                sprintf( 'Add %s to unlock this BOGO deal.', get_the_title( $bogo['trigger_product'] ) ),
                WC_Coupon::E_WC_COUPON_NOT_APPLICABLE
            );
        }

        return $valid;
    }

    public function add_bogo_resolutions( $resolutions, $issue_type, $code, $wc_coupon, $cart, $context ) {
        if ( $issue_type !== 'COUPON_NOT_APPLICABLE' || ! $wc_coupon ) {
            return $resolutions;
        }

        $bogo = get_post_meta( $wc_coupon->get_id(), '_acfw_bogo_deals', true );

        if ( $bogo ) {
            array_unshift( $resolutions, array(
                'action'   => 'ADD_PRODUCT',
                'label'    => sprintf( 'Add %s to unlock BOGO deal', get_the_title( $bogo['trigger_product'] ) ),
                'metadata' => array(
                    'priority'   => 'high',
                    'product_id' => $bogo['trigger_product'],
                ),
            ) );
        }

        return $resolutions;
    }
}

new ACFW_PayPal_Agentic_Integration();
```

## Best Practices

**Use Both WooCommerce and Agentic Filters**

Our filters complement WooCommerce's native filters:

```php
add_filter( 'woocommerce_coupon_is_valid', function( $valid, $coupon, $discounts ) {
    if ( ! custom_check( $coupon ) ) {
        return false;
    }
    return $valid;
}, 10, 3 );

add_filter( 'woocommerce_paypal_payments_agentic_commerce_coupon_validation_resolutions',
    function( $resolutions, $issue_type, $code, $wc_coupon, $cart, $context ) {
        $resolutions[] = array(
            'action' => 'CUSTOM_ACTION',
            'label'  => 'Custom resolution for this issue',
        );
        return $resolutions;
    }, 10, 6
);
```

**Preserve Existing Data**

When filtering, always preserve existing array keys:

```php
$context['custom_field'] = 'value';
return $context;
```

**Check Null Values**

WooCommerce coupon object can be null:

```php
if ( ! $wc_coupon ) {
    return $result;
}
```

**Use Specific Issue Types**

Filter only relevant validation scenarios:

```php
if ( $specific_issue !== 'COUPON_EXPIRED' ) {
    return $context;
}
```

**Clear Resolution Labels**

Provide actionable guidance for AI agents:

```php
'label' => 'Add $50.00 more to qualify'
```

## Implementation Details

**Architecture:**

Split into focused, single-responsibility classes:

| Class | Responsibility |
|-------|---------------|
| **CouponValidator** | Orchestrates validation flow, checks stacking conflicts, delegates to specialists |
| **CouponContextBuilder** | Builds enriched context data using declarative pattern, uses WC native methods |
| **DiscountCalculator** | Handles discount calculation via `WC_Discounts`, uses `wc_format_decimal()` |
| **CouponResolutionBuilder** | Builds resolution options from templates, handles stacking-specific resolutions |
| **AppliedCouponsBuilder** | Builds `applied_coupons` response data for successfully validated coupons |

**Declarative Configuration:**

Each issue type declares what it needs via `ISSUE_CONFIG`:

```php
'MINIMUM_ORDER_NOT_MET' => array(
    'message'          => 'Minimum order amount not met',
    'user_message'     => "The coupon '%s' requires a minimum order of %s. Your current order is %s.",
    'resolutions'      => array( 'add_items_to_minimum', 'continue_without' ),
    'context_builders' => array( 'minimum_spend', 'eligible_items' ),
),
```

Context builders are small focused methods in `CouponContextBuilder`:
- `build_expiration()` - Adds expiration date
- `build_usage_limits()` - Adds usage limit/count
- `build_minimum_spend()` - Adds minimum required, current subtotal, shortage amount
- `build_maximum_spend()` - Adds maximum allowed
- `build_eligible_items()` - Adds list of eligible product variant IDs
- `build_stacking()` - Adds stacking conflict details with discount comparison
- `build_email_restriction()` - Adds email restriction flag

Note: Alternative coupon suggestions are handled separately via the `woocommerce_paypal_payments_agentic_commerce_suggested_alternative_coupons` filter, not as a declarative context builder.

**Response Integration:**
- `ResponseFactory` uses `AppliedCouponsBuilder` to build coupon data before creating response objects
- Response DTOs (`CartResponse`, `NewCartResponse`, `PaidCartResponse`) receive `applied_coupons` as plain array data
- This keeps response classes as pure DTOs without business logic
- `AppliedCouponsBuilder::calculate_total_discount()` provides discount totals for PayPal order updates

