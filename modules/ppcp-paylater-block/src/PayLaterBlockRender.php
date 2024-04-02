<?php
/**
 * The Pay Later block render callback.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterBlock
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterBlock;

$attributes = get_block_wrapper_attributes()
?>
<div id="ppcp-paylater-message-block" <?php echo $attributes; ?>>
	<?php echo do_action('ppcp-paylater-message-block', $attributes); ?>
</div>

