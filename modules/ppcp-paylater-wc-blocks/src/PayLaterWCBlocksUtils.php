<?php

/**
 * The Pay Later WooCommerce Blocks Utils.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

/**
 * Class PayLaterWCBlocksUtils
 */
class PayLaterWCBlocksUtils
{
    /**
     * Inserts content before the last div in a block.
     *
     * @param string $block_content The block content.
     * @param string $content_to_insert The content to insert.
     * @return string The block content with the content inserted.
     */
    public static function insert_before_last_div(string $block_content, string $content_to_insert): string
    {
        $last_index = strrpos($block_content, '</div>');
        if (\false !== $last_index) {
            $block_content = substr_replace($block_content, $content_to_insert, $last_index, 0);
        }
        return $block_content;
    }
    /**
     * Inserts content after the closing div tag of a specific block.
     *
     * @param string $block_content The block content.
     * @param string $content_to_insert The content to insert.
     * @param string $reference_block The block markup to insert the content after.
     * @return string The block content with the content inserted.
     */
    public static function insert_before_opening_div(string $block_content, string $content_to_insert, string $reference_block): string
    {
        $reference_block_index = strpos($block_content, $reference_block);
        if (\false !== $reference_block_index) {
            return substr_replace($block_content, $content_to_insert, $reference_block_index, 0);
        } else {
            return self::insert_before_last_div($block_content, $content_to_insert);
        }
    }
    /**
     * Renders a PayLater message block and inserts it before the last closing div tag if the block id is not already present.
     *
     * @param string $block_content Current content of the block.
     * @param string $block_id ID of the block to render.
     * @param string $ppcp_id ID for the PPCP component.
     * @param string $context Rendering context (cart or checkout).
     * @param mixed  $container Dependency injection container.
     * @param bool   $is_under_cart_totals_placement_enabled Whether the block should be placed under the cart totals.
     * @return string Updated block content.
     */
    public static function render_and_insert_paylater_block(string $block_content, string $block_id, string $ppcp_id, string $context, $container, bool $is_under_cart_totals_placement_enabled = \false): string
    {
        $paylater_message_block = self::render_paylater_block($block_id, $ppcp_id, $context, $container);
        $cart_express_payment_block = '<div data-block-name="woocommerce/cart-express-payment-block" class="wp-block-woocommerce-cart-express-payment-block"></div>';
        if (\false !== $paylater_message_block) {
            if ($is_under_cart_totals_placement_enabled && $context === 'cart') {
                return self::insert_before_opening_div($block_content, $paylater_message_block, $cart_express_payment_block);
            } else {
                return self::insert_before_last_div($block_content, $paylater_message_block);
            }
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
    public static function render_paylater_block(string $block_id, string $ppcp_id, string $context, $container)
    {
        $renderer = $container->get('paylater-wc-blocks.' . $context . '-renderer');
        ob_start();
        // phpcs:ignore -- No need to escape it, the PayLaterWCBlocksRenderer class is responsible for escaping.
        echo $renderer->render(
            array(
                // phpcs:ignore
                'blockId' => $block_id,
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
