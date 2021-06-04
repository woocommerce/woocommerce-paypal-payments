<?php
/**
 * The PaymentToken object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class PaymentToken
 */
class PaymentToken {


	const TYPE_PAYMENT_METHOD_TOKEN = 'PAYMENT_METHOD_TOKEN';
	const VALID_TYPES               = array(
		self::TYPE_PAYMENT_METHOD_TOKEN,
	);

	/**
	 * The Id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The payment source.
	 *
	 * @var \stdClass
	 */
	private $source;

	/**
	 * PaymentToken constructor.
	 *
	 * @param string    $id The Id.
	 * @param string    $type The type.
	 * @param \stdClass $source The source.
	 * @throws RuntimeException When the type is not valid.
	 */
	public function __construct( string $id, string $type = self::TYPE_PAYMENT_METHOD_TOKEN, \stdClass $source ) {
		if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
			throw new RuntimeException(
				__( 'Not a valid payment source type.', 'woocommerce-paypal-payments' )
			);
		}
		$this->id     = $id;
		$this->type   = $type;
		$this->source = $source;
	}

	/**
	 * Returns the ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
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
	 * Returns the source.
	 *
	 * @return \stdClass
	 */
	public function source(): \stdClass {
		return $this->source;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'     => $this->id(),
			'type'   => $this->type(),
			'source' => $this->source(),
		);
	}
}
