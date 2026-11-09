<?php

/**
 * Provides a type-safe configuration container for managing environment-specific values.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

/**
 * Config class that can store and provide values for a production- and sandbox-environment.
 *
 * @template T
 */
class EnvironmentConfig
{
    /**
     * Value for the production environment.
     *
     * @var T
     */
    private $production_value;
    /**
     * Value for the sandbox environment.
     *
     * @var T
     */
    private $sandbox_value;
    /**
     * Private constructor.
     *
     * Used by the`::create()` method after validating the data types of both values.
     *
     * @param T $production_value The value for the live environment.
     * @param T $sandbox_value    The value for the sandbox environment.
     */
    private function __construct($production_value, $sandbox_value)
    {
        $this->production_value = $production_value;
        $this->sandbox_value = $sandbox_value;
    }
    /**
     * Factory method to create a validated EnvironmentConfig.
     *
     * @template U
     * @param string $data_type        Expected type for the values (class name or primitive type).
     * @param U      $production_value Value for production environment.
     * @param U      $sandbox_value    Value for the sandbox environment.
     * @return self<U>
     */
    public static function create(string $data_type, $production_value, $sandbox_value): self
    {
        assert(gettype($production_value) === $data_type || $production_value instanceof $data_type, "Production value must be of type '{$data_type}'");
        assert(gettype($sandbox_value) === $data_type || $sandbox_value instanceof $data_type, "Sandbox value must be of type '{$data_type}'");
        return new self($production_value, $sandbox_value);
    }
    /**
     * Get the value for the specified environment.
     *
     * @param bool|Environment $for_sandbox Whether to get the sandbox value.
     * @return T The value for the specified environment.
     */
    public function get_value($for_sandbox = \false)
    {
        if ($for_sandbox instanceof \WooCommerce\PayPalCommerce\WcGateway\Helper\Environment) {
            return $for_sandbox->is_sandbox() ? $this->sandbox_value : $this->production_value;
        }
        return $for_sandbox ? $this->sandbox_value : $this->production_value;
    }
}
