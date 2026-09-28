<?php

/**
 * Describes the current API environment (production or sandbox).
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

/**
 * Class Environment
 */
class Environment
{
    /**
     * Name of the production environment.
     */
    public const PRODUCTION = 'production';
    /**
     * Name of the sandbox environment.
     */
    public const SANDBOX = 'sandbox';
    /**
     * Name of the current environment.
     *
     * @var string
     */
    private string $environment_name;
    /**
     * Environment constructor.
     *
     * @param bool $is_sandbox Whether this instance represents a sandbox environment.
     */
    public function __construct(bool $is_sandbox = \false)
    {
        $this->environment_name = $this->prepare_environment_name($is_sandbox);
    }
    /**
     * Returns a valid environment name based on the provided argument.
     *
     * @param bool $is_sandbox Whether this instance represents a sandbox environment.
     * @return string The environment name.
     */
    private function prepare_environment_name(bool $is_sandbox): string
    {
        if ($is_sandbox) {
            return self::SANDBOX;
        }
        return self::PRODUCTION;
    }
    /**
     * Updates the current environment.
     *
     * @param bool $is_sandbox Whether this instance represents a sandbox environment.
     */
    public function set_environment(bool $is_sandbox): void
    {
        $new_environment = $this->prepare_environment_name($is_sandbox);
        if ($new_environment !== $this->environment_name) {
            /**
             * Action that fires before the environment status changes.
             *
             * @param string $new_environment The new environment name.
             * @param string $old_environment The previous environment name.
             */
            do_action('woocommerce_paypal_payments_merchant_environment_change', $new_environment, $this->environment_name);
        }
        $this->environment_name = $new_environment;
    }
    /**
     * Returns the current environment's name.
     *
     * @return string
     */
    public function current_environment(): string
    {
        return $this->environment_name;
    }
    /**
     * Detect whether the current environment equals $environment
     *
     * @deprecated 3.0.0 - Use the is_sandbox() and is_production() methods instead.
     *             These methods provide better encapsulation, are less error-prone,
     *             and improve code readability by removing the need to pass environment constants.
     * @param string $environment The value to check against.
     *
     * @return bool
     */
    public function current_environment_is(string $environment): bool
    {
        return $this->current_environment() === $environment;
    }
    /**
     * Returns whether the current environment is sandbox.
     *
     * @return bool
     */
    public function is_sandbox(): bool
    {
        return $this->current_environment() === self::SANDBOX;
    }
    /**
     * Returns whether the current environment is production.
     *
     * @return bool
     */
    public function is_production(): bool
    {
        return $this->current_environment() === self::PRODUCTION;
    }
}
