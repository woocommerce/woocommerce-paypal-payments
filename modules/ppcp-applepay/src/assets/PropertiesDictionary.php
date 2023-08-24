<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

/**
 * Class PropertiesDictionary
 */
class PropertiesDictionary {

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

	public const UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::PRODUCT_ID,
			self::PRODUCT_QUANTITY,
			self::SHIPPING_METHOD,
			self::CALLER_PAGE,
			self::SIMPLIFIED_CONTACT,
		);

	public const PRODUCT_ID = 'product_id';

	public const SIMPLIFIED_CONTACT = 'simplified_contact';

	public const SHIPPING_METHOD = 'shipping_method';

	public const SHIPPING_CONTACT = 'shipping_contact';

	public const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';

	public const NONCE = 'nonce';

	public const WCNONCE = 'woocommerce-process-checkout-nonce';

	public const CREATE_ORDER_CART_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::BILLING_CONTACT,
			self::SHIPPING_CONTACT,
		);

	public const PRODUCT_QUANTITY = 'product_quantity';

	public const CALLER_PAGE = 'caller_page';

	public const BILLING_CONTACT = 'billing_contact';

	public const NEED_SHIPPING = 'need_shipping';

	public const UPDATE_SHIPPING_CONTACT = 'ppcp_update_shipping_contact';

	public const UPDATE_SHIPPING_METHOD = 'ppcp_update_shipping_method';

	public const CREATE_ORDER = 'ppcp_create_order';

	public const CREATE_ORDER_CART = 'ppcp_create_order_cart';

	public const REDIRECT = 'ppcp_redirect';
}
