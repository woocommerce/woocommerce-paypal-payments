<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Helper\DccApplies;

class PartnerReferralsData
{

    private $merchantEmail;
    private $dccApplies;
    public function __construct(
        string $merchantEmail,
        DccApplies $dccApplies
    ) {

        $this->merchantEmail = $merchantEmail;
        $this->dccApplies = $dccApplies;
    }

    public function nonce(): string
    {
        return 'a1233wtergfsdt4365tzrshgfbaewa36AGa1233wtergfsdt4365tzrshgfbaewa36AG';
    }

    public function data(): array
    {
        $data = $this->defaultData();
        return $data;
    }

    private function defaultData(): array
    {

        return [
            "email" => $this->merchantEmail,
            "partner_config_override" => [
                "partner_logo_url" => "https://connect.woocommerce.com/images/woocommerce_logo.png",
                "return_url" => admin_url(
                    'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'
                ),
                "return_url_description" => __(
                    'Return to your shop.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                "show_add_credit_card" => true,
            ],
            "products" => [
                $this->dccApplies->forCountryCurrency() ? "PPCP" : "EXPRESS_CHECKOUT",
            ],
            "legal_consents" => [
                [
                    "type" => "SHARE_DATA_CONSENT",
                    "granted" => true,
                ],
            ],
            "operations" => [
                [
                    "operation" => "API_INTEGRATION",
                    "api_integration_preference" => [
                        "rest_api_integration" => [
                            "integration_method" => "PAYPAL",
                            "integration_type" => "FIRST_PARTY",
                            "first_party_details" => [
                                "features" => [
                                    "PAYMENT",
                                    "FUTURE_PAYMENT",
                                    "REFUND",
                                    "ADVANCED_TRANSACTIONS_SEARCH",
                                ],
                                "seller_nonce" => $this->nonce(),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
