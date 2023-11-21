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
	public const DISALLOWED_USER_AGENTS = array(
		'Chrome/',
		'CriOS/', // Chrome on iOS.
		'Firefox/',
		'OPR/', // Opera.
		'Edg/', // Edge.
	);
	public const ALLOWED_USER_BROWSERS  = array( 'Safari' );
	public const ALLOWED_USER_DEVICES   = array( 'Macintosh', 'iPhone', 'iPad', 'iPod' );

	public const BILLING_CONTACT_INVALID = 'billing Contact Invalid';

	public const BILLING_DATA_MODE_USE_WC       = 'use_wc';
	public const BILLING_DATA_MODE_USE_APPLEPAY = 'use_applepay';
	public const BILLING_DATA_MODE_DEFAULT      = self::BILLING_DATA_MODE_USE_WC;

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

	public const PRODUCTS           = 'products';
	public const PRODUCT_ID         = 'product_id';
	public const PRODUCT_QUANTITY   = 'product_quantity';
	public const PRODUCT_VARIATIONS = 'product_variations';
	public const PRODUCT_EXTRA      = 'product_extra';
	public const PRODUCT_BOOKING    = 'product_booking';

	public const SIMPLIFIED_CONTACT       = 'simplified_contact';
	public const SHIPPING_METHOD          = 'shipping_method';
	public const SHIPPING_CONTACT         = 'shipping_contact';
	public const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';
	public const BILLING_CONTACT          = 'billing_contact';

	public const NONCE        = 'nonce';
	public const NONCE_ACTION = 'woocommerce-process_checkout';
	public const WCNONCE      = 'woocommerce-process-checkout-nonce';

	public const CREATE_ORDER_CART_REQUIRED_FIELDS =
		array(
			self::WCNONCE,
			self::BILLING_CONTACT,
			self::SHIPPING_CONTACT,
		);

	public const CALLER_PAGE = 'caller_page';

	public const NEED_SHIPPING = 'need_shipping';

	public const UPDATE_SHIPPING_CONTACT = 'ppcp_update_shipping_contact';
	public const UPDATE_SHIPPING_METHOD  = 'ppcp_update_shipping_method';
	public const CREATE_ORDER            = 'ppcp_create_order';
	public const CREATE_ORDER_CART       = 'ppcp_create_order_cart';
	public const REDIRECT                = 'ppcp_redirect';
	public const VALIDATE                = 'ppcp_validate';

	/**
	 * Returns the possible list of button colors.
	 *
	 * @return array
	 */
	public static function button_colors(): array {
		return array(
			'white'         => __( 'White', 'woocommerce-paypal-payments' ),
			'black'         => __( 'Black', 'woocommerce-paypal-payments' ),
			'white-outline' => __( 'White with outline', 'woocommerce-paypal-payments' ),
		);
	}

	/**
	 * Returns the possible list of button types.
	 *
	 * @return array
	 */
	public static function button_types(): array {
		return array(
			'book'       => __( 'Book', 'woocommerce-paypal-payments' ),
			'buy'        => __( 'Buy', 'woocommerce-paypal-payments' ),
			'check-out'  => __( 'Checkout', 'woocommerce-paypal-payments' ),
			'donate'     => __( 'Donate', 'woocommerce-paypal-payments' ),
			'order'      => __( 'Order', 'woocommerce-paypal-payments' ),
			'pay'        => __( 'Pay', 'woocommerce-paypal-payments' ),
			'plain'      => __( 'Plain', 'woocommerce-paypal-payments' ),
			'subscribe'  => __( 'Book', 'woocommerce-paypal-payments' ),
			'add-money'  => __( 'Add money', 'woocommerce-paypal-payments' ),
			'continue'   => __( 'Continue', 'woocommerce-paypal-payments' ),
			'contribute' => __( 'Contribute', 'woocommerce-paypal-payments' ),
			'reload'     => __( 'Reload', 'woocommerce-paypal-payments' ),
			'rent'       => __( 'Rent', 'woocommerce-paypal-payments' ),
			'setup'      => __( 'Setup', 'woocommerce-paypal-payments' ),
			'support'    => __( 'Support', 'woocommerce-paypal-payments' ),
			'tip'        => __( 'Tip', 'woocommerce-paypal-payments' ),
			'top-up'     => __( 'Top up', 'woocommerce-paypal-payments' ),
		);
	}

	/**
	 * Returns the possible list of button languages.
	 *
	 * @return array
	 */
	public static function button_languages(): array {
		return array(
			''      => __( 'Browser language', 'woocommerce-paypal-payments' ),
			'ar-AB' => __( 'Arabic', 'woocommerce-paypal-payments' ),
			'ca-ES' => __( 'Catalan', 'woocommerce-paypal-payments' ),
			'cs-CZ' => __( 'Czech', 'woocommerce-paypal-payments' ),
			'da-DK' => __( 'Danish', 'woocommerce-paypal-payments' ),
			'de-DE' => __( 'German', 'woocommerce-paypal-payments' ),
			'el-GR' => __( 'Greek', 'woocommerce-paypal-payments' ),
			'en-AU' => __( 'English (Australia)', 'woocommerce-paypal-payments' ),
			'en-GB' => __( 'English (United Kingdom)', 'woocommerce-paypal-payments' ),
			'en-US' => __( 'English (United States)', 'woocommerce-paypal-payments' ),
			'es-ES' => __( 'Spanish (Spain)', 'woocommerce-paypal-payments' ),
			'es-MX' => __( 'Spanish (Mexico)', 'woocommerce-paypal-payments' ),
			'fi-FI' => __( 'Finnish', 'woocommerce-paypal-payments' ),
			'fr-CA' => __( 'French (Canada)', 'woocommerce-paypal-payments' ),
			'fr-FR' => __( 'French (France)', 'woocommerce-paypal-payments' ),
			'he-IL' => __( 'Hebrew', 'woocommerce-paypal-payments' ),
			'hi-IN' => __( 'Hindi', 'woocommerce-paypal-payments' ),
			'hr-HR' => __( 'Croatian', 'woocommerce-paypal-payments' ),
			'hu-HU' => __( 'Hungarian', 'woocommerce-paypal-payments' ),
			'id-ID' => __( 'Indonesian', 'woocommerce-paypal-payments' ),
			'it-IT' => __( 'Italian', 'woocommerce-paypal-payments' ),
			'ja-JP' => __( 'Japanese', 'woocommerce-paypal-payments' ),
			'ko-KR' => __( 'Korean', 'woocommerce-paypal-payments' ),
			'ms-MY' => __( 'Malay', 'woocommerce-paypal-payments' ),
			'nb-NO' => __( 'Norwegian', 'woocommerce-paypal-payments' ),
			'nl-NL' => __( 'Dutch', 'woocommerce-paypal-payments' ),
			'pl-PL' => __( 'Polish', 'woocommerce-paypal-payments' ),
			'pt-BR' => __( 'Portuguese (Brazil)', 'woocommerce-paypal-payments' ),
			'pt-PT' => __( 'Portuguese (Portugal)', 'woocommerce-paypal-payments' ),
			'ro-RO' => __( 'Romanian', 'woocommerce-paypal-payments' ),
			'ru-RU' => __( 'Russian', 'woocommerce-paypal-payments' ),
			'sk-SK' => __( 'Slovak', 'woocommerce-paypal-payments' ),
			'sv-SE' => __( 'Swedish', 'woocommerce-paypal-payments' ),
			'th-TH' => __( 'Thai', 'woocommerce-paypal-payments' ),
			'tr-TR' => __( 'Turkish', 'woocommerce-paypal-payments' ),
			'uk-UA' => __( 'Ukrainian', 'woocommerce-paypal-payments' ),
			'vi-VN' => __( 'Vietnamese', 'woocommerce-paypal-payments' ),
			'zh-CN' => __( 'Chinese (Simplified)', 'woocommerce-paypal-payments' ),
			'zh-HK' => __( 'Chinese (Hong Kong)', 'woocommerce-paypal-payments' ),
			'zh-TW' => __( 'Chinese (Traditional)', 'woocommerce-paypal-payments' ),
		);
	}

	/**
	 * Returns the possible list of billing data modes.
	 *
	 * @return array
	 */
	public static function billing_data_modes(): array {
		return array(
			self::BILLING_DATA_MODE_USE_WC       => __( 'Use WC checkout form data (do not show shipping address fields)', 'woocommerce-paypal-payments' ),
			self::BILLING_DATA_MODE_USE_APPLEPAY => __( 'Do not use WC checkout form data (request billing and shipping addresses on Apple Pay)', 'woocommerce-paypal-payments' ),
		);
	}
}
