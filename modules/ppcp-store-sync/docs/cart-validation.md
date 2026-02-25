# Cart Validation

Cart validators verify business rules before cart operations complete. Each validator checks one specific aspect (inventory, pricing, shipping, etc.) and reports issues found.

## Quick Reference

**For experienced developers:**

1. Implement `ValidatorInterface` with a `validate()` method
2. Return `null` (valid) or `ValidationIssue` / `ValidationIssue[]` (problems found)
3. Register via `woocommerce_paypal_payments_agentic_commerce_validators` hook

## How Validation Works

**When validators run:**
- `POST /merchant-cart` - Cart creation
- `PUT /merchant-cart/{id}` - Cart updates
- `POST /merchant-cart/{id}/checkout` - Checkout attempts

**What happens with issues:**
1. AI agent receives all issues and attempts resolution with buyer
2. Checkout is blocked until all issues are resolved

**Execution order:** Validators run in registration order.

## Writing a Validator

### Interface Location

```
modules/ppcp-agentic-commerce/src/CartValidation/ValidatorInterface.php
```

### Return Values

Return one of:
- `null` or `array()` - No issues found
- `ValidationIssue` - Single issue
- `ValidationIssue[]` - Multiple issues

```php
// Valid cart.
return null;
return array();

// Single problem.
return new MissingField( 'shipping_address is required' );

// Multiple problems.
return array( $issue1, $issue2 );
```

### Simple Example

Minimum order validator with no dependencies:

```php
<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Helper\CartHelper;
use WooCommerce\PayPalCommerce\StoreSync\Validation\BusinessRuleViolation;

class MinimumOrderValidator implements ValidatorInterface {
    private const MIN_ORDER_AMOUNT = 15.00;

    public function validate( PayPalCart $cart ) {
        $total = CartHelper::cart_item_total( $cart );

        if ( $total < self::MIN_ORDER_AMOUNT ) {
            return new BusinessRuleViolation(
                sprintf( 'Order minimum is $%.2f', self::MIN_ORDER_AMOUNT )
            );
        }

        return null;
    }
}
```

### Example with Dependencies

Product availability validator using `ProductManager`:

```php
<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidData;

class ProductAvailabilityValidator implements ValidatorInterface {

    private ProductManager $product_manager;

    public function __construct( ProductManager $product_manager ) {
        $this->product_manager = $product_manager;
    }

    public function validate( PayPalCart $cart ) {
        $issues = array();

        foreach ( $cart->items() as $key => $item ) {
            $product = $this->product_manager->find_product( $item );

            if ( ! $product || $product->get_status() !== 'publish' ) {
                $issues[] = new InvalidData(
                    'Product is no longer available',
                    sprintf( 'Product "%s" cannot be purchased at the moment', $item->name() ),                    
                    "items[{$key}]"
                );
            }
        }

        return $issues ?: null;
    }
}
```

## Registration

### Internal Development (DI Container)

**Step 1:** Add validator to `modules/ppcp-agentic-commerce/services.php`:

```php
'agentic.validator.minimum-order' => static function ( ContainerInterface $c ): MinimumOrderValidator {
    return new MinimumOrderValidator();
},

'agentic.validator.product-availability' => static function ( ContainerInterface $c ): ProductAvailabilityValidator {
    return new ProductAvailabilityValidator(
        $c->get( 'agentic.helper.product-manager' )
    );
},
```

**Step 2:** Add service ID to `CART_VALIDATION_SERVICES` in `AgenticCommerceModule.php`:

```php
private const CART_VALIDATION_SERVICES = array(
    'agentic.validator.product',
    'agentic.validator.inventory',
    'agentic.validator.minimum-order',        // ← Your validator
    'agentic.validator.product-availability', // ← Your validator
);
```

**Important:** Array order determines execution order.
This array is then processed by the `woocommerce_paypal_payments_agentic_commerce_validators` action to register the validators.

### Third-Party Plugins (Hook-Based)

Third-party plugins cannot access the DI container. Use the WordPress action hook instead:

```php
add_action(
    'woocommerce_paypal_payments_agentic_commerce_validators',
    function( $processor ) {
        // Instantiate directly (check constructor for required dependencies)
        $validator = new MinimumOrderValidator();
        $processor->register_validator( $validator );
    }
);
```

**Complete plugin example:**

```php
<?php
/**
 * Plugin Name: Custom Cart Validation
 */
 
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Validation\BusinessRuleViolation;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;

class My_Custom_Validator implements ValidatorInterface {
    
    public function validate( PayPalCart $cart ) {
        if ( count( $cart->items() ) > 50 ) {
            return new BusinessRuleViolation( 'Maximum 50 items per cart' );
        }
        
        return null;
    }
}

add_action(
    'woocommerce_paypal_payments_agentic_commerce_validators',
    function( CartValidationProcessor $processor ) {
        $processor->register_validator( new My_Custom_Validator() );
    }
);
```

## Best Practices

**Single Responsibility**

Each validator checks one business rule. Don't combine inventory + pricing in one validator.

**Clear Messages**

Error messages are shown to buyers via AI agent. Be specific and actionable:

```php
// ✗ Bad - generic issue and vague message.
return new BusinessRuleViolation( 'Invalid order' );

// ✓ Good - specific issue, clear message.
return new ShippingUnavailable( 'This product ships to US addresses only' );
```

**Performance Matters**

- Leverage helper class caching where available
- Consider checking `$cart->has_validation_issue()` to skip redundant checks

**Validation Issue Types**

Locate the most suitable issue class in `modules/ppcp-agentic-commerce/src/Validation`. Consider adding a new one if needed.
