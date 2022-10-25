<?php
/**
 * The PaymentToken object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class PaymentToken
 */
class PaymentToken {

	const TYPE_PAYMENT_METHOD_TOKEN = 'PAYMENT_METHOD_TOKEN';

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
	 * @param string   $id The Id.
	 * @param stdClass $source The source.
	 * @param string   $type The type.
	 * @throws RuntimeException When the type is not valid.
	 */
	public function __construct( string $id, stdClass $source, string $type = self::TYPE_PAYMENT_METHOD_TOKEN ) {
		if ( ! in_array( $type, self::get_valid_types(), true ) ) {
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

	/**
	 * Returns a list of valid token types.
	 * Can be modified through the `woocommerce_paypal_payments_valid_payment_token_types` filter.
	 *
	 * @return array
	 */
	public static function get_valid_types() {
		/**
		 * Returns a list of valid payment token types.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_valid_payment_token_types',
			array(
				self::TYPE_PAYMENT_METHOD_TOKEN,
			)
		);
	}

}
