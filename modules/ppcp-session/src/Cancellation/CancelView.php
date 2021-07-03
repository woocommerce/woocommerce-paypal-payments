<?php
/**
 * Renders the cancel view for the order on the checkout.
 *
 * @package WooCommerce\PayPalCommerce\Session\Cancellation
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session\Cancellation;

/**
 * Class CancelView
 */
class CancelView {

	/**
	 * Renders the cancel link.
	 *
	 * @param string $url The URL.
	 */
	public function render_session_cancellation( string $url ) {
		?>
		<p id="ppcp-cancel"
			class="has-text-align-center ppcp-cancel"
		>
			<?php
			printf(
					// translators: the placeholders are html tags for a link.
				esc_html__(
					'You are currently paying with PayPal. If you want to cancel
                            this process, please click %1$shere%2$s.',
					'woocommerce-paypal-payments'
				),
				'<a href="' . esc_url( $url ) . '">',
				'</a>'
			);
			?>
		</p>
		<?php
	}
}
