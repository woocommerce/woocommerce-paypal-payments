<?php
/**
 * The partner referrals data object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;

/**
 * Class PartnerReferralsData
 */
class PartnerReferralsData {

	/**
	 * The merchant email.
	 *
	 * @var string
	 */
	private $merchant_email;

	/**
	 * The DCC Applies Helper object.
	 *
	 * @var DccApplies
	 */
	private $dcc_applies;

	/**
	 * PartnerReferralsData constructor.
	 *
	 * @param string     $merchant_email  The email of the merchant.
	 * @param DccApplies $dcc_applies The DCC Applies helper.
	 */
	public function __construct(
		string $merchant_email,
		DccApplies $dcc_applies
	) {

		$this->merchant_email = $merchant_email;
		$this->dcc_applies    = $dcc_applies;
	}

	/**
	 * Returns a nonce.
	 *
	 * @return string
	 */
	public function nonce(): string {
		return 'a1233wtergfsdt4365tzrshgfbaewa36AGa1233wtergfsdt4365tzrshgfbaewa36AG';
	}

	/**
	 * Returns the data.
	 *
	 * @return array
	 */
	public function data(): array {
		$data = $this->default_data();
		return $data;
	}

	/**
	 * Returns the default data.
	 *
	 * @return array
	 */
	private function default_data(): array {

		return array(
			'partner_config_override' => array(
				'partner_logo_url'       => 'https://connect.woocommerce.com/images/woocommerce_logo.png',
				'return_url'             => apply_filters(
					'woocommerce_paypal_payments_partner_config_override_return_url',
					admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' )
				),
				'return_url_description' => apply_filters(
					'woocommerce_paypal_payments_partner_config_override_return_url_description',
					__( 'Return to your shop.', 'woocommerce-paypal-payments' )
				),
				'show_add_credit_card'   => true,
			),
			'products'                => array(
				$this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
			),
			'legal_consents'          => array(
				array(
					'type'    => 'SHARE_DATA_CONSENT',
					'granted' => true,
				),
			),
			'operations'              => array(
				array(
					'operation'                  => 'API_INTEGRATION',
					'api_integration_preference' => array(
						'rest_api_integration' => array(
							'integration_method'  => 'PAYPAL',
							'integration_type'    => 'FIRST_PARTY',
							'first_party_details' => array(
								'features'     => array(
									'PAYMENT',
									'FUTURE_PAYMENT',
									'REFUND',
									'ADVANCED_TRANSACTIONS_SEARCH',
									'VAULT',
								),
								'seller_nonce' => $this->nonce(),
							),
						),
					),
				),
			),
		);
	}
}
