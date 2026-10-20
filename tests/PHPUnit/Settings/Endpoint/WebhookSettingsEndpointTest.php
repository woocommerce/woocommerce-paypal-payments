<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Endpoint;

use Mockery;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Settings\Endpoint\WebhookSettingsEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Webhooks\IncomingWebhookEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\OwnWebhookResolver;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Endpoint\WebhookSettingsEndpoint
 */
class WebhookSettingsEndpointTest extends TestCase {

	/** @var WebhookEndpoint&Mockery\MockInterface */
	private $webhook_endpoint;

	/** @var WebhookRegistrar&Mockery\MockInterface */
	private $webhook_registrar;

	/** @var WebhookSimulation&Mockery\MockInterface */
	private $webhook_simulation;

	/** @var IncomingWebhookEndpoint&Mockery\MockInterface */
	private $incoming_webhook_endpoint;

	public function setUp(): void {
		parent::setUp();

		when( 'rest_ensure_response' )->alias( static fn( $data ) => new WP_REST_Response( $data ) );
		when( 'wp_parse_url' )->alias( 'parse_url' );
		when( 'get_option' )->justReturn( [] );

		$this->webhook_endpoint          = Mockery::mock( WebhookEndpoint::class );
		$this->webhook_registrar         = Mockery::mock( WebhookRegistrar::class );
		$this->webhook_simulation        = Mockery::mock( WebhookSimulation::class );
		$this->incoming_webhook_endpoint = Mockery::mock( IncomingWebhookEndpoint::class );

		$this->incoming_webhook_endpoint->shouldReceive( 'url' )
			->andReturn( 'https://mysite.com/wp-json/paypal/v1/incoming' );
	}

	private function createEndpoint(): WebhookSettingsEndpoint {
		return new WebhookSettingsEndpoint(
			$this->webhook_endpoint,
			$this->webhook_registrar,
			$this->webhook_simulation,
			new OwnWebhookResolver( $this->incoming_webhook_endpoint )
		);
	}

	/**
	 * GIVEN a PayPal account with a foreign site's webhook listed FIRST and this
	 * site's own webhook listed SECOND
	 * WHEN fetching webhook data
	 * THEN this site's own webhook URL and lower-cased event names are returned
	 *
	 * Regression case for PCP-6885: fetching list()[0] would have surfaced the
	 * foreign webhook instead of this install's own one.
	 */
	public function test_get_webhooks_returns_own_webhook_when_foreign_webhook_is_listed_first(): void {
		$foreign_webhook = new Webhook( 'https://other-clone.com/wp-json/paypal/v1/incoming', [ (object) [ 'name' => 'PAYMENT.CAPTURE.COMPLETED' ] ], 'FOREIGN' );
		$own_webhook     = new Webhook(
			'https://mysite.com/wp-json/paypal/v1/incoming',
			[ (object) [ 'name' => 'CHECKOUT.ORDER.APPROVED' ] ],
			'OWN'
		);

		$this->webhook_endpoint->shouldReceive( 'list' )->andReturn( [ $foreign_webhook, $own_webhook ] );

		$response = $this->createEndpoint()->get_webhooks();
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertSame( 'https://mysite.com/wp-json/paypal/v1/incoming', $data['data']['url'] );
		$this->assertSame( [ 'checkout.order.approved' ], $data['data']['events'] );
	}

	/**
	 * GIVEN a PayPal account whose only registered webhooks belong to other sites
	 * WHEN fetching webhook data
	 * THEN a failure response is returned reporting no webhooks were found
	 */
	public function test_get_webhooks_reports_failure_when_only_foreign_webhooks_exist(): void {
		$foreign_webhook = new Webhook( 'https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN' );

		$this->webhook_endpoint->shouldReceive( 'list' )->andReturn( [ $foreign_webhook ] );

		$response = $this->createEndpoint()->get_webhooks();
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertSame( 'No webhooks found.', $data['message'] );
	}

	/**
	 * GIVEN the PayPal webhook list request fails
	 * WHEN fetching webhook data
	 * THEN a failure response is returned instead of erroring out
	 */
	public function test_get_webhooks_reports_failure_when_listing_webhooks_throws(): void {
		$this->webhook_endpoint->shouldReceive( 'list' )->andThrow( new RuntimeException( 'API unavailable' ) );

		$response = $this->createEndpoint()->get_webhooks();
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}
}
