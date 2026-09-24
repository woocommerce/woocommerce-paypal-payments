<?php
/**
 * Renders the Single Product PayPal Smart Buttons wrapper.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Emits the button wrapper the existing Smart Buttons front-end pipeline mounts into.
 *
 * The wrapper markup is identical to what `SmartButton::button_renderer()` produces so
 * that `button.js` -> `SingleProductBootstrap` -> `Renderer` (v5) or `boot.js` (v6)
 * finds and hydrates it unchanged: the inner id is `ppc-button-ppcp-gateway`, or the
 * `-v6` variant when the SDK v6 stack owns the current page. No script is enqueued here -
 * `SmartButton::enqueue()` / the v6 manager already run on front-end product pages and
 * localize `context='product'` + `single_product_buttons_enabled='1'`.
 */
class ProductSmartButtonsRenderer {

	/**
	 * The wrapper id the v5 Smart Buttons stack mounts into.
	 */
	private const V5_WRAPPER_ID = 'ppc-button-ppcp-gateway';

	/**
	 * The wrapper id the v6 SDK stack mounts into.
	 */
	private const V6_WRAPPER_ID = 'ppc-button-ppcp-gateway-v6';

	/**
	 * Renders the button wrapper, or an empty string when Smart Buttons are off for the
	 * product location or the current request is not a product page.
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param ContainerInterface   $c          The container.
	 * @return string The rendered HTML.
	 */
	public function render( array $attributes, ContainerInterface $c ): string {
		if ( ! ProductBlocks::is_buttons_enabled( $c->get( 'wcgateway.settings.status' ) ) || ! is_product() ) {
			return '';
		}

		$wrapper_id = ProductBlocks::v6_owns_current_page( $c )
			? self::V6_WRAPPER_ID
			: self::V5_WRAPPER_ID;

		ob_start();
		// The outer `.ppc-button-wrapper` is required for the loading spinner; the inner
		// id is the SDK mount target. The actions mirror SmartButton::button_renderer()
		// so third-party integrations that hook them keep working.
		echo '<div class="ppc-button-wrapper">';
		do_action( 'ppcp_start_button_wrapper_ppcp_gateway' );
		echo '<div id="' . esc_attr( $wrapper_id ) . '"></div>';
		do_action( 'ppcp_end_button_wrapper_ppcp_gateway' );
		do_action( 'woocommerce_paypal_payments_single_product_button_render' );
		echo '</div>';
		$inner = (string) ob_get_clean();

		return sprintf(
			'<div %1$s>%2$s</div>',
			wp_kses_data( get_block_wrapper_attributes() ),
			$inner
		);
	}
}
