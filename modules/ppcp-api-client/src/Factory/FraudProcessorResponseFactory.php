<?php
/**
 * The FraudProcessorResponseFactory Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\FraudProcessorResponse;

/**
 * Class FraudProcessorResponseFactory
 */
class FraudProcessorResponseFactory {

	/**
	 * Returns a FraudProcessorResponse object based off a PayPal Response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return FraudProcessorResponse
	 */
	public function from_paypal_response( stdClass $data ): FraudProcessorResponse {
		$avs_code = $data->avs_code ?: null;
		$cvv_code = $data->cvv_code ?: null;

		return new FraudProcessorResponse( $avs_code, $cvv_code );
	}
}
