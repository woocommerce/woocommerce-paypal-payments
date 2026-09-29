<?php

/**
 * Manage the Seller status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatusResultCache;
class DCCProductStatus extends ProductStatus
{
    public const KEY = 'products_dcc_enabled';
    protected DccApplies $dcc_applies;
    public function __construct(bool $is_connected, PartnersEndpoint $partners_endpoint, FailureRegistry $api_failure_registry, ProductStatusResultCache $result_cache, DccApplies $dcc_applies)
    {
        parent::__construct($is_connected, $partners_endpoint, $api_failure_registry, $result_cache);
        $this->dcc_applies = $dcc_applies;
    }
    public function check_local_state(bool $skip_filters = \false): ?bool
    {
        if (!$skip_filters) {
            /**
             * Force BCDC (Standard Cards) for merchants migrated from legacy UI.
             *
             * This filter allows migrated merchants that used Standard Card buttons
             * in the legacy UI to maintain BCDC functionality in the new UI, regardless
             * of ACDC eligibility API responses.
             */
            $bcdc_override = apply_filters('woocommerce_paypal_payments_override_acdc_status_with_bcdc', null);
            if ($bcdc_override === \true) {
                // When overriding, short-circuit and mark ACDC as not available.
                return \false;
            }
        }
        return parent::check_local_state();
    }
    protected function check_api_response(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->products() as $product) {
            if (!in_array($product->vetting_status(), array(SellerStatusProduct::VETTING_STATUS_APPROVED, SellerStatusProduct::VETTING_STATUS_SUBSCRIBED), \true)) {
                continue;
            }
            if (in_array('CUSTOM_CARD_PROCESSING', $product->capabilities(), \true)) {
                return \true;
            }
        }
        return \false;
    }
    protected function get_cache_lifespan(bool $is_eligible): int
    {
        if (!$is_eligible && $this->dcc_applies->for_country_currency()) {
            return 3 * HOUR_IN_SECONDS;
        }
        return MONTH_IN_SECONDS;
    }
}
