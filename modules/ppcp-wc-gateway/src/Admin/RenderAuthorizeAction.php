<?php
/**
 * Renders the order action "Capture authorized PayPal payment"
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Admin
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Admin;

/**
 * Class RenderAuthorizeAction
 */
class RenderAuthorizeAction {
	/**
	 * The capture info column.
	 *
	 * @var OrderTablePaymentStatusColumn
	 */
	private $column;

	/**
	 * PaymentStatusOrderDetail constructor.
	 *
	 * @param OrderTablePaymentStatusColumn $column The capture info column.
	 */
	public function __construct( OrderTablePaymentStatusColumn $column ) {
		$this->column = $column;
	}

	/**
	 * Renders the action into the $order_actions array based on the WooCommerce order.
	 *
	 * @param array     $order_actions The actions to render into.
	 * @param \WC_Order $wc_order The order for which to render the action.
	 *
	 * @return array
	 */
	public function render( array $order_actions, \WC_Order $wc_order ) : array {

		if ( ! $this->should_render_for_order( $wc_order ) ) {
			return $order_actions;
		}

		$order_actions['ppcp_authorize_order'] = esc_html__(
			'Capture authorized PayPal payment',
			'woocommerce-paypal-payments'
		);
		return $order_actions;
	}

	/**
	 * Whether the action should be rendered for a certain WooCommerce order.
	 *
	 * @param \WC_Order $order The Woocommerce order.
	 *
	 * @return bool
	 */
	private function should_render_for_order( \WC_Order $order ) : bool {
		$status               = $order->get_status();
		$not_allowed_statuses = array( 'refunded', 'cancelled', 'failed' );
		return $this->column->should_render_for_order( $order ) &&
			! $this->column->is_captured( $order ) &&
			! in_array( $status, $not_allowed_statuses, true );
	}
}
