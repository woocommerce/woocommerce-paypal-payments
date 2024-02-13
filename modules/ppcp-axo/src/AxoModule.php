<?php
/**
 * The Axo module.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AxoModule
 */
class AxoModule implements ModuleInterface {
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

		add_filter(
			'woocommerce_payment_gateways',
			function ( $methods ) use ( $c ): array {
				$settings = $c->get( 'wcgateway.settings' );

				$methods[] = new AxoGateway(
					$settings
				);
				return $methods;
			},
			1,
			9
		);

		/**
		 * Param types removed to avoid third-party issues.
		 *
		 * @psalm-suppress MissingClosureParamType
		 */
		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			function( $components ) {
				$components[] = 'connect';
				return $components;
			}
		);

		add_action(
			'init',
			static function () use ( $c ) {

				// Enqueue frontend scripts.
				add_action(
					'wp_enqueue_scripts',
					static function () use ( $c ) {
						$module_url = $c->get( 'axo.url' );
						$version = '1';

						// Register styles.
						wp_register_style(
							'wc-ppcp-axo',
							untrailingslashit( $module_url ) . '/assets/css/styles.css',
							array(),
							$version
						);
						wp_enqueue_style( 'wc-ppcp-axo' );

						// Register scripts.
						wp_register_script(
							'wc-ppcp-axo',
							untrailingslashit( $module_url ) . '/assets/js/boot.js',
							array(),
							$version,
							true
						);
						wp_enqueue_script( 'wc-ppcp-axo' );

						wp_localize_script(
							'wc-ppcp-axo',
							'wc_ppcp_axo',
							array(
								// TODO
							)
						);

					}
				);

			},
			1
		);

		add_action(
			$this->checkout_button_renderer_hook(),
			array(
				$this,
				'axo_button_renderer',
			),
			11
		);

	}

	/**
	 * Returns the action name that PayPal AXO button will use for rendering on the checkout page.
	 *
	 * @return string
	 */
	private function checkout_button_renderer_hook(): string {
		/**
		 * The filter returning the action name that PayPal AXO button will use for rendering on the checkout page.
		 */
		return (string) apply_filters( 'woocommerce_paypal_payments_checkout_axo_renderer_hook', 'woocommerce_review_order_after_submit' );
	}

	/**
	 * Renders the HTML for the AXO submit button.
	 */
	public function axo_button_renderer() {
		$id = 'axo-submit-button-container';

		/**
		 * The WC filter returning the WC order button text.
		 * phpcs:disable WordPress.WP.I18n.TextDomainMismatch
		 */
		$label = apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );

		printf(
			'<div id="%1$s" style="display: none;">
				<button type="submit" class="button alt ppcp-axo-order-button">%2$s</button>
			</div>',
			esc_attr( $id ),
			esc_html( $label )
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
