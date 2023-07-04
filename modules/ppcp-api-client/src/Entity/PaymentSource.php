<?php
/**
 * The PaymentSource object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\Card;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource\PaymentSourceInterface;

/**
 * Class PaymentSource
 */
class PaymentSource {
	/**
	 * The map of source ID (paypal, card, ...) -> source object.
	 *
	 * @var array<string, PaymentSourceInterface>
	 */
	private $sources = array();

	/**
	 * PaymentSource constructor.
	 *
	 * @param PaymentSourceInterface ...$sources The payment source objects.
	 */
	public function __construct(
		PaymentSourceInterface ...$sources
	) {
		foreach ( $sources as $source ) {
			$this->sources[ $source->payment_source_id() ] = $source;
		}
	}

	/**
	 * Returns the payment source objects.
	 *
	 * @return PaymentSourceInterface[]
	 */
	public function sources(): array {
		return $this->sources;
	}

	/**
	 * Returns the card.
	 *
	 * @return Card|null
	 */
	public function card() {
		$card = $this->sources['card'] ?? null;
		if ( $card instanceof Card ) {
			return $card;
		}
		return null;
	}

	/**
	 * Returns the array of the object.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array();
		foreach ( $this->sources as $source ) {
			$data[ $source->payment_source_id() ] = $source->to_array();
		}
		return $data;
	}
}
