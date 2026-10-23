<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

class FastlaneSettings extends \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
{
    /**
     * Option key where Fastlane settings are stored.
     *
     * @var string
     */
    protected const OPTION_KEY = 'woocommerce-ppcp-data-fastlane';
    /**
     * Get default values for the model.
     *
     * @return array
     */
    protected function get_defaults(): array
    {
        return array('name_on_card' => '', 'style_root_bg_color' => '', 'style_root_error_color' => '', 'style_root_font_family' => '', 'style_root_text_color_base' => '', 'style_root_font_size_base' => '', 'style_root_padding' => '', 'style_root_primary_color' => '', 'style_input_bg_color' => '', 'style_input_border_radius' => '', 'style_input_border_color' => '', 'style_input_border_width' => '', 'style_input_text_color_base' => '', 'style_input_focus_border_color' => '');
    }
    /**
     * Get name on card setting.
     *
     * @return string
     */
    public function get_name_on_card(): string
    {
        return (string) $this->data['name_on_card'];
    }
    /**
     * Set name on card setting.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_name_on_card(string $value): void
    {
        $this->data['name_on_card'] = $value;
    }
    /**
     * Get root styles as an array formatted for the PayPal SDK.
     *
     * @return array
     */
    public function get_root_styles(): array
    {
        return array('backgroundColor' => (string) $this->data['style_root_bg_color'], 'errorColor' => (string) $this->data['style_root_error_color'], 'fontFamily' => (string) $this->data['style_root_font_family'], 'textColorBase' => (string) $this->data['style_root_text_color_base'], 'fontSizeBase' => (string) $this->data['style_root_font_size_base'], 'padding' => (string) $this->data['style_root_padding'], 'primaryColor' => (string) $this->data['style_root_primary_color']);
    }
    /**
     * Get input styles as an array formatted for the PayPal SDK.
     *
     * @return array
     */
    public function get_input_styles(): array
    {
        return array('backgroundColor' => (string) $this->data['style_input_bg_color'], 'borderRadius' => (string) $this->data['style_input_border_radius'], 'borderColor' => (string) $this->data['style_input_border_color'], 'borderWidth' => (string) $this->data['style_input_border_width'], 'textColorBase' => (string) $this->data['style_input_text_color_base'], 'focusBorderColor' => (string) $this->data['style_input_focus_border_color']);
    }
    /**
     * Set style root background color.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_root_bg_color(string $value): void
    {
        $this->data['style_root_bg_color'] = $value;
    }
    /**
     * Set style root error color.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_root_error_color(string $value): void
    {
        $this->data['style_root_error_color'] = $value;
    }
    /**
     * Set style root font family.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_root_font_family(string $value): void
    {
        $this->data['style_root_font_family'] = $value;
    }
    /**
     * Set style root text color base.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_root_text_color_base(string $value): void
    {
        $this->data['style_root_text_color_base'] = $value;
    }
    /**
     * Set style root font size base.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_root_font_size_base(string $value): void
    {
        $this->data['style_root_font_size_base'] = $value;
    }
    /**
     * Set style root padding.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_root_padding(string $value): void
    {
        $this->data['style_root_padding'] = $value;
    }
    /**
     * Set style root primary color.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_root_primary_color(string $value): void
    {
        $this->data['style_root_primary_color'] = $value;
    }
    /**
     * Set style input background color.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_input_bg_color(string $value): void
    {
        $this->data['style_input_bg_color'] = $value;
    }
    /**
     * Set style input border radius.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_input_border_radius(string $value): void
    {
        $this->data['style_input_border_radius'] = $value;
    }
    /**
     * Set style input border color.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_input_border_color(string $value): void
    {
        $this->data['style_input_border_color'] = $value;
    }
    /**
     * Set style input border width.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_input_border_width(string $value): void
    {
        $this->data['style_input_border_width'] = $value;
    }
    /**
     * Set style input text color base.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_input_text_color_base(string $value): void
    {
        $this->data['style_input_text_color_base'] = $value;
    }
    /**
     * Set style input focus border color.
     *
     * @param string $value The value.
     * @return void
     */
    public function set_style_input_focus_border_color(string $value): void
    {
        $this->data['style_input_focus_border_color'] = $value;
    }
}
