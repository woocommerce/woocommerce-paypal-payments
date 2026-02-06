<?php

/**
 * The partner referrals data object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
/**
 * Class PartnerReferralsData
 */
class PartnerReferralsData
{
    /**
     * The DCC Applies Helper object.
     *
     * @deprecated Deprecates with the new UI. In this class, the products are
     *             always explicit, and should not be deducted from the
     *             DccApplies state at this point.
     *             Remove this with the #legacy-ui code.
     * @var DccApplies
     */
    private DccApplies $dcc_applies;
    /**
     * PartnerReferralsData constructor.
     *
     * @param DccApplies $dcc_applies The DCC Applies helper.
     */
    public function __construct(DccApplies $dcc_applies)
    {
        $this->dcc_applies = $dcc_applies;
    }
    /**
     * Returns a nonce.
     *
     * @return string
     */
    public function nonce(): string
    {
        return 'a1233wtergfsdt4365tzrshgfbaewa36AGa1233wtergfsdt4365tzrshgfbaewa36AG';
    }
    /**
     * Returns the data.
     *
     * @param string[] $products          The list of products to use ('PPCP', 'EXPRESS_CHECKOUT').
     *                                    Default is based on DCC availability.
     * @param string   $onboarding_token  A security token to finalize the onboarding process.
     * @param bool     $use_subscriptions If the merchant requires subscription features.
     * @param bool     $use_card_payments If the merchant wants to process credit card payments.
     * @return array
     */
    public function data(array $products = array(), string $onboarding_token = '', ?bool $use_subscriptions = null, bool $use_card_payments = \true): array
    {
        $in_acdc_country = $this->dcc_applies->for_country_currency();
        if (!$products) {
            $products = array($in_acdc_country ? 'PPCP' : 'EXPRESS_CHECKOUT');
        }
        /**
         * Filter the return-URL, which is called at the end of the OAuth onboarding
         * process, when the merchant clicks the "Return to your shop" button.
         */
        $return_url = apply_filters('woocommerce_paypal_payments_partner_config_override_return_url', admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'));
        /**
         * Filter the label of the "Return to your shop" button.
         * It's displayed on the very last page of the onboarding popup.
         */
        $return_url_label = apply_filters('woocommerce_paypal_payments_partner_config_override_return_url_description', __('Return to your shop.', 'woocommerce-paypal-payments'));
        $capabilities = array();
        $first_party_features = array('PAYMENT', 'REFUND', 'ADVANCED_TRANSACTIONS_SEARCH', 'TRACKING_SHIPMENT_READWRITE');
        if ($in_acdc_country) {
            $products = array('PPCP', 'ADVANCED_VAULTING');
            $capabilities[] = 'PAYPAL_WALLET_VAULTING_ADVANCED';
        }
        $first_party_features[] = 'BILLING_AGREEMENT';
        if ($use_card_payments !== \false) {
            $first_party_features[] = 'VAULT';
            $first_party_features[] = 'FUTURE_PAYMENT';
        }
        $payload = array('partner_config_override' => array('return_url' => $return_url, 'return_url_description' => $return_url_label, 'show_add_credit_card' => $use_card_payments), 'products' => $products, 'capabilities' => $capabilities, 'legal_consents' => array(array('type' => 'SHARE_DATA_CONSENT', 'granted' => \true)), 'operations' => array(array('operation' => 'API_INTEGRATION', 'api_integration_preference' => array('rest_api_integration' => array('integration_method' => 'PAYPAL', 'integration_type' => 'FIRST_PARTY', 'first_party_details' => array('features' => $first_party_features, 'seller_nonce' => $this->nonce()))))));
        /**
         * Filter the final partners referrals data collection.
         */
        $payload = apply_filters('ppcp_partner_referrals_data', $payload);
        // An empty array is not permitted.
        if (isset($payload['capabilities']) && !$payload['capabilities']) {
            unset($payload['capabilities']);
        }
        // Add the nonce in the end, to maintain backwards compatibility of filters.
        $payload['partner_config_override']['return_url'] = add_query_arg(array('ppcpToken' => $onboarding_token), $payload['partner_config_override']['return_url']);
        return $payload;
    }
}
