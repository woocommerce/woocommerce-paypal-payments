<?php
/**
 * OXXO integration.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;

/**
 * Class OXXO.
 */
class OXXO {

	/**
	 * The checkout helper.
	 *
	 * @var CheckoutHelper
	 */
	protected $checkout_helper;

	/**
	 * The module URL.
	 *
	 * @var string
	 */
	protected $module_url;

	/**
	 * The asset version.
	 *
	 * @var string
	 */
	protected $asset_version;

	/**
	 * OXXO constructor.
	 *
	 * @param CheckoutHelper $checkout_helper The checkout helper.
	 * @param string         $module_url The module URL.
	 * @param string         $asset_version The asset version.
	 */
	public function __construct(
		CheckoutHelper $checkout_helper,
		string $module_url,
		string $asset_version
	) {

		$this->checkout_helper = $checkout_helper;
		$this->module_url      = $module_url;
		$this->asset_version   = $asset_version;
	}

	/**
	 * Initializes OXXO integration.
	 */
	public function init(): void {

		add_filter(
			'woocommerce_available_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $methods ) {
				if ( ! is_array( $methods ) ) {
					return $methods;
				}

				if ( ! $this->checkout_allowed_for_oxxo() ) {
					unset( $methods[ OXXOGateway::ID ] );
				}

				return $methods;
			}
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'register_assets' )
		);

		add_filter(
			'woocommerce_thankyou_order_received_text',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $message, $order ) {
				if ( ! is_string( $message ) || ! $order instanceof WC_Order ) {
					return $message;
				}

				$payer_action = $order->get_meta( 'ppcp_oxxo_payer_action' ) ?? '';

				$button = '';
				if ( $payer_action ) {
					$button = '<p><a id="ppcp-oxxo-payer-action" class="button" href="' . $payer_action . '" target="_blank">' . esc_html__( 'See OXXO voucher', 'woocommerce-paypal-payments' ) . '</a></p>';
				}

				return $message . ' ' . $button;
			},
			10,
			2
		);

		add_action(
			'woocommerce_email_before_order_table',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $order, $sent_to_admin ) {
				if ( ! is_a( $order, WC_Order::class ) || ! is_bool( $sent_to_admin ) ) {
					return;
				}

				if (
					! $sent_to_admin
					&& $order->get_payment_method() === OXXOGateway::ID
					&& $order->has_status( 'on-hold' )
				) {
					$payer_action = $order->get_meta( 'ppcp_oxxo_payer_action' ) ?? '';
					if ( $payer_action ) {
						echo '<p><a class="button" href="' . esc_url( $payer_action ) . '" target="_blank">' . esc_html__( 'See OXXO voucher', 'woocommerce-paypal-payments' ) . '</a></p>';
					}
				}
			},
			10,
			2
		);

		add_filter(
			'ppcp_payment_capture_reversed_webhook_update_status_note',
			function( string $note, WC_Order $wc_order, string $event_type ): string {
				if ( $wc_order->get_payment_method() === OXXOGateway::ID && $event_type === 'PAYMENT.CAPTURE.DENIED' ) {
					$note = __( 'OXXO voucher has expired or the buyer didn\'t complete the payment successfully.', 'woocommerce-paypal-payments' );
				}

				return $note;
			},
			10,
			3
		);

		add_action(
			'add_meta_boxes',
			function( string $post_type ) {
				/**
				 * Class and function exist in WooCommerce.
				 *
				 * @psalm-suppress UndefinedClass
				 * @psalm-suppress UndefinedFunction
				 */
				$screen = class_exists( CustomOrdersTableController::class ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
					? wc_get_page_screen_id( 'shop-order' )
					: 'shop_order';

				if ( $post_type === $screen ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$post_id = wc_clean( wp_unslash( $_GET['id'] ?? $_GET['post'] ?? '' ) );
					$order   = wc_get_order( $post_id );
					if ( is_a( $order, WC_Order::class ) && $order->get_payment_method() === OXXOGateway::ID ) {
						$payer_action = $order->get_meta( 'ppcp_oxxo_payer_action' );
						if ( $payer_action ) {
							add_meta_box(
								'ppcp_oxxo_payer_action',
								__( 'OXXO Voucher/Ticket', 'woocommerce-paypal-payments' ),
								function() use ( $payer_action ) {
									echo '<p><a class="button" href="' . esc_url( $payer_action ) . '" target="_blank">' . esc_html__( 'See OXXO voucher', 'woocommerce-paypal-payments' ) . '</a></p>';
								},
								$screen,
								'side',
								'high'
							);
						}
					}
				}
			}
		);

		add_action(
			'woocommerce_order_details_before_order_table_items',
			function( WC_Order $order ) {
				if ( $order->get_payment_method() === OXXOGateway::ID ) {
					$payer_action = $order->get_meta( 'ppcp_oxxo_payer_action' );
					if ( $payer_action ) {
						echo '<p><a class="button" href="' . esc_url( $payer_action ) . '" target="_blank">' . esc_html__( 'See OXXO voucher', 'woocommerce-paypal-payments' ) . '</a></p>';
					}
				}
			}
		);
	}

	/**
	 * Checks if checkout is allowed for OXXO.
	 *
	 * @return bool
	 */
	private function checkout_allowed_for_oxxo(): bool {
		if ( 'MXN' !== get_woocommerce_currency() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$billing_country = wc_clean( wp_unslash( $_POST['country'] ?? '' ) );
		if ( $billing_country && 'MX' !== $billing_country ) {
			return false;
		}

		if ( ! $this->checkout_helper->is_checkout_amount_allowed( 0, 10000 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Register OXXO assets.
	 */
	public function register_assets(): void {
		$gateway_settings = get_option( 'woocommerce_ppcp-oxxo-gateway_settings' );
		$gateway_enabled  = $gateway_settings['enabled'] ?? '';
		if ( $gateway_enabled === 'yes' && is_checkout() ) {
			wp_enqueue_script(
				'ppcp-oxxo',
				trailingslashit( $this->module_url ) . 'assets/js/oxxo.js',
				array(),
				$this->asset_version,
				true
			);
		}
	}
}
