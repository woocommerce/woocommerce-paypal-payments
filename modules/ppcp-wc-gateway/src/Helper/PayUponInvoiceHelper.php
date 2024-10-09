<?php
/**
 * Helper methods for PUI.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Customer;
use WC_Order;
use WC_Order_Item_Product;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayUponInvoiceHelper
 */
class PayUponInvoiceHelper {

	/**
	 * The checkout helper.
	 *
	 * @var CheckoutHelper
	 */
	protected $checkout_helper;

	/**
	 * The api shop country.
	 *
	 * @var string
	 */
	protected $api_shop_country;

	/**
	 * PayUponInvoiceHelper constructor.
	 *
	 * @param CheckoutHelper $checkout_helper The checkout helper.
	 * @param string         $api_shop_country The api shop country.
	 */
	public function __construct( CheckoutHelper $checkout_helper, string $api_shop_country ) {
		$this->checkout_helper  = $checkout_helper;
		$this->api_shop_country = $api_shop_country;
	}

	/**
	 * Checks whether checkout is ready for PUI.
	 *
	 * @return bool
	 * @psalm-suppress RedundantConditionGivenDocblockType
	 */
	public function is_checkout_ready_for_pui(): bool {
		$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
		if ( $gateway_settings && '' === $gateway_settings['customer_service_instructions'] ) {
			return false;
		}

		if ( ! WC()->customer instanceof WC_Customer ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$billing_country = WC()->customer->get_billing_country();
		if ( empty( $billing_country ) || 'DE' !== $billing_country ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$shipping_country = WC()->customer->get_shipping_country();
		if ( empty( $shipping_country ) || 'DE' !== $shipping_country ) {
			return false;
		}

		if (
			! $this->is_valid_product()
			|| ! $this->is_valid_currency()
			|| ! $this->checkout_helper->is_checkout_amount_allowed( 5, 2500 )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether PUI gateway is enabled.
	 *
	 * @return bool True if PUI gateway is enabled, otherwise false.
	 */
	public function is_pui_gateway_enabled(): bool {
		$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
		return isset( $gateway_settings['enabled'] ) && $gateway_settings['enabled'] === 'yes' && 'DE' === $this->api_shop_country;
	}

	/**
	 * Checks if product is valid for PUI.
	 *
	 * @return bool
	 */
	private function is_valid_product(): bool {
		$cart = WC()->cart ?? null;
		if ( $cart && ! is_checkout_pay_page() ) {
			$items = $cart->get_cart_contents();
			foreach ( $items as $item ) {
				$product_id = $item['product_id'];
				if ( isset( $item['variation_id'] ) && $item['variation_id'] ) {
					$product_id = $item['variation_id'];
				}
				$product = wc_get_product( $product_id );
				if ( $product && ! $this->checkout_helper->is_physical_product( $product ) ) {
					return false;
				}
			}
		}

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			/**
			 * Needed for WordPress `query_vars`.
			 *
			 * @psalm-suppress InvalidGlobal
			 */
			global $wp;

			if ( isset( $wp->query_vars['order-pay'] ) && absint( $wp->query_vars['order-pay'] ) > 0 ) {
				$order_id = absint( $wp->query_vars['order-pay'] );
				$order    = wc_get_order( $order_id );
				if ( is_a( $order, WC_Order::class ) ) {
					foreach ( $order->get_items() as $item_id => $item ) {
						if ( is_a( $item, WC_Order_Item_Product::class ) ) {
							$product = wc_get_product( $item->get_product_id() );
							if ( $product && ! $this->checkout_helper->is_physical_product( $product ) ) {
								return false;
							}
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * Checks whether pay for order is ready for PUI.
	 *
	 * @return bool
	 */
	public function is_pay_for_order_ready_for_pui(): bool {
		/**
		 * Needed for WordPress `query_vars`.
		 *
		 * @psalm-suppress InvalidGlobal
		 */
		global $wp;

		$order_id = (int) ( $wp->query_vars['order-pay'] ?? 0 );
		if ( $order_id === 0 ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, WC_Order::class ) ) {
			return false;
		}

		$address         = $order->get_address();
		$required_fields = array(
			'first_name',
			'last_name',
			'email',
			'phone',
			'address_1',
			'city',
			'postcode',
			'country',
		);

		foreach ( $required_fields as $key ) {
			$value = $address[ $key ] ?? '';
			if ( $value === '' ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if currency is allowed for PUI.
	 *
	 * @return bool
	 */
	private function is_valid_currency(): bool {
		global $wp;
		$order_id = isset( $wp->query_vars['order-pay'] ) ? (int) $wp->query_vars['order-pay'] : 0;
		if ( 0 === $order_id ) {
			return 'EUR' === get_woocommerce_currency();
		}

		$order = wc_get_order( $order_id );
		if ( is_a( $order, WC_Order::class ) ) {
			return 'EUR' === $order->get_currency();
		}

		return false;
	}
}
