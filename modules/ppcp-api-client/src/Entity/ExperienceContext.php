<?php

/**
 * The experience_context object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use ReflectionClass;
/**
 * Class ExperienceContext
 */
class ExperienceContext
{
    public const LANDING_PAGE_LOGIN = 'LOGIN';
    public const LANDING_PAGE_GUEST_CHECKOUT = 'GUEST_CHECKOUT';
    public const LANDING_PAGE_NO_PREFERENCE = 'NO_PREFERENCE';
    public const SHIPPING_PREFERENCE_GET_FROM_FILE = 'GET_FROM_FILE';
    public const SHIPPING_PREFERENCE_NO_SHIPPING = 'NO_SHIPPING';
    public const SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';
    public const USER_ACTION_CONTINUE = 'CONTINUE';
    public const USER_ACTION_PAY_NOW = 'PAY_NOW';
    public const PAYMENT_METHOD_UNRESTRICTED = 'UNRESTRICTED';
    public const PAYMENT_METHOD_IMMEDIATE_PAYMENT_REQUIRED = 'IMMEDIATE_PAYMENT_REQUIRED';
    public const CONTACT_PREFERENCE_NO_CONTACT_INFO = 'NO_CONTACT_INFO';
    public const CONTACT_PREFERENCE_UPDATE_CONTACT_INFO = 'UPDATE_CONTACT_INFO';
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
     * Controls the contact module, and when defined, the API response will
     * include additional details in the `purchase_units[].shipping` object.
     */
    private ?string $contact_preference = null;
    /**
     * The callback config.
     */
    private ?\WooCommerce\PayPalCommerce\ApiClient\Entity\CallbackConfig $order_update_callback_config = null;
    /**
     * Returns the return URL.
     */
    public function return_url(): ?string
    {
        return $this->return_url;
    }
    /**
     * Sets the return URL.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_return_url(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->return_url = $new_value;
        return $obj;
    }
    /**
     * Returns the cancel URL.
     */
    public function cancel_url(): ?string
    {
        return $this->cancel_url;
    }
    /**
     * Sets the cancel URL.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_cancel_url(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->cancel_url = $new_value;
        return $obj;
    }
    /**
     * Returns the brand name.
     *
     * @return string
     */
    public function brand_name(): ?string
    {
        return $this->brand_name;
    }
    /**
     * Sets the brand name.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_brand_name(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->brand_name = $new_value;
        return $obj;
    }
    /**
     * Returns the locale.
     */
    public function locale(): ?string
    {
        return $this->locale;
    }
    /**
     * Sets the locale.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_locale(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->locale = $new_value;
        return $obj;
    }
    /**
     * Returns the landing page.
     */
    public function landing_page(): ?string
    {
        return $this->landing_page;
    }
    /**
     * Sets the landing page.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_landing_page(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        if ($new_value && strtoupper($new_value) === 'BILLING') {
            $new_value = self::LANDING_PAGE_GUEST_CHECKOUT;
        }
        $obj = clone $this;
        $obj->landing_page = $new_value;
        return $obj;
    }
    /**
     * Returns the shipping preference.
     */
    public function shipping_preference(): ?string
    {
        return $this->shipping_preference;
    }
    /**
     * Sets the shipping preference.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_shipping_preference(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->shipping_preference = $new_value;
        return $obj;
    }
    /**
     * Returns the user action.
     */
    public function user_action(): ?string
    {
        return $this->user_action;
    }
    /**
     * Sets the user action.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_user_action(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->user_action = $new_value;
        return $obj;
    }
    /**
     * Returns the payment method preference.
     */
    public function payment_method_preference(): ?string
    {
        return $this->payment_method_preference;
    }
    /**
     * Sets the payment method preference.
     *
     * @param string|null $new_value The value to set.
     */
    public function with_payment_method_preference(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->payment_method_preference = $new_value;
        return $obj;
    }
    /**
     * Returns the contact preference.
     */
    public function contact_preference(): ?string
    {
        return $this->contact_preference;
    }
    /**
     * Sets the contact preference.
     *
     * This preference is only available for the payment source 'paypal' and 'venmo'.
     * https://developer.paypal.com/docs/api/orders/v2/#definition-paypal_wallet_experience_context
     *
     * @param string|null $new_value The value to set.
     */
    public function with_contact_preference(?string $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->contact_preference = $new_value;
        return $obj;
    }
    /**
     * Returns the callback config.
     */
    public function order_update_callback_config(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\CallbackConfig
    {
        return $this->order_update_callback_config;
    }
    /**
     * Sets the callback config.
     *
     * @param CallbackConfig|null $new_value The value to set.
     */
    public function with_order_update_callback_config(?\WooCommerce\PayPalCommerce\ApiClient\Entity\CallbackConfig $new_value): \WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext
    {
        $obj = clone $this;
        $obj->order_update_callback_config = $new_value;
        return $obj;
    }
    /**
     * Returns the object as array.
     */
    public function to_array(): array
    {
        $data = array();
        $class = new ReflectionClass($this);
        foreach ($class->getProperties() as $prop) {
            $value = $this->{$prop->getName()};
            if ($value === null) {
                continue;
            }
            if (is_object($value) && method_exists($value, 'to_array')) {
                $value = $value->to_array();
            }
            $data[$prop->getName()] = $value;
        }
        return $data;
    }
}
