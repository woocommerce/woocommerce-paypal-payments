<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\StoreSync;

use Mockery;
use Psr\Log\NullLogger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\CheckoutEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCheckoutProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Endpoint\CheckoutEndpoint
 */
class CheckoutEndpointTest extends IntegrationMockedTestCase {

	private CheckoutEndpoint $endpoint;

	private AgenticSessionHandler $session_handler;

	public function setUp(): void {
		parent::setUp();

		$c = $this->getContainer();

		$this->session_handler = $c->get( 'agentic.session.handler' );

		$order_manager      = Mockery::mock( PayPalOrderManager::class );
		$checkout_processor = Mockery::mock( AgenticCheckoutProcessor::class );

		$checkout_processor->allows( 'process' )
			->andReturn(
				new WP_Error( 'order_creation_failed', 'Could not capture the PayPal order.' )
			);

		$this->endpoint = new CheckoutEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$this->session_handler,
			$c->get( 'agentic.helper.session-manager' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.validation.processor' ),
			new NullLogger(),
			$order_manager,
			$c->get( 'agentic.store.data' ),
			$checkout_processor
		);
	}

	/**
	 * GIVEN a cart session exists with a buyer-unapproved PayPal token
	 * WHEN complete_checkout() is called and the checkout processor cannot capture the PayPal order
	 * THEN the HTTP status is 200
	 * AND the response status is INCOMPLETE
	 * AND validation_status is INVALID
	 * AND validation_issues contains exactly one entry
	 * AND that entry has code PAYMENT_ERROR, type BUSINESS_RULE,
	 *     context.specific_issue PAYMENT_DECLINED, and context.decline_reason matching the WP_Error code
	 */
	public function test_checkout_of_unapproved_order_returns_payment_error(): void {
		$ec_token = 'test-unapproved-token';

		$cart_data = array(
			'items'            => array(
				array(
					'variant_id' => 'DUMMY_SIMPLE_SKU_01',
					'quantity'   => 1,
				),
			),
			'customer'         => array(
				'name'          => array( 'given_name' => 'John', 'surname' => 'Doe' ),
				'email_address' => 'jdoe-pp@paypal.com',
			),
			'shipping_address' => array(
				'address_line_1' => '456 Fictional St',
				'admin_area_2'   => 'San Jose',
				'postal_code'    => '90210',
				'country_code'   => 'US',
			),
			'payment_method'   => array(
				'type'  => 'paypal',
				'token' => $ec_token,
			),
		);

		$initial_cart = PayPalCart::from_array( $cart_data, new StoreValidation() );
		$cart_id      = $this->session_handler->create_cart_session( $initial_cart, $ec_token );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( (string) json_encode( $cart_data ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->endpoint->complete_checkout( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( 'INCOMPLETE', $data['status'] ?? null, 'Response status must be INCOMPLETE' );
		$this->assertSame( 'INVALID', $data['validation_status'] ?? null, 'Validation status must be INVALID' );

		$issues = $data['validation_issues'] ?? array();
		$this->assertCount( 1, $issues, 'Exactly one validation issue must be present' );

		$issue = $issues[0];
		$this->assertSame( 'PAYMENT_ERROR', $issue['code'] ?? null, 'Issue code must be PAYMENT_ERROR' );
		$this->assertSame( 'BUSINESS_RULE', $issue['type'] ?? null, 'Issue type must be BUSINESS_RULE' );
		$this->assertSame( 'PAYMENT_DECLINED', $issue['context']['specific_issue'] ?? null, 'specific_issue must be PAYMENT_DECLINED' );
		$this->assertSame( 'order_creation_failed', $issue['context']['decline_reason'] ?? null, 'decline_reason must match the WP_Error code' );
	}
}
