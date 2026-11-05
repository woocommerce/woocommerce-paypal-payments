<?php

/**
 * Custom Blueprint step for PayPal settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Automattic\WooCommerce\Blueprint\Steps\Step;
/**
 * Custom step that carries PayPal-specific options under its own step name,
 * so it never collides with the core setSiteOptions processor.
 */
class SetPayPalSettings extends Step
{
    /**
     * PayPal options to export/import.
     *
     * @var array
     */
    private array $options;
    /**
     * Constructor.
     *
     * @param array $options PayPal options.
     */
    public function __construct(array $options = array())
    {
        $this->options = $options;
    }
    /**
     * Get the step name.
     *
     * @return string
     */
    public static function get_step_name(): string
    {
        return 'setPayPalSettings';
    }
    /**
     * Get the schema for the step.
     *
     * @param int $version Schema version.
     * @return array
     */
    public static function get_schema(int $version = 1): array
    {
        return array('type' => 'object', 'properties' => array('step' => array('type' => 'string', 'enum' => array(static::get_step_name())), 'options' => array('type' => 'object', 'additionalProperties' => new \stdClass())), 'required' => array('step', 'options'));
    }
    /**
     * Prepare the JSON array for the step.
     *
     * @return array
     */
    public function prepare_json_array(): array
    {
        return array('step' => static::get_step_name(), 'options' => $this->options);
    }
}
