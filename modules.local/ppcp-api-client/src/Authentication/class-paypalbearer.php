<?php
/**
 * The PayPal bearer.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Authentication
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Class PayPalBearer
 */
class PayPalBearer implements Bearer {

	use RequestTrait;

	public const CACHE_KEY = 'ppcp-bearer';

	/**
	 * The cache.
	 *
	 * @var CacheInterface
	 */
	private $cache;

	/**
	 * The host.
	 *
	 * @var string
	 */
	private $host;

	/**
	 * The client key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * The client secret.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PayPalBearer constructor.
	 *
	 * @param CacheInterface  $cache The cache.
	 * @param string          $host The host.
	 * @param string          $key The key.
	 * @param string          $secret The secret.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		CacheInterface $cache,
		string $host,
		string $key,
		string $secret,
		LoggerInterface $logger
	) {

		$this->cache  = $cache;
		$this->host   = $host;
		$this->key    = $key;
		$this->secret = $secret;
		$this->logger = $logger;
	}

	/**
	 * Returns a bearer token.
	 *
	 * @return Token
	 * @throws \Psr\SimpleCache\InvalidArgumentException When cache is invalid.
	 * @throws RuntimeException When request fails.
	 */
	public function bearer(): Token {
		try {
			$bearer = Token::from_json( (string) $this->cache->get( self::CACHE_KEY ) );
			return ( $bearer->is_valid() ) ? $bearer : $this->newBearer();
		} catch ( RuntimeException $error ) {
			return $this->newBearer();
		}
	}

	/**
	 * Creates a new bearer token.
	 *
	 * @return Token
	 * @throws \Psr\SimpleCache\InvalidArgumentException When cache is invalid.
	 * @throws RuntimeException When request fails.
	 */
	private function newBearer(): Token {
		$url      = trailingslashit( $this->host ) . 'v1/oauth2/token?grant_type=client_credentials';
		$args     = array(
			'method'  => 'POST',
			'headers' => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Authorization' => 'Basic ' . base64_encode( $this->key . ':' . $this->secret ),
			),
		);
		$response = $this->request(
			$url,
			$args
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$error = new RuntimeException(
				__( 'Could not create token.', 'woocommerce-paypal-commerce-gateway' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$token = Token::from_json( $response['body'] );
		$this->cache->set( self::CACHE_KEY, $token->as_json() );
		return $token;
	}
}
