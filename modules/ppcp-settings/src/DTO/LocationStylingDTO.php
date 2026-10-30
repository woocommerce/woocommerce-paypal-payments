<?php

/**
 * Data transfer object. Stores styling details for a single location.
 *
 * @package WooCommerce\PayPalCommerce\Settings\DTO;
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\DTO;

/**
 * DTO that collects all styling details of a single location
 *
 * Intentionally has no internal logic, sanitation or validation.
 */
class LocationStylingDTO
{
    /**
     * The location name.
     *
     * @var string [cart|classic_checkout|express_checkout|mini_cart|product]
     */
    public string $location;
    /**
     * Whether PayPal payments are enabled on this location.
     *
     * @var bool
     */
    public bool $enabled;
    /**
     * List of active payment methods, e.g., 'venmo', 'applepay', ...
     *
     * @var array
     */
    public array $methods;
    /**
     * Shape of buttons on this location.
     *
     * @var string [rect|pill]
     */
    public string $shape;
    /**
     * Label of the button on this location.
     *
     * @var string
     */
    public string $label;
    /**
     * Color of the button on this location.
     *
     * @var string [gold|blue|silver|black|white]
     */
    public string $color;
    /**
     * The button layout
     *
     * @var string [horizontal|vertical]
     */
    public string $layout;
    /**
     * Whether to show a tagline below the buttons.
     *
     * @var bool
     */
    public bool $tagline;
    /**
     * Constructor.
     *
     * @param string $location The location name.
     * @param bool   $enabled  Whether PayPal payments are enabled on this location.
     * @param array  $methods  List of active payment methods.
     * @param string $shape    Shape of buttons on this location.
     * @param string $label    Label of the button on this location.
     * @param string $color    Color of the button on this location.
     * @param string $layout   Horizontal or vertical button layout.
     * @param bool   $tagline  Whether to show a tagline below the buttons.
     */
    public function __construct(string $location = '', bool $enabled = \true, array $methods = array(), string $shape = 'rect', string $label = 'pay', string $color = 'gold', string $layout = 'vertical', bool $tagline = \false)
    {
        $this->location = $location;
        $this->enabled = $enabled;
        $this->methods = $methods;
        $this->shape = $shape;
        $this->label = $label;
        $this->color = $color;
        $this->layout = $layout;
        $this->tagline = $tagline;
    }
}
