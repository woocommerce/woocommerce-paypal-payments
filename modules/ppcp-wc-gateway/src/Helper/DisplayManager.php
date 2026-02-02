<?php

/**
 * Helper to manage the field display behaviour.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * DisplayManager class.
 */
class DisplayManager
{
    /**
     * The settings.
     *
     * @var Settings
     */
    private $settings;
    /**
     * The rules.
     *
     * @var array
     */
    protected $rules = array();
    /**
     * FieldDisplayManager constructor.
     *
     * @param Settings $settings The settings.
     * @return void
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }
    /**
     * Creates and returns a rule.
     *
     * @param string|null $key The rule key.
     * @return DisplayRule
     */
    public function rule(?string $key = null): \WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayRule
    {
        if (null === $key) {
            $key = '_rule_' . (string) count($this->rules);
        }
        $rule = new \WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayRule($key, $this->settings);
        $this->rules[$key] = $rule;
        return $rule;
    }
}
