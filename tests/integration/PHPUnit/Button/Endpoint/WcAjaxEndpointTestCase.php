<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Tests\Integration\Traits\HandlesWcAjaxEndpoints;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;

/**
 * Base class for the contract tests of the shared order WC-AJAX endpoints
 * (ppc-create-order, ppc-change-cart, ppc-approve-order, ppc-update-shipping).
 */
abstract class WcAjaxEndpointTestCase extends IntegrationMockedTestCase {

	use HandlesWcAjaxEndpoints;

	/** @var int[] */
	protected $postIds = array();

	/** @var int[] */
	protected $wcOrderIds = array();

	public function setUp(): void {
		parent::setUp();

		// SessionModule hooks ppcp_session_get_order to reload non-approved session
		// orders through its container's api.endpoint.order. Every bootstrapModule()
		// registers another listener, so reading a CREATED session order would go
		// through stale containers of earlier tests (dead mocks) and through the
		// process-level plugin container (real HTTP). Drop them; the container
		// bootstrapped by the current test re-registers its own.
		remove_all_actions( 'ppcp_session_get_order' );

		$this->resetWcFrontendState();
	}

	public function tearDown(): void {
		$this->tearDownHarnessHooks();

		foreach ( $this->wcOrderIds as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->delete( true );
			}
		}
		$this->wcOrderIds = array();

		foreach ( $this->postIds as $id ) {
			wp_delete_post( $id, true );
		}
		$this->postIds = array();

		$this->resetWcFrontendState();

		parent::tearDown();
	}

	/**
	 * Boots the plugin container with the PayPal order API replaced by a mock
	 * (no real HTTP), plus optional further service overrides.
	 *
	 * @param OrderEndpoint $order_endpoint The mocked api.endpoint.order.
	 * @param array         $extra Additional service overrides.
	 * @return ContainerInterface
	 */
	protected function bootstrapWithOrderApi( OrderEndpoint $order_endpoint, array $extra = array() ): ContainerInterface {
		$overrides                       = $extra;
		$overrides['api.endpoint.order'] = static function () use ( $order_endpoint ): OrderEndpoint {
			return $order_endpoint;
		};

		return $this->bootstrapModule( $overrides );
	}

	/**
	 * The custom id bound to the current WC session, as PurchaseUnitFactory computes it.
	 *
	 * Must be read at assertion time: the session may be (re)initialized by
	 * resetWcFrontendState().
	 *
	 * @return string
	 */
	protected function sessionCustomId(): string {
		return CustomIds::CUSTOMER_ID_PREFIX . WC()->session->get_customer_unique_id();
	}

	/**
	 * Creates a published virtual product (virtual, so approve-order flows do not
	 * require a chosen shipping method).
	 *
	 * @param float $price The product price.
	 * @return WC_Product_Simple
	 */
	protected function createVirtualProduct( float $price = 10.0 ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test product ' . uniqid() );
		$product->set_status( 'publish' );
		$product->set_regular_price( (string) $price );
		$product->set_virtual( true );
		$product->save();

		$this->postIds[] = $product->get_id();

		return $product;
	}

	/**
	 * Creates a virtual product and puts it into the real WC cart.
	 *
	 * @param float $price The product price.
	 * @param int   $quantity The quantity.
	 * @return WC_Product_Simple
	 */
	protected function addVirtualProductToCart( float $price = 10.0, int $quantity = 1 ): WC_Product_Simple {
		$product = $this->createVirtualProduct( $price );

		WC()->cart->add_to_cart( $product->get_id(), $quantity );
		WC()->cart->calculate_totals();

		return $product;
	}

	/**
	 * Creates a variable product with a 'color' attribute and a 'red' variation.
	 *
	 * @param float $price The variation price.
	 * @return array{parent: WC_Product_Variable, variation: WC_Product_Variation}
	 */
	protected function createVariableProduct( float $price = 10.0 ): array {
		// Use a custom (non-taxonomy) attribute to avoid needing registered taxonomies.
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'color' );
		$attribute->set_options( array( 'red', 'blue' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new WC_Product_Variable();
		$parent->set_name( 'Test variable ' . uniqid() );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();

		$this->postIds[] = $parent->get_id();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( (string) $price );
		$variation->set_attributes( array( 'color' => 'red' ) );
		$variation->set_status( 'publish' );
		$variation->set_virtual( true );
		$variation->save();

		$this->postIds[] = $variation->get_id();

		// Sync so data store can find the variation.
		WC_Product_Variable::sync( $parent->get_id() );

		return array(
			'parent'    => $parent,
			'variation' => $variation,
		);
	}

	/**
	 * The ids of all WC orders currently in the database.
	 *
	 * @return int[]
	 */
	protected function currentWcOrderIds(): array {
		return array_map(
			'intval',
			wc_get_orders(
				array(
					'limit'  => -1,
					'return' => 'ids',
				)
			)
		);
	}

	/**
	 * Tracks a WC order for deletion in tearDown().
	 *
	 * @param int $order_id The WC order id.
	 * @return void
	 */
	protected function trackWcOrder( int $order_id ): void {
		$this->wcOrderIds[] = $order_id;
	}
}
