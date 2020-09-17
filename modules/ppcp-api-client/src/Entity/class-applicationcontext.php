<?php
/**
 * The application context object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ApplicationContext
 */
class ApplicationContext {

	const LANDING_PAGE_LOGIN         = 'LOGIN';
	const LANDING_PAGE_BILLING       = 'BILLING';
	const LANDING_PAGE_NO_PREFERENCE = 'NO_PREFERENCE';
	const VALID_LANDING_PAGE_VALUES  = array(
		self::LANDING_PAGE_LOGIN,
		self::LANDING_PAGE_BILLING,
		self::LANDING_PAGE_NO_PREFERENCE,
	);

	const SHIPPING_PREFERENCE_GET_FROM_FILE        = 'GET_FROM_FILE';
	const SHIPPING_PREFERENCE_NO_SHIPPING          = 'NO_SHIPPING';
	const SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';
	const VALID_SHIPPING_PREFERENCE_VALUES         = array(
		self::SHIPPING_PREFERENCE_GET_FROM_FILE,
		self::SHIPPING_PREFERENCE_NO_SHIPPING,
		self::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
	);

	const USER_ACTION_CONTINUE     = 'CONTINUE';
	const USER_ACTION_PAY_NOW      = 'PAY_NOW';
	const VALID_USER_ACTION_VALUES = array(
		self::USER_ACTION_CONTINUE,
		self::USER_ACTION_PAY_NOW,
	);

	/**
	 * The brand name.
	 *
	 * @var string
	 */
	private $brand_name;

	/**
	 * The locale.
	 *
	 * @var string
	 */
	private $locale;

	/**
	 * The landing page.
	 *
	 * @var string
	 */
	private $landing_page;

	/**
	 * The shipping preference.
	 *
	 * @var string
	 */
	private $shipping_preference;

	/**
	 * The user action.
	 *
	 * @var string
	 */
	private $user_action;

	/**
	 * The return url.
	 *
	 * @var string
	 */
	private $return_url;

	/**
	 * The cancel url.
	 *
	 * @var string
	 */
	private $cancel_url;

	/**
	 * The payment method.
	 *
	 * @var null
	 */
	private $payment_method;

	/**
	 * ApplicationContext constructor.
	 *
	 * @param string $return_url The return URL.
	 * @param string $cancel_url The cancel URL.
	 * @param string $brand_name The brand name.
	 * @param string $locale The locale.
	 * @param string $landing_page The landing page.
	 * @param string $shipping_preference The shipping preference.
	 * @param string $user_action The user action.
	 *
	 * @throws RuntimeException When values are not valid.
	 */
	public function __construct(
		string $return_url = '',
		string $cancel_url = '',
		string $brand_name = '',
		string $locale = '',
		string $landing_page = self::LANDING_PAGE_NO_PREFERENCE,
		string $shipping_preference = self::SHIPPING_PREFERENCE_NO_SHIPPING,
		string $user_action = self::USER_ACTION_CONTINUE
	) {

		if ( ! in_array( $landing_page, self::VALID_LANDING_PAGE_VALUES, true ) ) {
			throw new RuntimeException( 'Landingpage not correct' );
		}
		if ( ! in_array( $shipping_preference, self::VALID_SHIPPING_PREFERENCE_VALUES, true ) ) {
			throw new RuntimeException( 'Shipping preference not correct' );
		}
		if ( ! in_array( $user_action, self::VALID_USER_ACTION_VALUES, true ) ) {
			throw new RuntimeException( 'User action preference not correct' );
		}
		$this->return_url          = $return_url;
		$this->cancel_url          = $cancel_url;
		$this->brand_name          = $brand_name;
		$this->locale              = $locale;
		$this->landing_page        = $landing_page;
		$this->shipping_preference = $shipping_preference;
		$this->user_action         = $user_action;

		// Currently we have not implemented the payment method.
		$this->payment_method = null;
	}

	/**
	 * Returns the brand name.
	 *
	 * @return string
	 */
	public function brand_name(): string {
		return $this->brand_name;
	}

	/**
	 * Returns the locale.
	 *
	 * @return string
	 */
	public function locale(): string {
		return $this->locale;
	}

	/**
	 * Returns the landing page.
	 *
	 * @return string
	 */
	public function landing_page(): string {
		return $this->landing_page;
	}

	/**
	 * Returns the shipping preference.
	 *
	 * @return string
	 */
	public function shipping_preference(): string {
		return $this->shipping_preference;
	}

	/**
	 * Returns the user action.
	 *
	 * @return string
	 */
	public function user_action(): string {
		return $this->user_action;
	}

	/**
	 * Returns the return URL.
	 *
	 * @return string
	 */
	public function return_url(): string {
		return $this->return_url;
	}

	/**
	 * Returns the cancel URL.
	 *
	 * @return string
	 */
	public function cancel_url(): string {
		return $this->cancel_url;
	}

	/**
	 * Returns the payment method.
	 *
	 * @return PaymentMethod|null
	 */
	public function payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array();
		if ( $this->user_action() ) {
			$data['user_action'] = $this->user_action();
		}
		if ( $this->payment_method() ) {
			$data['payment_method'] = $this->payment_method();
		}
		if ( $this->shipping_preference() ) {
			$data['shipping_preference'] = $this->shipping_preference();
		}
		if ( $this->landing_page() ) {
			$data['landing_page'] = $this->landing_page();
		}
		if ( $this->locale() ) {
			$data['locale'] = $this->locale();
		}
		if ( $this->brand_name() ) {
			$data['brand_name'] = $this->brand_name();
		}
		if ( $this->return_url() ) {
			$data['return_url'] = $this->return_url();
		}
		if ( $this->cancel_url() ) {
			$data['cancel_url'] = $this->cancel_url();
		}
		return $data;
	}
}
