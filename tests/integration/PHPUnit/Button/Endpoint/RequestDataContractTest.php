<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Contract tests for the request plumbing shared by all ppc-* order WC-AJAX
 * endpoints: the JSON body read from php://input, the nonce field, and the
 * guest-session nonce fix.
 *
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData
 */
class RequestDataContractTest extends WcAjaxEndpointTestCase {

	/** @var ContainerInterface */
	protected $container;

	public function setUp(): void {
		parent::setUp();

		// Any PayPal API call on a rejected request is a contract violation.
		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->shouldNotReceive( 'create' );
		$order_endpoint->shouldNotReceive( 'order' );
		$order_endpoint->shouldNotReceive( 'patch' );

		$this->container = $this->bootstrapWithOrderApi( $order_endpoint );
	}

	/**
	 * The four shared order endpoints, by container service key.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function endpointProvider(): array {
		return array(
			'ppc-create-order'    => array( 'button.endpoint.create-order' ),
			'ppc-change-cart'     => array( 'button.endpoint.change-cart' ),
			'ppc-approve-order'   => array( 'button.endpoint.approve-order' ),
			'ppc-update-shipping' => array( 'blocks.endpoint.update-shipping' ),
		);
	}

	/**
	 * GIVEN any of the four shared order endpoints
	 * WHEN it is called with an invalid nonce in the JSON body
	 * THEN the request is rejected with HTTP 400
	 * AND data.message is "Could not validate nonce."
	 * AND no PayPal API call is made
	 *
	 * @dataProvider endpointProvider
	 *
	 * @param string $service_key The endpoint's container service key.
	 */
	public function test_invalid_nonce_is_rejected_with_400( string $service_key ): void {
		$endpoint = $this->container->get( $service_key );

		$response = $this->dispatchAjaxRequest( $endpoint, array( 'nonce' => 'invalid-nonce' ) );

		$this->assertFalse( $response['success'], $service_key . ' must reject an invalid nonce' );
		$this->assertSame( 400, $response['status'], $service_key . ' must respond with HTTP 400' );
		$this->assertSame( 'Could not validate nonce.', $response['data']['message'] ?? null );
	}

	/**
	 * GIVEN a request body without a nonce field
	 * WHEN a shared order endpoint is called
	 * THEN the request is rejected exactly like an invalid nonce
	 *
	 * @dataProvider endpointProvider
	 *
	 * @param string $service_key The endpoint's container service key.
	 */
	public function test_missing_nonce_field_is_rejected( string $service_key ): void {
		$endpoint = $this->container->get( $service_key );

		$response = $this->dispatchAjaxRequest( $endpoint, array( 'nonce' => null ) );

		$this->assertFalse( $response['success'], $service_key . ' must reject a missing nonce' );
		$this->assertSame( 400, $response['status'] );
		$this->assertSame( 'Could not validate nonce.', $response['data']['message'] ?? null );
	}

	/**
	 * GIVEN a valid nonce in the JSON body and a different, invalid nonce in $_POST
	 * WHEN ppc-change-cart is called
	 * THEN the request succeeds
	 * (the JSON body read from php://input is the source of truth, not POST fields)
	 */
	public function test_nonce_in_json_body_not_post_is_accepted(): void {
		$product  = $this->createVirtualProduct( 10.0 );
		$endpoint = $this->container->get( 'button.endpoint.change-cart' );

		$_POST['nonce'] = 'invalid-post-nonce';
		try {
			$response = $this->dispatchAjaxRequest(
				$endpoint,
				array(
					'products' => array(
						array(
							'id'       => $product->get_id(),
							'quantity' => 1,
						),
					),
				)
			);
		} finally {
			unset( $_POST['nonce'] );
		}

		$this->assertTrue( $response['success'], 'The JSON body nonce must be the source of truth: ' . $response['raw'] );
	}

	/**
	 * GIVEN a nonce rendered for a logged-out visitor (uid 0)
	 * AND a third-party/legacy filter later mapping the logged-out nonce uid to
	 *     the WC customer session id (the situation RequestData::nonce_fix() exists for:
	 *     WC provides the customer object only from the second request on)
	 * WHEN ppc-change-cart is called with that nonce
	 * THEN the request still succeeds, because RequestData forces the logged-out
	 *      uid back to 0 (priority 100) during verification
	 * AND without that fix the same nonce fails plain wp_verify_nonce()
	 */
	public function test_guest_nonce_survives_wc_customer_session_uid_change(): void {
		$product  = $this->createVirtualProduct( 10.0 );
		$endpoint = $this->container->get( 'button.endpoint.change-cart' );

		$nonce = $this->createFrontendNonce( 'ppc-change-cart' );

		$uid_change = static function (): int {
			return 424242;
		};
		add_filter( 'nonce_user_logged_out', $uid_change, 10 );
		try {
			$this->assertFalse(
				(bool) wp_verify_nonce( $nonce, 'ppc-change-cart' ),
				'Control: without the nonce fix, the guest nonce must fail verification'
			);

			$response = $this->dispatchAjaxRequest(
				$endpoint,
				array(
					'nonce'    => $nonce,
					'products' => array(
						array(
							'id'       => $product->get_id(),
							'quantity' => 1,
						),
					),
				)
			);
		} finally {
			remove_filter( 'nonce_user_logged_out', $uid_change, 10 );
		}

		$this->assertTrue( $response['success'], 'The guest-session nonce fix must make the request pass: ' . $response['raw'] );
	}

	/**
	 * GIVEN a request body with a form_encoded field (urlencoded string)
	 * WHEN ppc-create-order is called
	 * THEN the endpoint receives the parsed form array under the form key,
	 *      with urlencoded values decoded
	 * (observed through the order_created action, which receives the parsed
	 * request data)
	 */
	public function test_form_encoded_is_parsed_into_form_array(): void {
		$this->addVirtualProductToCart( 10.0 );

		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->shouldReceive( 'with_bn_code' )->andReturnSelf()->byDefault();
		$order_endpoint->shouldReceive( 'create' )->andReturnUsing(
			static function ( array $purchase_units ): Order {
				return new Order( 'TEST-PAYPAL-ORDER-123', $purchase_units, new OrderStatus( OrderStatus::CREATED ) );
			}
		);
		$container = $this->bootstrapWithOrderApi( $order_endpoint );
		$endpoint  = $container->get( 'button.endpoint.create-order' );

		$calls = $this->recordAction( 'woocommerce_paypal_payments_create_order_endpoint_order_created', 2 );

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'context'        => 'cart',
				'payment_method' => 'ppcp-gateway',
				'funding_source' => 'paypal',
				'form_encoded'   => 'billing_email=a%40b.com&terms=1',
			)
		);

		$this->assertTrue( $response['success'], 'ppc-create-order must succeed: ' . $response['raw'] );
		$this->assertCount( 1, $calls );

		$data = $calls[0][1];
		$this->assertSame( 'a@b.com', $data['form']['billing_email'] ?? null, 'form_encoded must be parsed into the form array' );
		$this->assertSame( '1', $data['form']['terms'] ?? null );
		$this->assertSame( 'billing_email=a%40b.com&terms=1', $data['form_encoded'] ?? null, 'form_encoded itself must be preserved unsanitized' );
	}
}
