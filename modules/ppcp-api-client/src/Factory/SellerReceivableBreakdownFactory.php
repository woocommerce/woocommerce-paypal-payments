<?php
/**
 * The SellerReceivableBreakdown Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PlatformFee;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerReceivableBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class SellerReceivableBreakdownFactory
 */
class SellerReceivableBreakdownFactory {

	/**
	 * The Money factory.
	 *
	 * @var MoneyFactory
	 */
	private $money_factory;

	/**
	 * The ExchangeRate factory.
	 *
	 * @var ExchangeRateFactory
	 */
	private $exchange_rate_factory;

	/**
	 * The PlatformFee factory.
	 *
	 * @var PlatformFeeFactory
	 */
	private $platform_fee_factory;

	/**
	 * SellerReceivableBreakdownFactory constructor.
	 *
	 * @param MoneyFactory        $money_factory The Money factory.
	 * @param ExchangeRateFactory $exchange_rate_factory The ExchangeRate factory.
	 * @param PlatformFeeFactory  $platform_fee_factory The PlatformFee factory.
	 */
	public function __construct(
		MoneyFactory $money_factory,
		ExchangeRateFactory $exchange_rate_factory,
		PlatformFeeFactory $platform_fee_factory
	) {

		$this->money_factory         = $money_factory;
		$this->exchange_rate_factory = $exchange_rate_factory;
		$this->platform_fee_factory  = $platform_fee_factory;
	}

	/**
	 * Returns a SellerReceivableBreakdown object based off a PayPal Response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return SellerReceivableBreakdown
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( stdClass $data ): SellerReceivableBreakdown {
		if ( ! isset( $data->gross_amount ) ) {
			throw new RuntimeException( 'Seller breakdown gross amount not found' );
		}

		$gross_amount                      = $this->money_factory->from_paypal_response( $data->gross_amount );
		$paypal_fee                        = ( isset( $data->paypal_fee ) ) ? $this->money_factory->from_paypal_response( $data->paypal_fee ) : null;
		$paypal_fee_in_receivable_currency = ( isset( $data->paypal_fee_in_receivable_currency ) ) ? $this->money_factory->from_paypal_response( $data->paypal_fee_in_receivable_currency ) : null;
		$net_amount                        = ( isset( $data->net_amount ) ) ? $this->money_factory->from_paypal_response( $data->net_amount ) : null;
		$receivable_amount                 = ( isset( $data->receivable_amount ) ) ? $this->money_factory->from_paypal_response( $data->receivable_amount ) : null;
		$exchange_rate                     = ( isset( $data->exchange_rate ) ) ? $this->exchange_rate_factory->from_paypal_response( $data->exchange_rate ) : null;
		$platform_fees                     = ( isset( $data->platform_fees ) ) ? array_map(
			function ( stdClass $fee_data ): PlatformFee {
				return $this->platform_fee_factory->from_paypal_response( $fee_data );
			},
			$data->platform_fees
		) : array();

		return new SellerReceivableBreakdown(
			$gross_amount,
			$paypal_fee,
			$paypal_fee_in_receivable_currency,
			$net_amount,
			$receivable_amount,
			$exchange_rate,
			$platform_fees
		);
	}
}
