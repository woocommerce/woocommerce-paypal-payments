<?php
/**
 * Can create WC orders.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use RuntimeException;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class WooCommerceOrderCreator
 */
class WooCommerceOrderCreator {

	/**
	 * The funding source renderer.
	 *
	 * @var FundingSourceRenderer
	 */
	protected $funding_source_renderer;

	/**
	 * The Session handler.
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * WooCommerceOrderCreator constructor.
	 *
	 * @param FundingSourceRenderer $funding_source_renderer The funding source renderer.
	 * @param SessionHandler        $session_handler The session handler.
	 */
	public function __construct(
		FundingSourceRenderer $funding_source_renderer,
		SessionHandler $session_handler
	) {
		$this->funding_source_renderer = $funding_source_renderer;
		$this->session_handler         = $session_handler;
	}

	/**
	 * Creates WC order based on given PayPal order.
	 *
	 * @param Order   $order The PayPal order.
	 * @param WC_Cart $wc_cart The Cart.
	 * @return WC_Order The WC order.
	 * @throws RuntimeException If problem creating.
	 */
	public function create_from_paypal_order( Order $order, WC_Cart $wc_cart ): WC_Order {
		$wc_order = wc_create_order();

		if ( ! $wc_order instanceof WC_Order ) {
			throw new RuntimeException( 'Problem creating WC order.' );
		}

		$this->configure_line_items( $wc_order, $wc_cart );
		$this->configure_shipping( $wc_order, $order->payer(), $order->purchase_units()[0]->shipping() );
		$this->configure_payment_source( $wc_order );
		$this->configure_customer( $wc_order );
		$this->configure_coupons( $wc_order, $wc_cart->get_applied_coupons() );

		$wc_order->calculate_totals();
		$wc_order->save();

		return $wc_order;
	}

	/**
	 * Configures the line items.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @param WC_Cart  $wc_cart The Cart.
	 * @return void
	 */
	protected function configure_line_items( WC_Order $wc_order, WC_Cart $wc_cart ): void {
		$cart_contents = $wc_cart->get_cart();

		foreach ( $cart_contents as $cart_item ) {
			$product_id           = $cart_item['product_id'] ?? 0;
			$variation_id         = $cart_item['variation_id'] ?? 0;
			$quantity             = $cart_item['quantity'] ?? 0;
			$variation_attributes = $cart_item['variation'];

			$item = new WC_Order_Item_Product();
			$item->set_product_id( $product_id );
			$item->set_quantity( $quantity );

			if ( $variation_id ) {
				$item->set_variation_id( $variation_id );
				$item->set_variation( $variation_attributes );
			}

			$product = wc_get_product( $variation_id ?: $product_id );
			if ( ! $product ) {
				return;
			}

			$item->set_name( $product->get_name() );
			$item->set_subtotal( $product->get_price() * $quantity );
			$item->set_total( $product->get_price() * $quantity );

			$wc_order->add_item( $item );
		}
	}

	/**
	 * Configures the shipping & billing addresses for WC order from given payer.
	 *
	 * @param WC_Order      $wc_order The WC order.
	 * @param Payer|null    $payer The payer.
	 * @param Shipping|null $shipping The shipping.
	 * @return void
	 */
	protected function configure_shipping( WC_Order $wc_order, ?Payer $payer, ?Shipping $shipping ): void {
		$shipping_address = null;
		$billing_address  = null;
		$shipping_options = null;

		if ( $payer ) {
			$address    = $payer->address();
			$payer_name = $payer->name();

			$billing_address = array(
				'first_name' => $payer_name ? $payer_name->given_name() : '',
				'last_name'  => $payer_name ? $payer_name->surname() : '',
				'address_1'  => $address ? $address->address_line_1() : '',
				'address_2'  => $address ? $address->address_line_2() : '',
				'city'       => $address ? $address->admin_area_2() : '',
				'state'      => $address ? $address->admin_area_1() : '',
				'postcode'   => $address ? $address->postal_code() : '',
				'country'    => $address ? $address->country_code() : '',
			);
		}

		if ( $shipping ) {
			$address = $shipping->address();

			$shipping_address = array(
				'first_name' => $shipping->name(),
				'last_name'  => '',
				'address_1'  => $address->address_line_1(),
				'address_2'  => $address->address_line_2(),
				'city'       => $address->admin_area_2(),
				'state'      => $address->admin_area_1(),
				'postcode'   => $address->postal_code(),
				'country'    => $address->country_code(),
			);

			$shipping_options = $shipping->options()[0] ?? '';
		}

		if ( $shipping_address ) {
			$wc_order->set_shipping_address( $shipping_address );
		}

		if ( $billing_address || $shipping_address ) {
			$wc_order->set_billing_address( $billing_address ?: $shipping_address );
		}

		if ( $shipping_options ) {
			$shipping = new WC_Order_Item_Shipping();
			$shipping->set_method_title( $shipping_options->label() );
			$shipping->set_method_id( $shipping_options->id() );
			$shipping->set_total( $shipping_options->amount()->value_str() );

			$wc_order->add_item( $shipping );
		}
	}

	/**
	 * Configures the payment source.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @return void
	 */
	protected function configure_payment_source( WC_Order $wc_order ): void {
		$funding_source = $this->session_handler->funding_source();
		$wc_order->set_payment_method( PayPalGateway::ID );

		if ( $funding_source ) {
			$wc_order->set_payment_method_title( $this->funding_source_renderer->render_name( $funding_source ) );
		}
	}

	/**
	 * Configures the customer ID.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @return void
	 */
	protected function configure_customer( WC_Order $wc_order ): void {
		$current_user = wp_get_current_user();

		if ( $current_user->ID !== 0 ) {
			$wc_order->set_customer_id( $current_user->ID );
		}
	}

	/**
	 * Configures the applied coupons.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @param string[] $coupons The list of applied coupons.
	 * @return void
	 */
	protected function configure_coupons( WC_Order $wc_order, array $coupons ): void {
		foreach ( $coupons as $coupon_code ) {
			$wc_order->apply_coupon( $coupon_code );
		}
	}

}
