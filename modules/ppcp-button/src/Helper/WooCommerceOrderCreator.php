<?php
/**
 * Can create WC orders.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use RuntimeException;
use WC_Order;
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
	 * @param Order $order The PayPal order.
	 * @param array $line_items The list of line item IDs.
	 * @return WC_Order The WC order.
	 */
	public function create_from_paypal_order( Order $order, array $line_items ): WC_Order {
		$wc_order = wc_create_order();

		if ( ! $wc_order instanceof WC_Order ) {
			throw new RuntimeException( 'Problem creating WC order.' );
		}

		$this->configure_line_items( $wc_order, $line_items );
		$this->configure_shipping( $wc_order, $order->payer(), $order->purchase_units()[0]->shipping() );
		$this->configure_payment_source( $wc_order );
		$this->configure_customer( $wc_order );

		$wc_order->calculate_totals();
		$wc_order->save();

		return $wc_order;
	}

	/**
	 * Configures the line items.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @param array    $line_items The list of line item IDs.
	 * @return void
	 */
	protected function configure_line_items( WC_Order $wc_order, array $line_items ): void {
		foreach ( $line_items as $line_item ) {
			$product_id   = $line_item['product_id'] ?? 0;
			$variation_id = $line_item['variation_id'] ?? 0;
			$args         = $variation_id > 0 ? array( 'variation_id' => $variation_id ) : array();
			$quantity     = $line_item['quantity'] ?? 0;

			$item = wc_get_product( $product_id );

			if ( ! $item ) {
				return;
			}

			$wc_order->add_product( $item, $quantity, $args );
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
		$billing_address = null;
		$shipping_options = null;

		if ( $payer  && $address = $payer->address() ) {
			$payerName = $payer->name();

			$billing_address = array(
				'first_name' => $payerName ? $payerName->given_name() : '',
				'last_name'  => $payerName ? $payerName->surname() : '',
				'address_1'  => $address->address_line_1(),
				'address_2'  => $address->address_line_2(),
				'city'       => $address->admin_area_2(),
				'state'      => $address->admin_area_1(),
				'postcode'   => $address->postal_code(),
				'country'    => $address->country_code(),
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

}
