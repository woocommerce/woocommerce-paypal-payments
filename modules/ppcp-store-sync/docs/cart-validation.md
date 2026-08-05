# Cart validation

Validators enforce store rules on an agent's cart. Each issue they report travels back to the agent, which resolves it with the shopper before trying again. Any unresolved issue blocks checkout.

Validation runs when a cart is created, replaced, or checked out. Reading a cart does not re-validate it.

## Writing a validator

Implement one method:

```php
interface ValidatorInterface {
	/**
	 * @return ValidationIssue|ValidationIssue[]|null
	 */
	public function validate( StorePayPalCart $store_cart );
}
```

Return `null` or an empty array when everything is fine. `InventoryValidator` is the reference implementation: it shows the issue-building chain, a context object, and two resolution options.

Validators see the issues that earlier validators already reported, so you can skip work that is already covered. `InventoryValidator` opens with `has_issue_with_code( ErrorCode::INVENTORY_ISSUE )` and bails out for exactly that reason.

## What the cart object gives you

`PayPalCart` is what the agent claimed. `StorePayPalCart` is what the store knows. Validation is mostly the business of comparing the two, so `StoreCartItem` exposes both sides: `real_price()` against `assumed_price_as_money()`, plus ready-made comparisons like `is_price_correct()` and `is_currency_correct()`.

| Method          | Returns                                                                         |
|-----------------|---------------------------------------------------------------------------------|
| `cart_items()`  | `StoreCartItem[]`, each pairing the agent's item with the resolved `WC_Product` |
| `wc_cart()`     | A real `WC_Cart`, or `null` when one could not be built                         |
| `currency()`    | The store currency                                                              |
| `validation()`  | The `StoreValidation` collector for this request                                |
| `paypal_cart()` | The unmodified agent payload                                                    |

Use `$item->field_path()` for the `field` on an issue, so the agent knows which item you are complaining about.

## Registering a validator

```php
add_action(
	'woocommerce_paypal_payments_store_sync_validators',
	function ( CartValidationProcessor $processor ): void {
		$processor->register_validator( new My_Stock_Rule() );
	}
);
```

The action fires once per request, just before the first validation. Built-in validators register on the same hook and usually run first, in the order listed in `StoreSyncModule::CART_VALIDATION_SERVICES`.

1. Validators are stored **keyed by class name**. Registering a second instance of the same class replaces the first rather than adding it.
2. Exceptions are **caught and logged**, then that validator is skipped. A validator that throws will not break the endpoint, but it also will not tell you it failed unless you read the log.

## Reporting an issue

An issue combines a code and type (fixed by the factory you choose), a shopper-facing message, an optional context object carrying machine-readable detail, and up to five resolution options describing what the agent may offer:

```php
return ValidationIssue::create_item_out_of_stock( 'Product is no longer available' )
	->user_message( sprintf( '%s is currently out of stock.', $product->get_name() ) )
	->for_field( $item->field_path() )
	->add_context( InventoryIssueContext::create_item_out_of_stock()->item_id( $item->id() ) )
	->add_resolution( ResolutionOption::create_remove_item()->label( 'Remove from cart' )->priority( Priority::HIGH ) );
```

`ValidationIssue` holds the available factories, each documented with when to use it. `ResolutionOption` holds one factory per action in `ResolutionAction`. Context classes live in `src/Validation/Context/`, one per error category.

Inside a validator you may also use the `add_*()` shortcuts on `StoreValidation` instead of returning the issue.

## Schema limits

Each of these is applied silently, so nothing warns you when content is lost:

| Rule               | Limit                               |
|--------------------|-------------------------------------|
| `message`          | Truncated to 255 characters         |
| `user_message`     | Truncated to 500 characters         |
| Resolution options | Maximum 5; further ones are ignored |
| Context objects    | Only the first is serialized        |

## Adding a new issue type or resolution action

From a plugin, you cannot mint a new error code or type: the constructor is private and each factory fixes its own pair. Use `ValidationIssue::create_business_rule_violation()` or `create_invalid_data()`, which exist for this purpose, and carry your specifics in the field path, the user message, a context object, and `ResolutionOption::set_meta()`.

Contributors adding one work outward from the enum: add the `specific_issue` constant to the matching `Context*Issue` enum, add a factory to the matching context class, then add the `create_*()` factory to `ValidationIssue`. Resolution actions follow the same path through `ResolutionAction` and `ResolutionOption`. These enums mirror PayPal's published schema, and PayPal rejects values outside it, so extending them is not a local decision.

## Coupons

`CouponValidator` delegates to WooCommerce and translates the outcome. One detail is worth knowing before touching it: WooCommerce coupon error messages are translated, so the validator captures the numeric error code through `woocommerce_coupon_error` and maps that instead of matching message text. Pattern matching would silently stop working on any non-English store.

Suggested alternative coupons are off by default, because the module cannot know which coupons a store is willing to reveal.

## Hooks

| Hook                                                                    | Type   | Arguments                                                                                                                           | Source                                                            |
|-------------------------------------------------------------------------|--------|-------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------|
| `woocommerce_paypal_payments_store_sync_validators`                     | action | `CartValidationProcessor $processor`                                                                                                | `src/CartValidation/CartValidationProcessor.php:87`               |
| `woocommerce_paypal_payments_store_sync_item_requires_signature`        | filter | `bool $required`, `WC_Product $product`, `CartItem $item`                                                                           | `src/CartValidation/ShippingValidator.php:304`                    |
| `woocommerce_paypal_payments_store_sync_coupon_validation_user_message` | filter | `string $message`, `string $issue_type`, `string $code`, `?WC_Coupon $coupon`, `PayPalCart $cart`, `array $context`                 | `src/CartValidation/CouponValidator/CouponValidator.php:400`      |
| `woocommerce_paypal_payments_store_sync_coupon_validation_resolutions`  | filter | `ResolutionOption[] $resolutions`, `string $issue_type`, `string $code`, `?WC_Coupon $coupon`, `PayPalCart $cart`, `array $context` | `src/CartValidation/CouponValidator/CouponValidator.php:483`      |
| `woocommerce_paypal_payments_store_sync_suggested_alternative_coupons`  | filter | `string[] $codes`, `string $failed_code`, `string $reason`, `PayPalCart $cart`                                                      | `src/CartValidation/CouponValidator/CouponContextBuilder.php:273` |
