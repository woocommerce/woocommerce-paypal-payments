<?php

/**
 * The ExperienceContext builder.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WC_AJAX;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CallbackConfig;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Shipping\ShippingCallbackUrlFactory;
/**
 * Class ExperienceContextBuilder
 */
class ExperienceContextBuilder
{
    /**
     * The object being built.
     */
    private ExperienceContext $experience_context;
    /**
     * The Settings.
     */
    private ContainerInterface $settings;
    private ShippingCallbackUrlFactory $shipping_callback_url_factory;
    public function __construct(ContainerInterface $settings, ShippingCallbackUrlFactory $shipping_callback_url_factory)
    {
        $this->experience_context = new ExperienceContext();
        $this->settings = $settings;
        $this->shipping_callback_url_factory = $shipping_callback_url_factory;
    }
    /**
     * Uses the default config for the PayPal buttons.
     *
     * @param string $shipping_preference One of the ExperienceContext::SHIPPING_PREFERENCE_* values.
     * @param string $user_action One of the ExperienceContext::USER_ACTION_* values.
     */
    public function with_default_paypal_config(string $shipping_preference = ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING, string $user_action = ExperienceContext::USER_ACTION_CONTINUE): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder = $builder->with_current_locale()->with_current_brand_name()->with_current_landing_page()->with_current_payment_method_preference()->with_endpoint_return_urls();
        $builder->experience_context = $builder->experience_context->with_shipping_preference($shipping_preference)->with_user_action($user_action);
        return $builder;
    }
    /**
     * Uses the ReturnUrlEndpoint return URL.
     */
    public function with_endpoint_return_urls(): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder->experience_context = $builder->experience_context->with_return_url(home_url(WC_AJAX::get_endpoint(ReturnUrlEndpoint::ENDPOINT)))->with_cancel_url(wc_get_checkout_url());
        return $builder;
    }
    /**
     * Uses the WC order return URLs.
     *
     * @param WC_Order $wc_order The WC order.
     */
    public function with_order_return_urls(WC_Order $wc_order): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $url = $wc_order->get_checkout_order_received_url();
        $builder->experience_context = $builder->experience_context->with_return_url($url)->with_cancel_url(add_query_arg('cancelled', 'true', $url));
        return $builder;
    }
    /**
     * Uses a custom return URL.
     *
     * @param  string $url The return URL.
     */
    public function with_custom_return_url(string $url): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder->experience_context = $builder->experience_context->with_return_url($url);
        return $builder;
    }
    /**
     * Uses a custom cancel URL.
     *
     * @param  string $url The cancel URL.
     */
    public function with_custom_cancel_url(string $url): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder->experience_context = $builder->experience_context->with_cancel_url($url);
        return $builder;
    }
    /**
     * Uses the current brand name from the settings.
     */
    public function with_current_brand_name(): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $brand_name = $this->settings->has('brand_name') ? (string) $this->settings->get('brand_name') : '';
        if (empty($brand_name)) {
            $brand_name = null;
        }
        $builder->experience_context = $builder->experience_context->with_brand_name($brand_name);
        return $builder;
    }
    /**
     * Uses the current user locale.
     */
    public function with_current_locale(): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder->experience_context = $builder->experience_context->with_locale($this->locale_to_bcp47(get_user_locale()));
        return $builder;
    }
    /**
     * Uses the current landing page from the settings.
     */
    public function with_current_landing_page(): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $landing_page = $this->settings->has('landing_page') ? (string) $this->settings->get('landing_page') : ExperienceContext::LANDING_PAGE_NO_PREFERENCE;
        if (empty($landing_page)) {
            $landing_page = ExperienceContext::LANDING_PAGE_NO_PREFERENCE;
        }
        $builder->experience_context = $builder->experience_context->with_landing_page($landing_page);
        return $builder;
    }
    /**
     * Uses the payment method preference from the settings.
     */
    public function with_current_payment_method_preference(): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder->experience_context = $builder->experience_context->with_payment_method_preference($this->settings->has('payee_preferred') && $this->settings->get('payee_preferred') ? ExperienceContext::PAYMENT_METHOD_IMMEDIATE_PAYMENT_REQUIRED : ExperienceContext::PAYMENT_METHOD_UNRESTRICTED);
        return $builder;
    }
    /**
     * Uses the server-side shipping callback configuration.
     */
    public function with_shipping_callback(): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder->experience_context = $builder->experience_context->with_order_update_callback_config(new CallbackConfig(array(CallbackConfig::EVENT_SHIPPING_ADDRESS, CallbackConfig::EVENT_SHIPPING_OPTIONS), $this->shipping_callback_url_factory->create()));
        return $builder;
    }
    /**
     * Applies a custom contact preference to the experience context.
     *
     * @param string|null $preference The new preference to apply.
     */
    public function with_contact_preference(?string $preference = null): \WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder
    {
        $builder = clone $this;
        $builder->experience_context = $builder->experience_context->with_contact_preference($preference);
        return $builder;
    }
    /**
     * Returns the ExperienceContext.
     */
    public function build(): ExperienceContext
    {
        return $this->experience_context;
    }
    /**
     * Returns BCP-47 code supported by PayPal, for example de-DE-formal becomes de-DE.
     *
     * @param string $locale The locale, e.g. from get_user_locale.
     */
    protected function locale_to_bcp47(string $locale): string
    {
        $locale = str_replace('_', '-', $locale);
        if (preg_match('/^[a-z]{2}(?:-[A-Z][a-z]{3})?(?:-(?:[A-Z]{2}))?$/', $locale)) {
            return $locale;
        }
        $parts = explode('-', $locale);
        if (count($parts) === 3) {
            $ret = substr($locale, 0, (int) strrpos($locale, '-'));
            if (\false !== $ret) {
                return $ret;
            }
        }
        return 'en';
    }
}
