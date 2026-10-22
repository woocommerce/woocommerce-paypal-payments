<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Tests\Integration\Fixtures\PayPalOrderPresets;

/**
 * Cart handling of the express "Pay Now" approval in ppc-approve-order:
 * Pay Now enabled, not on the checkout page, should_create_wc_order set.
 *
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint
 */
class ApproveOrderEndpointPayNowCartTest extends WcAjaxEndpointTestCase {

	private const PAYPAL_ORDER_ID = 'TEST-PAYPAL-ORDER-PAY-NOW';

	/**
	 * @return object The ppc-approve-order endpoint, where PayPal captures the order.
	 */
	private function endpointWhereCaptureSucceeds() {
		$setup = $this->bootstrapWithApprovedOrder();

		$setup['order_endpoint']->shouldReceive( 'capture' )->andReturn(
			$this->paypalOrder( $setup['container'], PayPalOrderPresets::completedWithCapture( self::PAYPAL_ORDER_ID, $this->sessionCustomId() ) )
		);

		return $setup['container']->get( 'button.endpoint.approve-order' );
	}

	/**
	 * @return object The ppc-approve-order endpoint, where PayPal rejects the capture with PAYER_ACTION_REQUIRED.
	 */
	private function endpointWherePayerActionIsRequired() {
		$setup = $this->bootstrapWithApprovedOrder();

		$setup['order_endpoint']->shouldReceive( 'capture' )->andThrow( $this->payerActionRequiredException() );

		return $setup['container']->get( 'button.endpoint.approve-order' );
	}

	/**
	 * Boots the plugin with Pay Now enabled and PayPal reporting the order as APPROVED.
	 *
	 * @return array{order_endpoint: \Mockery\MockInterface, container: \Psr\Container\ContainerInterface}
	 */
	private function bootstrapWithApprovedOrder(): array {
		$order_endpoint = Mockery::mock( OrderEndpoint::class );

		$this->enablePayNow();
		$container = $this->bootstrapWithOrderApi( $order_endpoint );

		$approved = $this->paypalOrder(
			$container,
			PayPalOrderPresets::order( self::PAYPAL_ORDER_ID, 'APPROVED', $this->sessionCustomId() )
		);

		$order_endpoint->shouldReceive( 'order' )->with( self::PAYPAL_ORDER_ID )->andReturn( $approved );
		$order_endpoint->shouldReceive( 'patch_order_with' )->andReturnUsing(
			static function ( Order $order_to_update ): Order {
				return $order_to_update;
			}
		);

		return array(
			'order_endpoint' => $order_endpoint,
			'container'      => $container,
		);
	}

	/**
	 * Builds a PayPal order entity from a PayPal API response payload.
	 *
	 * @param object $container The plugin container.
	 * @param mixed  $payload   The PayPal API response data.
	 */
	private function paypalOrder( $container, $payload ): Order {
		return $container->get( 'api.factory.order' )->from_paypal_response(
			json_decode( (string) wp_json_encode( $payload ) )
		);
	}

	private function payerActionRequiredException(): PayPalApiException {
		return new PayPalApiException(
			(object) array(
				'name'    => 'UNPROCESSABLE_ENTITY',
				'message' => 'The requested action could not be performed.',
				'details' => array(
					(object) array(
						'issue'       => 'PAYER_ACTION_REQUIRED',
						'description' => 'Payer must complete an action.',
					),
				),
			),
			422
		);
	}

	/**
	 * Approves the PayPal order the way the express button does and returns the created WC order.
	 *
	 * @param object $endpoint The ppc-approve-order endpoint.
	 * @return array{response: array, wc_order: WC_Order}
	 */
	private function approveWithPayNow( $endpoint ): array {
		$order_ids_before = $this->currentWcOrderIds();

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'order_id'               => self::PAYPAL_ORDER_ID,
				'funding_source'         => 'paypal',
				'should_create_wc_order' => true,
			)
		);

		$this->assertTrue( $response['success'], 'ppc-approve-order must succeed: ' . $response['raw'] );

		$new_order_ids = array_values( array_diff( $this->currentWcOrderIds(), $order_ids_before ) );
		$this->assertCount( 1, $new_order_ids, 'Exactly one WC order must be created' );
		$this->trackWcOrder( $new_order_ids[0] );

		return array(
			'response' => $response,
			'wc_order' => wc_get_order( $new_order_ids[0] ),
		);
	}

	/**
	 * Simulates the next front-end page load, where WooCommerce empties the cart
	 * of a paid order that is awaiting payment in the session.
	 * For an unpaid order it must leave the cart as it is.
	 */
	private function loadNextFrontendPage(): void {
		do_action( 'template_redirect' );
	}

	/**
	 * GIVEN a cart with one product and an approved PayPal order
	 * AND the capture succeeds
	 * WHEN the buyer approves the order with Pay Now
	 * THEN the WC order is paid
	 * AND the cart is empty right after the approval request, without a further page load
	 */
	public function test_successful_capture_empties_cart_in_the_approval_request(): void {
		$this->addVirtualProductToCart( 10.0 );
		$endpoint = $this->endpointWhereCaptureSucceeds();

		$result = $this->approveWithPayNow( $endpoint );

		$this->assertContains( $result['wc_order']->get_status(), array( 'processing', 'completed' ) );
		$this->assertTrue( WC()->cart->is_empty() );
	}

	/**
	 * GIVEN a cart with one product and an approved PayPal order
	 * AND PayPal rejects the capture with PAYER_ACTION_REQUIRED
	 * WHEN the buyer approves the order with Pay Now
	 * THEN the response sends the buyer to the PayPal approval URL, not to order-received
	 * AND the WC order stays pending
	 * AND the cart is kept right after the request and on the next front-end page load
	 */
	public function test_payer_action_required_redirects_to_paypal_and_keeps_cart(): void {
		$this->addVirtualProductToCart( 10.0 );
		$endpoint = $this->endpointWherePayerActionIsRequired();

		$result = $this->approveWithPayNow( $endpoint );

		$redirect = (string) ( $result['response']['data']['order_received_url'] ?? '' );
		$this->assertStringContainsString( 'checkoutnow?token=' . self::PAYPAL_ORDER_ID, $redirect );
		$this->assertStringNotContainsString( 'order-received', $redirect );
		$this->assertSame( 'pending', $result['wc_order']->get_status() );
		$this->assertCount( 1, WC()->cart->get_cart() );

		$this->loadNextFrontendPage();

		$this->assertCount( 1, WC()->cart->get_cart() );
	}
}
