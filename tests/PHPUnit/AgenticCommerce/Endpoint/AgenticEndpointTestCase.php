<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartPayloadBuilder;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\ProductValidator;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use Mockery;
use Mockery\MockInterface;

/**
 * Base test case for Agentic Commerce endpoint tests.
 */
abstract class AgenticEndpointTestCase extends TestCase {

	/**
	 * Create standard mocks for endpoint tests.
	 *
	 * @return array{auth_provider: AuthServiceProvider&MockInterface, session_handler:
	 *                              AgenticSessionHandler&MockInterface, response_factory:
	 *                              ResponseFactory&MockInterface, logger: LoggerInterface&MockInterface,
	 *                              product_validator: ProductValidator&MockInterface, order_manager:
	 *                              PayPalOrderManager&MockInterface}
	 */
	protected function create_mocks(): array {
		$auth_service  = Mockery::mock( JwtAuthService::class );
		$auth_provider = Mockery::mock( AuthServiceProvider::class );
		$auth_provider->allows( 'auth_service' )->andReturn( $auth_service );

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->allows( 'info' );
		$logger->allows( 'error' );
		$logger->allows( 'warning' );
		$logger->allows( 'debug' );

		$product_validator = Mockery::mock( ProductValidator::class );
		$product_validator->allows( 'validate_products_exist' )->andReturn( array() );

		$order_manager = Mockery::mock( PayPalOrderManager::class );
		$order_manager->allows( 'update_order' );

		return array(
			'auth_provider'     => $auth_provider,
			'session_handler'   => Mockery::mock( AgenticSessionHandler::class ),
			'response_factory'  => Mockery::mock( ResponseFactory::class ),
			'logger'            => $logger,
			'product_validator' => $product_validator,
			'order_manager'     => $order_manager,
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
