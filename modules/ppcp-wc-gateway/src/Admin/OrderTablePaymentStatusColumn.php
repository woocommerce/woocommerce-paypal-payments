<?php
/**
 * Renders the columns to display to the merchant, which orders have been authorized and
 * which have not been authorized yet.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Admin
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Admin;

use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class OrderTablePaymentStatusColumn
 */
class OrderTablePaymentStatusColumn {

	const COLUMN_KEY       = 'ppcp_payment_status';
	const INTENT           = 'authorize';
	const AFTER_COLUMN_KEY = 'order_status';

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * OrderTablePaymentStatusColumn constructor.
	 *
	 * @param Settings $settings The Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register the columns.
	 *
	 * @param array $columns The existing columns.
	 *
	 * @return array
	 */
	public function register( array $columns ): array {
		if ( ! $this->settings->has( 'intent' ) || $this->settings->get( 'intent' ) !== self::INTENT ) {
			return $columns;
		}

		$status_column_position = array_search( self::AFTER_COLUMN_KEY, array_keys( $columns ), true );
		$to_insert_position     = false === $status_column_position ? count( $columns ) : $status_column_position + 1;

		$columns = array_merge(
			array_slice( $columns, 0, $to_insert_position ),
			array(
				self::COLUMN_KEY => __( 'Payment Captured', 'woocommerce-paypal-payments' ),
			),
			array_slice( $columns, $to_insert_position )
		);

		return $columns;
	}

	/**
	 * Render the column.
	 *
	 * @param string $column The column.
	 * @param int    $wc_order_id The id or the WooCommerce order.
	 */
	public function render( string $column, int $wc_order_id ) {
		if ( ! $this->settings->has( 'intent' ) || $this->settings->get( 'intent' ) !== self::INTENT ) {
			return;
		}

		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$wc_order = wc_get_order( $wc_order_id );

		if ( ! is_a( $wc_order, \WC_Order::class ) || ! $this->render_for_order( $wc_order ) ) {
			return;
		}

		if ( $this->is_captured( $wc_order ) ) {
			$this->render_completed_status();
			return;
		}

		$this->render_incomplete_status();
	}

	/**
	 * Whether to render the authorization status of an order or not.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	private function render_for_order( \WC_Order $order ): bool {
		return ! empty( $order->get_meta( PayPalGateway::CAPTURED_META_KEY ) );
	}

	/**
	 * Whether the order has been captured or not.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @return bool
	 */
	private function is_captured( \WC_Order $wc_order ): bool {
		$captured = $wc_order->get_meta( PayPalGateway::CAPTURED_META_KEY );
		return wc_string_to_bool( $captured );
	}

	/**
	 * Renders the captured status.
	 */
	private function render_completed_status() {
		printf(
			'<span class="dashicons dashicons-yes">
                        <span class="screen-reader-text">%s</span>
                    </span>',
			esc_html__( 'Payment captured', 'woocommerce-paypal-payments' )
		);
	}

	/**
	 * Renders the "not captured" status.
	 */
	private function render_incomplete_status() {
		printf(
			'<mark class="onbackorder">%s</mark>',
			esc_html__( 'Not captured', 'woocommerce-paypal-payments' )
		);
	}
}
