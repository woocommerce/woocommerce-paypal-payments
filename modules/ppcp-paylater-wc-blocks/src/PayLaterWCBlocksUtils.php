<?php
/**
 * The Pay Later WooCommerce Blocks Utils.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

/**
 * Class PayLaterWCBlocksUtils
 */
class PayLaterWCBlocksUtils {

	/**
	 * Inserts content before the last div in a block.
	 *
	 * @param string $block_content The block content.
	 * @param string $content_to_insert The content to insert.
	 * @return string The block content with the content inserted.
	 */
	public static function insert_before_last_div( string $block_content, string $content_to_insert ): string {
		$last_index = strrpos( $block_content, '</div>' );

		if ( $last_index !== false ) {
			$block_content = substr_replace( $block_content, $content_to_insert, $last_index, 0 );
		}

		return $block_content;
	}

	/**
	 * Renders a PayLater message block and inserts it before the last closing div tag if the block id is not already present.
	 *
	 * @param string $block_content Current content of the block.
	 * @param string $block_id ID of the block to render.
	 * @param string $ppcp_id ID for the PPCP component.
	 * @param string $context Rendering context (cart or checkout).
	 * @param mixed  $container Dependency injection container.
	 * @return string Updated block content.
	 */
	public static function render_and_insert_paylater_block( string $block_content, string $block_id, string $ppcp_id, string $context, $container ): string {
		$paylater_message_block = self::render_paylater_block( $block_id, $ppcp_id, $context, $container );
		if ( false !== $paylater_message_block ) {
			return self::insert_before_last_div( $block_content, $paylater_message_block );
		}
		return $block_content;
	}

	/**
	 * Renders the PayLater block based on the provided parameters.
	 *
	 * @param string $block_id ID of the block to render.
	 * @param string $ppcp_id ID for the PPCP component.
	 * @param string $context Rendering context (cart or checkout).
	 * @param mixed  $container Dependency injection container.
	 * @return false|string Rendered content.
	 */
	public static function render_paylater_block( string $block_id, string $ppcp_id, string $context, $container ) {
		$renderer = $container->get( 'paylater-wc-blocks.' . $context . '-renderer' );
		ob_start();
		// phpcs:ignore -- No need to escape it, the PayLaterWCBlocksRenderer class is responsible for escaping.
		echo $renderer->render(
			array(
				// phpcs:ignore
				'blockId'     => $block_id,
				// phpcs:ignore
				'ppcpId' => $ppcp_id,
			),
			// phpcs:ignore
			$context,
			// phpcs:ignore
			$container
		);
		return ob_get_clean();
	}
}
