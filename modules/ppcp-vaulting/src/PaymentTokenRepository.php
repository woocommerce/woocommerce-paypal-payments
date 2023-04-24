<?php
/**
 * The payment token repository returns or deletes payment tokens for users.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;

/**
 * Class PaymentTokenRepository
 */
class PaymentTokenRepository {


	/**
	 * The payment token factory.
	 *
	 * @var PaymentTokenFactory
	 */
	private $factory;

	/**
	 * The payment token endpoint.
	 *
	 * @var PaymentTokenEndpoint
	 */
	private $endpoint;

	/**
	 * PaymentTokenRepository constructor.
	 *
	 * @param PaymentTokenFactory  $factory The payment token factory.
	 * @param PaymentTokenEndpoint $endpoint The payment token endpoint.
	 */
	public function __construct(
		PaymentTokenFactory $factory,
		PaymentTokenEndpoint $endpoint
	) {

		$this->factory  = $factory;
		$this->endpoint = $endpoint;
	}

	/**
	 * Return all tokens for a user.
	 *
	 * @param int $id The user id.
	 * @return PaymentToken[]
	 */
	public function all_for_user_id( int $id ) {
		try {
			$tokens = $this->endpoint->for_user( $id );
			return $tokens;
		} catch ( RuntimeException $exception ) {
			return array();
		}
	}

	/**
	 * Delete a token for a user.
	 *
	 * @param int          $user_id The user id.
	 * @param PaymentToken $token The token.
	 *
	 * @return bool
	 */
	public function delete_token( int $user_id, PaymentToken $token ): bool {
		return $this->endpoint->delete_token( $token );
	}

	/**
	 * Check if tokens has card source.
	 *
	 * @param PaymentToken[] $tokens The tokens.
	 * @return bool Whether tokens contains card or not.
	 */
	public function tokens_contains_card( array $tokens ): bool {
		return $this->token_contains_source( $tokens, 'card' );
	}

	/**
	 * Check if tokens has PayPal source.
	 *
	 * @param PaymentToken[] $tokens The tokens.
	 * @return bool Whether tokens contains card or not.
	 */
	public function tokens_contains_paypal( array $tokens ): bool {
		return $this->token_contains_source( $tokens, 'paypal' );
	}

	/**
	 * Checks if tokens has the given source.
	 *
	 * @param array  $tokens Payment tokens.
	 * @param string $source_type Payment token source type.
	 * @return bool Whether tokens contains source or not.
	 */
	private function token_contains_source( array $tokens, string $source_type ): bool {
		foreach ( $tokens as $token ) {
			if ( isset( $token->source()->card ) && 'card' === $source_type || isset( $token->source()->paypal ) && 'paypal' === $source_type ) {
				return true;
			}
		}

		return false;
	}
}
