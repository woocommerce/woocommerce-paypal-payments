<?php
/**
 * The API module.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderTransient;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;

/**
 * Class ApiModule
 */
class ApiModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): void {
		add_action(
			'woocommerce_after_calculate_totals',
			function ( \WC_Cart $cart ) {
				$fees = $cart->fees_api()->get_fees();
				WC()->session->set( 'ppcp_fees', $fees );
			}
		);
		add_filter(
			'ppcp_create_order_request_body_data',
			function( array $data ) use ( $c ) {

				foreach ( $data['purchase_units'] as $purchase_unit_index => $purchase_unit ) {
					foreach ( $purchase_unit['items'] as $item_index => $item ) {
						$data['purchase_units'][ $purchase_unit_index ]['items'][ $item_index ]['name'] =
							apply_filters( 'woocommerce_paypal_payments_cart_line_item_name', $item['name'], $item['cart_item_key'] ?? null );
					}
				}

				return $data;
			}
		);
		add_action(
			'woocommerce_paypal_payments_paypal_order_created',
			function ( Order $order ) use ( $c ) {
				$transient = $c->has( 'api.helper.order-transient' ) ? $c->get( 'api.helper.order-transient' ) : null;

				if ( $transient instanceof OrderTransient ) {
					$transient->on_order_created( $order );
				}
			},
			10,
			1
		);
		add_action(
			'woocommerce_paypal_payments_woocommerce_order_created',
			function ( WC_Order $wc_order, Order $order ) use ( $c ) {
				$transient = $c->has( 'api.helper.order-transient' ) ? $c->get( 'api.helper.order-transient' ) : null;

				if ( $transient instanceof OrderTransient ) {
					$transient->on_woocommerce_order_created( $wc_order, $order );
				}
			},
			10,
			2
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
