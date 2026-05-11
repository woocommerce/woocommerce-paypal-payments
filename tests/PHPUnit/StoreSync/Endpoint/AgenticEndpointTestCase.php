<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use Psr\Log\LoggerInterface;
use Mockery;
use Mockery\MockInterface;
use WooCommerce\PayPalCommerce\TestCase;

use WooCommerce\PayPalCommerce\StoreSync\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\StoreSync\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\StoreSync\CartPayloadBuilder;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticSessionManager;
use WooCommerce\PayPalCommerce\StoreSync\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;

/**
 * Base test case for Agentic Commerce endpoint tests.
 */
abstract class AgenticEndpointTestCase extends TestCase {

	/**
	 * Create standard mocks for endpoint tests.
	 *
	 * @return array{auth_provider: AuthServiceProvider&MockInterface, session_handler:
	 *                              AgenticSessionHandler&MockInterface, response_factory:
	 *                              ResponseFactory&MockInterface}
	 */
	protected function create_mocks(): array {
		$auth_service  = Mockery::mock( JwtAuthService::class );
		$auth_provider = Mockery::mock( AuthServiceProvider::class );
		$auth_provider->allows( 'auth_service' )->andReturn( $auth_service );

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->allows( 'info' );
		$logger->allows( 'error' );

		$store_data = Mockery::mock( StoreData::class );
		$store_data->allows( 'create_cart' )->andReturnUsing(
			function ( $paypal_cart, $validation ) {
				$mock = Mockery::mock( StorePayPalCart::class );
				$mock->allows( 'paypal_cart' )->andReturn( $paypal_cart );
				$mock->allows( 'validation' )->andReturn( $validation );
				$mock->allows( 'to_array' )->andReturn( array() );

				return $mock;
			}
		);

		return array(
			'auth_provider'        => $auth_provider,
			'session_handler'      => Mockery::mock( AgenticSessionHandler::class ),
			'session_manager'      => Mockery::mock( AgenticSessionManager::class ),
			'response_factory'     => Mockery::mock( ResponseFactory::class ),
			'validation_processor' => Mockery::mock( CartValidationProcessor::class ),
			'logger'               => $logger,
			'order_manager'        => Mockery::mock( PayPalOrderManager::class ),
			'store_data'           => $store_data,
		);
	}

	/**
	 * Start building a cart payload.
	 *
	 * @return CartPayloadBuilder
	 */
	protected function cart(): CartPayloadBuilder {
		return new CartPayloadBuilder();
	}

	/**
	 * Assert that response contains error information.
	 *
	 * @param array $data Response data.
	 */
	protected function assert_error_response( array $data ): void {
		$has_error_info = isset( $data['validation_issues'] )
			|| isset( $data['error'] )
			|| isset( $data['message'] )
			|| isset( $data['issues'] );

		$this->assertTrue(
			$has_error_info,
			'Response should contain error information. Got: ' . json_encode( $data )
		);
	}
}
