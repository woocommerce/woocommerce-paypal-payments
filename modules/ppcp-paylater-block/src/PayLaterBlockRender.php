<?php
/**
 * The Pay Later block render callback.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterBlock
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterBlock;

// Early return if $attributes is not set or not an array.
if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

// Escape the 'id' attribute to prevent XSS vulnerabilities.
$html = '<div id="' . esc_attr( $attributes['id'] ?? '' ) . '" class="ppcp-messages" data-partner-attribution-id="Woo_PPCP"></div>';

// Create an instance of WP_HTML_Tag_Processor with your HTML content.
$processor = new \WP_HTML_Tag_Processor( $html );

// Find the first div tag.
if ( $processor->next_tag( 'div' ) ) {
	$layout = $attributes['layout'] ?? 'text'; // Default to 'text' layout if not set.

	if ( 'flex' === $layout ) {
		$processor->set_attribute( 'data-pp-style-layout', 'flex' );
		$processor->set_attribute( 'data-pp-style-color', $attributes['flexColor'] ?? '' );
		$processor->set_attribute( 'data-pp-style-ratio', $attributes['flexRatio'] ?? '' );
	} else {
		// Apply 'text' layout attributes.
		$processor->set_attribute( 'data-pp-style-layout', 'text' );
		$processor->set_attribute( 'data-pp-style-logo-type', $attributes['logo'] ?? '' );
		$processor->set_attribute( 'data-pp-style-logo-position', $attributes['position'] ?? '' );
		$processor->set_attribute( 'data-pp-style-text-color', $attributes['color'] ?? '' );
		$processor->set_attribute( 'data-pp-style-text-size', $attributes['size'] ?? '' );
	}

	if ( ( $attributes['placement'] ?? 'auto' ) !== 'auto' ) {
		$processor->set_attribute( 'data-pp-placement', $attributes['placement'] );
	}
}

$updated_html = (string) $processor;
?>

<div id="ppcp-paylater-message-block" <?php echo get_block_wrapper_attributes(); ?>>
	<?php echo $updated_html; ?>
</div>
