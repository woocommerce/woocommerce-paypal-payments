<?php

/**
 * Handles migration of general settings from legacy format to new structure.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
/**
 * Class SettingsMigration
 *
 * Handles migration of general plugin settings.
 */
class SettingsMigration implements \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigrationInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $settings;
    protected GeneralSettings $general_settings;
    protected PartnersEndpoint $partners_endpoint;
    public function __construct(array $settings, GeneralSettings $general_settings, PartnersEndpoint $partners_endpoint)
    {
        $this->settings = $settings;
        $this->general_settings = $general_settings;
        $this->partners_endpoint = $partners_endpoint;
    }
    public function migrate(): void
    {
        if (empty($this->settings['client_id']) || empty($this->settings['client_secret']) || empty($this->settings['merchant_id'])) {
            return;
        }
        $connection = new MerchantConnectionDTO(!empty($this->settings['sandbox_on']), $this->settings['client_id'], $this->settings['client_secret'], $this->settings['merchant_id'], $this->settings['merchant_email'] ?? '', $this->partners_endpoint->seller_status()->country(), $this->merchant_account_type($this->partners_endpoint->seller_status()));
        $this->general_settings->set_merchant_data($connection);
        $this->general_settings->save();
    }
    /**
     * Determines the merchant account type based on seller status capabilities.
     *
     * Analyzes PayPal seller capabilities to determine if the account is a business
     * account or falls back to unknown.
     *
     * @param SellerStatus $seller_status
     * @return 'business' | 'unknown' The merchant account type (SellerTypeEnum constant).
     */
    protected function merchant_account_type(SellerStatus $seller_status): string
    {
        if ($this->has_capability_active($seller_status, 'COMMERCIAL_ENTITY')) {
            return SellerTypeEnum::BUSINESS;
        }
        $business_capabilities = array('CUSTOM_CARD_PROCESSING', 'CARD_PROCESSING_VIRTUAL_TERMINAL', 'FRAUD_TOOL_ACCESS', 'PAY_UPON_INVOICE', 'SEND_INVOICE');
        foreach ($business_capabilities as $capability) {
            if ($this->has_capability_active($seller_status, $capability)) {
                return SellerTypeEnum::BUSINESS;
            }
        }
        foreach ($seller_status->products() as $product) {
            if ($product->name() === 'PPCP_CUSTOM' && $product->vetting_status() === 'SUBSCRIBED') {
                return SellerTypeEnum::BUSINESS;
            }
        }
        return SellerTypeEnum::UNKNOWN;
    }
    /**
     * Checks if a specific capability is active for the seller.
     *
     * @param SellerStatus $seller_status
     * @param string       $capability_name
     * @return bool True if the capability is active, false otherwise.
     */
    private function has_capability_active(SellerStatus $seller_status, string $capability_name): bool
    {
        foreach ($seller_status->capabilities() as $capability) {
            if ($capability->name() === $capability_name && $capability->status() === 'ACTIVE') {
                return \true;
            }
        }
        return \false;
    }
}
