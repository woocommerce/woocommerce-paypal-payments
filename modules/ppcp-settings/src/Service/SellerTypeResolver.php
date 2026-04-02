<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
class SellerTypeResolver
{
    /**
     * Determines the merchant account type based on seller status capabilities.
     *
     * @param SellerStatus $seller_status The seller status from PayPal API.
     * @return string A SellerTypeEnum value.
     */
    public function resolve(SellerStatus $seller_status): string
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
     * For merchants that migrated with an unknown seller type (e.g. API was down
     * during migration), this retries the seller status call and persists the
     * resolved type. Also backfills empty merchant_country.
     *
     * @param FailureRegistry  $failure_registry  The failure registry.
     * @param GeneralSettings  $general_settings  The general settings.
     * @param PartnersEndpoint $partners_endpoint The partners endpoint.
     * @param LoggerInterface  $logger            The logger.
     */
    public function resolve_unknown_seller_type(FailureRegistry $failure_registry, GeneralSettings $general_settings, PartnersEndpoint $partners_endpoint, LoggerInterface $logger): void
    {
        if (!$this->needs_seller_type_resolution($failure_registry, $general_settings)) {
            return;
        }
        try {
            $seller_status = $partners_endpoint->seller_status();
            $seller_type = $this->resolve($seller_status);
            if ($seller_type !== SellerTypeEnum::UNKNOWN) {
                $current = $general_settings->get_merchant_data();
                $connection = new MerchantConnectionDTO($current->is_sandbox, $current->client_id, $current->client_secret, $current->merchant_id, $current->merchant_email, empty($current->merchant_country) ? $seller_status->country() : $current->merchant_country, $seller_type);
                $general_settings->set_merchant_data($connection);
                $general_settings->save();
                do_action('woocommerce_paypal_payments_clear_apm_product_status');
                return;
            }
        } catch (Exception $e) {
            $logger->debug('Seller type resolution deferred; will retry in 1 hour.', array('error' => $e->getMessage()));
        }
        // Seller type still unknown — throttle retries to once per hour.
        $failure_registry->add_failure(FailureRegistry::SELLER_STATUS_KEY);
    }
    /**
     * Checks whether seller type resolution is needed.
     *
     * @param FailureRegistry $failure_registry The failure registry.
     * @param GeneralSettings $general_settings The general settings.
     * @return bool True if the merchant is connected but has an unknown seller type.
     */
    public function needs_seller_type_resolution(FailureRegistry $failure_registry, GeneralSettings $general_settings): bool
    {
        if ($failure_registry->has_failure_in_timeframe(FailureRegistry::SELLER_STATUS_KEY, HOUR_IN_SECONDS)) {
            return \false;
        }
        if (!$general_settings->is_merchant_connected()) {
            return \false;
        }
        return SellerTypeEnum::UNKNOWN === $general_settings->get_merchant_data()->seller_type;
    }
    /**
     * Checks if a specific capability is active for the seller.
     *
     * @param SellerStatus $seller_status  The seller status.
     * @param string       $capability_name The capability name to check.
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
