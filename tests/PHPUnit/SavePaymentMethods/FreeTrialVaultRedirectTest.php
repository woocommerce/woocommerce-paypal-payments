<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\FreeTrialVaultReturnEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class FreeTrialVaultRedirectTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/** @var PaymentMethodTokensEndpoint&\Mockery\MockInterface */
	private $tokens_endpoint;

	/** @var LoggerInterface&\Mockery\MockInterface */
	private $logger;

	/** @var FreeTrialVaultRedirect */
	private $sut;

	public function setUp(): void {
		parent::setUp();

		$this->tokens_endpoint = Mockery::mock( PaymentMethodTokensEndpoint::class );
		$this->logger          = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->sut = new FreeTrialVaultRedirect(
			$this->tokens_endpoint,
			$this->logger
		);

		when( 'is_user_logged_in' )->justReturn( false );
		when( 'get_user_meta' )->justReturn( '' );
		when( 'home_url' )->returnArg();
		when( 'wc_get_checkout_url' )->justReturn( 'https://example.com/checkout/' );
		when( 'esc_url_raw' )->returnArg();
		when( 'wp_generate_password' )->justReturn( 'nonce-abc123' );
		when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ): string {
				return $url . '?' . http_build_query( $args );
			}
		);
	}

	private function create_wc_order( int $order_id = 1 ): \WC_Order {
		$order = Mockery::mock( \WC_Order::class );
		$order->allows( 'get_id' )->andReturn( $order_id );

		return $order;
	}

	/**
	 * GIVEN a $0 free-trial order with no saved PayPal account
	 * WHEN a vault-approval setup token is successfully created for it
	 * THEN the approval URL from the "approve" link is returned
	 * AND the setup token id and one-time nonce are persisted on the order
	 */
	public function test_returns_approve_url_and_persists_setup_token_on_order(): void {
		$order = $this->create_wc_order( 42 );

		$response        = new \stdClass();
		$response->id    = 'SETUP-TOKEN-1';
		$response->links = array(
			(object) array(
				'rel'  => 'approve',
				'href' => 'https://www.sandbox.paypal.com/agreements/approve?ba_token=XYZ',
			),
		);

		$this->tokens_endpoint
			->shouldReceive( 'setup_tokens' )
			->once()
			->withArgs(
				function ( PaymentSource $source, string $customer_id ): bool {
					return 'paypal' === $source->name() && '' === $customer_id;
				}
			)
			->andReturn( $response );

		$order->shouldReceive( 'update_meta_data' )
			->twice()
			->withArgs(
				function ( string $key, $value ): bool {
					return in_array( $key, array( FreeTrialVaultReturnEndpoint::SETUP_TOKEN_META, FreeTrialVaultReturnEndpoint::RETURN_NONCE_META ), true );
				}
			);
		$order->shouldReceive( 'save' )->once();

		$result = $this->sut->create_redirect_url( $order );

		$this->assertSame( 'https://www.sandbox.paypal.com/agreements/approve?ba_token=XYZ', $result );
	}

	/**
	 * GIVEN a setup token response that contains no "approve" or "payer-action" link
	 * WHEN create_redirect_url() processes that response
	 * THEN an empty string is returned instead of a broken redirect
	 * AND the order is never persisted with a setup token
	 */
	public function test_returns_empty_string_when_response_has_no_approve_link(): void {
		$order = $this->create_wc_order( 42 );

		$response        = new \stdClass();
		$response->id    = 'SETUP-TOKEN-1';
		$response->links = array(
			(object) array(
				'rel'  => 'self',
				'href' => 'https://api.paypal.com/v3/vault/setup-tokens/SETUP-TOKEN-1',
			),
		);

		$this->tokens_endpoint
			->shouldReceive( 'setup_tokens' )
			->once()
			->andReturn( $response );

		$order->shouldNotReceive( 'update_meta_data' );
		$order->shouldNotReceive( 'save' );

		$result = $this->sut->create_redirect_url( $order );

		$this->assertSame( '', $result );
	}

	/**
	 * GIVEN PayPal's setup-token API call fails
	 * WHEN create_redirect_url() attempts to create the vault-approval setup token
	 * THEN an empty string is returned so the caller can fall back to its existing
	 * "no saved PayPal account" failure handling
	 * AND the order is never persisted with a setup token
	 */
	public function test_returns_empty_string_when_setup_tokens_throws(): void {
		$order = $this->create_wc_order( 42 );

		$this->tokens_endpoint
			->shouldReceive( 'setup_tokens' )
			->once()
			->andThrow( new RuntimeException( 'PayPal API error.' ) );

		$order->shouldNotReceive( 'update_meta_data' );
		$order->shouldNotReceive( 'save' );

		$result = $this->sut->create_redirect_url( $order );

		$this->assertSame( '', $result );
	}
}
