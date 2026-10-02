<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\DTO;

/**
 * DTO that collects all Pay Later messaging details of a single location.
 */
class PayLaterMessagingDTO
{
    public string $location;
    public bool $enabled;
    public string $layout;
    public string $logo_type;
    public string $logo_position;
    public string $text_color;
    public string $text_size;
    public string $flex_color;
    public string $flex_ratio;
    public function __construct(string $location = '', bool $enabled = \false, string $layout = 'text', string $logo_type = 'inline', string $logo_position = 'left', string $text_color = 'black', string $text_size = '12', string $flex_color = 'black', string $flex_ratio = '8x1')
    {
        $this->location = $location;
        $this->enabled = $enabled;
        $this->layout = $layout;
        $this->logo_type = $logo_type;
        $this->logo_position = $logo_position;
        $this->text_color = $text_color;
        $this->text_size = $text_size;
        $this->flex_color = $flex_color;
        $this->flex_ratio = $flex_ratio;
    }
}
