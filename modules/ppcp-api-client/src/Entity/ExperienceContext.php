<?php
/**
 * The experience_context object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use ReflectionClass;

/**
 * Class ExperienceContext
 */
class ExperienceContext {

	public const LANDING_PAGE_LOGIN          = 'LOGIN';
	public const LANDING_PAGE_GUEST_CHECKOUT = 'GUEST_CHECKOUT';
	public const LANDING_PAGE_NO_PREFERENCE  = 'NO_PREFERENCE';

	public const SHIPPING_PREFERENCE_GET_FROM_FILE        = 'GET_FROM_FILE';
	public const SHIPPING_PREFERENCE_NO_SHIPPING          = 'NO_SHIPPING';
	public const SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

	public const USER_ACTION_CONTINUE = 'CONTINUE';
	public const USER_ACTION_PAY_NOW  = 'PAY_NOW';

	public const PAYMENT_METHOD_UNRESTRICTED               = 'UNRESTRICTED';
	public const PAYMENT_METHOD_IMMEDIATE_PAYMENT_REQUIRED = 'IMMEDIATE_PAYMENT_REQUIRED';

	/**
	 * The return url.
	 */
	private ?string $return_url = null;

	/**
	 * The cancel url.
	 */
	private ?string $cancel_url = null;

	/**
	 * The brand name.
	 */
	private ?string $brand_name = null;

	/**
	 * The locale.
	 */
	private ?string $locale = null;

	/**
	 * The landing page.
	 */
	private ?string $landing_page = null;

	/**
	 * The shipping preference.
	 */
	private ?string $shipping_preference = null;

	/**
	 * The user action.
	 */
	private ?string $user_action = null;

	/**
	 * The payment method preference.
	 */
	private ?string $payment_method_preference = null;

	/**
	 * Returns the return URL.
	 */
	public function return_url(): ?string {
		return $this->return_url;
	}

	/**
	 * Sets the return URL.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_return_url( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->return_url = $new_value;
		return $obj;
	}

	/**
	 * Returns the cancel URL.
	 */
	public function cancel_url(): ?string {
		return $this->cancel_url;
	}

	/**
	 * Sets the cancel URL.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_cancel_url( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->cancel_url = $new_value;
		return $obj;
	}

	/**
	 * Returns the brand name.
	 *
	 * @return string
	 */
	public function brand_name(): ?string {
		return $this->brand_name;
	}

	/**
	 * Sets the brand name.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_brand_name( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->brand_name = $new_value;
		return $obj;
	}

	/**
	 * Returns the locale.
	 */
	public function locale(): ?string {
		return $this->locale;
	}

	/**
	 * Sets the locale.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_locale( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->locale = $new_value;
		return $obj;
	}

	/**
	 * Returns the landing page.
	 */
	public function landing_page(): ?string {
		return $this->landing_page;
	}

	/**
	 * Sets the landing page.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_landing_page( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->landing_page = $new_value;
		return $obj;
	}

	/**
	 * Returns the shipping preference.
	 */
	public function shipping_preference(): ?string {
		return $this->shipping_preference;
	}

	/**
	 * Sets the shipping preference.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_shipping_preference( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->shipping_preference = $new_value;
		return $obj;
	}

	/**
	 * Returns the user action.
	 */
	public function user_action(): ?string {
		return $this->user_action;
	}

	/**
	 * Sets the user action.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_user_action( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->user_action = $new_value;
		return $obj;
	}

	/**
	 * Returns the payment method preference.
	 */
	public function payment_method_preference(): ?string {
		return $this->payment_method_preference;
	}

	/**
	 * Sets the payment method preference.
	 *
	 * @param string|null $new_value The value to set.
	 */
	public function with_payment_method_preference( ?string $new_value ): ExperienceContext {
		$obj = clone $this;

		$obj->payment_method_preference = $new_value;
		return $obj;
	}

	/**
	 * Returns the object as array.
	 */
	public function to_array(): array {
		$data = array();

		$class = new ReflectionClass( $this );
		foreach ( $class->getProperties() as $prop ) {
			$value = $this->{$prop->getName()};
			if ( $value === null ) {
				continue;
			}
			$data[ $prop->getName() ] = $value;
		}

		return $data;
	}
}
