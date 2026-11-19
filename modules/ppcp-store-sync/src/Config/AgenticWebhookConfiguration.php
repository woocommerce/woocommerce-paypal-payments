<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Config;

use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
/**
 * Centralized configuration for the Agentic Commerce API endpoints.
 *
 * This service manages all environment-specific URLs and provides a single
 * source of truth for endpoint configuration across the application.
 */
class AgenticWebhookConfiguration
{
    private const LIVE_BASE_URL = 'https://d.joinhoney.com';
    private const SANDBOX_BASE_URL = 'https://d-staging.joinhoney.com';
    private const ENDPOINT_REGISTRATION_INSTALL = '/webhooks/ws/install';
    private const ENDPOINT_REGISTRATION_UNINSTALL = '/webhooks/ws/uninstall';
    private const ENDPOINT_PRODUCT_INGESTION = '/webhooks/products';
    private ConnectionState $connection_state;
    public function __construct(ConnectionState $connection_state)
    {
        $this->connection_state = $connection_state;
    }
    private function base_url(): string
    {
        return $this->connection_state->is_production() ? self::LIVE_BASE_URL : self::SANDBOX_BASE_URL;
    }
    /**
     * Get the registration install endpoint URL.
     *
     * @return string The complete URL for registration install.
     */
    public function get_registration_install_url(): string
    {
        return $this->base_url() . self::ENDPOINT_REGISTRATION_INSTALL;
    }
    /**
     * Get the registration uninstall endpoint URL.
     *
     * @return string The complete URL for registration uninstall.
     */
    public function get_registration_uninstall_url(): string
    {
        return $this->base_url() . self::ENDPOINT_REGISTRATION_UNINSTALL;
    }
    /**
     * Get the product ingestion endpoint URL.
     *
     * @return string The complete URL for product ingestion.
     */
    public function get_product_ingestion_url(): string
    {
        return $this->base_url() . self::ENDPOINT_PRODUCT_INGESTION;
    }
}
