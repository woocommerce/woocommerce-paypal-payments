<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Settings\Data\FastlaneSettings;
class FastlaneSettingsMigration implements \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigrationInterface
{
    /**
     * Legacy settings array.
     *
     * @var array<string, mixed>
     */
    protected array $settings;
    /**
     * Fastlane settings model.
     *
     * @var FastlaneSettings
     */
    protected FastlaneSettings $fastlane_settings;
    /**
     * Constructor.
     *
     * @param array            $settings          Legacy settings array.
     * @param FastlaneSettings $fastlane_settings Fastlane settings model.
     */
    public function __construct(array $settings, FastlaneSettings $fastlane_settings)
    {
        $this->settings = $settings;
        $this->fastlane_settings = $fastlane_settings;
    }
    /**
     * Migrate legacy Fastlane settings to the new structure.
     *
     * @return void
     */
    public function migrate(): void
    {
        $data = array();
        foreach ($this->map() as $old_key => $new_key) {
            if (isset($this->settings[$old_key])) {
                $data[$new_key] = $this->settings[$old_key];
            }
        }
        if (!empty($data)) {
            $this->fastlane_settings->from_array($data);
            $this->fastlane_settings->save();
        }
    }
    /**
     * Maps old setting keys to new setting keys.
     *
     * @return array<string, string>
     */
    protected function map(): array
    {
        return array('axo_name_on_card' => 'name_on_card', 'axo_style_root_bg_color' => 'style_root_bg_color', 'axo_style_root_error_color' => 'style_root_error_color', 'axo_style_root_font_family' => 'style_root_font_family', 'axo_style_root_text_color_base' => 'style_root_text_color_base', 'axo_style_root_font_size_base' => 'style_root_font_size_base', 'axo_style_root_padding' => 'style_root_padding', 'axo_style_root_primary_color' => 'style_root_primary_color', 'axo_style_input_bg_color' => 'style_input_bg_color', 'axo_style_input_border_radius' => 'style_input_border_radius', 'axo_style_input_border_color' => 'style_input_border_color', 'axo_style_input_border_width' => 'style_input_border_width', 'axo_style_input_text_color_base' => 'style_input_text_color_base', 'axo_style_input_focus_border_color' => 'style_input_focus_border_color');
    }
}
