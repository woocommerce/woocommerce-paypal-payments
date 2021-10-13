<?php
/**
 * The bearer interface.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Authentication
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;

/**
 * Interface Bearer
 */
interface Bearer {

	/**
	 * Returns the bearer.
	 *
	 * @return Token
	 */
	public function bearer(): Token;
}
