<?php
/**
 * Renders the Mini-Cart PayPal Smart Buttons wrapper.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Prints the mini-cart wrapper markup (matching SdkV6Manager::render_mini_cart_wrapper()) that
 * boot.js mounts the v6 buttons into, inside the block Mini-Cart where the classic action never fires.
 */
class MiniCartSmartButtonsRenderer {

	/**
	 * The v6 mount-target id; a literal that must match SdkV6Manager::MINI_CART_WRAPPER_ID,
	 * duplicated to avoid coupling this module to the SDK v6 module.
	 */
	private const WRAPPER_ID = 'ppc-button-minicart-v6';

	/**
	 * Renders the button wrapper, or an empty string when Smart Buttons are off for the
	 * mini-cart location.
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param ContainerInterface   $c          The container.
	 * @return string The rendered HTML.
	 */
	public function render( array $attributes, ContainerInterface $c ): string {
		if ( ! MiniCartBlocks::is_buttons_enabled( $c->get( 'wcgateway.settings.status' ) ) ) {
			return '';
		}

		ob_start();
		// The paragraph carries the drawer's button spacing; the span is the SDK mount target.
		echo '<p class="woocommerce-mini-cart__buttons buttons">';
		echo '<span id="' . esc_attr( self::WRAPPER_ID ) . '"></span>';
		echo '</p>';
		$inner = (string) ob_get_clean();

		return sprintf(
			'<div %1$s>%2$s</div>',
			wp_kses_data( get_block_wrapper_attributes() ),
			$inner
		);
	}
}
