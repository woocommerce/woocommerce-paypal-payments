<?php
/**
 * The PayerTaxInfo object.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class PayerTaxInfo
 */
class PayerTaxInfo {


	public const VALID_TYPES = array(
		'BR_CPF',
		'BR_CNPJ',
	);

	/**
	 * The tax id.
	 *
	 * @var string
	 */
	private $tax_id;

	/**
	 * The type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * PayerTaxInfo constructor.
	 *
	 * @param string $tax_id The tax id.
	 * @param string $type The type.
	 * @throws RuntimeException When the type is not valid.
	 */
	public function __construct(
		string $tax_id,
		string $type
	) {

		if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
			throw new RuntimeException(
				sprintf(
				// translators: %s is the current type.
					__( '%s is not a valid tax type.', 'paypal-for-woocommerce' ),
					$type
				)
			);
		}
		$this->tax_id = $tax_id;
		$this->type   = $type;
	}

	/**
	 * Returns the type.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Returns the tax id
	 *
	 * @return string
	 */
	public function tax_id(): string {
		return $this->tax_id;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'tax_id'      => $this->tax_id(),
			'tax_id_type' => $this->type(),
		);
	}
}
