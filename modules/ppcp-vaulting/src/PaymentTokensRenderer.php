<?php
/**
 * The payment tokens renderer.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;

/**
 * Class PaymentTokensRendered
 */
class PaymentTokensRenderer {

	/**
	 * Render payment tokens.
	 *
	 * @param PaymentToken[] $tokens The tokens.
	 * @return false|string
	 */
	public function render( array $tokens ) {
		ob_start();
		?>
		<table class="shop_table shop_table_responsive">
			<thead>
			<tr>
				<th><?php echo esc_html__( 'Payment sources', 'woocommerce-paypal-payments' ); ?></th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $tokens as $token ) {
				$source = $token->source() ?? null;
				if ( $source && isset( $source->card ) ) {
					?>
						<tr>
							<td><?php echo esc_attr( $source->card->brand ) . ' ...' . esc_attr( $source->card->last_digits ); ?></td>
							<td>
								<a class="ppcp-delete-payment-button" id="<?php echo esc_attr( $token->id() ); ?>" href=""><?php echo esc_html__( 'Delete', 'woocommerce-paypal-payments' ); ?></a>
							</td>
						</tr>
					<?php
				}
				if ( $source && isset( $source->paypal ) ) {
					?>
						<tr>
							<td><?php echo esc_attr( $source->paypal->payer->email_address ); ?></td>
							<td>
								<a class="ppcp-delete-payment-button" id="<?php echo esc_attr( $token->id() ); ?>" href=""><?php echo esc_html__( 'Delete', 'woocommerce-paypal-payments' ); ?></a>
							</td>
						</tr>
					<?php
				}
				?>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render no payments message.
	 *
	 * @return false|string
	 */
	public function render_no_tokens() {
		ob_start();
		?>
		<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
			<?php echo esc_html__( 'No payments available yet.', 'woocommerce-paypal-payments' ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
