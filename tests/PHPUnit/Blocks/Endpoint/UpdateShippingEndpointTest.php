<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

/**
 * @covers \WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint
 */
class UpdateShippingEndpointTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private RequestData $request_data;
	private OrderEndpoint $order_endpoint;
	private PurchaseUnitFactory $purchase_unit_factory;
	private SessionHandler $session_handler;
	private LoggerInterface $logger;
	private UpdateShippingEndpoint $sut;

	public function setUp(): void
	{
		parent::setUp();
		$this->request_data          = Mockery::mock( RequestData::class );
		$this->order_endpoint        = Mockery::mock( OrderEndpoint::class );
		$this->purchase_unit_factory = Mockery::mock( PurchaseUnitFactory::class );
		$this->session_handler       = Mockery::mock( SessionHandler::class );
		$this->logger                = Mockery::mock( LoggerInterface::class );

		$this->sut = new UpdateShippingEndpoint(
			$this->request_data,
			$this->order_endpoint,
			$this->purchase_unit_factory,
			$this->session_handler,
			$this->logger
		);
	}

	/**
	 * @scenario When the request order_id matches the session's stored PayPal order,
	 *           the PATCH is issued and a success response is returned.
	 */
	public function test_patch_proceeds_when_order_id_matches_session(): void
	{
		$order_id = 'ORDER-MATCH-123';

		$session_order = Mockery::mock( Order::class );
		$session_order->shouldReceive( 'id' )->andReturn( $order_id );

		$pu = Mockery::mock( PurchaseUnit::class );
		$pu->shouldReceive( 'to_array' )->andReturn( array() );
		$pu->shouldReceive( 'reference_id' )->andReturn( 'default' );

		$this->request_data->shouldReceive( 'read_request' )
			->with( UpdateShippingEndpoint::nonce() )
			->andReturn( array( 'order_id' => $order_id ) );

		$this->session_handler->shouldReceive( 'order' )->andReturn( $session_order );

		$this->purchase_unit_factory->shouldReceive( 'from_wc_cart' )
			->with( null, true )
			->andReturn( $pu );

		$this->order_endpoint->shouldReceive( 'patch' )
			->with( $order_id, Mockery::any() )
			->once();

		expect( 'wp_send_json_success' )->once();

		$this->sut->handle_request();
	}

	/**
	 * @scenario When the request order_id does not match the session's stored order,
	 *           no PATCH is issued and an error response is returned.
	 */
	public function test_patch_is_rejected_when_order_id_does_not_match_session(): void
	{
		$session_order = Mockery::mock( Order::class );
		$session_order->shouldReceive( 'id' )->andReturn( 'ORDER-SESSION' );

		$this->request_data->shouldReceive( 'read_request' )
			->with( UpdateShippingEndpoint::nonce() )
			->andReturn( array( 'order_id' => 'ORDER-REQUEST' ) );

		$this->session_handler->shouldReceive( 'order' )->andReturn( $session_order );

		$this->purchase_unit_factory->shouldReceive( 'from_wc_cart' )->never();
		$this->order_endpoint->shouldReceive( 'patch' )->never();

		expect( 'wp_send_json_error' )->once();

		$this->sut->handle_request();
	}

	/**
	 * @scenario When there is no PayPal order stored in the session,
	 *           no PATCH is issued and an error response is returned.
	 */
	public function test_patch_is_rejected_when_no_session_order_exists(): void
	{
		$this->request_data->shouldReceive( 'read_request' )
			->with( UpdateShippingEndpoint::nonce() )
			->andReturn( array( 'order_id' => 'ORDER-123' ) );

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );

		$this->purchase_unit_factory->shouldReceive( 'from_wc_cart' )->never();
		$this->order_endpoint->shouldReceive( 'patch' )->never();

		expect( 'wp_send_json_error' )->once();

		$this->sut->handle_request();
	}
}
