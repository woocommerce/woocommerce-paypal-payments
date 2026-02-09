<?php

/**
 * Status of local alternative payment methods.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
/**
 * Class LocalApmProductStatus
 */
class LocalApmProductStatus extends ProductStatus
{
    public const SETTINGS_KEY = 'products_local_apms_enabled';
    public const SETTINGS_VALUE_ENABLED = 'yes';
    public const SETTINGS_VALUE_DISABLED = 'no';
    public const SETTINGS_VALUE_UNDEFINED = '';
    /**
     * The settings.
     *
     * @var Settings
     */
    private Settings $settings;
    /**
     * ApmProductStatus constructor.
     *
     * @param Settings         $settings             The Settings.
     * @param PartnersEndpoint $partners_endpoint    The Partner Endpoint.
     * @param bool             $is_connected         The onboarding state.
     * @param FailureRegistry  $api_failure_registry The API failure registry.
     */
    public function __construct(Settings $settings, PartnersEndpoint $partners_endpoint, bool $is_connected, FailureRegistry $api_failure_registry)
    {
        parent::__construct($is_connected, $partners_endpoint, $api_failure_registry);
        $this->settings = $settings;
    }
    /** {@inheritDoc} */
    protected function check_local_state(): ?bool
    {
        if ($this->settings->has(self::SETTINGS_KEY) && $this->settings->get(self::SETTINGS_KEY)) {
            return wc_string_to_bool($this->settings->get(self::SETTINGS_KEY));
        }
        return null;
    }
    /** {@inheritDoc} */
    protected function check_active_state(SellerStatus $seller_status): bool
    {
        $has_capability = \false;
        foreach ($seller_status->capabilities() as $capability) {
            if ('ACTIVE' !== $capability->status()) {
                continue;
            }
            if ('PAYPAL_CHECKOUT_ALTERNATIVE_PAYMENT_METHODS' === $capability->name()) {
                $has_capability = \true;
                break;
            }
        }
        // Settings used as a cache; `settings->set` is compatible with new UI.
        if ($has_capability) {
            $this->settings->set(self::SETTINGS_KEY, self::SETTINGS_VALUE_ENABLED);
        } else {
            $this->settings->set(self::SETTINGS_KEY, self::SETTINGS_VALUE_DISABLED);
        }
        $this->settings->persist();
        return $has_capability;
    }
    /** {@inheritDoc} */
    protected function clear_state(?Settings $settings = null): void
    {
        if (null === $settings) {
            $settings = $this->settings;
        }
        if ($settings->has(self::SETTINGS_KEY)) {
            $settings->set(self::SETTINGS_KEY, self::SETTINGS_VALUE_UNDEFINED);
            $settings->persist();
        }
    }
}
