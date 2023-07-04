<?php
/**
 * The interface for payment source objects.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;

/**
 * Interface PaymentSourceInterface
 */
interface PaymentSourceInterface {

	/**
	 * Returns the payment source ID.
	 */
	public function payment_source_id(): string;

	/**
	 * Returns the object as array.
	 */
	public function to_array(): array;
}
