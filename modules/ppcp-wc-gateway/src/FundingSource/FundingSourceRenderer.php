<?php

/**
 * Renders info about funding sources like Venmo.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\FundingSource
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\FundingSource;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
/**
 * Class FundingSourceRenderer
 */
class FundingSourceRenderer
{
    protected SettingsProvider $settings_provider;
    /**
     * Map funding source ID -> human-readable name.
     *
     * @var array<string, string>
     */
    protected $funding_sources;
    /**
     * The IDs of the sources belonging to PayPal that do not need to mention "via PayPal".
     *
     * @var string[]
     */
    protected $own_funding_sources = array('venmo', 'paylater', 'paypal');
    /**
     * @param SettingsProvider      $settings_provider Settings provider.
     * @param array<string, string> $funding_sources   Map funding source ID -> human-readable name.
     */
    public function __construct(SettingsProvider $settings_provider, array $funding_sources)
    {
        $this->settings_provider = $settings_provider;
        $this->funding_sources = $funding_sources;
    }
    /**
     * Returns name of the funding source (suitable for displaying to user).
     *
     * @param string $id The ID of the funding source, such as 'venmo'.
     */
    public function render_name(string $id): string
    {
        $id = $this->sanitize_id($id);
        if (array_key_exists($id, $this->funding_sources)) {
            if (in_array($id, $this->own_funding_sources, \true)) {
                return $this->funding_sources[$id];
            }
            return sprintf(
                /* translators: %s - BLIK, iDeal, Mercado Pago, etc. */
                __('%s (via PayPal)', 'woocommerce-paypal-payments'),
                $this->funding_sources[$id]
            );
        }
        return $this->settings_provider->paypal_gateway_title();
    }
    /**
     * Returns description of the funding source (for checkout).
     *
     * @param string $id The ID of the funding source, such as 'venmo'.
     */
    public function render_description(string $id): string
    {
        $id = $this->sanitize_id($id);
        if (array_key_exists($id, $this->funding_sources)) {
            return sprintf(
                /* translators: %s - BLIK, iDeal, Mercado Pago, etc. */
                __('Pay via %s.', 'woocommerce-paypal-payments'),
                $this->funding_sources[$id]
            );
        }
        return $this->settings_provider->paypal_gateway_description();
    }
    /**
     * Sanitizes the id to a standard format.
     *
     * @param string $id The funding source id.
     * @return string
     */
    private function sanitize_id(string $id): string
    {
        return str_replace('_', '', strtolower($id));
    }
}
