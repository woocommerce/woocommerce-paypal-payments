<?php
/**
 * The payment token repository returns or deletes payment tokens for users.
 *
 * @package WooCommerce\PayPalCommerce\Subscription\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;

/**
 * Class PaymentTokenRepository
 */
class PaymentTokenRepository {


	const USER_META = 'ppcp-vault-token';

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
	 * Return a token for a user.
	 *
	 * @param int $id The user id.
	 *
	 * @return PaymentToken|null
	 */
	public function for_user_id( int $id ) {
		try {
			$token = (array) get_user_meta( $id, self::USER_META, true );
			if ( ! $token || ! isset( $token['id'] ) ) {
				return $this->fetch_for_user_id( $id );
			}

			$token = $this->factory->from_array( $token );
			return $token;
		} catch ( RuntimeException $error ) {
			return null;
		}
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
			update_user_meta( $id, self::USER_META, $tokens );
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
		delete_user_meta( $user_id, self::USER_META );
		return $this->endpoint->delete_token( $token );
	}

	/**
	 * Check if tokens has card source.
	 *
	 * @param PaymentToken[] $tokens The tokens.
	 * @return bool Whether tokens contains card or not.
	 */
	public function tokens_contains_card( $tokens ): bool {
		foreach ( $tokens as $token ) {
			if ( isset( $token->source()->card ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if tokens has PayPal source.
	 *
	 * @param PaymentToken[] $tokens The tokens.
	 * @return bool Whether tokens contains card or not.
	 */
	public function tokens_contains_paypal( $tokens ): bool {
		foreach ( $tokens as $token ) {
			if ( isset( $token->source()->paypal ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fetch PaymentToken from PayPal for a user.
	 *
	 * @param int $id The user id.
	 * @return PaymentToken
	 */
	private function fetch_for_user_id( int $id ): PaymentToken {

		$tokens      = $this->endpoint->for_user( $id );
		$token       = current( $tokens );
		$token_array = $token->to_array();
		update_user_meta( $id, self::USER_META, $token_array );
		return $token;
	}
}
