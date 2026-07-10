<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Mockery;
use Psr\Log\NullLogger;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookEventFactory;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WP_REST_Request;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\IncomingWebhookEndpoint
 */
class IncomingWebhookEndpointTest extends TestCase
{
	/** @var WebhookEndpoint&\Mockery\MockInterface */
	private $webhook_endpoint;

	/** @var WebhookEventFactory&\Mockery\MockInterface */
	private $webhook_event_factory;

	/** @var WebhookSimulation&\Mockery\MockInterface */
	private $simulation;

	/** @var WebhookEventStorage&\Mockery\MockInterface */
	private $last_webhook_event_storage;

	/** @var Webhook&\Mockery\MockInterface */
	private $webhook;

	public function setUp(): void
	{
		parent::setUp();

		$this->webhook_endpoint           = Mockery::mock( WebhookEndpoint::class );
		$this->webhook_event_factory      = Mockery::mock( WebhookEventFactory::class );
		$this->simulation                 = Mockery::mock( WebhookSimulation::class );
		$this->last_webhook_event_storage = Mockery::mock( WebhookEventStorage::class );

		$this->webhook = Mockery::mock( Webhook::class );
		$this->webhook->allows( 'id' )->andReturn( 'WH-1' );
	}

	private function sut(): IncomingWebhookEndpoint
	{
		return new IncomingWebhookEndpoint(
			$this->webhook_endpoint,
			$this->webhook,
			new NullLogger(),
			true,
			$this->webhook_event_factory,
			$this->simulation,
			$this->last_webhook_event_storage
		);
	}

	/**
	 * @scenario A request whose Content-Type is not application/json (the replay-with-altered-Content-Type
	 * attack) is rejected before any dispatch and the PayPal signature verifier is never consulted.
	 */
	public function test_rejects_non_json_content_type_and_never_verifies_signature(): void
	{
		// Arrange
		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'get_content_type' )->andReturn( array( 'value' => 'text/plain' ) );

		$this->webhook_endpoint->expects( 'verify_current_request_for_webhook' )->never();

		// When
		$result = $this->sut()->verify_request( $request );

		// Then
		$this->assertFalse( $result );
	}

	/**
	 * @scenario A request with no Content-Type at all is rejected.
	 */
	public function test_rejects_missing_content_type(): void
	{
		// Arrange
		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'get_content_type' )->andReturn( null );

		$this->webhook_endpoint->expects( 'verify_current_request_for_webhook' )->never();

		// When
		$result = $this->sut()->verify_request( $request );

		// Then
		$this->assertFalse( $result );
	}

	/**
	 * @scenario An application/json request has its query-string and URL parameter slots cleared so the
	 * signature-verified body is the only source of parameters, and a valid signature passes.
	 */
	public function test_accepts_application_json_and_clears_query_and_url_params(): void
	{
		// Arrange
		$body  = array(
			'id'         => 'EVT-1',
			'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
			'resource'   => array( 'custom_id' => '42' ),
		);
		$event = Mockery::mock( WebhookEvent::class );
		$event->allows( 'id' )->andReturn( 'EVT-1' );

		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'get_content_type' )->andReturn( array( 'value' => 'application/json' ) );
		$request->expects( 'set_query_params' )->once()->with( array() );
		$request->expects( 'set_url_params' )->once()->with( array() );
		$request->allows( 'get_params' )->andReturn( $body );

		$this->webhook_event_factory->allows( 'from_array' )->with( $body )->andReturn( $event );
		$this->simulation->allows( 'is_simulation_event' )->with( $event )->andReturn( false );
		$this->webhook_endpoint->expects( 'verify_current_request_for_webhook' )
			->once()
			->with( $this->webhook )
			->andReturn( true );

		// When
		$result = $this->sut()->verify_request( $request );

		// Then
		$this->assertTrue( $result );
	}

	/**
	 * @scenario The Content-Type check reads the parsed media-type value, so a charset parameter
	 * (application/json; charset=utf-8) is still accepted.
	 */
	public function test_accepts_application_json_with_charset_parameter(): void
	{
		// Arrange
		$body  = array(
			'id'         => 'EVT-2',
			'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
		);
		$event = Mockery::mock( WebhookEvent::class );
		$event->allows( 'id' )->andReturn( 'EVT-2' );

		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'get_content_type' )->andReturn(
			array(
				'value'      => 'application/json',
				'parameters' => 'charset=utf-8',
			)
		);
		$request->expects( 'set_query_params' )->once()->with( array() );
		$request->expects( 'set_url_params' )->once()->with( array() );
		$request->allows( 'get_params' )->andReturn( $body );

		$this->webhook_event_factory->allows( 'from_array' )->with( $body )->andReturn( $event );
		$this->simulation->allows( 'is_simulation_event' )->with( $event )->andReturn( false );
		$this->webhook_endpoint->expects( 'verify_current_request_for_webhook' )
			->once()
			->with( $this->webhook )
			->andReturn( true );

		// When
		$result = $this->sut()->verify_request( $request );

		// Then
		$this->assertTrue( $result );
	}
}
