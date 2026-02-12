<?php

/**
 * Status of the GooglePay merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
/**
 * Class ApmProductStatus
 */
class ApmProductStatus extends ProductStatus
{
    public const CAPABILITY_NAME = 'GOOGLE_PAY';
    public const SETTINGS_KEY = 'products_googlepay_enabled';
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
        $status_override = apply_filters('woocommerce_paypal_payments_google_pay_product_status', null);
        if (null !== $status_override) {
            return $status_override;
        }
        if ($this->settings->has(self::SETTINGS_KEY) && $this->settings->get(self::SETTINGS_KEY)) {
            return wc_string_to_bool($this->settings->get(self::SETTINGS_KEY));
        }
        return null;
    }
    /** {@inheritDoc} */
    protected function check_active_state(SellerStatus $seller_status): bool
    {
        // Check the seller status for the intended capability.
        $has_capability = \false;
        foreach ($seller_status->products() as $product) {
            if ($product->name() !== 'PAYMENT_METHODS') {
                continue;
            }
            if (in_array(self::CAPABILITY_NAME, $product->capabilities(), \true)) {
                $has_capability = \true;
            }
        }
        if (!$has_capability) {
            foreach ($seller_status->capabilities() as $capability) {
                if ($capability->name() === self::CAPABILITY_NAME && $capability->status() === SellerStatusCapability::STATUS_ACTIVE) {
                    $has_capability = \true;
                }
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
