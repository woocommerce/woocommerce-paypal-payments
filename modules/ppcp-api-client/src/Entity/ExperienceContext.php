<?php
/**
 * The experience_context object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ExperienceContext
 */
class ExperienceContext {

	const LANDING_PAGE_LOGIN          = 'LOGIN';
	const LANDING_PAGE_GUEST_CHECKOUT = 'GUEST_CHECKOUT';
	const LANDING_PAGE_NO_PREFERENCE  = 'NO_PREFERENCE';

	const SHIPPING_PREFERENCE_GET_FROM_FILE        = 'GET_FROM_FILE';
	const SHIPPING_PREFERENCE_NO_SHIPPING          = 'NO_SHIPPING';
	const SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

	const USER_ACTION_CONTINUE = 'CONTINUE';
	const USER_ACTION_PAY_NOW  = 'PAY_NOW';

	const PAYMENT_METHOD_UNRESTRICTED               = 'UNRESTRICTED';
	const PAYMENT_METHOD_IMMEDIATE_PAYMENT_REQUIRED = 'IMMEDIATE_PAYMENT_REQUIRED';

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
	 * The payment method preference.
	 *
	 * @var string
	 */
	private $payment_method_preference;

	/**
	 * ExperienceContext constructor.
	 *
	 * @param string $return_url The return URL.
	 * @param string $cancel_url The cancel URL.
	 * @param string $brand_name The brand name.
	 * @param string $locale The locale.
	 * @param string $landing_page The landing page.
	 * @param string $shipping_preference The shipping preference.
	 * @param string $user_action The user action.
	 * @param string $payment_method_preference The payment method preference.
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
		string $user_action = self::USER_ACTION_CONTINUE,
		string $payment_method_preference = self::PAYMENT_METHOD_UNRESTRICTED
	) {
		$this->return_url                = $return_url;
		$this->cancel_url                = $cancel_url;
		$this->brand_name                = $brand_name;
		$this->locale                    = $locale;
		$this->landing_page              = $landing_page;
		$this->shipping_preference       = $shipping_preference;
		$this->user_action               = $user_action;
		$this->payment_method_preference = $payment_method_preference;
	}

	/**
	 * Returns the return URL.
	 */
	public function return_url(): string {
		return $this->return_url;
	}

	/**
	 * Returns the cancel URL.
	 */
	public function cancel_url(): string {
		return $this->cancel_url;
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
	 */
	public function locale(): string {
		return $this->locale;
	}

	/**
	 * Returns the landing page.
	 */
	public function landing_page(): string {
		return $this->landing_page;
	}

	/**
	 * Returns the shipping preference.
	 */
	public function shipping_preference(): string {
		return $this->shipping_preference;
	}

	/**
	 * Returns the user action.
	 */
	public function user_action(): string {
		return $this->user_action;
	}

	/**
	 * Returns the payment method preference.
	 */
	public function payment_method_preference(): string {
		return $this->payment_method_preference;
	}

	/**
	 * Returns the object as array.
	 */
	public function to_array(): array {
		$data = array();
		if ( $this->user_action ) {
			$data['user_action'] = $this->user_action;
		}
		if ( $this->shipping_preference ) {
			$data['shipping_preference'] = $this->shipping_preference;
		}
		if ( $this->landing_page ) {
			$data['landing_page'] = $this->landing_page;
		}
		if ( $this->payment_method_preference ) {
			$data['payment_method_preference'] = $this->payment_method_preference;
		}
		if ( $this->locale ) {
			$data['locale'] = $this->locale;
		}
		if ( $this->brand_name ) {
			$data['brand_name'] = $this->brand_name;
		}
		if ( $this->return_url ) {
			$data['return_url'] = $this->return_url;
		}
		if ( $this->cancel_url ) {
			$data['cancel_url'] = $this->cancel_url;
		}
		return $data;
	}
}
