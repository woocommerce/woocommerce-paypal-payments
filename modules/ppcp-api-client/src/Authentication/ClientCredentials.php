<?php

/**
 * The client credentials.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Authentication
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
/**
 * Class ClientCredentials
 */
class ClientCredentials
{
    /**
     * The settings.
     *
     * @var SettingsProvider
     */
    protected $settings;
    /**
     * ClientCredentials constructor.
     *
     * @param SettingsProvider $settings The settings.
     */
    public function __construct(SettingsProvider $settings)
    {
        $this->settings = $settings;
    }
    /**
     * Returns encoded client credentials.
     *
     * @return string
     */
    public function credentials(): string
    {
        $merchant_data = $this->settings->merchant_data();
        $client_id = $merchant_data->client_id;
        $client_secret = $merchant_data->client_secret;
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return 'Basic ' . base64_encode($client_id . ':' . $client_secret);
    }
}
