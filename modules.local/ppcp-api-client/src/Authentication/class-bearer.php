<?php
/**
 * The bearer interface.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Authentication
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;

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
