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
	 * The DCC Applies Helper object.
	 *
	 * @var DccApplies
	 */
	private $dcc_applies;

	/**
	 * The list of products ('PPCP', 'EXPRESS_CHECKOUT').
	 *
	 * @var string[]
	 */
	private $products;

	/**
	 * PartnerReferralsData constructor.
	 *
	 * @param DccApplies $dcc_applies The DCC Applies helper.
	 */
	public function __construct(
		DccApplies $dcc_applies
	) {
		$this->dcc_applies = $dcc_applies;
		$this->products    = array(
			$this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
		);
	}

	/**
	 * Returns a new copy of this object with the given value set.
	 *
	 * @param string[] $products The list of products ('PPCP', 'EXPRESS_CHECKOUT').
	 * @return static
	 */
	public function with_products( array $products ): self {
		$obj = clone $this;

		$obj->products = $products;

		return $obj;
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
		return array(
			'partner_config_override' => array(
				'partner_logo_url'       => 'https://connect.woocommerce.com/images/woocommerce_logo.png',
				/**
				 * Returns the URL which will be opened at the end of onboarding.
				 */
				'return_url'             => apply_filters(
					'woocommerce_paypal_payments_partner_config_override_return_url',
					admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' )
				),
				/**
				 * Returns the description of the URL which will be opened at the end of onboarding.
				 */
				'return_url_description' => apply_filters(
					'woocommerce_paypal_payments_partner_config_override_return_url_description',
					__( 'Return to your shop.', 'woocommerce-paypal-payments' )
				),
				'show_add_credit_card'   => true,
			),
			'products'                => $this->products,
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
