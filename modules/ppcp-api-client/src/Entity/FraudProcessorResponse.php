<?php
/**
 * The FraudProcessorResponse object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class FraudProcessorResponse
 */
class FraudProcessorResponse {

	/**
	 * The AVS response code.
	 *
	 * @var string|null
	 */
	protected $avs_code;

	/**
	 * The CVV response code.
	 *
	 * @var string|null
	 */
	protected $cvv_code;

	/**
	 * FraudProcessorResponse constructor.
	 *
	 * @param string|null $avs_code The AVS response code.
	 * @param string|null $cvv_code The CVV response code.
	 */
	public function __construct( ?string $avs_code, ?string $cvv_code ) {
		$this->avs_code = $avs_code;
		$this->cvv_code = $cvv_code;
	}

	/**
	 * Returns the AVS response code.
	 *
	 * @return string|null
	 */
	public function avs_code(): ?string {
		return $this->avs_code;
	}

	/**
	 * Returns the CVV response code.
	 *
	 * @return string|null
	 */
	public function cvv_code(): ?string {
		return $this->cvv_code;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'avs_code'      => $this->avs_code() ?: '',
			'address_match' => $this->avs_code() === 'M' ? 'Y' : 'N',
			'postal_match'  => $this->avs_code() === 'M' ? 'Y' : 'N',
			'cvv_match'     => $this->cvv_code() === 'M' ? 'Y' : 'N',
		);
	}

}
