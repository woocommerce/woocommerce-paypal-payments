<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\Tests\Integration\Fixtures\PayPalOrderPresets;

/**
 * Contract tests for the ppc-update-shipping WC-AJAX endpoint, shared by the
 * v5 and v6 SDK frontends.
 *
 * @covers \WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint
 */
class UpdateShippingEndpointContractTest extends WcAjaxEndpointTestCase {

	private const PAYPAL_ORDER_ID = 'TEST-PAYPAL-ORDER-123';

	/**
	 * Stores a real PayPal Order entity in the plugin session.
	 *
	 * @param \WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface $container The plugin container.
	 * @param string                                                              $order_id The PayPal order id.
	 * @return void
	 */
	private function storeSessionOrder( $container, string $order_id ): void {
		$order = $container->get( 'api.factory.order' )->from_paypal_response(
			json_decode( (string) wp_json_encode( PayPalOrderPresets::order( $order_id, 'APPROVED', $this->sessionCustomId() ) ) )
		);
		$container->get( 'session.handler' )->replace_order( $order );
	}

	/**
	 * GIVEN a PayPal order stored in the plugin session
	 * AND a WC cart with a virtual product (price 10, quantity 3)
	 * WHEN ppc-update-shipping is called with the matching order_id
	 * THEN the PayPal order is patched with exactly one replace operation on
	 *      /purchase_units/@reference_id=='default'
	 * AND the patch value carries the recalculated cart total and the
	 *     session-bound custom_id
	 * AND the response is a plain success with no data payload
	 */
	public function test_patches_paypal_order_with_recalculated_totals(): void {
		$captured = null;

		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->shouldReceive( 'patch' )->once()->withArgs(
			function ( string $order_id, PatchCollection $patches ) use ( &$captured ): bool {
				$captured = array( $order_id, $patches );
				return true;
			}
		);

		$container = $this->bootstrapWithOrderApi( $order_endpoint );
		$this->storeSessionOrder( $container, self::PAYPAL_ORDER_ID );
		$this->addVirtualProductToCart( 10.0, 3 );

		$response = $this->dispatchAjaxRequest(
			$container->get( 'blocks.endpoint.update-shipping' ),
			array( 'order_id' => self::PAYPAL_ORDER_ID )
		);

		$this->assertTrue( $response['success'], 'ppc-update-shipping must succeed: ' . $response['raw'] );
		$this->assertNull( $response['data'], 'The success response must carry no data payload' );

		$this->assertNotNull( $captured, 'The PayPal patch API must be called' );
		list( $order_id, $patches ) = $captured;
		$this->assertSame( self::PAYPAL_ORDER_ID, $order_id );

		$patch_list = $patches->patches();
		$this->assertCount( 1, $patch_list, 'Exactly one patch operation must be sent' );

		$patch = $patch_list[0];
		$this->assertSame( 'replace', $patch->op() );
		$this->assertSame( "/purchase_units/@reference_id=='default'", $patch->path() );

		$value = $patch->value();
		$this->assertSame( 'default', $value['reference_id'] ?? null );
		$this->assertSame( '30.00', $value['amount']['value'] ?? null, 'The patch must carry the recalculated cart total' );
		$this->assertSame( $this->sessionCustomId(), $value['custom_id'] ?? null, 'The patch must keep the session-bound custom_id' );
	}

	/**
	 * The session states that must cause a rejection.
	 *
	 * @return array<string, array{0: string|null}>
	 */
	public function sessionMismatchProvider(): array {
		return array(
			'different session order id' => array( 'TEST-OTHER-ORDER-456' ),
			'no session order'           => array( null ),
		);
	}

	/**
	 * GIVEN no PayPal order in the plugin session, or one with a different id
	 * WHEN ppc-update-shipping is called with an order_id
	 * THEN the request is rejected with message "Order validation failed."
	 * AND the PayPal patch API is never called
	 *
	 * @dataProvider sessionMismatchProvider
	 *
	 * @param string|null $session_order_id The PayPal order id in the session, or null for none.
	 */
	public function test_session_order_id_mismatch_returns_error( ?string $session_order_id ): void {
		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->shouldNotReceive( 'patch' );

		$container = $this->bootstrapWithOrderApi( $order_endpoint );
		if ( null !== $session_order_id ) {
			$this->storeSessionOrder( $container, $session_order_id );
		}
		$this->addVirtualProductToCart( 10.0 );

		$response = $this->dispatchAjaxRequest(
			$container->get( 'blocks.endpoint.update-shipping' ),
			array( 'order_id' => self::PAYPAL_ORDER_ID )
		);

		$this->assertFalse( $response['success'], 'ppc-update-shipping must reject a session mismatch' );
		$this->assertSame( 'Order validation failed.', $response['data']['message'] ?? null );
	}
}
