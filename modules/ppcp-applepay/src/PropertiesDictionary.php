<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

/**
 * Class PropertiesDictionary
 */
class PropertiesDictionary {


	public const VALIDATION_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::VALIDATION_URL,
		);

	public const BILLING_CONTACT_INVALID = 'billing Contact Invalid';

	public const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::PRODUCT_ID,
			self::PRODUCT_QUANTITY,
			self::BILLING_CONTACT,
			self::SHIPPING_CONTACT,
		);

	public const UPDATE_METHOD_CART_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::SHIPPING_METHOD,
			self::CALLER_PAGE,
			self::SIMPLIFIED_CONTACT,
		);

	public const UPDATE_CONTACT_CART_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::CALLER_PAGE,
			self::SIMPLIFIED_CONTACT,
			self::NEED_SHIPPING,
		);

	public const UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::PRODUCT_ID,
			self::PRODUCT_QUANTITY,
			self::CALLER_PAGE,
			self::SIMPLIFIED_CONTACT,
			self::NEED_SHIPPING,
		);

	public const VALIDATION_URL = 'validationUrl';

	public const UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::PRODUCT_ID,
			self::PRODUCT_QUANTITY,
			self::SHIPPING_METHOD,
			self::CALLER_PAGE,
			self::SIMPLIFIED_CONTACT,
		);

	public const PRODUCT_ID = 'productId';

	public const SIMPLIFIED_CONTACT = 'simplifiedContact';

	public const SHIPPING_METHOD = 'shippingMethod';

	public const SHIPPING_CONTACT = 'shippingContact';

	public const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';

	public const NONCE = 'nonce';

	public const WCNONCE = 'woocommerce-process-checkout-nonce';

	public const CREATE_ORDER_CART_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::BILLING_CONTACT,
			self::SHIPPING_CONTACT,
		);

	public const PRODUCT_QUANTITY = 'productQuantity';

	public const CALLER_PAGE = 'callerPage';

	public const BILLING_CONTACT = 'billingContact';

	public const NEED_SHIPPING = 'needShipping';

	public const UPDATE_SHIPPING_CONTACT = 'woocommerce_paypal_payments_update_shipping_contact';

	public const UPDATE_SHIPPING_METHOD = 'woocommerce_paypal_payments_update_shipping_method';

	public const VALIDATION = 'woocommerce_paypal_payments_validation';

	public const CREATE_ORDER = 'woocommerce_paypal_payments_create_order';

	public const CREATE_ORDER_CART = 'woocommerce_paypal_payments_create_order_cart';

	public const REDIRECT = 'woocommerce_paypal_payments_redirect';
}
