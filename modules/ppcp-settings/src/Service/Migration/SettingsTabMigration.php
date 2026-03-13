<?php

/**
 * Handles migration of settings tab settings from legacy format to new structure.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PurchaseUnitSanitizer;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
/**
 * Class SettingsTabMigration
 *
 * Handles migration of settings tab settings.
 */
class SettingsTabMigration implements \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigrationInterface
{
    /**
     * A map of new to old 3d secure values.
     *
     * @var array<string, string>
     */
    public const THREE_D_SECURE_VALUES_MAP = array('no-3d-secure' => 'NO_3D_SECURE', 'only-required-3d-secure' => 'SCA_WHEN_REQUIRED', 'always-3d-secure' => 'SCA_ALWAYS');
    /**
     * @var array<string, mixed>
     */
    protected array $settings;
    protected SettingsModel $settings_tab;
    public function __construct(array $settings, SettingsModel $settings_tab)
    {
        $this->settings = $settings;
        $this->settings_tab = $settings_tab;
    }
    public function migrate(): void
    {
        $data = array();
        foreach ($this->map() as $old_key => $new_key) {
            if (!isset($this->settings[$old_key])) {
                continue;
            }
            switch ($old_key) {
                case 'subtotal_mismatch_behavior':
                    $value = $this->settings[$old_key];
                    $data[$new_key] = $value === PurchaseUnitSanitizer::MODE_EXTRA_LINE ? 'correction' : 'no_details';
                    break;
                case 'landing_page':
                    $value = $this->settings[$old_key];
                    $data[$new_key] = $value === ExperienceContext::LANDING_PAGE_LOGIN ? 'login' : ($value === ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT ? 'guest_checkout' : 'any');
                    break;
                case 'intent':
                    $value = $this->settings[$old_key];
                    $data['authorize_only'] = $value === 'authorize';
                    break;
                case 'blocks_final_review_enabled':
                    $data[$new_key] = !$this->settings[$old_key];
                    break;
                case '3d_secure_contingency':
                    $value = $this->settings[$old_key];
                    $old_to_new_3d_secure_map = array_flip(self::THREE_D_SECURE_VALUES_MAP);
                    $data[$new_key] = $old_to_new_3d_secure_map[$value] ?? 'NO_3D_SECURE';
                    break;
                default:
                    $data[$new_key] = $this->settings[$old_key];
            }
        }
        if (isset($this->settings['stay_updated']) && !$this->settings['stay_updated']) {
            $this->settings_tab->set_payment_level_processing(\false);
        }
        $this->settings_tab->from_array($data);
        $this->settings_tab->save();
    }
    /**
     * Maps old setting keys to new setting keys.
     *
     * @psalm-return array<string, string>
     */
    protected function map(): array
    {
        return array('disable_cards' => 'disabled_cards', 'brand_name' => 'brand_name', 'soft_descriptor' => 'soft_descriptor', 'payee_preferred' => 'instant_payments_only', 'subtotal_mismatch_behavior' => 'subtotal_adjustment', 'landing_page' => 'landing_page', 'smart_button_language' => 'button_language', 'prefix' => 'invoice_prefix', 'intent' => '', 'capture_for_virtual_only' => 'capture_virtual_orders', 'vault_enabled_dcc' => 'save_card_details', 'blocks_final_review_enabled' => 'enable_pay_now', 'logging_enabled' => 'enable_logging', 'vault_enabled' => FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO, '3d_secure_contingency' => 'three_d_secure', 'stay_updated' => 'stay_updated', 'card_icons' => 'card_icons');
    }
}
