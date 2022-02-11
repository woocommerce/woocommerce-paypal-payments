<?php
/**
 * The ExchangeRateFactory Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExchangeRate;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ExchangeRateFactory
 */
class ExchangeRateFactory {
	/**
	 * Returns an ExchangeRate object based off a PayPal Response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return ExchangeRate
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( stdClass $data ): ExchangeRate {
		if ( ! isset( $data->source_currency ) ) {
			throw new RuntimeException( 'Exchange rate source currency not found' );
		}
		if ( ! isset( $data->target_currency ) ) {
			throw new RuntimeException( 'Exchange rate target currency not found' );
		}
		if ( ! isset( $data->value ) ) {
			throw new RuntimeException( 'Exchange rate value not found' );
		}

		return new ExchangeRate( $data->source_currency, $data->target_currency, $data->value );
	}
}
