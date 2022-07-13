<?php
/**
 * OXXO integration.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO;

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
			function ( array $methods ): array {

				if ( ! $this->checkout_allowed_for_oxxo() ) {
					unset( $methods[ OXXOGateway::ID ] );
				}

				return $methods;
			}
		);

		add_filter(
			'woocommerce_thankyou_order_received_text',
			function( string $message, WC_Order $order ) {
				$payer_action = $order->get_meta( 'ppcp_oxxo_payer_action' ) ?? '';

				$button = '';
				if ( $payer_action ) {
					$button = '<p><a id="ppcp-oxxo-payer-action" class="button" href="' . $payer_action . '" target="_blank">See OXXO Voucher/Ticket</a></p>';
				}

				return $message . ' ' . $button;
			},
			10,
			2
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'register_assets' )
		);

		add_action('woocommerce_review_order_after_payment', function () {

			echo '<button class="button" id="ppcp-oxxo">Pago en OXXO</button>';
		});
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

		$billing_country = filter_input( INPUT_POST, 'country', FILTER_SANITIZE_STRING ) ?? null;
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
		if ( $gateway_enabled === 'yes' && is_checkout() ) { // && ! empty( is_wc_endpoint_url( 'order-received' ) )
			wp_enqueue_script(
				'ppcp-oxxo',
				trailingslashit( $this->module_url ) . 'assets/js/oxxo.js',
				array(),
				$this->asset_version,
				true
			);
		}

		wp_localize_script(
			'ppcp-oxxo',
			'OXXOConfig',
			array(
				'oxxo_endpoint'     => \WC_AJAX::get_endpoint( 'ppc-oxxo' ),
				'oxxo_nonce'        => wp_create_nonce( 'ppc-oxxo' ),
			)
		);
	}
}
