<?php
/**
 * The connect dummy bearer.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Authentication
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;

/**
 * Class ConnectBearer
 */
class ConnectBearer implements Bearer {

	/**
	 * Returns the bearer.
	 *
	 * @return Token
	 */
	public function bearer(): Token {
		$data = (object) array(
			'created'    => time(),
			'expires_in' => 3600,
			'token'      => 'token',
		);
		return new Token( $data );
	}
}
