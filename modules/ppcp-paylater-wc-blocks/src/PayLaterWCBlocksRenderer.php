<?php
/**
 * The Pay Later WooCommerce Blocks Renderer.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class PayLaterWCBlocksRenderer
 */
class PayLaterWCBlocksRenderer {

	/**
	 * Renders the WC Pay Later Messaging blocks.
	 *
	 * @param array              $attributes The block attributes.
	 * @param string             $location The location of the block.
	 * @param ContainerInterface $c The container.
	 * @return string|void
	 */
	public function render( array $attributes, string $location, ContainerInterface $c ) {
		if ( PayLaterWCBlocksModule::is_placement_enabled( $c->get( 'wcgateway.settings.status' ), $location ) ) {
			return '<div id="' . esc_attr( $attributes['ppcpId'] ?? '' ) . '" data-block-name="' . esc_attr( $attributes['id'] ?? '' ) . '" class="ppcp-messages" data-partner-attribution-id="Woo_PPCP"></div>';
		}
	}
}
