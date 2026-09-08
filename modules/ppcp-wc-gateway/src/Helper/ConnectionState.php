<?php

/**
 * Describes the current API connection state (connected vs onboarding).
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

/**
 * Class ConnectionState
 *
 * Manages the merchants' connection status details and provides inspection
 * methods to describe the current state.
 *
 * DI service: 'settings.connection-state'
 */
class ConnectionState
{
    /**
     * The connection status.
     *
     * A merchant can be either "connected" (true) or "onboarding" (false).
     * During the "onboarding" phase, the environment is undefined.
     *
     * @var bool
     */
    private bool $is_connected;
    /**
     * The environment instance, which is managed by this class because it's
     * hierarchically coupled to the connection status: Only connected merchants
     * have a defined environment.
     *
     * @var Environment
     */
    private \WooCommerce\PayPalCommerce\WcGateway\Helper\Environment $environment;
    /**
     * Constructor.
     *
     * @param bool        $is_connected Initial connection status.
     * @param Environment $environment  The environment instance.
     */
    public function __construct(bool $is_connected, \WooCommerce\PayPalCommerce\WcGateway\Helper\Environment $environment)
    {
        $this->is_connected = $is_connected;
        $this->environment = $environment;
    }
    /**
     * Set connection status to "connected to PayPal" (end onboarding).
     *
     * @param bool $is_sandbox Whether to connect to a sandbox environment.
     */
    public function connect(bool $is_sandbox = \false): void
    {
        if (!$this->is_connected) {
            /**
             * Action that fires before the connection status changes from
             * disconnected to connected.
             */
            do_action('woocommerce_paypal_payments_merchant_connection_change', \true);
        }
        $this->is_connected = \true;
        $this->environment->set_environment($is_sandbox);
    }
    /**
     * Set connection status to "not connected to PayPal" (start onboarding).
     */
    public function disconnect(): void
    {
        if ($this->is_connected) {
            /**
             * Action that fires before the connection status changes from
             * connected to disconnected.
             */
            do_action('woocommerce_paypal_payments_merchant_connection_change', \false);
        }
        $this->is_connected = \false;
    }
    /**
     * Returns the managed environment instance.
     *
     * @return Environment The environment instance.
     */
    public function get_environment(): \WooCommerce\PayPalCommerce\WcGateway\Helper\Environment
    {
        return $this->environment;
    }
    /**
     * Is the merchant connected to a PayPal account?
     *
     * @return bool True, if onboarding was completed and connection details are present.
     */
    public function is_connected(): bool
    {
        return $this->is_connected;
    }
    /**
     * Is the merchant currently in the "onboarding phase"?
     *
     * @return bool True, if we don't know merchant connection details.
     */
    public function is_onboarding(): bool
    {
        return !$this->is_connected;
    }
    /**
     * Is the merchant connected to a sandbox environment?
     *
     * @return bool True, if connected to a sandbox environment.
     */
    public function is_sandbox(): bool
    {
        return $this->is_connected && $this->environment->is_sandbox();
    }
    /**
     * Is the merchant connected to a production environment and can receive payments?
     *
     * @return bool True, if connected to a production environment.
     */
    public function is_production(): bool
    {
        return $this->is_connected && $this->environment->is_production();
    }
    /**
     * Returns the current environment's name.
     *
     * @return string Name of the currently connected environment; empty string if not connected.
     */
    public function current_environment(): string
    {
        return $this->is_connected ? $this->environment->current_environment() : '';
    }
}
